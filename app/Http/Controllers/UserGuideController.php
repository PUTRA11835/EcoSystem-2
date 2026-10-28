<?php

namespace App\Http\Controllers;

use App\Services\Guide\UserGuideService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * User Guide: halaman panduan pengguna (/user-guide) dan unduhan PDF-nya.
 * Dapat dibuka SEMUA pengguna yang sudah masuk; isi disaring di SERVER menurut izin menu pengguna (topik yang tak boleh dilihat tidak
 * pernah dikirim, termasuk ke PDF). Bahasa: ?lang= → preferensi pengguna (Settings › language) → Inggris.
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
        $d['chapters'] = $this->inlineImages($d['chapters']);
        $d['generatedAt'] = now()->format('d M Y H:i');
        $d['who'] = (string) (session('user.name') ?? '');
        $pdf = Pdf::loadView('user-guide.pdf', $d)->setPaper('a4', 'portrait');
        $file = 'EcoSystem User Guide ' . strtoupper($d['locale']) . ' ' . ($d['updated'] ?: now()->toDateString()) . '.pdf';

        return $request->boolean('inline') ? $pdf->stream($file) : $pdf->download($file);
    }

    /**
     * dompdf tidak mengambil gambar lewat HTTP, jadi gambar panduan (images/guide/*) disisipkan sebagai data URI khusus untuk PDF.
     * Di halaman web, alamat relatif itu diselesaikan peramban menjadi /images/guide/*.
     *
     * @param  array<string,array<string,mixed>>  $chapters
     * @return array<string,array<string,mixed>>
     */
    private function inlineImages(array $chapters): array
    {
        foreach ($chapters as &$chapter) {
            foreach ($chapter['topics'] as &$topic) {
                $topic['html'] = preg_replace_callback('#src="(images/guide/[A-Za-z0-9._-]+\.(?:png|jpe?g))"#', function (array $m) {
                    $path = public_path($m[1]);
                    if (!is_file($path)) {
                        return $m[0];
                    }
                    $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

                    return 'src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path)) . '"';
                }, $topic['html']);
            }
            unset($topic);
        }
        unset($chapter);

        return $chapters;
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

            // Izin semu per role: "@role:Nama Role" dihitung dari SEMUA role yang dipegang karyawan (bisa lebih dari satu).
            // Dipakai topik yang memang ditujukan untuk satu role (mis. panduan Delivery RPMO Head), di samping izin menu.
            try {
                $roleNames = DB::table('employee_role_assignment as a')
                    ->join('employee_role as r', 'r.id', '=', 'a.role_id')
                    ->where('a.employee_id', (int) session('user.id'))
                    ->pluck('r.name');
                foreach ($roleNames as $name) {
                    $extra[] = '@role:' . $name;
                }
            } catch (\Throwable) { /* tanpa izin semu role, topik tetap disaring menurut izin menu */ }
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
