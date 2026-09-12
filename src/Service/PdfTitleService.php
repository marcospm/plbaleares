<?php

namespace App\Service;

/**
 * Actualiza el metadato /Title de un PDF mediante actualización incremental.
 * Así el visor del navegador muestra el nombre correcto del tema en la pestaña.
 */
final class PdfTitleService
{
    public function applyTitleToFile(string $absolutePath, string $title): void
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false || !str_starts_with($content, '%PDF')) {
            throw new \InvalidArgumentException('Archivo PDF inválido: ' . $absolutePath);
        }

        $updated = $this->withTitle($content, $title);
        if (@file_put_contents($absolutePath, $updated) === false) {
            throw new \RuntimeException('No se pudo escribir el PDF actualizado: ' . $absolutePath);
        }
    }

    public function withTitle(string $pdfContent, string $title): string
    {
        if (!str_starts_with($pdfContent, '%PDF') || $title === '') {
            return $pdfContent;
        }

        if (!preg_match_all('/startxref\s+(\d+)\s*%%EOF/s', $pdfContent, $matches, PREG_OFFSET_CAPTURE)) {
            return $pdfContent;
        }

        $last = count($matches[0]) - 1;
        $prevXrefOffset = (int) $matches[1][$last][0];
        $eofEnd = $matches[0][$last][1] + strlen($matches[0][$last][0]);

        $beforeEof = substr($pdfContent, 0, $matches[0][$last][1]);
        if (!preg_match('/trailer\s*<<(.*)>>/s', $beforeEof, $trailerMatch)) {
            return $pdfContent;
        }

        $trailerDict = $trailerMatch[1];
        if (!preg_match('/\/Root\s+(\d+\s+\d+\s+R)/', $trailerDict, $rootMatch)) {
            return $pdfContent;
        }

        $size = 1;
        if (preg_match('/\/Size\s+(\d+)/', $trailerDict, $sizeMatch)) {
            $size = (int) $sizeMatch[1];
        }

        $newObjNum = $size;
        $newSize = $size + 1;
        $encodedTitle = $this->encodePdfString($title);

        $base = substr($pdfContent, 0, $eofEnd);
        if (!str_ends_with($base, "\n")) {
            $base .= "\n";
        }

        $objectOffset = strlen($base);
        $object = sprintf(
            "%d 0 obj\n<<\n/Title %s\n>>\nendobj\n",
            $newObjNum,
            $encodedTitle
        );

        $xrefOffset = $objectOffset + strlen($object);
        $xref = sprintf("xref\n%d 1\n%010d 00000 n \n", $newObjNum, $objectOffset);
        $trailer = sprintf(
            "trailer\n<<\n/Size %d\n/Root %s\n/Info %d 0 R\n/Prev %d\n>>\nstartxref\n%d\n%%%%EOF\n",
            $newSize,
            $rootMatch[1],
            $newObjNum,
            $prevXrefOffset,
            $xrefOffset
        );

        return $base . $object . $xref . $trailer;
    }

    private function encodePdfString(string $title): string
    {
        // UTF-16BE con BOM: soporta tildes y ñ en el título de la pestaña
        $utf16 = "\xFE\xFF" . mb_convert_encoding($title, 'UTF-16BE', 'UTF-8');

        return '<' . strtoupper(bin2hex($utf16)) . '>';
    }
}
