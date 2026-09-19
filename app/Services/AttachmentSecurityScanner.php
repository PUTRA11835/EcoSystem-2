<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Flags a suspicious ticket attachment for the Security Center dashboard.
 * Log-only, like the rest of Security Center — a support ticket can
 * legitimately carry a .sh/.log/.sql file as troubleshooting evidence, so
 * this never rejects the upload, only surfaces it for review.
 */
class AttachmentSecurityScanner
{
    /** @return array{reasons: string[], filename: string, extension: string, mime: ?string, size: int}|null */
    public static function inspect(UploadedFile $file): ?array
    {
        $dangerousExt = config('security_center.upload.dangerous_extensions', []);
        $safeLooking  = config('security_center.upload.safe_looking_extensions', []);
        $mimeMap      = config('security_center.upload.mime_signatures', []);

        $originalName = $file->getClientOriginalName();
        $extension    = strtolower((string) $file->getClientOriginalExtension());
        $realMime     = $file->getMimeType(); // server-side, fileinfo/magic-byte based — not the spoofable client Content-Type

        $reasons = [];

        if ($extension !== '' && in_array($extension, $dangerousExt, true)) {
            $reasons[] = "executable/script extension \".{$extension}\"";
        }

        // Double extension: "invoice.pdf.php" — a document/image-looking
        // inner extension immediately followed by a dangerous outer one.
        $parts = explode('.', $originalName);
        if (count($parts) >= 3) {
            $innerExt = strtolower($parts[count($parts) - 2]);
            if (in_array($innerExt, $safeLooking, true) && in_array($extension, $dangerousExt, true)) {
                $reasons[] = "double extension disguising a \".{$extension}\" file as \".{$innerExt}\"";
            }
        }

        if (isset($mimeMap[$extension]) && $realMime && !in_array($realMime, $mimeMap[$extension], true)) {
            $reasons[] = "extension \".{$extension}\" but detected content type is \"{$realMime}\"";
        }

        if (empty($reasons)) {
            return null;
        }

        return [
            'reasons'   => $reasons,
            'filename'  => $originalName,
            'extension' => $extension,
            'mime'      => $realMime,
            'size'      => $file->getSize(),
        ];
    }
}
