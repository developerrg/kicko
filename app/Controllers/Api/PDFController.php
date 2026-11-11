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
    private const MAX_IMAGES      = 20; // upload guard only; the PDF itself shows the first 5 images
    private const MAX_SIZE_BYTES  = 50 * 1024 * 1024; // 50 MB
    private const ALLOWED_MIME    = ['image/jpeg', 'image/jpg', 'image/png'];
    private const ALLOWED_EXT     = ['jpg', 'jpeg', 'png'];
    private const DEFAULT_GST_PERCENT = 18; // override with GST_PERCENT in .env

    public function generate(): ResponseInterface
    {
        try {
            // ── Parse payload ─────────────────────────────────────────────────
            // Three accepted formats:
            //   A) Raw JSON body                  → Content-Type: application/json
            //   B) Multipart + `payload` JSON str → single field with full JSON
            //   C) Multipart flat fields           → customerDetails[name], items[0][productCode], ...
            $contentType = $this->request->getHeaderLine('Content-Type');
            $isMultipart = str_contains($contentType, 'multipart/form-data');

            $customerDetails = [];
            $items           = [];

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
                } else {
                    // Format C — flat form-data fields
                    $customerDetails  = $this->request->getPost('customerDetails') ?? [];
                    $items            = $this->request->getPost('items')           ?? [];
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
            $customerDetails['serviceSupport'] = '+91 8281654455';
            $customerDetails['companyEmail']   = 'hello@kicomod.com';

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
                $code = trim((string)($item['productCode'] ?? ''));
                if ($code !== '') {
                    $ckey = strtolower($code);
                    if (!array_key_exists($ckey, $productCache)) {
                        $row = $db->table('product p')
                                  ->select('p.pr_Id, p.pr_Name, p.pr_Code, p.pr_Description, p.pr_Selling_Price, p.mrp, '
                                         . 'c.cat_Name, s.sub_Category_Name')
                                  ->join('category c', 'c.cat_Id = p.cat_Id', 'left')
                                  ->join('subcategory s', 's.sub_Id = p.sub_Id', 'left')
                                  ->where('p.pr_Code', $code)
                                  ->where('p.pr_Status !=', 3)
                                  ->get()->getRowArray();
                        $productCache[$ckey] = $row ?: null;
                    }
                }
            }

            // Active accessories by code (AC01, ...). Each item may carry "accessories": {"AC01": 2, ...}
            $accessories = (new \App\Models\Admin\AccessoryModel())->getActiveAccessories();
            $accByCode   = [];
            foreach ($accessories as $a) {
                $accByCode[strtoupper((string)$a['acc_Code'])] = $a;
            }

            // ── Map payload items → PDF rows ───────────────────────────────────
            $pdfItems     = [];
            $autoSubTotal = 0.0;
            $itemErrors   = [];
            $slNo         = 0;
            $accTotals    = []; // acc_Id => total quantity over all products
            $accBreakdown = []; // acc_Id => [[product name, count per unit, product qty], ...]

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

                // productCode and quantity are mandatory; everything priced comes from the DB
                $code = trim((string)($item['productCode'] ?? ''));
                if ($code === '') {
                    $itemErrors["items[$i].productCode"] = 'productCode is required';
                    continue;
                }
                $product = $productCache[strtolower($code)] ?? null;
                if (!$product) {
                    $itemErrors["items[$i].productCode"] = "Product not found for code '{$code}'";
                    continue;
                }
                $rawQty = $item['quantity'] ?? $item['qty'] ?? null;
                if ($rawQty === null || !is_numeric($rawQty) || (float)$rawQty <= 0) {
                    $itemErrors["items[$i].quantity"] = 'Quantity must be a number greater than 0';
                    continue;
                }
                if (!is_numeric($product['pr_Selling_Price'])) {
                    $itemErrors["items[$i].productCode"] = "Product '{$code}' has no selling price";
                    continue;
                }

                // PDF description: product name | cupboard type | size | design | texture [| accessories]
                $productName = trim($product['pr_Name'] ?? '');
                $desc        = $productName;

                // Cupboard type + size come from the product's category / subcategory:
                //   "Top Cupboards" → "Top";  "120 Top" → "120"
                $cupboardType = trim(preg_replace('/\s+Cupboards?$/i', '', trim((string)($product['cat_Name'] ?? ''))));
                $subName      = trim((string)($product['sub_Category_Name'] ?? ''));
                $size         = preg_match('/^\s*(\d+(?:\.\d+)?)/', $subName, $mm) ? $mm[1] : $subName;
                $designCode   = trim((string)($item['designCode'] ?? ''));

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
                if (!empty($item['textureCode'])) {
                    $extras[] = $item['textureCode'];
                }
                if ($extras) {
                    $desc .= ($desc !== '' ? ' | ' : '') . implode(' | ', $extras);
                }

                // Rate is never taken from the client: DB selling price only (accessories are priced separately below).
                $rate   = round((float)$product['pr_Selling_Price'], 2);
                $qty    = (float)$rawQty;
                $amount = round($rate * $qty, 2);
                $autoSubTotal += $amount;

                $itemNo = ++$slNo;
                $pdfItems[] = [
                    'isCategory'  => false,
                    'slNo'        => (string)$itemNo,
                    'productName' => $productName,
                    'description' => $desc,
                    'qty'         => $qty,
                    'unit'        => $item['unit'] ?? 'Nos',
                    'textureCode' => isset($item['textureCode']) && $item['textureCode'] !== '' ? (string)$item['textureCode'] : null,
                    'cupboardType' => $cupboardType !== '' ? $cupboardType : null,
                    'size'         => $size         !== '' ? $size         : null,
                    'designCode'   => $designCode   !== '' ? $designCode   : null,
                    'rate'      => $rate,
                    'amount'      => $amount,
                ];

                // Accessories: {"AC01": 2, "AC02": 1} (or [{"code":"AC01","count":2}]). Counts > 0 are added to the
                // estimate-wide accessory totals, listed together after all products. Unknown codes are errors.
                foreach ($this->parseAccessoryCounts($item['accessories'] ?? null, "items[$i].accessories", $itemErrors) as $accCode => $accCount) {
                    $acc = $accByCode[$accCode] ?? null;
                    if (!$acc) {
                        $itemErrors["items[$i].accessories.$accCode"] = "Accessory not found for code '{$accCode}' (or it is inactive)";
                        continue;
                    }
                    $accTotals[$acc['acc_Id']] = ($accTotals[$acc['acc_Id']] ?? 0.0) + $accCount * $qty;
                    $accBreakdown[$acc['acc_Id']][] = [$productName, $accCount, $qty];
                }
            }

            // ── Accessories section: one row per accessory, quantity summed over all products ──
            if (empty($itemErrors) && !empty($accTotals)) {
                $pdfItems[] = ['isCategory' => true, 'description' => 'Accessories'];
                foreach ($accessories as $acc) {
                    if (!isset($accTotals[$acc['acc_Id']])) {
                        continue;
                    }
                    $accQty    = round($accTotals[$acc['acc_Id']], 2);
                    $accRate   = round((float)$acc['acc_Price'], 2);
                    $accAmount = round($accQty * $accRate, 2);
                    $autoSubTotal += $accAmount;

                    // Description = accessory name, then one line per product it is used on:
                    //   "Door\n120 Top: 3 x 2 = 6\n90 Top: 4 x 1 = 4"   (count per unit x product quantity = total)
                    $accDesc = $acc['acc_Name'];
                    foreach ($accBreakdown[$acc['acc_Id']] ?? [] as [$pName, $perUnit, $pQty]) {
                        $accDesc .= "\n" . $pName . ': ' . $this->plainNumber($perUnit) . ' each x ' . $this->plainNumber($pQty)
                                  . ' = ' . $this->plainNumber($perUnit * $pQty);
                    }

                    $pdfItems[] = [
                        'isCategory'  => false,
                        'isAccessory' => true,
                        'slNo'        => (string)(++$slNo),
                        'productName' => $acc['acc_Name'],
                        'description' => $accDesc,
                        'qty'         => $accQty,
                        'unit'        => 'Nos',
                        'rate'        => $accRate,
                        'amount'      => $accAmount,
                    ];
                }
            }

            if (!empty($itemErrors)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => $itemErrors,
                ]);
            }
            if (empty($pdfItems)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => ['items' => 'No valid items found after processing'],
                ]);
            }

            // ── Totals (always computed here; nothing is taken from the client) ──
            $subTotal   = round($autoSubTotal, 2);
            $gstPercent = (float)env('GST_PERCENT', self::DEFAULT_GST_PERCENT);
            $gstAmount  = round($subTotal * $gstPercent / 100, 2);
            $grandTotal = round($subTotal + $gstAmount, 2);

            $totals = [
                'subTotalLabel'   => 'TOTAL',
                'subTotal'        => $subTotal,
                'gstPercent'      => $gstPercent,
                'gstAmount'       => $gstAmount,
                'grandTotal'      => $grandTotal,
                'grandTotalWords' => $this->amountInWords($grandTotal),
            ];

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
            $pdfImages   = []; // files + flags handed to the PDF (thumbnails; 360 images get their viewer link)

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
                            $pdfImages[]   = ['path' => $imageDir . $newName, 'is360' => true, 'url' => $viewerUrl];
                        } else {
                            $savedImages[] = ['url' => $publicUrl, 'rawUrl' => $publicUrl, 'is360' => false];
                            $pdfImages[]   = ['path' => $imageDir . $newName, 'is360' => false, 'url' => $publicUrl];
                        }
                    }

                    // images saved individually above
                }
            }

            // ── Generate PDF (now with 360 URLs) ──────────────────────────────
            $pdfService = new PDFService();
            $pdfResult  = $pdfService->generateKitchenExportPDF($pdfItems, $customerDetails, $totals, $urls360, $pdfImages);

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
                    // viewUrl opens the PDF in the browser; downloadUrl forces a file download
                    'viewUrl'         => $pdfResult['downloadUrl'],
                    'downloadUrl'     => base_url('pdf/download/' . $pdfResult['fileName']),
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

    /** 3.0 -> "3", 1.5 -> "1.5" */
    private function plainNumber(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /**
     * Normalises an item's accessories into [CODE => count] (counts > 0 only).
     * Accepts {"AC01": 2, "ac02": 1} or [{"code": "AC01", "count": 2}]; invalid counts add an error.
     */
    private function parseAccessoryCounts($raw, string $path, array &$errors): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_object($raw)) {
            $raw = (array)$raw;
        }
        if (!is_array($raw)) {
            $errors[$path] = 'accessories must be an object like {"AC01": 2}';
            return [];
        }

        $pairs = [];
        foreach ($raw as $k => $v) {
            if (is_object($v)) {
                $v = (array)$v;
            }
            if (is_array($v)) {                       // list form: {"code": "AC01", "count": 2}
                $pairs[] = [$v['code'] ?? '', $v['count'] ?? null, "$path.$k"];
            } else {                                  // map form: "AC01": 2
                $pairs[] = [$k, $v, "$path.$k"];
            }
        }

        $out = [];
        foreach ($pairs as [$code, $count, $where]) {
            $code = strtoupper(trim((string)$code));
            if ($code === '') {
                $errors[$where] = 'Accessory code is required';
                continue;
            }
            if (!is_numeric($count) || (float)$count < 0) {
                $errors["$path.$code"] = 'Accessory count must be a number of 0 or more';
                continue;
            }
            if ((float)$count > 0) {
                $out[$code] = ($out[$code] ?? 0.0) + (float)$count;
            }
        }
        return $out;
    }

    /** 1234.5 → "Rupees One Thousand Two Hundred Thirty Four and Fifty Paise Only" (Indian grouping). */
    private function amountInWords(float $amount): string
    {
        $rupees = (int)floor($amount);
        $paise  = (int)round(($amount - $rupees) * 100);
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        $words = 'Rupees ' . ($rupees === 0 ? 'Zero' : $this->indianWords($rupees));
        if ($paise > 0) {
            $words .= ' and ' . $this->indianWords($paise) . ' Paise';
        }
        return $words . ' Only';
    }

    private function indianWords(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven',
                 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $below100 = static fn(int $x): string => $x < 20
            ? $ones[$x]
            : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        $below1000 = static fn(int $x): string => trim(
            ($x >= 100 ? $ones[intdiv($x, 100)] . ' Hundred ' : '') . $below100($x % 100)
        );

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $label]) {
            if ($n >= $div) {
                $parts[] = $below100(intdiv($n, $div)) . ' ' . $label;
                $n %= $div;
            }
        }
        if ($n > 0) {
            $parts[] = $below1000($n);
        }
        return trim(implode(' ', $parts));
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
