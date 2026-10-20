<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\Approval\ApprovalEngine;

abstract class Controller
{
    /** The "Rows per page" choices of the shared pagination footer (hr-general/recruitment/components/pagination). */
    public const PAGE_SIZES = [10, 15, 25, 50];

    /**
     * Rows per page for a list: the footer's `per_page` choice when it is one of PAGE_SIZES, otherwise the page's own default.
     * Keeps every list on the same rows-per-page contract without each controller re-validating the query string.
     */
    protected function pageSize(int $default = 25): int
    {
        $asked = (int) request()->query('per_page');

        return in_array($asked, self::PAGE_SIZES, true) ? $asked : $default;
    }

    /** Menu Access slugs of the extra powers a designated approver can be given on the document they review. */
    public const APPROVER_SLUG        = 'general.approvals';          // Edit box = adjust the document · Delete box = delete it
    public const APPROVER_EXPORT_SLUG = 'general.approvals.export';   // View box = export the document

    /**
     * May the signed-in employee take the approval decision buttons of an approval module (approve / reject)?
     *
     * Yes for a holder of the module's `.approve` slug, OR for anyone a document of that module is waiting on right now (the
     * person is named on the workflow — they need no menu slug, see ApprovalEngine). Which document they may act on is
     * still decided per document by the module's service (canAct).
     */
    protected function mayDecide(string $module, string $approveSlug): bool
    {
        $employeeId = (int) session('user.id');
        if ($employeeId <= 0) {
            return false;
        }

        return (bool) Employee::find($employeeId)?->canAccessMenu($approveSlug)
            || app(ApprovalEngine::class)->isPending($module, $employeeId);
    }

    /**
     * An EXTRA power on a document reviewed as a designated approver: 'edit' (adjust it), 'delete', 'export'.
     *
     * Being named on a workflow only gives the right to see the document and decide it. Adjusting, deleting and exporting are
     * separate, off by default, and granted per position in Management → Roles on the "My Approvals" menu (Edit / Delete boxes,
     * and the View box of "My Approvals › Export"). Holders of the module's own slugs (HR) are unaffected — they are checked
     * where they always were.
     */
    protected function approverMay(string $action): bool
    {
        $employee = Employee::find((int) session('user.id'));
        if (!$employee) {
            return false;
        }

        return match ($action) {
            'edit', 'delete' => $employee->hasMenuPermission(self::APPROVER_SLUG, 'can_' . $action),
            'export'         => $employee->hasMenuPermission(self::APPROVER_EXPORT_SLUG, 'can_view'),
            default          => false,
        };
    }

    /** Where "Back" leads: the module list for those who may open it, otherwise the approver's own inbox. */
    protected function reviewBack(string $listSlug, string $listRoute): string
    {
        $employee = Employee::find((int) session('user.id'));

        return $employee?->canAccessMenu($listSlug) ? route($listRoute) : route('general.approvals.index');
    }
}
