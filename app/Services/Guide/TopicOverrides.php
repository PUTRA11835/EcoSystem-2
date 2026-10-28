<?php

namespace App\Services\Guide;

/**
 * Sumber topik tambahan di atas berkas bawaan (mis. hasil sunting admin).
 * UserGuideService hanya mengenal antarmuka ini, bukan database.
 */
interface TopicOverrides
{
    /**
     * Topik satu bahasa dengan bentuk yang sama seperti UserGuideService::parseFile().
     *
     * @return array<string,array<string,mixed>> key => topik
     */
    public function forLocale(string $locale): array;

    /** Berubah setiap kali isi sumber berubah; ikut dalam tanda tangan cache. */
    public function version(): string;
}
