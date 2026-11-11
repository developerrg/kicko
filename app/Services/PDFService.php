<?php

namespace App\Services;

use App\Libraries\DocxTermsRenderer;
use TCPDF;

class KicoPDF extends TCPDF
{
    /** Set to false on cover / back-cover pages to suppress the footer. */
    public bool $printPageFooter = true;

    public function Footer(): void
    {
        if (!$this->printPageFooter) {
            return;
        }

        // Page 1 is always the cover image (no footer printed there).
        // So content pages are  PageNo() - 1  → first content page = 1.
        $contentPage = $this->PageNo() - 1;

        $this->SetY(-10);
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(60, 5, 'For KICO..................', 0, 0, 'L');
        $this->Cell(60, 5, 'Page ' . $contentPage,             0, 0, 'C');
        $this->Cell(60, 5, 'For Customer.....................', 0, 1, 'R');
    }
}

class PDFService
{
    private string $firstPageImage = '';
    private string $lastPageImage  = '';

    // A4 usable width: 210 - 15 (left) - 15 (right) = 180 mm
    private const PAGE_W    = 210;
    private const PAGE_H    = 297;
    private const MARGIN    = 15;

    /** Colour of every section header bar: PROPOSAL FOR INTERIOR WORKS, IMAGE PREVIEW and the terms headers. */
    private const HEADER_RGB = [103, 82, 63];   // #67523f

    /** Light tint of the header colour (25 % of it, 75 % white): table column headers and highlighted rows. */
    private static function headerLite(): array
    {
        return array_map(static fn(int $c) => (int)round(255 - (255 - $c) * 0.25), self::HEADER_RGB);
    }
    private const USABLE_W  = 180;  // 210 - 15 - 15

    public function __construct()
    {
        $this->firstPageImage = FCPATH . 'public/images/start.jpg';
        $this->lastPageImage  = FCPATH . 'public/images/end.jpg';
    }

    public function setFirstPageImage(string $imagePath): self
    {
        if (file_exists($imagePath)) { $this->firstPageImage = $imagePath; }
        return $this;
    }

    public function setLastPageImage(string $imagePath): self
    {
        if (file_exists($imagePath)) { $this->lastPageImage = $imagePath; }
        return $this;
    }

    public function getFirstPageImage(): string { return $this->firstPageImage; }
    public function getLastPageImage(): string  { return $this->lastPageImage;  }

    // ─────────────────────────────────────────────────────────────────────────
    // Public entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function generateKitchenExportPDF(
        array $items,
        array $customerDetails = [],
        array $totals = [],
        array $urls360 = [],
        array $images = []   // [['path' => abs file, 'is360' => bool, 'url' => 360 viewer url], ...]
    ): array {
        if (empty($items)) {
            throw new \Exception('Items array cannot be empty');
        }

        $validatedItems = $this->validateItems($items);

        $hasCover     = !empty($this->firstPageImage) && file_exists($this->firstPageImage);
        $hasBackCover = !empty($this->lastPageImage)  && file_exists($this->lastPageImage);

        $pdf = new KicoPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Kitchen Export System');
        $pdf->SetAuthor('KICO');
        $pdf->SetTitle('Kitchen Export PDF');
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(true, self::MARGIN);
        $pdf->setPrintHeader(false);

        // Page 1 – cover image, footer suppressed
        // printPageFooter stays FALSE here so that when AddPage() for page 2
        // is called below, TCPDF closes page 1 (firing Footer()) while the
        // flag is still false → no footer on the cover.
        $pdf->printPageFooter = false;
        $pdf->AddPage();
        if ($hasCover) {
            $this->addFullPageImage($pdf, $this->firstPageImage);
        }

        // Page 2+ – content pages
        // AddPage() closes page 1 with printPageFooter=false (cover stays clean),
        // then opens page 2.  We enable the footer AFTER AddPage() so it takes
        // effect from page 2's footer onward (PageNo()-1 = 1 on first content page).
        $pdf->AddPage();
        $pdf->printPageFooter = true;
        $this->addCustomerDetailsBlock($pdf, $customerDetails);
        $this->addItemsTable($pdf, $validatedItems);
        if (!empty($totals)) {
            $this->addTotalsSection($pdf, $totals);
        }

        // Uploaded images: small thumbnails, 360 images last (clickable, marked with a 360 badge)
        $tmpFiles = [];
        if (!empty($images)) {
            $this->addImagePreviewSection($pdf, $images, $tmpFiles);
        }

        // Terms & conditions: "kico terms.docx" printed fully on two dedicated pages
        $this->addTermsPages($pdf);

        // Last page – back cover, no footer
        // AddPage() closes the last content page with printPageFooter=true (footer
        // prints correctly for that page), then opens the back-cover page.
        // We disable the footer AFTER AddPage() so the back cover stays clean.
        if ($hasBackCover) {
            $pdf->AddPage();
            $pdf->printPageFooter = false;
            $this->addFullPageImage($pdf, $this->lastPageImage);
        }

        $uploadsDir = FCPATH . 'uploads/';
        if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
            throw new \RuntimeException('Failed to create uploads directory: ' . $uploadsDir);
        }

        $fileName = 'kico_estimate_' . time() . '.pdf';
        $filePath = $uploadsDir . $fileName;

        try {
            $pdf->Output($filePath, 'F');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to write PDF file: ' . $e->getMessage(), 0, $e);
        } finally {
            foreach ($tmpFiles as $tmp) {
                @unlink($tmp);
            }
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException('PDF file was not created at: ' . $filePath);
        }

        return [
            'success'         => true,
            'fileName'        => $fileName,
            'downloadUrl'     => base_url('uploads/' . $fileName),
            'filePath'        => $filePath,
            // accessory sub-rows belong to their item, so they are not counted as separate items
            'itemsCount'      => count(array_filter($validatedItems, static fn($r) => empty($r['isAccessory']) && empty($r['isCategory']))), // products only
            'customerDetails' => $customerDetails,
            'generatedAt'     => date('Y-m-d H:i:s'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Full-page cover / back-cover image
    // ─────────────────────────────────────────────────────────────────────────

    private function addFullPageImage(KicoPDF $pdf, string $imagePath): void
    {
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        $info = getimagesize($imagePath);
        if ($info === false) {
            throw new \Exception('Unable to read image: ' . $imagePath);
        }

        $pw = self::PAGE_W;
        $ph = self::PAGE_H;
        $scale = max($pw / ($info[0] / 25.4), $ph / ($info[1] / 25.4));
        $w = ($info[0] / 25.4) * $scale;
        $h = ($info[1] / 25.4) * $scale;
        $pdf->Image($imagePath, ($pw - $w) / 2, ($ph - $h) / 2, $w, $h);

        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(true, self::MARGIN);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Customer details block – 2-column, address spans 3 rows
    //
    // Layout (total = 180 mm):
    //   [lbl 28] [val 62] [rLbl 38] [rVal 52]
    //
    //   Row 1 (h=7):  Name         | name_val    | Project Number | proj_val
    //   Row 2 (h=7):  Address      | addr_val    | Date of Propos.| date_val
    //   Row 3 (h=7):  (span)       | (span)      | Sales Manager  | mgr_val
    //   Row 4 (h=7):  (span)       | (span)      | Service Support| svc_val
    //   Row 5 (h=7):  Phone        | phone_val   | Email.         | co_email
    //   Row 6 (h=7):  Email        | email_val   | Refered by     | ref_val
    // ─────────────────────────────────────────────────────────────────────────

    private function addCustomerDetailsBlock(KicoPDF $pdf, array $cd): void
    {
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(true, 20);

        $startX = self::MARGIN;
        $startY = $pdf->GetY();

        $rowH  = 7;       // standard row height
        $lineH = 5;       // MultiCell line height
        $addrH = $rowH * 3; // address spans 3 rows = 21 mm

        // Column widths: left label | left value | right label | right value
        // 28 + 62 + 38 + 52 = 180 mm
        $lbl  = 28;
        $val  = 62;
        $rLbl = 38;
        $rVal = 52;

        // Vertical separator X positions
        $sepL  = $startX + $lbl;              // between left label and left value
        $sepM  = $startX + $lbl + $val;       // between left half and right half
        $sepR  = $startX + $lbl + $val + $rLbl; // between right label and right value

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->setCellPaddings(1, 1, 1, 1);

        // ── Render all cell text with border=0 ────────────────────────────

        // Row 1: Name | Project Number
        $y = $startY;
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($startX, $y);
        $pdf->MultiCell($lbl,  $lineH, 'Name',           0, 'L', false, 0, $startX,        $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepL, $y);
        $pdf->MultiCell($val,  $lineH, substr(trim($cd['name'] ?? ''), 0, 70), 0, 'L', false, 0, $sepL, $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($sepM, $y);
        $pdf->MultiCell($rLbl, $lineH, 'Project Number', 0, 'L', false, 0, $sepM,          $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepR, $y);
        $pdf->MultiCell($rVal, $lineH, substr(trim($cd['projectNumber'] ?? ''), 0, 50), 0, 'L', false, 0, $sepR, $y, true, 0, false, true, $rowH, 'M', false);

        // Rows 2-4: Address (left, spans 3 rows) | Date / Sales Mgr / Service Support (right)
        $y = $startY + $rowH;

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($startX, $y);
        $pdf->MultiCell($lbl, $lineH, 'Address', 0, 'L', false, 0, $startX, $y, true, 0, false, true, $addrH, 'T', false);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepL, $y);
        $pdf->MultiCell($val, $lineH, trim($cd['address'] ?? ''), 0, 'L', false, 0, $sepL, $y, true, 0, false, true, $addrH, 'T', false);

        $rightRows = [
            ['Date of Proposal', trim($cd['dateOfProposal'] ?? date('d-m-Y'))],
            ['Sales Manager',    trim($cd['salesManager']   ?? '')],
            ['Service Support',  trim($cd['serviceSupport'] ?? '')],
        ];
        foreach ($rightRows as $i => [$rLabel, $rValue]) {
            $ry = $y + $i * $rowH;
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($sepM, $ry);
            $pdf->MultiCell($rLbl, $lineH, $rLabel,                    0, 'L', false, 0, $sepM, $ry, true, 0, false, true, $rowH, 'M', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetXY($sepR, $ry);
            $pdf->MultiCell($rVal, $lineH, substr($rValue, 0, 50),     0, 'L', false, 0, $sepR, $ry, true, 0, false, true, $rowH, 'M', false);
        }

        // Row 5: Phone | Email
        $y = $startY + $rowH + $addrH;
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($startX, $y);
        $pdf->MultiCell($lbl,  $lineH, 'Phone',  0, 'L', false, 0, $startX, $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepL, $y);
        $pdf->MultiCell($val,  $lineH, substr(trim($cd['phone'] ?? ''), 0, 70), 0, 'L', false, 0, $sepL, $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($sepM, $y);
        $pdf->MultiCell($rLbl, $lineH, 'Email',  0, 'L', false, 0, $sepM,    $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepR, $y);
        $pdf->MultiCell($rVal, $lineH, substr(trim($cd['companyEmail'] ?? ''), 0, 50), 0, 'L', false, 0, $sepR, $y, true, 0, false, true, $rowH, 'M', false);

        // Row 6: Email | Referred by
        $y = $startY + $rowH + $addrH + $rowH;
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($startX, $y);
        $pdf->MultiCell($lbl,  $lineH, 'Email',       0, 'L', false, 0, $startX, $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepL, $y);
        $pdf->MultiCell($val,  $lineH, substr(trim($cd['email'] ?? ''), 0, 70), 0, 'L', false, 0, $sepL, $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($sepM, $y);
        $pdf->MultiCell($rLbl, $lineH, 'Referred by', 0, 'L', false, 0, $sepM,   $y, true, 0, false, true, $rowH, 'M', false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($sepR, $y);
        $pdf->MultiCell($rVal, $lineH, substr(trim($cd['referredBy'] ?? ''), 0, 50), 0, 'L', false, 0, $sepR, $y, true, 0, false, true, $rowH, 'M', false);

        // ── Row 7 (optional): Location  ──────────────────────────────────
        // Build location text from whatever fields are present
        // Only show site name in PDF; coordinates are saved to DB only
        // Full-width extra row now shows Project Name (address already = location + pin)
        $hasLocation = !empty($cd['projectName']);
        $locText     = $hasLocation ? trim((string)$cd['projectName']) : '';

        // Base block height (rows 1–6)
        $baseH  = $rowH + $addrH + $rowH + $rowH;  // 7+21+7+7 = 42 mm
        $blockH = $hasLocation ? $baseH + $rowH : $baseH;

        if ($hasLocation) {
            $y7 = $startY + $baseH;
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($startX, $y7);
            // Full-width row: "Site name" label cell + value spanning all remaining columns
            $pdf->MultiCell($lbl, $lineH, 'Project Name', 0, 'L', false, 0, $startX, $y7, true, 0, false, true, $rowH, 'M', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetXY($startX + $lbl, $y7);
            $pdf->MultiCell(self::USABLE_W - $lbl, $lineH, $locText, 0, 'L', false, 0, $startX + $lbl, $y7, true, 0, false, true, $rowH, 'M', false);
        }

        // ── Outer border ──────────────────────────────────────────────────
        $pdf->Rect($startX, $startY, self::USABLE_W, $blockH);

        // ── Vertical separators ───────────────────────────────────────────
        // sepL runs full block height — gives Site name label its right border
        $pdf->Line($sepL, $startY, $sepL, $startY + $blockH);
        // sepM and sepR stop before location row (location row has no mid-dividers)
        $pdf->Line($sepM, $startY, $sepM, $startY + $baseH);
        $pdf->Line($sepR, $startY, $sepR, $startY + $baseH);

        // ── Horizontal separators ─────────────────────────────────────────
        // After row 1
        $pdf->Line($startX, $startY + $rowH,                      $startX + self::USABLE_W, $startY + $rowH);
        // After address block (rows 2–4)
        $pdf->Line($startX, $startY + $rowH + $addrH,             $startX + self::USABLE_W, $startY + $rowH + $addrH);
        // Internal right-half separators inside address span
        $pdf->Line($sepM,   $startY + $rowH + $rowH,              $startX + self::USABLE_W, $startY + $rowH + $rowH);
        $pdf->Line($sepM,   $startY + $rowH + $rowH + $rowH,      $startX + self::USABLE_W, $startY + $rowH + $rowH + $rowH);
        // After row 5
        $pdf->Line($startX, $startY + $rowH + $addrH + $rowH,     $startX + self::USABLE_W, $startY + $rowH + $addrH + $rowH);
        // After row 6 (only when location row exists — acts as separator before it)
        if ($hasLocation) {
            $pdf->Line($startX, $startY + $baseH, $startX + self::USABLE_W, $startY + $baseH);
        }

        // ── Cursor below block ────────────────────────────────────────────
        $pdf->SetXY($startX, $startY + $blockH);

        // ── "PROPOSAL FOR INTERIOR WORKS" orange bar ─────────────────────
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(...self::HEADER_RGB);
        $pdf->Cell(self::USABLE_W, 8, 'PROPOSAL FOR INTERIOR WORKS', 0, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Ln(3);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Items table – 6 columns, 180 mm total
    //
    //  Sl.No  Description  Qty  Unit  Rate(Rs.)  Amount(Rs.)
    //   10       74         14   18      32          32       = 180
    // ─────────────────────────────────────────────────────────────────────────

    private const COL_SL   = 10;
    private const COL_DESC = 74;
    private const COL_QTY  = 14;
    private const COL_UNIT = 18;
    private const COL_RATE = 32;
    private const COL_AMT  = 32;
    private const LINE_H   = 5;  // per-line height inside MultiCell (mm)
    private const MIN_ROW  = 7;  // minimum row height (mm)
    private const CELL_PAD = 1;  // uniform cell padding all sides (mm)

    /**
     * Calculate exact row height using TCPDF's getStringHeight().
     * This accounts for font metrics, padding, and character-level wrapping.
     * Description column is the single source of truth for every row.
     */
    private function calcRowHeight(KicoPDF $pdf, string $desc): float
    {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->setCellPaddings(self::CELL_PAD, self::CELL_PAD, self::CELL_PAD, self::CELL_PAD);
        $measured = $pdf->getStringHeight(self::COL_DESC, $desc);
        return max(self::MIN_ROW, $measured);
    }

    private function addItemsTable(KicoPDF $pdf, array $items): void
    {
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);

        $this->drawTableHeader($pdf);

        // Pre-compute cumulative X positions for vertical separator lines
        $colWidths = [self::COL_SL, self::COL_DESC, self::COL_QTY, self::COL_UNIT, self::COL_RATE, self::COL_AMT];
        $sepX = [];
        $cx   = self::MARGIN;
        foreach ($colWidths as $w) {
            $cx   += $w;
            $sepX[] = $cx;
        }
        array_pop($sepX); // remove rightmost — that is the outer border, not a separator

        foreach ($items as $item) {

            // ── Category header row ──────────────────────────────────────────
            if (!empty($item['isCategory'])) {
                $y = $pdf->GetY();
                if ($y + 6 > self::PAGE_H - self::MARGIN - 10) {
                    $pdf->AddPage();
                    $this->drawTableHeader($pdf);
                }
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->setCellPaddings(self::CELL_PAD, self::CELL_PAD, self::CELL_PAD, self::CELL_PAD);
                $pdf->Cell(self::USABLE_W, 6, '  ' . ($item['description'] ?? ''), 1, 1, 'L', true);
                $pdf->SetFillColor(255, 255, 255);
                continue;
            }

            // ── Prepare cell values ──────────────────────────────────────────
            $slNo    = (string)($item['slNo']        ?? '');
            $desc    = (string)($item['description'] ?? '');
            $qty     = isset($item['qty']) ? (string)$item['qty'] : '';
            $unit    = (string)($item['unit']        ?? '');
            $rate    = ($item['rate'] !== null && $item['rate'] !== '')
                       ? $this->formatIndian($item['rate']) : '';
            $rawAmt  = $item['amount'] ?? null;
            $amtBold = false;

            if ($rawAmt === null || $rawAmt === '') {
                $amtText = '';
            } elseif (is_numeric($rawAmt)) {
                $amtText = $this->formatIndian((float)$rawAmt);
            } else {
                $amtText = (string)$rawAmt;
                $amtBold = true;
            }

            // ── Row height: Description is the single source of truth ────────
            $rowHeight = $this->calcRowHeight($pdf, $desc);

            // ── Page-break: entire row moves to next page if needed ──────────
            $y = $pdf->GetY();
            if ($y + $rowHeight > self::PAGE_H - self::MARGIN - 10) {
                $pdf->AddPage();
                $this->drawTableHeader($pdf);
                $y = $pdf->GetY();
            }

            $x = self::MARGIN;

            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->setCellPaddings(self::CELL_PAD, self::CELL_PAD, self::CELL_PAD, self::CELL_PAD);

            // ── Render all cells with border=0 (no individual cell borders) ──

            // Sl. No — middle aligned
            $pdf->SetXY($x, $y);
            $pdf->MultiCell(
                self::COL_SL, self::LINE_H, $slNo,
                0, 'C', false, 0,
                $x, $y, true, 0, false, true, $rowHeight, 'M', false
            );

            // Description — top aligned, wraps naturally
            $pdf->SetXY($x + self::COL_SL, $y);
            $pdf->MultiCell(
                self::COL_DESC, self::LINE_H, $desc,
                0, 'L', false, 0,
                $x + self::COL_SL, $y, true, 0, false, true, $rowHeight, 'T', false
            );

            // Qty — middle aligned
            $pdf->SetXY($x + self::COL_SL + self::COL_DESC, $y);
            $pdf->MultiCell(
                self::COL_QTY, self::LINE_H, $qty,
                0, 'C', false, 0,
                $x + self::COL_SL + self::COL_DESC, $y,
                true, 0, false, true, $rowHeight, 'M', false
            );

            // Unit — middle aligned
            $pdf->SetXY($x + self::COL_SL + self::COL_DESC + self::COL_QTY, $y);
            $pdf->MultiCell(
                self::COL_UNIT, self::LINE_H, $unit,
                0, 'C', false, 0,
                $x + self::COL_SL + self::COL_DESC + self::COL_QTY, $y,
                true, 0, false, true, $rowHeight, 'M', false
            );

            // Rate — middle aligned
            $pdf->SetXY($x + self::COL_SL + self::COL_DESC + self::COL_QTY + self::COL_UNIT, $y);
            $pdf->MultiCell(
                self::COL_RATE, self::LINE_H, $rate,
                0, 'R', false, 0,
                $x + self::COL_SL + self::COL_DESC + self::COL_QTY + self::COL_UNIT, $y,
                true, 0, false, true, $rowHeight, 'M', false
            );

            // Amount — middle aligned
            if ($amtBold) { $pdf->SetFont('helvetica', 'B', 9); }
            $pdf->SetXY($x + self::COL_SL + self::COL_DESC + self::COL_QTY + self::COL_UNIT + self::COL_RATE, $y);
            $pdf->MultiCell(
                self::COL_AMT, self::LINE_H, $amtText,
                0, 'R', false, 0,
                $x + self::COL_SL + self::COL_DESC + self::COL_QTY + self::COL_UNIT + self::COL_RATE, $y,
                true, 0, false, true, $rowHeight, 'M', false
            );
            if ($amtBold) { $pdf->SetFont('helvetica', '', 9); }

            // ── Single outer row border ──────────────────────────────────────
            $pdf->Rect($x, $y, self::USABLE_W, $rowHeight);

            // ── Vertical column separators only (no top/bottom per cell) ─────
            foreach ($sepX as $sx) {
                $pdf->Line($sx, $y, $sx, $y + $rowHeight);
            }

            // ── Advance cursor — no automatic TCPDF movement ─────────────────
            $pdf->SetXY($x, $y + $rowHeight);
        }

        $pdf->SetAutoPageBreak(true, self::MARGIN);
    }

    private function drawTableHeader(KicoPDF $pdf): void
    {
        $pdf->SetFont('helvetica', 'BI', 9);
        $pdf->SetFillColor(...self::headerLite());
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->setCellPaddings(self::CELL_PAD, self::CELL_PAD, self::CELL_PAD, self::CELL_PAD);

        $pdf->Cell(self::COL_SL,   7, 'Sl. No',       1, 0, 'C', true);
        $pdf->Cell(self::COL_DESC, 7, 'Description',  1, 0, 'C', true);
        $pdf->Cell(self::COL_QTY,  7, 'Qty',          1, 0, 'C', true);
        $pdf->Cell(self::COL_UNIT, 7, 'Unit',         1, 0, 'C', true);
        $pdf->Cell(self::COL_RATE, 7, 'Rate (Rs.)',   1, 0, 'C', true);
        $pdf->Cell(self::COL_AMT,  7, 'Amount (Rs.)', 1, 1, 'C', true);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineWidth(0.3);
        $pdf->SetTextColor(0, 0, 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Totals section
    // ─────────────────────────────────────────────────────────────────────────

    private function addTotalsSection(KicoPDF $pdf, array $t): void
    {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);

        // Labels take left portion, values right-aligned
        $labelW = 134;
        $valW   = 46;
        // Total: 134 + 46 = 180 mm ✓

        $subTotalLabel = $t['subTotalLabel'] ?? 'TOTAL';
        $rows = [];

        if (isset($t['subTotal'])) {
            $rows[] = [$subTotalLabel, $this->formatIndian($t['subTotal']), false];
        }
        if (isset($t['gstPercent'])) {
            $gstAmt = $t['gstAmount'] ?? round(($t['subTotal'] ?? 0) * $t['gstPercent'] / 100);
            $rows[] = ['GST @' . $t['gstPercent'] . '% of Total', $this->formatIndian($gstAmt), false];
            if (isset($t['subTotal'])) {
                $rows[] = ['Total', $this->formatIndian(($t['subTotal'] ?? 0) + $gstAmt), false];
            }
        }
        if (isset($t['discount'])) {
            $rows[] = ['Discount', $this->formatIndian($t['discount']), false];
        }
        if (isset($t['grandTotal'])) {
            $rows[] = ['Grand Total', $this->formatIndian($t['grandTotal']), true];
        }

        foreach ($rows as [$label, $value, $bold]) {
            $pdf->SetFont('helvetica', $bold ? 'B' : '', $bold ? 10 : 9);
            $pdf->Cell($labelW, 7, $label, 1, 0, 'R');
            $pdf->Cell($valW,   7, $value, 1, 1, 'R');
        }

        if (!empty($t['grandTotalWords'])) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(self::USABLE_W, 7, $t['grandTotalWords'], 1, 1, 'C');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image preview section (the first 5 uploaded images only, 360° images included; no captions)
    //
    //   - photos first, in upload order; 360° images LAST, each on its own full-width row
    //   - the photo grid is as large as the page allows: one column (full width) when everything still fits
    //     on one page, otherwise two columns; a lone last photo is centred
    //   - a 360° image gets a dashed orange frame, an orange "360°" badge, a "Click to view in 360°" bar and
    //     the whole picture is a link to the 360° viewer
    // ─────────────────────────────────────────────────────────────────────────

    private const IMG_GAP        = 4.0;    // mm between pictures
    private const PHOTO_RATIO    = 0.75;   // photo box height = width x 0.75 (4:3)
    private const PHOTO_MAX_H    = 115.0;  // tallest photo box (single column)
    private const PANO_H         = 90.0;   // 360° box: full width x 90 mm (2:1 panorama)
    private const BOTTOM_LIMIT   = 12.0;   // keep clear of the page footer
    public  const MAX_IMAGES     = 5;      // pictures shown in the PDF (photos + 360° images)

    /** @param array<int,array{path?:string,is360?:bool,url?:string}> $images  @param string[] $tmpFiles filled with temp files to delete */
    private function addImagePreviewSection(KicoPDF $pdf, array $images, array &$tmpFiles): void
    {
        // The PDF shows only the FIRST 5 uploaded images (360° ones included); every upload is still saved.
        $usable = array_values(array_filter($images, static fn($im) => !empty($im['path']) && is_file($im['path'])));
        $usable = array_slice($usable, 0, self::MAX_IMAGES);

        // then photos first (upload order), 360° images last
        $photos = [];
        $panos  = [];
        foreach ($usable as $im) {
            if (!empty($im['is360'])) {
                $panos[] = $im;
            } else {
                $photos[] = $im;
            }
        }
        if (!$photos && !$panos) {
            return;
        }

        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);

        $room    = self::PAGE_H - self::MARGIN - self::BOTTOM_LIMIT;    // lowest usable Y
        $pageAvl = $room - self::MARGIN - 10;                           // height for pictures on a page of its own (10 = heading)

        // 1. choose the photo grid: 1 column if all pictures then fit on one page, else 2 columns
        $panoBlock = count($panos) * (self::PANO_H + self::IMG_GAP);
        $layout = null;
        foreach ([1, 2] as $cols) {
            $cellW = (self::USABLE_W - self::IMG_GAP * ($cols - 1)) / $cols;
            $cellH = min($cellW * self::PHOTO_RATIO, self::PHOTO_MAX_H);
            $rows  = (int)ceil(count($photos) / $cols);
            $total = $rows * ($cellH + self::IMG_GAP) + $panoBlock;
            $layout = ['cols' => $cols, 'w' => $cellW, 'h' => $cellH, 'total' => $total];
            if ($total <= $pageAvl) {
                break;   // fits on one page at this (largest) size
            }
        }
        if (!$photos) {
            $layout = ['cols' => 1, 'w' => self::USABLE_W, 'h' => 0.0, 'total' => $panoBlock];
        }

        // 2. keep the gallery together: if it does not fit below the content it starts on a fresh page
        //    (a gallery longer than one page then flows over pages row by row)
        $galleryH = 10 + $layout['total'];
        $newPage  = ($pdf->GetY() + 5 + $galleryH > $room);
        $this->imageSectionHeading($pdf, 'IMAGE PREVIEW', $newPage, $photos ? $layout['h'] : self::PANO_H);

        // 3. photos (grid)
        $cols = $layout['cols'];
        foreach (array_chunk($photos, $cols) as $rowImages) {
            $this->ensureImageRowFits($pdf, $layout['h'] + self::IMG_GAP);
            $y = $pdf->GetY();
            // a lone last photo is centred
            $rowW = count($rowImages) * $layout['w'] + (count($rowImages) - 1) * self::IMG_GAP;
            $x0   = self::MARGIN + (self::USABLE_W - $rowW) / 2;
            foreach ($rowImages as $i => $im) {
                $this->drawImageCell($pdf, $im, $x0 + $i * ($layout['w'] + self::IMG_GAP), $y, $layout['w'], $layout['h'], false, $tmpFiles);
            }
            $pdf->SetY($y + $layout['h'] + self::IMG_GAP);
        }

        // 4. 360° images last, full width
        foreach ($panos as $im) {
            $this->ensureImageRowFits($pdf, self::PANO_H + self::IMG_GAP);
            $y = $pdf->GetY();
            $this->drawImageCell($pdf, $im, self::MARGIN, $y, self::USABLE_W, self::PANO_H, true, $tmpFiles);
            $pdf->SetY($y + self::PANO_H + self::IMG_GAP);
        }

        $pdf->SetAutoPageBreak(true, self::MARGIN);
    }

    private function imageSectionHeading(KicoPDF $pdf, string $title, bool $newPage, float $firstRowH): void
    {
        // keep the heading together with at least the first row of pictures
        if ($newPage || $pdf->GetY() + 7 + 3 + $firstRowH > self::PAGE_H - self::MARGIN - self::BOTTOM_LIMIT) {
            $pdf->AddPage();
        } else {
            $pdf->Ln(5);
        }
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(...self::HEADER_RGB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(self::USABLE_W, 7, $title, 0, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);
    }

    private function ensureImageRowFits(KicoPDF $pdf, float $rowHeight): void
    {
        if ($pdf->GetY() + $rowHeight > self::PAGE_H - self::MARGIN - self::BOTTOM_LIMIT) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell(self::USABLE_W, 5, 'IMAGE PREVIEW (continued)', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(1);
        }
    }

    /** One framed picture; 360° pictures get the badge, dashed frame, click bar and link. No caption. */
    private function drawImageCell(KicoPDF $pdf, array $im, float $x, float $y, float $w, float $h, bool $is360, array &$tmpFiles): void
    {
        $accent = self::HEADER_RGB;   // same colour as the header bars
        $url    = (string)($im['url'] ?? '');

        // frame + light background
        $pdf->SetLineStyle(['width' => 0.25, 'color' => [200, 200, 200], 'dash' => 0]);
        $pdf->SetFillColor(246, 246, 246);
        $pdf->Rect($x, $y, $w, $h, 'DF');

        // the picture, fitted (keeps aspect ratio) and centred inside the frame; large source for sharp zoom
        $prepared = $this->prepareThumbnail($im['path'], $is360 ? 2600 : 2000, $tmpFiles);
        $pad = 1.0;
        if ($prepared !== null) {
            $pdf->Image(
                $prepared, $x + $pad, $y + $pad, $w - 2 * $pad, $h - 2 * $pad,
                'JPG', ($is360 && $url !== '') ? $url : '', '', false, 300, '', false, false, 0, 'CM'
            );
        } else {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY($x, $y + $h / 2 - 3);
            $pdf->Cell($w, 6, 'Image not available', 0, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        if ($is360) {
            // dashed orange frame
            $pdf->SetLineStyle(['width' => 0.8, 'color' => $accent, 'dash' => '3,2']);
            $pdf->Rect($x, $y, $w, $h, 'D');
            $pdf->SetLineStyle(['width' => 0.2, 'color' => [0, 0, 0], 'dash' => 0]);

            // "360°" badge, top-left
            $pdf->SetFillColor($accent[0], $accent[1], $accent[2]);
            $pdf->RoundedRect($x + 3, $y + 3, 18, 8, 2, '1111', 'F');
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x + 3, $y + 3);
            $pdf->Cell(18, 8, '360°', 0, 0, 'C');

            // semi-transparent bar at the bottom with a play triangle + hint
            $barH = 8.0;
            $by   = $y + $h - $barH - 0.5;
            $pdf->SetAlpha(0.72);
            $pdf->SetFillColor(30, 30, 30);
            $pdf->Rect($x + 0.5, $by, $w - 1, $barH, 'F');
            $pdf->SetAlpha(1);
            $pdf->SetFillColor(255, 255, 255);
            $ty = $by + $barH / 2;
            $pdf->Polygon([$x + 4, $ty - 2.2, $x + 4, $ty + 2.2, $x + 8, $ty], 'F');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x + 10, $by);
            $pdf->Cell($w - 12, $barH, 'Click to view in 360°', 0, 0, 'L');

            // whole picture is clickable
            if ($url !== '') {
                $pdf->Link($x, $y, $w, $h, $url);
            }
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
    }

    /**
     * Down-scales an uploaded image to a PDF-friendly JPEG (original photos can be many MB), fixing the
     * camera orientation. Returns the temp file path (registered in $tmpFiles) or null on failure.
     */
    private function prepareThumbnail(string $src, int $maxSide, array &$tmpFiles): ?string
    {
        $info = @getimagesize($src);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return null;
        }
        [$w, $h] = $info;
        $type = $info[2];
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }

        // GD needs ~5 bytes per pixel to decode; skip the resize when it would not fit in memory
        @ini_set('memory_limit', '768M');
        $limit = $this->memoryLimitBytes();
        if ($limit > 0 && ($w * $h * 5 + memory_get_usage()) > $limit * 0.9) {
            return $type === IMAGETYPE_JPEG ? $src : null;
        }

        $img = $type === IMAGETYPE_JPEG ? @imagecreatefromjpeg($src) : @imagecreatefrompng($src);
        if ($img === false) {
            return null;
        }

        if ($type === IMAGETYPE_JPEG) {
            $img = $this->applyOrientation($img, $this->jpegOrientation($src));
        }

        $iw = imagesx($img);
        $ih = imagesy($img);
        $ratio = min(1.0, $maxSide / max($iw, $ih));
        $nw = max(1, (int)round($iw * $ratio));
        $nh = max(1, (int)round($ih * $ratio));

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // flatten PNG transparency on white
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $iw, $ih);
        imagedestroy($img);

        $tmp = tempnam(sys_get_temp_dir(), 'kico_img_');
        if ($tmp === false) {
            imagedestroy($out);
            return null;
        }
        $tmpFiles[] = $tmp;
        imageinterlace($out, false);
        $ok = imagejpeg($out, $tmp, 82);
        imagedestroy($out);
        return $ok ? $tmp : null;
    }

    private function memoryLimitBytes(): int
    {
        $v = trim((string)ini_get('memory_limit'));
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (float)$v;
        return (int)match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /** EXIF orientation tag of a JPEG (1 = upright). Parsed by hand: the exif extension may be missing. */
    private function jpegOrientation(string $path): int
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return 1;
        }
        $head = (string)fread($fh, 65536);
        fclose($fh);
        $len = strlen($head);
        if ($len < 12 || substr($head, 0, 2) !== "\xFF\xD8") {
            return 1;
        }

        $pos = 2;
        while ($pos + 4 < $len && $head[$pos] === "\xFF") {
            $marker = ord($head[$pos + 1]);
            $size   = unpack('n', substr($head, $pos + 2, 2))[1];
            if ($marker === 0xE1 && substr($head, $pos + 4, 6) === "Exif\0\0") {
                $tiff = $pos + 10;
                $le   = substr($head, $tiff, 2) === 'II';
                $u16  = static fn(int $o) => $o + 2 <= strlen($head) ? unpack($le ? 'v' : 'n', substr($head, $o, 2))[1] : 0;
                $u32  = static fn(int $o) => $o + 4 <= strlen($head) ? unpack($le ? 'V' : 'N', substr($head, $o, 4))[1] : 0;
                $ifd  = $tiff + $u32($tiff + 4);
                $n    = $u16($ifd);
                for ($i = 0; $i < $n && $i < 64; $i++) {
                    $e = $ifd + 2 + $i * 12;
                    if ($u16($e) === 0x0112) {
                        $o = $u16($e + 8);
                        return ($o >= 1 && $o <= 8) ? $o : 1;
                    }
                }
                return 1;
            }
            if ($marker === 0xDA || $size < 2) {
                break;
            }
            $pos += 2 + $size;
        }
        return 1;
    }

    private function applyOrientation($img, int $orientation)
    {
        switch ($orientation) {
            case 2: imageflip($img, IMG_FLIP_HORIZONTAL); return $img;
            case 3: $r = imagerotate($img, 180, 0); break;
            case 4: imageflip($img, IMG_FLIP_VERTICAL); return $img;
            case 5: imageflip($img, IMG_FLIP_HORIZONTAL); $r = imagerotate($img, 90, 0); break;
            case 6: $r = imagerotate($img, -90, 0); break;
            case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $r = imagerotate($img, -90, 0); break;
            case 8: $r = imagerotate($img, 90, 0); break;
            default: return $img;
        }
        if ($r === false) {
            return $img;
        }
        imagedestroy($img);
        return $r;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Terms & conditions: "kico terms.docx" printed in full on two dedicated pages
    //
    // The document's tables/paragraphs are converted to HTML by DocxTermsRenderer. Blocks (one table or
    // paragraph each) are never split across pages. The largest font scale at which everything fits on
    // exactly two pages is used, so the pages are filled as fully as possible.
    // ─────────────────────────────────────────────────────────────────────────

    private const TERMS_DOCX = 'kico terms.docx';
    private const TERMS_GAP  = 2.5;   // mm between blocks

    private function termsDocxPath(): string
    {
        foreach ([FCPATH, defined('ROOTPATH') ? ROOTPATH : FCPATH] as $base) {
            $p = rtrim((string)$base, '/\\') . DIRECTORY_SEPARATOR . self::TERMS_DOCX;
            if (is_file($p)) {
                return $p;
            }
        }
        return '';
    }

    private function addTermsPages(KicoPDF $pdf): void
    {
        $path = $this->termsDocxPath();
        if ($path === '') {
            log_message('warning', 'PDFService: "' . self::TERMS_DOCX . '" not found - terms pages skipped.');
            return;
        }
        // the document's header colour (#E36414) and light-orange highlight (#FFF3E0) are printed in the PDF's colours
        $docx = (new DocxTermsRenderer($path))
            ->mapColor('E36414', sprintf('%02X%02X%02X', ...self::HEADER_RGB))        // header bars + accents
            ->mapColor('FFF3E0', sprintf('%02X%02X%02X', ...self::headerLite()));       // highlighted row (Grand Total)
        if (!$docx->isAvailable()) {
            log_message('warning', 'PDFService: could not read "' . self::TERMS_DOCX . '" - terms pages skipped.');
            return;
        }

        $top   = (float)self::MARGIN;
        $limit = self::PAGE_H - self::MARGIN - self::BOTTOM_LIMIT;   // lowest usable Y
        $avail = $limit - $top;

        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // pick the largest scale whose blocks fit on two pages
        $blocks  = [];
        $heights = [];
        for ($scale = 1.50; $scale >= 0.50; $scale -= 0.025) {
            $blocks = $docx->blocks($scale);
            if (!$blocks) {
                return;
            }
            $heights = array_map(fn($b) => $this->measureHtmlHeight($pdf, $b['html']), $blocks);

            $pages = 1;
            $y     = 0.0;
            foreach ($heights as $h) {
                $need = $h + ($y > 0 ? self::TERMS_GAP : 0);
                if ($y > 0 && $y + $need > $avail) {
                    $pages++;
                    $y = $h;
                } else {
                    $y += $need;
                }
            }
            if ($pages <= 2) {
                break;
            }
        }

        // assign blocks to pages (greedy, blocks stay whole)
        $pageBlocks = [[]];
        $y = 0.0;
        foreach ($heights as $i => $h) {
            $cur  = count($pageBlocks) - 1;
            $need = $h + ($pageBlocks[$cur] ? self::TERMS_GAP : 0);
            if ($pageBlocks[$cur] && $y + $need > $avail) {
                $pageBlocks[] = [$i];
                $y = $h;
            } else {
                $pageBlocks[$cur][] = $i;
                $y += $need;
            }
        }

        // print (page 1 is already added). Leftover height on a page is spread over the gaps between its
        // blocks (max 9 mm each) so the page looks full rather than ending in a band of white space.
        foreach ($pageBlocks as $pageNo => $idxs) {
            if ($pageNo > 0) {
                $pdf->AddPage();
            }
            $pdf->SetY($top);

            $used = array_sum(array_map(fn($i) => $heights[$i], $idxs)) + self::TERMS_GAP * (count($idxs) - 1);
            $gap  = self::TERMS_GAP;
            if (count($idxs) > 1) {
                $gap += min(9.0, max(0.0, ($avail - $used - 4.0) / (count($idxs) - 1)));
            }
            foreach ($idxs as $k => $i) {
                $pdf->writeHTML($blocks[$i]['html'], true, false, true, false, '');
                if ($k < count($idxs) - 1) {
                    $pdf->SetY($pdf->GetY() + $gap);
                }
            }
        }

        $pdf->SetAutoPageBreak(true, self::MARGIN);
    }

    /** Height (mm) a block of HTML takes when printed at the top of a page; nothing is kept. */
    private function measureHtmlHeight(KicoPDF $pdf, string $html): float
    {
        $pdf->startTransaction();
        $pdf->SetY((float)self::MARGIN);
        $y0 = $pdf->GetY();
        $pdf->writeHTML($html, true, false, true, false, '');
        $h = $pdf->GetY() - $y0;
        $pdf->rollbackTransaction(true);
        return max(0.0, $h);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validate & normalise items
    // ─────────────────────────────────────────────────────────────────────────

    private function validateItems(array $items): array
    {
        $validated = [];
        $errors    = [];

        foreach ($items as $index => $item) {
            if (is_object($item)) {
                $item = (array)$item;
            }
            if (!is_array($item)) {
                $errors[] = "Item at index {$index} is not a valid object or array";
                continue;
            }

            // Category header row
            if (!empty($item['isCategory'])) {
                if (empty($item['description'])) {
                    $errors[] = "Category item at index {$index}: 'description' is required";
                    continue;
                }
                $validated[] = ['isCategory' => true, 'description' => (string)$item['description']];
                continue;
            }

            if (!isset($item['description']) || trim((string)$item['description']) === '') {
                $errors[] = "Item at index {$index}: 'description' is required";
                continue;
            }

            $out = [
                'isCategory'  => false,
                'isAccessory' => !empty($item['isAccessory']),
                'slNo'        => isset($item['slNo'])  ? (string)$item['slNo']  : '',
                'description' => trim((string)$item['description']),
                'qty'         => null,
                'unit'        => isset($item['unit'])  ? (string)$item['unit']  : '',
                'rate'        => null,
                'amount'      => null,
            ];

            // qty — accept either 'qty' or pre-set numeric value
            $qtyVal = $item['qty'] ?? null;
            if ($qtyVal !== null && $qtyVal !== '') {
                if (!is_numeric($qtyVal) || $qtyVal < 0) {
                    $errors[] = "Item at index {$index}: 'qty' must be a non-negative number";
                    continue;
                }
                $out['qty'] = (float)$qtyVal == (int)$qtyVal ? (int)$qtyVal : (float)$qtyVal;
            }

            if (isset($item['rate']) && $item['rate'] !== null && $item['rate'] !== '') {
                if (!is_numeric($item['rate'])) {
                    $errors[] = "Item at index {$index}: 'rate' must be numeric";
                    continue;
                }
                $out['rate'] = (float)$item['rate'];
            }

            if (isset($item['amount']) && $item['amount'] !== null && $item['amount'] !== '') {
                $out['amount'] = is_numeric($item['amount']) ? (float)$item['amount'] : (string)$item['amount'];
            }

            $validated[] = $out;
        }

        if (!empty($errors)) {
            throw new \Exception('Validation errors: ' . implode('; ', $errors));
        }
        if (empty($validated)) {
            throw new \Exception('No valid items found in the request');
        }

        return $validated;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Indian number formatting  e.g.  154662 → 1,54,662
    // ─────────────────────────────────────────────────────────────────────────

    private function formatIndian(float|int $number): string
    {
        $number   = (float)$number;
        $decimals = ($number != floor($number)) ? 2 : 0;

        // number_format adds Western commas — remove them before Indian grouping
        $formatted = number_format($number, $decimals, '.', '');
        $parts     = explode('.', $formatted);
        $int       = $parts[0];
        $dec       = isset($parts[1]) ? '.' . $parts[1] : '';

        if (strlen($int) <= 3) {
            return $int . $dec;
        }

        $last3 = substr($int, -3);
        $rest  = substr($int, 0, -3);
        $rest  = preg_replace('/(\d)(?=(\d{2})+$)/', '$1,', $rest);
        return $rest . ',' . $last3 . $dec;
    }
}
