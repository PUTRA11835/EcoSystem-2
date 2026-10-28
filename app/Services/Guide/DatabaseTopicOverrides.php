<?php

namespace App\Services\Guide;

use App\Models\UserGuideTopic;
use Illuminate\Support\Facades\Schema;

/**
 * Topik hasil sunting admin dari tabel user_guide_topics.
 * Bila tabel belum ada (migrasi belum jalan) atau DB bermasalah, panduan tetap tampil dari berkas.
 */
final class DatabaseTopicOverrides implements TopicOverrides
{
    /** @var array<string,array<string,array<string,mixed>>>|null */
    private ?array $byLocale = null;
    private ?string $version = null;

    public function forLocale(string $locale): array
    {
        return $this->all()[$locale] ?? [];
    }

    public function version(): string
    {
        $this->all();

        return $this->version ?? '0';
    }

    /** Dibaca sekali per proses (satu permintaan web). */
    private function all(): array
    {
        if ($this->byLocale !== null) {
            return $this->byLocale;
        }
        $this->byLocale = [];
        $this->version = '0';

        try {
            if (!Schema::hasTable('user_guide_topics')) {
                return $this->byLocale;
            }
            $rows = UserGuideTopic::query()->get();
            foreach ($rows as $row) {
                $this->byLocale[$row->locale][$row->key] = $row->toTopic();
            }
            // Hash seluruh isi, bukan updated_at: dua penyuntingan dalam detik yang sama (atau hanya judul/syarat yang berubah) tetap membuang cache.
            $this->version = md5($rows->toJson());
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->byLocale;
    }
}
