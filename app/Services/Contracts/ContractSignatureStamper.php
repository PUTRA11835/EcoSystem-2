<?php

namespace App\Services\Contracts;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Draws each signer's signature (from the master data) at the bottom of EVERY page of a stamped contract PDF.
 *
 * The stamped original is never touched: a new file is produced from it. The free FPDI parser cannot read every PDF (a file
 * that uses compressed cross-reference streams is refused); that is reported as a reason, not thrown, so the workflow can
 * carry on with a signing record instead of a marked-up copy.
 */
class ContractSignatureStamper
{
    /**
     * @param  string  $pdfBytes  the stamped PDF
     * @param  array<int,array{image:string,label:string}>  $signers  in order, left to right (binary image: PNG or JPEG)
     * @return array{bytes:?string,pages:int,reason:?string}
     */
    public function overlay(string $pdfBytes, array $signers): array
    {
        if (!class_exists(Fpdi::class)) {
            return ['bytes' => null, 'pages' => 0, 'reason' => 'The PDF library is not installed.'];
        }

        $slotW = (float) config('hc_contract.signing.slot_width_mm', 30);
        $slotH = (float) config('hc_contract.signing.slot_height_mm', 11);
        $bottom = (float) config('hc_contract.signing.bottom_mm', 14);
        $right = (float) config('hc_contract.signing.right_mm', 10);
        $gap = 3.0;

        $temp = [];

        try {
            $pdf = new Fpdi();
            $pdf->SetAutoPageBreak(false);
            $pages = $pdf->setSourceFile(StreamReader::createByString($pdfBytes));

            // Signature images go to temp files once: FPDF reads images from a path.
            $images = [];
            foreach ($signers as $i => $signer) {
                $file = $this->tempImage($signer['image']);
                if ($file === null) {
                    continue;
                }
                $temp[] = $file['path'];
                $images[$i] = $file + ['label' => $this->ascii($signer['label'])];
            }

            for ($n = 1; $n <= $pages; $n++) {
                $tpl = $pdf->importPage($n);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);

                $count = count($images);
                $k = 0;
                foreach ($images as $img) {
                    $x = $size['width'] - $right - ($count - $k) * $slotW - ($count - $k - 1) * $gap;
                    $y = $size['height'] - $bottom - $slotH;
                    $pdf->Image($img['path'], $x, $y, 0, $slotH - 3, $img['type']);
                    $pdf->SetFont('Helvetica', '', 5);
                    $pdf->SetXY($x, $y + $slotH - 3);
                    $pdf->Cell($slotW, 3, $img['label'], 0, 0, 'L');
                    $k++;
                }
            }

            return ['bytes' => $pdf->Output('S'), 'pages' => $pages, 'reason' => null];
        } catch (\Throwable $e) {
            return ['bytes' => null, 'pages' => 0, 'reason' => $this->reasonOf($e)];
        } finally {
            foreach ($temp as $path) {
                @unlink($path);
            }
        }
    }

    /** Number of pages, or null when the file cannot be read by the free parser. */
    public function pageCount(string $pdfBytes): ?int
    {
        if (!class_exists(Fpdi::class)) {
            return null;
        }
        try {
            return (new Fpdi())->setSourceFile(StreamReader::createByString($pdfBytes));
        } catch (\Throwable) {
            return null;
        }
    }

    // ── internals ─────────────────────────────────────────────────────────

    /** @return array{path:string,type:string}|null */
    private function tempImage(string $binary): ?array
    {
        $type = str_starts_with($binary, "\x89PNG") ? 'PNG' : (str_starts_with($binary, "\xFF\xD8") ? 'JPG' : null);
        if ($type === null) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'sig') . '.' . strtolower($type);
        file_put_contents($path, $binary);

        return ['path' => $path, 'type' => $type];
    }

    /** FPDF core fonts have no Unicode: keep the label plain. */
    private function ascii(string $text): string
    {
        $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return mb_substr(trim((string) $text), 0, 48);
    }

    private function reasonOf(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (stripos($message, 'compression') !== false || stripos($message, 'cross-reference') !== false) {
            return 'This PDF uses a newer compression the built-in reader cannot open. Save it as PDF 1.4 to have the signatures drawn on its pages.';
        }

        return 'The PDF could not be read: ' . mb_substr($message, 0, 160);
    }
}
