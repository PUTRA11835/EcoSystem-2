<?php

namespace App\Support\External;

use App\Services\Letters\LetterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siapa yang boleh menandatangani dokumen External (kontrak konsultan / tiga surat konsultan) — config/hc_external.php.
 * Hanya orang yang juga terdaftar sebagai penandatangan (Settings → Signers) yang dihitung; tak ada yang memenuhi syarat = tanpa pembatasan.
 */
final class ExternalSigners
{
    public const CONTRACT = 'contract';
    public const LETTERS = 'letters';

    /**
     * @return array{options:Collection,default:?int,restricted:bool,names:string[]}
     *   options = orang yang boleh dipilih; default = penandatangan awal; restricted = apakah daftar dibatasi oleh config
     */
    public static function for(string $scope): array
    {
        $all = LetterService::signatoryOptions();
        $cfg = (array) config("hc_external.signers.{$scope}", []);
        $ecis = array_map('strtoupper', (array) ($cfg['eci'] ?? []));

        $ids = $ecis ? DB::table('employee')->whereIn('eci', $ecis)->pluck('employee_id', 'eci')->mapWithKeys(fn ($id, $eci) => [strtoupper($eci) => (int) $id])->all() : [];
        // urutan seperti di config; hanya yang juga terdaftar sebagai penandatangan
        $allowed = collect($ecis)->map(fn ($e) => $ids[$e] ?? null)->filter()->map(fn ($id) => $all->firstWhere('id', $id))->filter()->values();

        $restricted = $allowed->isNotEmpty();
        $options = $restricted ? $allowed : $all;

        $defaultId = isset($cfg['default'], $ids[strtoupper($cfg['default'])]) ? $ids[strtoupper($cfg['default'])] : null;
        if ($defaultId === null || !$options->contains('id', $defaultId)) {
            $defaultId = $options->first()['id'] ?? null;
        }

        return ['options' => $options, 'default' => $defaultId !== null ? (int) $defaultId : null, 'restricted' => $restricted, 'names' => $options->pluck('name')->all()];
    }

    /** Boleh menandatangani? Tanpa pembatasan, semua penandatangan terdaftar boleh. */
    public static function allows(string $scope, int $employeeId): bool
    {
        return self::for($scope)['options']->contains('id', $employeeId);
    }
}
