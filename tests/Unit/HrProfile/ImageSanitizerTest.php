<?php

namespace Tests\Unit\HrProfile;

use App\Services\HrProfile\ImageSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Uji pembersih gambar foto/tanda tangan (HC-D26). Memakai GD sungguhan dan berkas sementara.
 */
class ImageSanitizerTest extends TestCase
{
    /** @var string[] */
    private array $tmp = [];

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD tidak tersedia.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
    }

    private function file(string $bytes, string $name = 'x.bin'): string
    {
        $p = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'san_' . uniqid() . '_' . $name;
        file_put_contents($p, $bytes);
        $this->tmp[] = $p;

        return $p;
    }

    private function png(int $w, int $h, bool $transparent = false): string
    {
        $im = imagecreatetruecolor($w, $h);
        if ($transparent) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
            imagealphablending($im, true);
            imageline($im, 0, 0, $w - 1, $h - 1, imagecolorallocate($im, 10, 10, 120));
        } else {
            imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
        }
        ob_start();
        imagepng($im);
        $b = (string) ob_get_clean();
        imagedestroy($im);

        return $b;
    }

    private function jpeg(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 30));
        ob_start();
        imagejpeg($im, null, 90);
        $b = (string) ob_get_clean();
        imagedestroy($im);

        return $b;
    }

    public function test_foto_png_menjadi_jpeg_dan_diperkecil(): void
    {
        $r = ImageSanitizer::sanitize($this->file($this->png(2000, 1000)), 'photo');

        $this->assertSame('jpg', $r['extension']);
        $this->assertSame('image/jpeg', $r['mime']);
        $info = getimagesizefromstring($r['bytes']);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertSame(800, $info[0]);
        $this->assertSame(400, $info[1]);
    }

    public function test_foto_kecil_tidak_diperbesar(): void
    {
        $info = getimagesizefromstring(ImageSanitizer::sanitize($this->file($this->jpeg(120, 90)), 'photo')['bytes']);
        $this->assertSame([120, 90], [$info[0], $info[1]]);
    }

    public function test_tanda_tangan_png_tetap_transparan(): void
    {
        $r = ImageSanitizer::sanitize($this->file($this->png(1200, 400, true)), 'signature');

        $this->assertSame('png', $r['extension']);
        $im = imagecreatefromstring($r['bytes']);
        $this->assertSame(600, imagesx($im));
        $this->assertSame(200, imagesy($im));
        $alphaCorner = (imagecolorat($im, 1, imagesy($im) - 2) >> 24) & 0x7F; // sudut tanpa garis → transparan
        $this->assertGreaterThan(100, $alphaCorner, 'latar tanda tangan harus tetap transparan');
        imagedestroy($im);
    }

    public function test_muatan_php_yang_ditempel_di_gambar_terbuang(): void
    {
        $evil = $this->png(40, 40) . "\n<?php system(\$_GET['c']); ?>\n";
        foreach (['photo', 'signature'] as $kind) {
            $r = ImageSanitizer::sanitize($this->file($evil, 'evil.png'), $kind);
            $this->assertStringNotContainsString('<?php', $r['bytes'], $kind);
            $this->assertStringNotContainsString('system(', $r['bytes'], $kind);
        }
    }

    public function test_metadata_exif_terbuang(): void
    {
        // JPEG dengan segmen APP1 (EXIF) palsu disisipkan tepat setelah SOI.
        $jpeg = $this->jpeg(50, 50);
        $exif = "Exif\0\0" . 'GPS-LAT-6.2088,LON-106.8456';
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        $withExif = substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
        $this->assertStringContainsString('GPS-LAT', $withExif);

        $r = ImageSanitizer::sanitize($this->file($withExif, 'geo.jpg'), 'photo');
        $this->assertStringNotContainsString('GPS-LAT', $r['bytes']);
    }

    public function test_bukan_gambar_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageSanitizer::sanitize($this->file('<?php echo 1; ?>', 'shell.png'), 'photo');
    }

    public function test_html_dan_svg_ditolak(): void
    {
        foreach (['<html><script>alert(1)</script></html>', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>'] as $body) {
            try {
                ImageSanitizer::sanitize($this->file($body, 'x.png'), 'photo');
                $this->fail('seharusnya ditolak');
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_webp_boleh_untuk_foto_tetapi_tidak_untuk_tanda_tangan(): void
    {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('WEBP tidak didukung GD.');
        }
        $im = imagecreatetruecolor(60, 60);
        ob_start();
        imagewebp($im);
        $webp = (string) ob_get_clean();
        imagedestroy($im);

        $this->assertSame('jpg', ImageSanitizer::sanitize($this->file($webp, 'a.webp'), 'photo')['extension']);

        $this->expectException(InvalidArgumentException::class);
        ImageSanitizer::sanitize($this->file($webp, 'a.webp'), 'signature');
    }

    public function test_batas_ukuran_berkas(): void
    {
        $big = $this->png(10, 10) . str_repeat("\0", 1024 * 1024 + 10); // > 1 MB
        $this->expectException(InvalidArgumentException::class);
        ImageSanitizer::sanitize($this->file($big, 'big.png'), 'signature');
    }

    public function test_gambar_dengan_piksel_terlalu_banyak_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // PNG 6000×6000 = 36 juta piksel (> 25 juta) yang sangat kecil di disk.
        $im = imagecreate(6000, 6000);
        imagecolorallocate($im, 255, 255, 255);
        ob_start();
        imagepng($im, null, 9);
        $b = (string) ob_get_clean();
        imagedestroy($im);
        ImageSanitizer::sanitize($this->file($b, 'huge.png'), 'photo');
    }

    public function test_jenis_gambar_tak_dikenal_dan_berkas_kosong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageSanitizer::sanitize($this->file('', 'e.png'), 'photo');
    }
}
