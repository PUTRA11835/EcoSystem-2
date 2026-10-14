<?php

namespace App\Http\Controllers\HR_General\Concerns;

use App\Models\Employee;

/**
 * Pemeriksaan izin kecil untuk controller seksi External: apakah orang yang masuk memegang kotak View sebuah slug
 * (mis. izin tarif konsultan atau izin seksi di Master Employee), di luar gerbang rute `menu:` / `menu.can:`.
 */
trait ChecksExternalAccess
{
    protected function canView(string $slug): bool
    {
        $employee = Employee::find(session('user.id'));

        return $employee !== null && $employee->canAccessMenu($slug);
    }

    protected function actorId(): int
    {
        return (int) session('user.id');
    }
}
