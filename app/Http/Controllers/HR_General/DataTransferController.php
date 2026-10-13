<?php

namespace App\Http\Controllers\HR_General;

use App\Exports\Tabular\TabularExport;
use App\Exports\Tabular\TemplateExport;
use App\Http\Controllers\Controller;
use App\Support\DataTransfer\Dataset;
use App\Support\DataTransfer\Importer;
use App\Support\DataTransfer\Registry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Ekspor / impor Excel untuk tabel HR & General (lihat Registry).
 *
 * Izin diperiksa per tabel, memakai slug menu halamannya sendiri: ekspor
 * menuntut hak membuka halaman, impor menuntut hak Create (atau `.manage`).
 */
class DataTransferController extends Controller
{
    public function export(Request $request, string $dataset)
    {
        $def = $this->resolve($dataset);
        abort_unless(Registry::canExport($def), 403, 'You are not allowed to export this table.');

        $filters = collect($request->query())
            ->except(['page', 'per_page'])
            ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(fn ($v, $k) => "{$k}: {$v}")
            ->values()
            ->implode(', ');

        $meta = [
            'Generated ' . now()->format('d M Y H:i'),
            'By ' . (session('user.name') ?? 'system'),
            $filters !== '' ? 'Filters — ' . $filters : 'No filters applied',
        ];

        return Excel::download(
            new TabularExport($def->title, $def->title, $def->headings(), $def->rows($request), $def->formats(), $meta),
            $def->filename()
        );
    }

    public function template(string $dataset)
    {
        $def = $this->resolve($dataset);
        abort_unless(Registry::canImport($def), 403, 'You are not allowed to import into this table.');

        $guide = collect($def->columns)->map(fn ($c) => [
            $c['label'],
            !empty($c['required']) ? 'Yes' : 'No',
            $c['hint'] ?? '',
        ])->all();

        $example = collect($def->columns)->map(fn ($c) => $c['example'] ?? '')->all();

        return Excel::download(
            new TemplateExport($def->title, $def->headings(), $example, $guide),
            str_replace('.', '_', $def->key) . '_import_template.xlsx'
        );
    }

    public function import(Request $request, string $dataset, Importer $importer)
    {
        $def = $this->resolve($dataset);
        abort_unless(Registry::canImport($def), 403, 'You are not allowed to import into this table.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'Choose an Excel file to import.',
            'file.mimes'    => 'The file must be .xlsx, .xls or .csv.',
            'file.max'      => 'The file may not be larger than 5 MB.',
        ]);

        try {
            $result = $importer->run($def, $request->file('file'), (int) session('user.id'));
        } catch (\Throwable $e) {
            Log::error('Data import failed.', ['dataset' => $def->key, 'message' => $e->getMessage()]);

            return back()->with('error', 'The file could not be read. Download the template and try again.');
        }

        if ($result['errors'] !== []) {
            return back()
                ->with('error', 'Nothing was imported. Fix the ' . count($result['errors']) . ' problem(s) below and upload again.')
                ->with('import_errors', array_slice($result['errors'], 0, 50));
        }

        Log::info('Data imported.', [
            'dataset'  => $def->key,
            'actor_id' => session('user.id'),
            'created'  => $result['created'],
            'updated'  => $result['updated'],
            'skipped'  => $result['skipped'] ?? 0,
        ]);

        $skipped = (int) ($result['skipped'] ?? 0);

        return back()->with('success', "{$def->title} imported: {$result['created']} added, {$result['updated']} updated"
            . ($skipped > 0 ? ", {$skipped} left unchanged (already closed)." : '.'));
    }

    private function resolve(string $key): Dataset
    {
        $def = Registry::find($key);
        abort_if($def === null, 404);

        return $def;
    }
}
