<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ProposalModel;
use App\Models\ProposalItemModel;
use App\Models\ProposalImageModel;
use App\Services\PDFService;
use CodeIgniter\HTTP\ResponseInterface;

class PDFController extends BaseController
{
    private const MAX_IMAGES      = 20;
    private const MAX_SIZE_BYTES  = 50 * 1024 * 1024; // 50 MB
    private const ALLOWED_MIME    = ['image/jpeg', 'image/jpg', 'image/png'];
    private const ALLOWED_EXT     = ['jpg', 'jpeg', 'png'];

    public function generate(): ResponseInterface
    {
        try {
            // ── Parse payload ─────────────────────────────────────────────────
            // Three accepted formats:
            //   A) Raw JSON body                  → Content-Type: application/json
            //   B) Multipart + `payload` JSON str → single field with full JSON
            //   C) Multipart flat fields           → customerDetails[name], items[0][productId], ...
            $contentType = $this->request->getHeaderLine('Content-Type');
            $isMultipart = str_contains($contentType, 'multipart/form-data');

            $customerDetails = [];
            $items           = [];
            $payloadTotalsRaw = [];

            if ($isMultipart) {
                $raw = $this->request->getPost('payload');
                if ($raw) {
                    // Format B — single JSON payload field
                    $data = json_decode($raw);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $this->response->setStatusCode(400)->setJSON([
                            'status'  => 'error',
                            'message' => 'Invalid JSON in "payload" field: ' . json_last_error_msg(),
                        ]);
                    }
                    $items            = is_array($data->items ?? null) ? $data->items : (array)($data->items ?? []);
                    $customerDetails  = property_exists($data, 'customerDetails') ? (array)$data->customerDetails : [];
                    $payloadTotalsRaw = property_exists($data, 'totals') ? (array)$data->totals : [];
                } else {
                    // Format C — flat form-data fields
                    $customerDetails  = $this->request->getPost('customerDetails') ?? [];
                    $items            = $this->request->getPost('items')           ?? [];
                    $payloadTotalsRaw = $this->request->getPost('totals')          ?? [];
                }
            } else {
                // Format A — raw JSON
                $data = $this->request->getJSON();
                if (!$data) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status'  => 'error',
                        'message' => 'Invalid or empty JSON body',
                    ]);
                }
                $items            = is_array($data->items ?? null) ? $data->items : (array)($data->items ?? []);
                $customerDetails  = property_exists($data, 'customerDetails') ? (array)$data->customerDetails : [];
                $payloadTotalsRaw = property_exists($data, 'totals') ? (array)$data->totals : [];
            }

            if (empty($items)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => ['items' => 'At least 1 item is mandatory'],
                ]);
            }

            // ── Validate uploaded images ───────────────────────────────────────
            $uploadedFiles = [];
            if ($isMultipart) {
                $fileErrors = $this->validateUploadedImages($uploadedFiles);
                if (!empty($fileErrors)) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status'  => 'error',
                        'message' => 'Image validation failed',
                        'errors'  => $fileErrors,
                    ]);
                }
            }

            // ── Customer details ───────────────────────────────────────────────
            // $customerDetails already populated from parse block above.

            // Static company fields
            $customerDetails['serviceSupport'] = '0471-4064545';
            $customerDetails['companyEmail']   = 'info@insidedesignindia.com';

            // Location — from customerDetails only (flat form or JSON object)
            $rawLocation = isset($customerDetails['location']) ? trim((string)$customerDetails['location']) : '';
            $location    = $rawLocation !== '' ? $rawLocation : null;

            $latitude = null;
            if (isset($customerDetails['latitude']) && is_numeric($customerDetails['latitude'])) {
                $latitude = (float)$customerDetails['latitude'];
            }

            $longitude = null;
            if (isset($customerDetails['longitude']) && is_numeric($customerDetails['longitude'])) {
                $longitude = (float)$customerDetails['longitude'];
            }

            $customerDetails['location']  = $location;
            $customerDetails['latitude']  = $latitude;
            $customerDetails['longitude'] = $longitude;

            // Project name + PIN; address = location + pin (no separate address field)
            $projectName = trim((string)($customerDetails['projectName'] ?? ''));
            $pin         = trim((string)($customerDetails['pin'] ?? ''));
            $customerDetails['projectName'] = $projectName;
            $customerDetails['pin']         = $pin;
            $customerDetails['address']     = implode(' - ', array_filter([$location, $pin], static fn($v) => $v !== null && $v !== ''));

            // ── Pre-load product cache ─────────────────────────────────────────
            $productCache = [];
            $db = \Config\Database::connect();
            foreach ($items as $item) {
                $item = (array)$item;
                if (!empty($item['productId']) && is_numeric($item['productId'])) {
                    $pid = (int)$item['productId'];
                    if (!isset($productCache[$pid])) {
                        $row = $db->table('product')
                                  ->select('pr_Id, pr_Name, pr_Code, pr_Description, pr_Selling_Price, mrp')
                                  ->where('pr_Id', $pid)
                                  ->where('pr_Status !=', 3)
                                  ->get()->getRowArray();
                        $productCache[$pid] = $row ?: null;
                    }
                }
            }

            // ── Map payload items → PDF rows ───────────────────────────────────
            $pdfItems     = [];
            $autoSubTotal = 0.0;

            foreach ($items as $i => $item) {
                if (!is_object($item) && !is_array($item)) {
                    continue;
                }
                $item = (array)$item;

                if (!empty($item['isCategory'])) {
                    $pdfItems[] = [
                        'isCategory'  => true,
                        'description' => trim((string)($item['description'] ?? '')),
                    ];
                    continue;
                }

                $product = null;
                if (!empty($item['productId']) && is_numeric($item['productId'])) {
                    $product = $productCache[(int)$item['productId']] ?? null;
                }

                if ($product) {
                    $desc = trim($product['pr_Name'] ?? '');
                    if (!empty($product['pr_Code'])) {
                        $desc .= ' | ' . $product['pr_Code'];
                    }
                } else {
                    $desc = trim(
                        ($item['description'] ?? '') !== ''
                            ? ($item['description'] ?? '')
                            : ($item['boxModelCode'] ?? '')
                    );
                }
                $cupboardType = trim((string)($item['cupboardType'] ?? ''));
                $size         = trim((string)($item['size'] ?? ''));
                $designCode   = trim((string)($item['designCode'] ?? ''));
                $doorState    = trim((string)($item['doorState'] ?? ''));

                $extras = [];
                if ($cupboardType !== '') {
                    // CornerTopLeft → "Corner Top Left"
                    $typeLabel = trim(preg_replace('/(?<!^)(?=[A-Z])/', ' ', $cupboardType));
                    if (strcasecmp($typeLabel, $desc) !== 0) { // avoid "Filler | Filler"
                        $extras[] = $typeLabel;
                    }
                }
                if ($size !== '') {
                    $extras[] = $size . (is_numeric($size) ? ' cm' : '');
                }
                if ($designCode !== '') {
                    $extras[] = $designCode;
                }
                if (!empty($item['handleType'])) {
                    $extras[] = $item['handleType'];
                }
                if ($doorState !== '') {
                    $extras[] = 'Door ' . $doorState;
                }
                if (!empty($item['textureCode'])) {
                    $extras[] = $item['textureCode'];
                }
                if ($extras) {
                    $desc .= ($desc !== '' ? ' | ' : '') . implode(' | ', $extras);
                }

                $rate = null;
                if (isset($item['rate']) && is_numeric($item['rate'])) {
                    $rate = (float)$item['rate'];
                } elseif ($product && is_numeric($product['pr_Selling_Price'])) {
                    $rate = (float)$product['pr_Selling_Price'];
                }

                $qty    = null;
                $rawQty = $item['qty'] ?? $item['quantity'] ?? null;
                if ($rawQty !== null && is_numeric($rawQty)) {
                    $qty = (float)$rawQty;
                }

                $amount = null;
                if (isset($item['amount']) && $item['amount'] !== '') {
                    $amount = is_numeric($item['amount']) ? (float)$item['amount'] : (string)$item['amount'];
                } elseif ($rate !== null && $qty !== null) {
                    $amount = round($rate * $qty, 2);
                }

                if (is_numeric($amount)) {
                    $autoSubTotal += (float)$amount;
                }

                $pdfItems[] = [
                    'isCategory'  => false,
                    'slNo'        => isset($item['slNo']) ? (string)$item['slNo'] : (string)($i + 1),
                    'description' => $desc,
                    'qty'         => $qty,
                    'unit'        => $item['unit'] ?? 'Nos',
                    'textureCode' => isset($item['textureCode']) && $item['textureCode'] !== '' ? (string)$item['textureCode'] : null,
                    'handleType'  => isset($item['handleType'])  && $item['handleType']  !== '' ? (string)$item['handleType']  : null,
                    'cupboardType' => $cupboardType !== '' ? $cupboardType : null,
                    'size'         => $size         !== '' ? $size         : null,
                    'designCode'   => $designCode   !== '' ? $designCode   : null,
                    'doorState'    => $doorState    !== '' ? $doorState    : null,
                    'rate'      => $rate,
                    'amount'      => $amount,
                ];
            }

            if (empty($pdfItems)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => ['items' => 'No valid items found after processing'],
                ]);
            }

            // ── Totals ────────────────────────────────────────────────────────
            $payloadTotals = $payloadTotalsRaw; // populated in parse block
            $totals = [];

            if ($autoSubTotal > 0) {
                $gstPercent  = isset($payloadTotals['gstPercent'])  && is_numeric($payloadTotals['gstPercent'])  ? (float)$payloadTotals['gstPercent']  : null;
                $discount    = isset($payloadTotals['discount'])     && is_numeric($payloadTotals['discount'])    ? (float)$payloadTotals['discount']     : null;
                $gstAmount   = ($gstPercent !== null) ? round($autoSubTotal * $gstPercent / 100, 2) : 0;
                $afterGst    = $autoSubTotal + $gstAmount;
                $grandTotal  = ($discount !== null) ? $afterGst - $discount : $afterGst;

                $totals = [
                    'subTotalLabel'   => $payloadTotals['subTotalLabel']   ?? 'TOTAL',
                    'subTotal'        => $autoSubTotal,
                    'grandTotal'      => $grandTotal,
                    'grandTotalWords' => $payloadTotals['grandTotalWords'] ?? null,
                ];
                if ($gstPercent !== null) { $totals['gstPercent'] = $gstPercent; }
                if ($discount   !== null) { $totals['discount']   = $discount; }
            } elseif (!empty($payloadTotals)) {
                $totals = $payloadTotals;
            }

            // ── Generate PDF (without images — proposal ID not yet known) ─────
            // We pass empty 360 URLs first, then regenerate PDF after saving images.
            // Instead: save proposal first (without pdf fields), save images, then generate PDF.

            // Save proposal header (pdf fields filled after generation)
            $rawDate  = $customerDetails['dateOfProposal'] ?? date('d-m-Y');
            $propDate = \DateTime::createFromFormat('d-m-Y', $rawDate);
            $dbDate   = $propDate ? $propDate->format('Y-m-d') : date('Y-m-d');

            $proposalModel = new ProposalModel();
            $proposalId    = $proposalModel->insert([
                'name'              => $customerDetails['name']           ?? null,
                'project_name'      => $projectName !== '' ? $projectName : null,
                'pin'               => $pin !== '' ? $pin : null,
                'address'           => $customerDetails['address'] !== '' ? $customerDetails['address'] : null,
                'phone'             => $customerDetails['phone']          ?? null,
                'email'             => $customerDetails['email']          ?? null,
                'project_number'    => $customerDetails['projectNumber']  ?? null,
                'date_of_proposal'  => $dbDate,
                'sales_manager'     => $customerDetails['salesManager']   ?? null,
                'service_support'   => $customerDetails['serviceSupport'] ?? null,
                'referred_by'       => $customerDetails['referredBy']     ?? null,
                'location'          => $location,
                'latitude'          => $latitude,
                'longitude'         => $longitude,
                'sub_total_label'   => $totals['subTotalLabel']           ?? null,
                'sub_total'         => $totals['subTotal']                ?? null,
                'gst_percent'       => $totals['gstPercent']              ?? null,
                'discount'          => $totals['discount']                ?? null,
                'grand_total'       => $totals['grandTotal']              ?? null,
                'grand_total_words' => $totals['grandTotalWords']         ?? null,
                'pdf_file_name'     => '',
                'pdf_url'           => '',
                'status'            => 1,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            if ($proposalId === false) {
                log_message('error', 'PDFController::generate — ProposalModel::insert failed. Errors: ' . json_encode($proposalModel->errors()));
                return $this->response->setStatusCode(500)->setJSON([
                    'status'  => 'error',
                    'message' => 'Failed to save proposal record',
                    'errors'  => $proposalModel->errors(),
                ]);
            }

            // ── Save line items ───────────────────────────────────────────────
            $itemModel = new ProposalItemModel();
            if (!$itemModel->insertItems((int)$proposalId, $pdfItems)) {
                log_message('warning', "PDFController::generate — insertItems failed for proposal #{$proposalId}");
            }

            // ── Save images, collect 360 viewer URLs ──────────────────────────
            $urls360    = [];
            $savedImages = [];

            if (!empty($uploadedFiles)) {
                $imageDir = FCPATH . 'uploads/proposals/' . $proposalId . '/';
                if (!is_dir($imageDir) && !mkdir($imageDir, 0755, true) && !is_dir($imageDir)) {
                    log_message('error', "PDFController::generate — failed to create image dir: {$imageDir}");
                } else {
                    $imageModel = new ProposalImageModel();
                    $now        = date('Y-m-d H:i:s');

                    foreach ($uploadedFiles as $idx => $fileInfo) {
                        /** @var \CodeIgniter\HTTP\Files\UploadedFile $file */
                        $file    = $fileInfo['file'];
                        $is360   = $fileInfo['is360'];
                        $newName = time() . '_' . $idx . '_' . $file->getRandomName();

                        if (!$file->move($imageDir, $newName)) {
                            log_message('warning', "PDFController::generate — failed to move image #{$idx} for proposal #{$proposalId}");
                            continue;
                        }

                        $relPath   = 'uploads/proposals/' . $proposalId . '/' . $newName;
                        $publicUrl = base_url($relPath);

                        // Insert first — need ID to build viewer URL for 360 images
                        $imageId = $imageModel->insert([
                            'proposal_id' => (int)$proposalId,
                            'file_name'   => $newName,
                            'file_path'   => $relPath,
                            'url'         => $publicUrl,
                            'is_360'      => $is360 ? 1 : 0,
                            'sort_order'  => $idx,
                            'status'      => 1,
                            'created_at'  => $now,
                        ]);

                        // For 360 images: short viewer URL stored + used in PDF
                        if ($is360 && $imageId) {
                            $viewerUrl = base_url('view360/' . $imageId);
                            $imageModel->update($imageId, ['url' => $viewerUrl]);
                            $urls360[]  = $viewerUrl;
                            $savedImages[] = ['url' => $viewerUrl, 'rawUrl' => $publicUrl, 'is360' => true];
                        } else {
                            $savedImages[] = ['url' => $publicUrl, 'rawUrl' => $publicUrl, 'is360' => false];
                        }
                    }

                    // images saved individually above
                }
            }

            // ── Generate PDF (now with 360 URLs) ──────────────────────────────
            $pdfService = new PDFService();
            $pdfResult  = $pdfService->generateKitchenExportPDF($pdfItems, $customerDetails, $totals, $urls360);

            // ── Update proposal with PDF info ─────────────────────────────────
            $proposalModel->update($proposalId, [
                'pdf_file_name' => $pdfResult['fileName'],
                'pdf_url'       => $pdfResult['downloadUrl'],
            ]);

            return $this->response->setStatusCode(200)->setJSON([
                'status'  => 'success',
                'message' => 'PDF generated successfully',
                'data'    => [
                    'proposalId'      => $proposalId,
                    'fileName'        => $pdfResult['fileName'],
                    'downloadUrl'     => $pdfResult['downloadUrl'],
                    'itemsCount'      => $pdfResult['itemsCount'],
                    'imagesCount'     => count($savedImages),
                    'images'          => $savedImages,
                    'customerDetails' => $pdfResult['customerDetails'],
                    'generatedAt'     => $pdfResult['generatedAt'],
                ],
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'PDFController::generate — ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            $isDev = in_array(ENVIRONMENT, ['development', 'testing'], true);
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => $isDev ? $e->getMessage() : 'An unexpected error occurred. Please try again.',
                ...($isDev ? ['trace' => $e->getFile() . ':' . $e->getLine()] : []),
            ]);
        }
    }

    /**
     * Validate all uploaded images. Populates $uploadedFiles on success.
     * Returns array of error strings (empty = valid).
     *
     * Expected form fields (each image is a grouped object):
     *   images[0][file]   — file upload
     *   images[0][is360]  — "1" or "0"
     *   images[1][file]   — file upload
     *   images[1][is360]  — "1" or "0"
     *   ...
     */
    private function validateUploadedImages(array &$uploadedFiles): array
    {
        $errors = [];

        // CI4 getFiles() returns nested structure matching the input name
        // images[0][file] → $allFiles['images'][0]['file'] => UploadedFile
        $allFiles   = $this->request->getFiles();
        $imageGroup = $allFiles['images'] ?? [];

        if (empty($imageGroup)) {
            return []; // No images is fine
        }

        if (count($imageGroup) > self::MAX_IMAGES) {
            return ['images' => 'Maximum ' . self::MAX_IMAGES . ' images allowed'];
        }

        // images[n][is360] lives in POST
        $postImages = $this->request->getPost('images') ?? [];

        foreach ($imageGroup as $idx => $group) {
            /** @var \CodeIgniter\HTTP\Files\UploadedFile $file */
            $file = $group['file'] ?? null;

            if (!$file || !($file instanceof \CodeIgniter\HTTP\Files\UploadedFile)) {
                $errors[] = "Image #{$idx}: missing file field";
                continue;
            }

            if (!$file->isValid()) {
                $errors[] = "Image #{$idx}: upload error — " . $file->getErrorString();
                continue;
            }

            if ($file->getSize() > self::MAX_SIZE_BYTES) {
                $errors[] = "Image #{$idx} ({$file->getClientName()}): exceeds 50 MB limit";
                continue;
            }

            $mime = $file->getMimeType();
            $ext  = strtolower($file->getClientExtension());

            if (!in_array($mime, self::ALLOWED_MIME, true) || !in_array($ext, self::ALLOWED_EXT, true)) {
                $errors[] = "Image #{$idx} ({$file->getClientName()}): only JPG and PNG allowed";
                continue;
            }

            $is360Flag = $postImages[$idx]['is360'] ?? '0';

            $uploadedFiles[] = [
                'file'  => $file,
                'is360' => $is360Flag === '1',
            ];
        }

        return $errors;
    }
}
