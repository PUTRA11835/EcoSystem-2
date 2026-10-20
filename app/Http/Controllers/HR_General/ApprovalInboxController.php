<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Services\Approval\ApprovalEngine;
use Illuminate\Http\Request;

/**
 * General → My Approvals: everything that waits for the signed-in person across Overtime, Reimbursement, Purchase Request,
 * Cash Advance and Cash Advance Report — one list for approvers who are named on a workflow but hold no HR / general menu.
 *
 * Opened by being a designated approver in ANY module (route middleware `menu.approver:any`), not by a menu slug. The list
 * holds only the documents waiting for this person; each link opens one document, which the same middleware then limits to the
 * documents the person takes part in.
 */
class ApprovalInboxController extends Controller
{
    public function index(Request $request, ApprovalEngine $engine)
    {
        $employeeId = (int) session('user.id');
        $all = $engine->inbox($employeeId);

        $module = (string) $request->query('module', '');
        $rows = $engine->has($module) ? $all->where('module', $module)->values() : $all;

        return view('hr-general.approvals.index', [
            'rows'    => $rows,
            'counts'  => $all->countBy('module'),
            'total'   => $all->count(),
            'modules' => collect(ApprovalEngine::MODULES)->map(fn ($cfg) => $cfg['label']),
            'active'  => $engine->has($module) ? $module : '',
        ]);
    }

    /**
     * One document, for the approver: the same document the module shows HR, on its own approver URL ("back" leads to this inbox).
     * Opened by taking part in the document, not by any menu slug — so a department head or a finance officer reviews here without
     * holding an HR menu, and the module's own URLs stay HR's. The sidebar is the normal one: it lists only the menus the person
     * holds, so for an outside approver that is their self-service items and My Approvals — never HR's.
     *
     * Approve / reject post to the modules' routes (same rules, same canAct), which come back here. Adjust, delete and export show
     * only for positions given the "My Approvals" boxes (see Controller::approverMay).
     */
    public function show(string $module, int $id, ApprovalEngine $engine)
    {
        abort_unless($engine->has($module), 404);

        $cfg = ApprovalEngine::MODULES[$module];
        $document = $cfg['document']::query()
            ->when(method_exists($cfg['document'], 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->findOrFail($id);

        // Only a document the person takes part in (requester or named on a step) — the inbox links here, nothing else does.
        abort_unless($engine->participates($module, $document, (int) session('user.id')), 403);

        if ($module === 'overtime') {
            $document->load(['employee.basicData', 'approvals']);

            return view('hr-general.approvals.overtime', [
                'request'   => $document,
                'canAct'    => app(\App\Services\Overtime\OvertimeService::class)->canAct($document, (int) session('user.id'), false)['allowed'],
                'settings'  => \App\Models\Overtime\OvertimeSetting::current(),
            ]);
        }

        // The module's own show() builds the page; only the way back is the inbox.
        $view = app()->call([app($cfg['controller']), 'show'], [$cfg['param'] => $document]);

        return $view->with('backRoute', route('general.approvals.index'));
    }
}
