<?php

namespace App\Http\Controllers\HR_General\Concerns;

use App\Models\Employee;
use Illuminate\Http\Request;

/**
 * Shared by the Recruitment list pages: the rows-per-page choice of the
 * pagination footer, and the capability check for the few actions a route
 * middleware cannot express because they depend on the submitted form.
 */
trait HandlesRecruitmentTables
{
    /** Offered by resources/views/hr-general/recruitment/components/pagination.blade.php. */
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    protected function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page');

        return in_array($requested, self::PER_PAGE_OPTIONS, true) ? $requested : 15;
    }

    /** Whether the signed-in employee holds a Create / Edit / Delete box of a menu slug (Management → Roles). */
    protected function employeeCan(string $menuSlug, string $action): bool
    {
        $employee = Employee::find(session('user.id'));

        return $employee !== null && $employee->hasMenuPermission($menuSlug, "can_{$action}");
    }
}
