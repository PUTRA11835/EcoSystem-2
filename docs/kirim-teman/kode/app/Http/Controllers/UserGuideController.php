<?php

namespace App\Http\Controllers;

use App\Services\Guide\UserGuideService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * User Guide — halaman panduan pengguna (/user-guide) dan unduhan PDF-nya.
 * Dapat dibuka SEMUA pengguna yang sudah masuk; isi disaring di SERVER menurut izin menu pengguna (topik yang tak boleh dilihat tidak
 * pernah dikirim — termasuk ke PDF). Bahasa: ?lang= → preferensi pengguna (Settings › language) → Inggris.
 */
class UserGuideController extends Controller
{
    public function __construct(private UserGuideService $guide) {}

    public function index(Request $request)
    {
        return view('user-guide.index', $this->data($request));
    }

    public function pdf(Request $request)
    {
        $d = $this->data($request);
        $d['generatedAt'] = now()->format('d M Y H:i');
        $d['who'] = (string) (session('user.name') ?? '');
        $pdf = Pdf::loadView('user-guide.pdf', $d)->setPaper('a4', 'portrait');
        $file = 'EcoSystem User Guide ' . strtoupper($d['locale']) . ' ' . ($d['updated'] ?: now()->toDateString()) . '.pdf';

        return $request->boolean('inline') ? $pdf->stream($file) : $pdf->download($file);
    }

    /** @return array<string,mixed> */
    private function data(Request $request): array
    {
        $locale = $this->guide->resolveLocale($request->query('lang'), session('user_preferences.language'));
        $permSlugs = (array) View::shared('permSlugs', []);

        $topics = $this->guide->topics($locale);
        // Izin semu: penyetuju yang ditunjuk di alur persetujuan melihat menu My Approvals walau tanpa izin menu (sama dengan sidebar).
        $extra = [];
        if ((session('user.type') ?? null) === 'employee') {
            try {
                if (app(\App\Services\Approval\ApprovalEngine::class)->isApproverAnywhere((int) session('user.id'))) {
                    $extra[] = '@approver';
                }
            } catch (\Throwable) { /* panduan tetap tampil tanpa izin semu */ }
        }
        $visible = $this->guide->visible($topics, $permSlugs, $extra);
        $chapters = $this->guide->chapters($visible, $locale);

        return [
            'locale'       => $locale,
            'ui'           => config("user_guide.ui.{$locale}"),
            'locales'      => config('user_guide.locales'),
            'chapters'     => $chapters,
            'visibleCount' => count($visible),
            'totalCount'   => count($topics),
            'updated'      => $this->guide->lastUpdated($visible),
            'index'        => $this->guide->searchIndex($visible, $chapters),
        ];
    }
}
