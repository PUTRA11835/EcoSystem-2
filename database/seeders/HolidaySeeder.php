<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Impor hari libur dari config/indonesian-holidays.php ke tabel `holidays`.
     * Idempotent: updateOrCreate by (date, name) — aman dijalankan berulang.
     */
    public function run(): void
    {
        $byYear = config('indonesian-holidays', []);

        foreach ($byYear as $list) {
            foreach ($list as $h) {
                if (empty($h['date']) || empty($h['name'])) {
                    continue;
                }

                Holiday::updateOrCreate(
                    ['date' => $h['date'], 'name' => $h['name']],
                    ['type' => $h['type'] ?? 'national', 'is_active' => true],
                );
            }
        }
    }
}
