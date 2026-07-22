<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginLogController extends Controller
{
    /** Slug menu yang mengatur akses halaman ini (lihat MenuSeeder / Menu Access). */
    private const MENU_SLUG = 'control-center.login-log';

    /**
     * Render halaman Login Log (satu baris per employee).
     */
    public function index(Request $request)
    {
        return view('admin.login-log', [
            'user' => session('user'),
        ]);
    }

    /**
     * Data JSON: aktivitas login/logout TERAKHIR per employee (satu baris per employee).
     * GET /api/admin/login-logs?page=1&per_page=25&search=&status=
     *
     * Berbeda dari Activity Log yang menampilkan tiap event — di sini kita agregasi
     * ke event terbaru per user_id sehingga cukup satu baris untuk masing-masing
     * employee, plus kolom relatif "terakhir login".
     */
    public function getData(Request $request)
    {
        if (!$this->userCanAccess()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 500));
        $search  = trim($request->input('search', ''));
        $status  = $request->input('status', ''); // '', 'login', 'logout'

        // Event login/logout terbaru per employee. Abaikan percobaan gagal (failed)
        // supaya status baris mencerminkan kondisi login/logout yang sebenarnya.
        $latest = DB::table('login_activity')
            ->select('user_id', DB::raw('MAX(activity_id) as max_id'))
            ->where('user_type', 'employee')
            ->whereIn('status', ['success', 'logout'])
            ->groupBy('user_id');

        // Waktu login sukses paling akhir per employee → dasar kolom "terakhir login".
        $lastLogin = DB::table('login_activity')
            ->select('user_id', DB::raw('MAX(login_at) as last_login_at'))
            ->where('user_type', 'employee')
            ->where('status', 'success')
            ->groupBy('user_id');

        $query = DB::table('login_activity as la')
            ->joinSub($latest, 'latest', 'la.activity_id', '=', 'latest.max_id')
            ->leftJoinSub($lastLogin, 'll', 'la.user_id', '=', 'll.user_id')
            ->select('la.*', 'll.last_login_at')
            ->orderByDesc('la.created_at');

        if ($search !== '') {
            $query->where('la.user_name', 'like', "%{$search}%");
        }

        if ($status === 'login') {
            $query->where('la.status', 'success');
        } elseif ($status === 'logout') {
            $query->where('la.status', 'logout');
        }

        $records = $query->paginate($perPage);

        $items = collect($records->items())->map(function ($row) {
            $displayStatus = $row->status === 'logout' ? 'logout' : 'login';

            // Waktu event terakhir (login/logout) untuk kolom "Time".
            $eventAt = $row->status === 'logout'
                ? ($row->logout_at ?? $row->created_at)
                : ($row->login_at ?? $row->created_at);
            $eventLabel = $eventAt
                ? \Carbon\Carbon::parse($eventAt)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                : '-';

            return [
                'user_id'      => $row->user_id,
                'user_name'    => $row->user_name ?? '-',
                'status'       => $displayStatus,
                'device_type'  => $row->device_type ?? '-',
                'device_brand' => $row->device_brand ?? null,
                'device_model' => $row->device_model ?? null,
                'browser'      => $row->browser ?? '-',
                'os'           => $row->os ?? '-',
                'time'         => $eventLabel,
                'last_login'   => $this->relativeLastLogin($row->last_login_at),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'        => $records->total(),
                'per_page'     => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }

    /**
     * Format relatif "terakhir login":
     *  - < 60 menit  → "Terakhir login X menit lalu"
     *  - < 24 jam    → "Terakhir login X jam lalu"
     *  - < 7 hari    → "Terakhir login X hari lalu"
     *  - >= 7 hari   → "Terakhir login 2 Juli 2026" (tanggal)
     */
    private function relativeLastLogin($loginAt): string
    {
        if (!$loginAt) {
            return 'Never logged in';
        }

        $dt  = \Carbon\Carbon::parse($loginAt)->setTimezone('Asia/Jakarta');
        $now = \Carbon\Carbon::now('Asia/Jakarta');

        // Pakai selisih detik mentah agar konsisten lintas versi Carbon.
        $seconds = max(0, $now->timestamp - $dt->timestamp);
        $minutes = intdiv($seconds, 60);
        $hours   = intdiv($seconds, 3600);
        $days    = intdiv($seconds, 86400);

        if ($minutes < 1) {
            return 'Last login just now';
        }
        if ($minutes < 60) {
            $unit = $minutes === 1 ? 'minute' : 'minutes';
            return "Last login {$minutes} {$unit} ago";
        }
        if ($hours < 24) {
            $unit = $hours === 1 ? 'hour' : 'hours';
            return "Last login {$hours} {$unit} ago";
        }
        if ($days < 7) {
            $unit = $days === 1 ? 'day' : 'days';
            return "Last login {$days} {$unit} ago";
        }

        return 'Last login ' . $dt->locale('en')->isoFormat('MMMM D, Y');
    }

    /**
     * Cek izin via menu access (bukan hardcode role) supaya benar-benar mengikuti
     * pengaturan Menu Access seperti menu lain.
     */
    private function userCanAccess(): bool
    {
        $user = session('user');
        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return false;
        }

        $employee = Employee::find($user['id'] ?? null);

        return $employee && $employee->canAccessMenu(self::MENU_SLUG);
    }
}
