<?php

namespace App\Controllers;

use App\Models\ProposalModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Serves generated proposal PDFs as a forced DOWNLOAD (Content-Disposition: attachment).
 * Opening the file inline in the browser is still done through the plain /uploads/<file>.pdf URL.
 *
 *   GET pdf/download/kico_estimate_1794133748.pdf   (older files: kico_estimate<digits>.pdf, kitchen_export_<digits>.pdf)
 */
class PdfFile extends BaseController
{
    public function download(string $fileName = ''): ResponseInterface
    {
        // Only files this app generated: no path separators, no traversal
        if (!preg_match('/^(?:kico_estimate_?|kitchen_export_)\d+\.pdf$/', $fileName)) {
            return $this->response->setStatusCode(404)->setBody('File not found');
        }

        $path = FCPATH . 'uploads/' . $fileName;
        if (!is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('File not found');
        }

        // Friendly download name: "kico_estimate_<project number>.pdf" when the proposal is known
        $downloadName = $fileName;
        $proposal = (new ProposalModel())->where('pdf_file_name', $fileName)->first();
        if ($proposal) {
            $label = trim((string)($proposal['project_number'] ?? ''));
            $label = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $label), '-');
            $downloadName = 'kico_estimate_' . ($label !== '' ? $label : $proposal['id']) . '.pdf';
        }

        return $this->response
            ->download($path, null)
            ->setFileName($downloadName)
            ->setContentType('application/pdf')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
    }
}
