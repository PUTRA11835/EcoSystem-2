<?php

namespace App\Services\HrProfile;

use InvalidArgumentException;

/**
 * Pembersih gambar foto profil & tanda tangan (HC-D26).
 *
 * Gambar TIDAK pernah disimpan apa adanya. Berkas dibaca, tipe aslinya diperiksa dari ISI (bukan
 * nama/ekstensi/MIME kiriman klien), lalu DIBUAT ULANG lewat GD. Akibatnya:
 *   - metadata EXIF (lokasi GPS, perangkat, waktu) terbuang;
 *   - muatan tersembunyi (mis. skrip PHP yang ditempel di akhir PNG/JPEG — "polyglot") ikut hilang;
 *   - ukuran dibatasi, sehingga gambar raksasa tidak membebani halaman/dokumen cetak.
 * Kegagalan melempar InvalidArgumentException dengan pesan yang aman ditampilkan ke pengguna.
 *
 * Foto → JPEG (maks 800 px, latar putih bila ada transparansi).
 * Tanda tangan → PNG berlatar transparan (maks 600×300 px) agar bisa ditumpuk di dokumen; masukan
 * JPEG dipakai apa adanya tanpa transparansi.
 */
class ImageSanitizer
{
    public const PHOTO_MAX_BYTES     = 4 * 1024 * 1024; // sama dengan form konsultan ESH ("maks 4 MB")
    public const SIGNATURE_MAX_BYTES = 1 * 1024 * 1024;

    /** Batas piksel masukan (anti "decompression bomb": file kecil, gambar sangat besar). */
    private const MAX_INPUT_PIXELS = 25_000_000;

    private const PHOTO_MAX_SIDE = 800;
    private const SIG_MAX_W      = 600;
    private const SIG_MAX_H      = 300;

    /**
     * @param  string  $kind  'photo' | 'signature'
     * @return array{bytes: string, extension: string, mime: string}
     */
    public static function sanitize(string $path, string $kind): array
    {
        if (!in_array($kind, ['photo', 'signature'], true)) {
            throw new InvalidArgumentException('Unknown image kind.');
        }
        if (!extension_loaded('gd')) {
            throw new InvalidArgumentException('Image processing is not available on this server.');
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('The uploaded file could not be read.');
        }

        $size = filesize($path);
        $max  = $kind === 'photo' ? self::PHOTO_MAX_BYTES : self::SIGNATURE_MAX_BYTES;
        if ($size === false || $size <= 0) {
            throw new InvalidArgumentException('The uploaded file is empty.');
        }
        if ($size > $max) {
            throw new InvalidArgumentException('The image is too large. Maximum size is ' . ($max / 1024 / 1024) . ' MB.');
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw new InvalidArgumentException('The file is not a valid image.');
        }

        [$w, $h, $type] = $info;
        $allowed = $kind === 'photo'
            ? [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP]
            : [IMAGETYPE_PNG, IMAGETYPE_JPEG];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(
                $kind === 'photo' ? 'Photo must be a JPG, PNG or WEBP image.' : 'Signature must be a PNG or JPG image.'
            );
        }
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_INPUT_PIXELS) {
            throw new InvalidArgumentException('The image dimensions are not supported.');
        }

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };
        if (!$src) {
            throw new InvalidArgumentException('The image could not be processed. Please try another file.');
        }

        try {
            return $kind === 'photo' ? self::photo($src, $w, $h) : self::signature($src, $w, $h, $type === IMAGETYPE_PNG);
        } finally {
            imagedestroy($src);
        }
    }

    private static function photo($src, int $w, int $h): array
    {
        [$nw, $nh] = self::fit($w, $h, self::PHOTO_MAX_SIDE, self::PHOTO_MAX_SIDE);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG/WEBP transparan
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($dst);

        return ['bytes' => $bytes, 'extension' => 'jpg', 'mime' => 'image/jpeg'];
    }

    private static function signature($src, int $w, int $h, bool $isPng): array
    {
        [$nw, $nh] = self::fit($w, $h, self::SIG_MAX_W, self::SIG_MAX_H);
        $dst = imagecreatetruecolor($nw, $nh);

        if ($isPng) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagealphablending($dst, true);
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if ($isPng) {
            imagesavealpha($dst, true);
        }

        ob_start();
        imagepng($dst, null, 6);
        $bytes = (string) ob_get_clean();
        imagedestroy($dst);

        return ['bytes' => $bytes, 'extension' => 'png', 'mime' => 'image/png'];
    }

    /** Skala ke dalam kotak maxW×maxH tanpa memperbesar. @return array{0:int,1:int} */
    private static function fit(int $w, int $h, int $maxW, int $maxH): array
    {
        $ratio = min($maxW / $w, $maxH / $h, 1);

        return [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
    }
}
