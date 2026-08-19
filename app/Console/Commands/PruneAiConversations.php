<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Buang berkas percakapan AI yang sudah kedaluwarsa dari cache store 'ai_chat'
 * (dipakai bersama oleh halaman AI Assistant dan AI Research).
 *
 * KENAPA PERLU: cache file Laravel hanya menghapus entri kedaluwarsa saat kunci
 * itu DIBACA lagi (lihat FileStore::getPayload). Percakapan yang ditinggalkan —
 * user menutup tab dan tidak pernah kembali — kuncinya tidak akan pernah dibaca,
 * jadi berkasnya menetap di disk selamanya. Dengan banyak user, dan lampiran
 * gambar yang ikut tersimpan di dalam riwayat, disk naik terus tanpa ada yang
 * menyadari sampai penuh.
 *
 * CARA KERJA: 10 byte pertama tiap berkas cache adalah timestamp UNIX
 * kedaluwarsa dalam bentuk teks. Itu saja yang dibaca — isi berkasnya SENGAJA
 * tidak pernah di-unserialize, karena entri lama bisa memuat objek SDK Anthropic
 * yang meledak saat di-unserialize ("Typed static property … must not be
 * accessed before initialization"). Berkas yang tidak terbaca atau kepalanya
 * rusak ikut dibuang: tidak ada nilainya, dan hanya akan bikin error saat dibaca.
 *
 * SEJAK ARSIP ADA: command ini juga memangkas tabel ai_conversations yang
 * melewati retensi (config services.ai.retention_days). Dua pekerjaan itu
 * hanya menumpang command yang sama karena sama-sama sampah yang tidak ada
 * yang membersihkan — sifatnya berbeda: cache berumur jam, arsip berumur bulan.
 *
 * Default = DRY-RUN. Tambahkan --apply untuk benar-benar menghapus.
 */
class PruneAiConversations extends Command
{
    protected $signature = 'ai:prune-conversations
        {--apply : Terapkan penghapusan (tanpa flag ini hanya dry-run/preview)}';

    protected $description = 'Buang cache percakapan AI yang kedaluwarsa + arsip riwayat yang melewati retensi';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $root = config('cache.stores.ai_chat.path');

        if (!$root || !is_dir($root)) {
            $this->info('Direktori cache ai_chat belum ada — tidak ada yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $now = time();
        $expired = 0;
        $corrupt = 0;
        $kept = 0;
        $freed = 0;

        foreach (File::allFiles($root) as $file) {
            $path = $file->getPathname();
            $size = $file->getSize();

            $head = @file_get_contents($path, false, null, 0, 10);

            // Kepala tidak terbaca / bukan timestamp → berkas rusak.
            if (false === $head || 10 !== strlen($head) || !ctype_digit($head)) {
                ++$corrupt;
                $freed += $size;

                if ($apply) {
                    @unlink($path);
                }

                continue;
            }

            // Cache "abadi" ditulis Laravel dengan expiry 9999999999 — biarkan.
            if ((int) $head > $now) {
                ++$kept;
                continue;
            }

            ++$expired;
            $freed += $size;

            if ($apply) {
                @unlink($path);
            }
        }

        if ($apply) {
            $this->removeEmptyDirectories($root);
        }

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Masih berlaku (dibiarkan)', $kept],
                ['Kedaluwarsa', $expired],
                ['Rusak / tidak terbaca', $corrupt],
                ['Ruang dibebaskan', $this->humanSize($freed)],
            ],
        );

        $prunedArchive = $this->pruneArchive($apply);

        if (!$apply && ($expired + $corrupt + $prunedArchive) > 0) {
            $this->warn('Dry-run: belum ada yang dihapus. Jalankan ulang dengan --apply untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Buang arsip percakapan (tabel ai_conversations) yang melewati retensi.
     *
     * Terpisah dari pembersihan cache di atas dan memang harus begitu: cache
     * adalah konteks kerja berumur jam, arsip adalah riwayat berumur bulan.
     * Keduanya kebetulan dibersihkan oleh command yang sama karena sama-sama
     * "sampah AI yang tidak ada yang membersihkan", bukan karena sejenis.
     *
     * Retensi 0 = simpan selamanya (lihat config/services.php → ai.retention_days).
     *
     * @return int jumlah percakapan yang (akan) dihapus
     */
    private function pruneArchive(bool $apply): int
    {
        $days = (int) config('services.ai.retention_days', 90);

        if ($days < 1) {
            $this->line('Retensi arsip: nonaktif (AI_HISTORY_RETENTION_DAYS=0) — riwayat disimpan selamanya.');

            return 0;
        }

        $cutoff = now()->subDays($days);

        // last_message_at NULL = baris lama/rusak yang tidak pernah menerima
        // pesan; ikut dibuang lewat created_at supaya tidak menetap selamanya.
        $query = AiConversation::where(function ($q) use ($cutoff) {
            $q->where('last_message_at', '<', $cutoff)
              ->orWhere(fn ($q2) => $q2->whereNull('last_message_at')->where('created_at', '<', $cutoff));
        });

        $count = (clone $query)->count();

        if ($count > 0 && $apply) {
            // Dihapus per model (bukan mass delete) supaya cascade DB tetap
            // dipakai konsisten dan chunk-nya tidak memuat seluruh tabel.
            $query->select('id')->chunkById(200, function ($rows): void {
                AiConversation::whereIn('id', $rows->pluck('id'))->get()->each->delete();
            });
        }

        $this->table(
            ['Arsip percakapan', 'Jumlah'],
            [
                ['Retensi (hari)', $days],
                ['Lewat retensi', $count],
            ],
        );

        return $count;
    }

    /**
     * Cache file Laravel membuat direktori bersarang dua tingkat (ab/cd/hash).
     * Setelah berkasnya dibuang, direktori kosongnya ikut dirapikan supaya
     * tidak menyisakan ribuan folder kosong.
     */
    private function removeEmptyDirectories(string $root): void
    {
        foreach (array_reverse(File::directories($root)) as $dir) {
            $this->removeEmptyDirectories($dir);
        }

        // Jangan pernah hapus direktori akar store-nya sendiri.
        if ($root !== config('cache.stores.ai_chat.path')
            && empty(File::files($root))
            && empty(File::directories($root))) {
            @rmdir($root);
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1024 / 1024, 1) . ' MB';
    }
}
