<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;

/**
 * Provides a simplified document renderer capable of reusing existing PDF
 * attachments when available or falling back to a lightweight textual export
 * encoded as PDF content.
 */
class PdfRenderer
{
    /**
     * @return array{content: string, filename: string, mime_type: string, source: string}
     */
    public function render(BusinessDocument $document): array
    {
        foreach ($document->getAttachedFiles() as $relation) {
            $file = $relation->getFile();
            if (!$file->isPdf()) {
                continue;
            }

            $path = $file->getFullPath();
            if (!is_file($path)) {
                continue;
            }

            $binary = (string)file_get_contents($path);
            if ($binary === '') {
                continue;
            }

            return [
                'content' => $binary,
                'filename' => $file->filename,
                'mime_type' => $file->mimetype ?: 'application/pdf',
                'source' => 'attachment',
            ];
        }

        $content = $this->renderFallback($document);

        return [
            'content' => $content,
            'filename' => $this->buildDefaultFilename($document),
            'mime_type' => 'application/pdf',
            'source' => 'generated',
        ];
    }

    private function buildDefaultFilename(BusinessDocument $document): string
    {
        $code = (string)($document->codigo ?? $document->id());
        if ($code === '') {
            $code = $document->modelClassName();
        }

        $code = Tools::slug($code, '-', 0);
        if ($code === '') {
            $code = 'documento';
        }

        return $code . '.pdf';
    }

    private function renderFallback(BusinessDocument $document): string
    {
        $lines = [
            'FacturaScripts document export',
            'Model: ' . $document->modelClassName(),
            'Code: ' . (string)($document->codigo ?? $document->id()),
            'Date: ' . (string)($document->fecha ?? Tools::date()),
            'Subject: ' . $this->resolveSubject($document),
            'Total: ' . (string)($document->total ?? ''),
        ];

        $text = implode("\n", $lines);
        return $this->wrapAsPdf($text);
    }

    private function resolveSubject(BusinessDocument $document): string
    {
        if (!empty($document->nombrecliente)) {
            return (string)$document->nombrecliente;
        }
        if (!empty($document->nombre)) {
            return (string)$document->nombre;
        }
        if (!empty($document->subjectColumnValue())) {
            return (string)$document->subjectColumnValue();
        }

        return '';
    }

    private function wrapAsPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $contentStream = "BT /F1 12 Tf 50 780 Td ($escaped) Tj ET";
        $length = strlen($contentStream);

        $objects = [];
        $offsets = [0];
        $pdf = "%PDF-1.4\n";

        $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
        $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj";
        $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj";
        $objects[] = "4 0 obj << /Length $length >> stream\n$contentStream\nendstream endobj";
        $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . count($offsets) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); ++$i) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer << /Size " . count($offsets) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}
