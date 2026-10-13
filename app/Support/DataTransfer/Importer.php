<?php

namespace App\Support\DataTransfer;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Impor spreadsheet ke satu Dataset.
 *
 * SEMUA-ATAU-TIDAK-SAMA-SEKALI: bila satu baris saja gagal validasi, tidak ada
 * baris yang disimpan dan seluruh galat dikembalikan sekaligus, sehingga
 * pengguna memperbaiki berkasnya satu kali — bukan mengimpor setengah jadi.
 */
class Importer
{
    public const MAX_ROWS = 2000;

    /** @return array{created: int, updated: int, skipped?: int, errors: array<int, string>} */
    public function run(Dataset $dataset, UploadedFile $file, int $actorId): array
    {
        $config = $dataset->import;
        $sheet  = $this->firstSheet($file);

        [$headerIndex, $map] = $this->locateHeader($sheet, $dataset);

        if ($headerIndex === null) {
            $missing = collect($dataset->columns)
                ->filter(fn ($c) => !empty($c['required']))
                ->pluck('label')
                ->implode(', ');

            return $this->failure("Header row not found. The sheet must contain the columns: {$missing}. Download the template to get the right layout.");
        }

        $rows   = array_slice($sheet, $headerIndex + 1, null, true);
        $errors = [];
        $parsed = [];
        $seen   = [];

        foreach ($rows as $index => $cells) {
            $line = $index + 1;

            if ($this->isEmpty($cells)) {
                continue;
            }

            if (count($parsed) >= self::MAX_ROWS) {
                return $this->failure('Too many rows. Import at most ' . self::MAX_ROWS . ' rows at a time.');
            }

            $values = [];
            $rowErr = [];

            foreach ($dataset->columns as $column) {
                if (!isset($column['rules']) && !isset($column['parse'])) {
                    continue;
                }

                $raw = isset($map[$column['key']]) ? ($cells[$map[$column['key']]] ?? null) : null;

                try {
                    $values[$column['key']] = isset($column['parse'])
                        ? ($column['parse'])($raw)
                        : Cell::text($raw);
                } catch (\InvalidArgumentException $e) {
                    $rowErr[] = $column['label'] . ': ' . $e->getMessage();
                }
            }

            if ($rowErr === []) {
                $rules  = collect($dataset->columns)->filter(fn ($c) => isset($c['rules']))
                    ->mapWithKeys(fn ($c) => [$c['key'] => $c['rules']])->all();
                $labels = collect($dataset->columns)->pluck('label', 'key')->all();

                $validator = Validator::make($values, $rules, [], $labels);

                if ($validator->fails()) {
                    $rowErr = $validator->errors()->all();
                } elseif (isset($config['check']) && ($message = ($config['check'])($values))) {
                    $rowErr[] = $message;
                }
            }

            $key = $values[$config['key']] ?? null;
            if ($rowErr === [] && $key !== null) {
                $id = mb_strtolower((string) $key);
                if (isset($seen[$id])) {
                    $rowErr[] = "Duplicate \"{$key}\" — already used on row {$seen[$id]}.";
                }
                $seen[$id] = $line;
            }

            if ($rowErr !== []) {
                $errors[] = "Row {$line}: " . implode(' ', $rowErr);
                continue;
            }

            $parsed[] = $values;
        }

        if ($errors !== []) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        if ($parsed === []) {
            return $this->failure('The file has no data rows below the header.');
        }

        $created = $updated = $skipped = 0;

        DB::transaction(function () use ($parsed, $config, $actorId, &$created, &$updated, &$skipped) {
            foreach ($parsed as $values) {
                $existing = ($config['find'])($values);

                // A save callback returns false for a row it must leave alone
                // (e.g. a letter that is already closed) — counted, not failed.
                if (($config['save'])($values, $existing, $actorId) === false) {
                    $skipped++;
                    continue;
                }

                $existing ? $updated++ : $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => []];
    }

    /**
     * Baris sheet pertama, diindeks 0-based menurut baris SEBENARNYA di Excel
     * (baris kosong tidak dibuang), supaya nomor baris pada galat cocok dengan
     * yang dilihat pengguna.
     *
     * @return array<int, array<int, mixed>>
     */
    private function firstSheet(UploadedFile $file): array
    {
        $book = IOFactory::load($file->getRealPath());

        return $book->getActiveSheet()->toArray(null, true, false, false);
    }

    /** @return array{0: ?int, 1: array<string, int>} */
    private function locateHeader(array $sheet, Dataset $dataset): array
    {
        $required = collect($dataset->columns)->filter(fn ($c) => !empty($c['required']));

        foreach (array_slice($sheet, 0, 15, true) as $index => $cells) {
            $normalised = array_map(fn ($v) => $this->norm($v), array_values($cells));

            if ($required->every(fn ($c) => in_array($this->norm($c['label']), $normalised, true))) {
                $map = [];

                foreach ($dataset->columns as $column) {
                    $position = array_search($this->norm($column['label']), $normalised, true);

                    if ($position !== false) {
                        $map[$column['key']] = $position;
                    }
                }

                return [$index, $map];
            }
        }

        return [null, []];
    }

    private function norm(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{created: int, updated: int, errors: array<int, string>} */
    private function failure(string $message): array
    {
        return ['created' => 0, 'updated' => 0, 'errors' => [$message]];
    }
}
