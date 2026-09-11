<?php

namespace App\Http\Middleware;

use App\Models\DeliveryProject;
use App\Models\Document;
use App\Models\DeliveryProjectUpdate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci edit untuk Delivery Project yang sudah di-close (read-only).
 *
 * Memblokir semua request yang MENGUBAH data (POST/PUT/PATCH/DELETE) ketika
 * project terkait berstatus is_closed. Request baca (GET/HEAD/OPTIONS) selalu
 * lolos. Route "reopen" di-allowlist supaya project bisa dibuka kembali.
 *
 * Project di-resolve dari route param `project`; untuk beberapa sub-resource
 * yang tidak membawa `{project}` di path (document, project_update) di-resolve
 * lewat parent-nya.
 */
class EnsureProjectNotClosed
{
    /** Route name yang tetap diizinkan meskipun project closed. */
    private const ALLOWED_ROUTES = [
        'projects.reopen',
        'projects.destroy',
        'projects.destroy.post',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Baca selalu boleh.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        // Aksi yang di-allowlist (reopen / delete) tetap lolos.
        $routeName = optional($request->route())->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        $project = $this->resolveProject($request);

        if ($project && $project->is_closed) {
            $message = 'This project is closed and is read-only. Reopen it to make changes.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 423); // 423 Locked
            }
            return back()->with('error', $message);
        }

        return $next($request);
    }

    /**
     * Resolve DeliveryProject terkait request dari route parameter yang tersedia.
     */
    private function resolveProject(Request $request): ?DeliveryProject
    {
        $param = $request->route('project');
        if ($param instanceof DeliveryProject) {
            return $param;
        }
        if (!empty($param)) {
            return DeliveryProject::find($param);
        }

        // Sub-resource tanpa {project} di path → naik lewat parent.
        $documentParam = $request->route('document');
        if (!empty($documentParam)) {
            $doc = $documentParam instanceof Document
                ? $documentParam
                : Document::find($documentParam);
            if ($doc) {
                return DeliveryProject::find($doc->project_id);
            }
        }

        $updateParam = $request->route('project_update');
        if (!empty($updateParam)) {
            $upd = $updateParam instanceof DeliveryProjectUpdate
                ? $updateParam
                : DeliveryProjectUpdate::find($updateParam);
            if ($upd) {
                return DeliveryProject::find($upd->delivery_projects_id ?? $upd->delivery_project_id ?? null);
            }
        }

        return null;
    }
}
