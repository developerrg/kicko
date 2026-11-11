<?php

namespace App\Libraries;

/**
 * Reads a .docx (the "kico terms.docx" terms & conditions document) and converts its body into HTML
 * fragments that TCPDF::writeHTML() can print: tables (cell shading, borders, widths, merged cells,
 * bold / italic / colour / size) and paragraphs.
 *
 * Every top-level table or paragraph becomes ONE block; a table block is a stack of single-row
 * <table>s so each row keeps its own column widths. Rows with no text at all are skipped.
 * The caller decides on page breaks between blocks and passes a $scale that grows/shrinks all fonts.
 */
class DocxTermsRenderer
{
    private const NS   = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const BASE = 22;          // docDefaults font size (half-points) when a run has none: 11pt

    /** @var array<string,string> document colour (RRGGBB) => colour to print instead */
    private array $colorMap = [];

    private ?\DOMDocument $doc  = null;
    private ?\DOMXPath    $xp   = null;
    private bool          $read = false;

    public function __construct(private string $path)
    {
    }

    /** Print every use of a document colour (header fills, accent text, accent borders) in another colour. */
    public function mapColor(string $from, string $to): self
    {
        $this->colorMap[strtoupper(ltrim($from, '#'))] = strtoupper(ltrim($to, '#'));
        return $this;
    }

    private function color(?string $hex): ?string
    {
        if ($hex === null) {
            return null;
        }
        $hex = strtoupper($hex);
        return $this->colorMap[$hex] ?? $hex;
    }

    public function isAvailable(): bool
    {
        $this->load();
        return $this->doc !== null;
    }

    /**
     * @return array<int, array{type:string, html:string}> one entry per top-level table / non-empty paragraph
     */
    public function blocks(float $scale = 1.0): array
    {
        $this->load();
        if ($this->doc === null) {
            return [];
        }

        $blocks = [];
        $body   = $this->xp->query('/w:document/w:body')->item(0);
        foreach ($body->childNodes as $node) {
            if (!($node instanceof \DOMElement)) {
                continue;
            }
            if ($node->localName === 'tbl') {
                $html = $this->table($node, $scale);
                if ($html !== '') {
                    $blocks[] = ['type' => 'table', 'html' => $html];
                }
            } elseif ($node->localName === 'p') {
                $html = $this->paragraph($node, $scale, 0);
                if (trim(strip_tags($html)) !== '') {
                    $blocks[] = ['type' => 'p', 'html' => $html];
                }
            }
        }
        return $blocks;
    }

    // ── loading ──────────────────────────────────────────────────────────────

    private function load(): void
    {
        if ($this->read) {
            return;
        }
        $this->read = true;

        if (!is_file($this->path) || !class_exists(\ZipArchive::class)) {
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            return;
        }

        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return;
        }
        $this->doc = $doc;
        $this->xp  = new \DOMXPath($doc);
        $this->xp->registerNamespace('w', self::NS);
    }

    // ── tables ───────────────────────────────────────────────────────────────

    private function table(\DOMElement $tbl, float $scale): string
    {
        // 1. collect rows: cells with text, width (dxa), fill, borders, padding
        $rows = [];
        foreach ($this->xp->query('w:tr', $tbl) as $tr) {
            $cells = [];
            foreach ($this->xp->query('w:tc', $tr) as $tc) {
                $cells[] = $this->cell($tc);
            }
            if (!$cells) {
                continue;
            }
            $hasText = false;
            foreach ($cells as $c) {
                if ($c['text'] !== '') {
                    $hasText = true;
                    break;
                }
            }
            if ($hasText) {
                $rows[] = $cells;
            }
        }
        if (!$rows) {
            return '';
        }

        // 2. column template: rows with the same number of cells share the most common width split,
        //    so a header row lines up with the body rows even when the file stores slightly different widths
        $templates = [];
        foreach ($rows as $cells) {
            if (count($cells) < 2) {
                continue;
            }
            $key = count($cells);
            $vec = $this->normWidths($cells);
            $sig = implode(',', array_map(static fn($v) => (string)round($v), $vec));
            $templates[$key][$sig] = ($templates[$key][$sig] ?? ['n' => 0, 'vec' => $vec]);
            $templates[$key][$sig]['n']++;
        }
        $best = [];
        foreach ($templates as $key => $variants) {
            uasort($variants, static fn($a, $b) => $b['n'] <=> $a['n']);
            $best[$key] = reset($variants)['vec'];
        }

        // 3. one single-row table per row
        $html = '';
        foreach ($rows as $cells) {
            $widths = count($cells) < 2 ? [100.0] : ($best[count($cells)] ?? $this->normWidths($cells));
            $pad    = $cells[0]['pad'];
            $padPt  = max(1.0, $pad * $scale);

            $html .= '<table cellpadding="' . round($padPt, 1) . '" cellspacing="0" border="0" width="100%"><tr>';
            foreach ($cells as $i => $c) {
                $style = [];
                $style[] = 'border-bottom:0.4px solid ' . ($c['borderBottom'] ?? '#E0E0E0');
                $style[] = 'border-top:0.4px solid ' . ($c['borderTop'] ?? '#E0E0E0');
                if ($c['borderLeft'] !== null) {
                    $style[] = 'border-left:1.1px solid ' . $c['borderLeft'];
                }
                $bg = $c['fill'] !== null ? ' bgcolor="#' . $c['fill'] . '"' : '';
                $html .= '<td width="' . round($widths[$i] ?? 0, 3) . '%"' . $bg . ' style="' . implode(';', $style) . '">'
                       . $this->cellContent($c['node'], $scale) . '</td>';
            }
            $html .= '</tr></table>';
        }
        return $html;
    }

    /** @return array{node:\DOMElement,text:string,w:float,fill:?string,pad:float,borderLeft:?string,borderTop:?string,borderBottom:?string} */
    private function cell(\DOMElement $tc): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $this->plainText($tc)));

        $w = (float)($this->attr($this->xp->query('w:tcPr/w:tcW', $tc)->item(0), 'w') ?? 0);
        $fill = $this->attr($this->xp->query('w:tcPr/w:shd', $tc)->item(0), 'fill');
        $fill = ($fill !== null && strtolower($fill) !== 'auto' && preg_match('/^[0-9A-Fa-f]{6}$/', $fill)) ? $this->color($fill) : null;

        // cell margin (dxa -> pt): use the left margin as the table-cell padding
        $mar = $this->attr($this->xp->query('w:tcPr/w:tcMar/w:left', $tc)->item(0), 'w');
        $pad = $mar !== null ? ((float)$mar / 20.0) : 4.0;

        $left = $this->border($tc, 'left');
        return [
            'node'         => $tc,
            'text'         => $text,
            'w'            => $w,
            'fill'         => $fill,
            'pad'          => $pad,
            'borderLeft'   => $left,
            'borderTop'    => $this->border($tc, 'top'),
            'borderBottom' => $this->border($tc, 'bottom'),
        ];
    }

    private function border(\DOMElement $tc, string $side): ?string
    {
        $b = $this->xp->query('w:tcPr/w:tcBorders/w:' . $side, $tc)->item(0);
        if (!$b) {
            return null;
        }
        $val   = $this->attr($b, 'val');
        $color = $this->attr($b, 'color');
        if ($val === null || $val === 'nil' || $val === 'none') {
            return null;
        }
        return ($color !== null && preg_match('/^[0-9A-Fa-f]{6}$/', $color)) ? '#' . $this->color($color) : '#E0E0E0';
    }

    /** @param array<int,array{w:float}> $cells */
    private function normWidths(array $cells): array
    {
        $sum = array_sum(array_column($cells, 'w'));
        if ($sum <= 0) {
            $n = count($cells);
            return array_fill(0, $n, 100.0 / $n);
        }
        return array_map(static fn($c) => $c['w'] / $sum * 100.0, $cells);
    }

    private function cellContent(\DOMElement $tc, float $scale): string
    {
        $out = '';
        $first = true;
        foreach ($this->xp->query('w:p', $tc) as $p) {
            $out .= $this->paragraph($p, $scale, $first ? 0 : 1);
            $first = false;
        }
        return $out;
    }

    // ── paragraphs ───────────────────────────────────────────────────────────

    /**
     * One paragraph as a <div> with alignment; runs become styled <span>s, <w:br/> becomes <br/>.
     * Space before the paragraph (w:spacing before) is rendered as a small blank line.
     */
    private function paragraph(\DOMElement $p, float $scale, int $index): string
    {
        $jc = $this->attr($this->xp->query('w:pPr/w:jc', $p)->item(0), 'val');
        $align = match ($jc) {
            'center' => 'center',
            'right', 'end' => 'right',
            'both', 'distribute' => 'justify',
            default => 'left',
        };

        $before = (float)($this->attr($this->xp->query('w:pPr/w:spacing', $p)->item(0), 'before') ?? 0) / 20.0; // pt
        $after  = (float)($this->attr($this->xp->query('w:pPr/w:spacing', $p)->item(0), 'after') ?? 0) / 20.0;
        $lineAttr = $this->attr($this->xp->query('w:pPr/w:spacing', $p)->item(0), 'line');
        $lineMul  = ($lineAttr !== null && is_numeric($lineAttr) && (float)$lineAttr > 240) ? (float)$lineAttr / 240.0 : 1.0;

        $inner = '';
        $lastSize = 8.0;
        foreach ($this->xp->query('w:r', $p) as $r) {
            $rp = $this->runProps($r);
            $size = $rp['size'] * $scale;
            $lastSize = $size;

            // bold/italic go in the span style (TCPDF leaks <b>/<i> tags into following cells)
            $styleParts = ['font-size:' . round($size, 2) . 'pt', 'font-weight:' . ($rp['bold'] ? 'bold' : 'normal'), 'font-style:' . ($rp['italic'] ? 'italic' : 'normal')];
            if ($rp['color'] !== null) {
                $styleParts[] = 'color:#' . $rp['color'];
            }

            $chunk = '';
            foreach ($r->childNodes as $child) {
                if (!($child instanceof \DOMElement)) {
                    continue;
                }
                if ($child->localName === 't') {
                    $chunk .= htmlspecialchars($child->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                } elseif ($child->localName === 'br') {
                    $chunk .= '<br/>';
                } elseif ($child->localName === 'tab') {
                    $chunk .= '    ';
                }
            }
            if ($chunk === '') {
                continue;
            }
            $inner .= '<span style="' . implode(';', $styleParts) . '">' . $chunk . '</span>';
        }

        if (trim(strip_tags($inner)) === '' && strpos($inner, '<br') === false) {
            return '';
        }

        $html = '';
        // paragraph spacing: a blank line whose font size equals the spacing (line-height 1 = that many pt)
        if ($before > 0 && $index > 0) {
            $html .= '<div style="font-size:' . round(max(1.5, $before * 0.55 * $scale), 1) . 'pt;line-height:1">&nbsp;</div>';
        }
        $html .= '<div style="text-align:' . $align . ';line-height:' . round($lineMul * 1.25, 2) . '">' . $inner . '</div>';
        if ($after >= 3) {
            $html .= '<div style="font-size:' . round(max(1.5, $after * 0.55 * $scale), 1) . 'pt;line-height:1">&nbsp;</div>';
        }
        return $html;
    }

    /** @return array{bold:bool,italic:bool,color:?string,size:float} size in pt */
    private function runProps(\DOMElement $r): array
    {
        $rpr = $this->xp->query('w:rPr', $r)->item(0);
        $bold = $italic = false;
        $color = null;
        $half = self::BASE;

        if ($rpr) {
            $b = $this->xp->query('w:b', $rpr)->item(0);
            $i = $this->xp->query('w:i', $rpr)->item(0);
            $bold   = $b && !in_array($this->attr($b, 'val'), ['0', 'false', 'off'], true);
            $italic = $i && !in_array($this->attr($i, 'val'), ['0', 'false', 'off'], true);

            $c = $this->attr($this->xp->query('w:color', $rpr)->item(0), 'val');
            if ($c !== null && preg_match('/^[0-9A-Fa-f]{6}$/', $c)) {
                $color = $this->color($c);
            }
            $sz = $this->attr($this->xp->query('w:sz', $rpr)->item(0), 'val');
            if ($sz !== null && is_numeric($sz)) {
                $half = (float)$sz;
            }
        }
        return ['bold' => $bold, 'italic' => $italic, 'color' => $color, 'size' => $half / 2.0];
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function plainText(\DOMElement $el): string
    {
        $t = '';
        foreach ($this->xp->query('.//w:t', $el) as $n) {
            $t .= $n->textContent;
        }
        return $t;
    }

    private function attr(?\DOMNode $node, string $name): ?string
    {
        if (!$node instanceof \DOMElement) {
            return null;
        }
        return $node->hasAttributeNS(self::NS, $name) ? $node->getAttributeNS(self::NS, $name) : null;
    }
}
