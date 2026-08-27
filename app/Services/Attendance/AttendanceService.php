<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Attendance\AttendanceSource;
use App\Models\Attendance\Branch;
use App\Models\Attendance\EmployeeShift;
use App\Models\Attendance\ProjectSite;
use App\Models\Attendance\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin presensi: check-in, check-out, dan turunannya.
 *
 * AGNOSTIK TERHADAP TRANSPORT. Seluruh masukan diterima sebagai array biasa,
 * bukan objek Request. Alasannya: endpoint presensi untuk aplikasi mobile
 * (routes/lite.php) masih ditahan sampai token API-nya diperbaiki, dan saat
 * nanti dibuka, yang dibutuhkan hanya controller tipis — bukan menulis ulang
 * seluruh aturan bisnis ini.
 *
 * Pola kembalian ['allowed' => bool, 'reason' => string] meniru PeriodService,
 * yang sudah menjadi konvensi gerbang bisnis di basis kode ini.
 *
 * 🔴 KEAMANAN: $employeeId SELALU berasal dari session('user')['id'] di
 * controller, TIDAK PERNAH dari badan request. Kalau aturan ini dilanggar,
 * siapa pun dapat mencatatkan presensi atas nama rekannya.
 */
class AttendanceService
{
    public function __construct(private GeofenceService $geofence)
    {
    }

    /** Selisih jam perangkat vs server yang dianggap mencurigakan. */
    private const CLOCK_SKEW_TOLERANCE_SECONDS = 300;

    // ── Presensi ────────────────────────────────────────────────────────────

    /**
     * @param  array{latitude?:float|null, longitude?:float|null, accuracy?:float|null,
     *               connection?:string|null, client_time?:string|null,
     *               ip?:string|null, user_agent?:string|null}  $payload
     * @return array{allowed: bool, reason: string, record: ?AttendanceRecord}
     */
    public function checkIn(int $employeeId, array $payload): array
    {
        $settings = AttendanceSetting::current();
        $now      = now();
        $date     = $now->toDateString();

        $record = $this->recordFor($employeeId, $date);

        if ($record && $record->check_in_at) {
            return $this->deny('You have already checked in today at '
                . $record->check_in_at->format('H:i') . '.');
        }

        if ($denial = $this->locationDenial($payload, $settings)) {
            return $denial;
        }

        $geo  = $this->evaluateLocation($employeeId, $payload, $settings);
        $mode = $this->resolveMode($geo, $settings);

        if ($mode === AttendanceSetting::GEOFENCE_ENFORCE && $this->geofence->isOutside($geo)) {
            return $this->deny($this->outsideMessage($geo));
        }

        // Waktu punch diteruskan supaya karyawan bermultishift dinilai terhadap
        // shift yang benar-benar sedang dijalaninya.
        $shift = $this->activeShift($employeeId, $now);
        $late  = $this->lateMinutes($now, $shift, $settings);

        $record = DB::transaction(function () use ($employeeId, $date, $now, $geo, $shift, $late, $payload, $settings) {
            $record = AttendanceRecord::firstOrNew([
                'employee_id'     => $employeeId,
                'attendance_date' => $date,
            ]);

            $record->fill($this->sideColumns('check_in', $now, $geo, $payload));

            $record->shift_id     = $shift?->id;
            $record->late_minutes = $late;
            $record->day_status   = $late > 0 ? AttendanceRecord::STATUS_LATE : AttendanceRecord::STATUS_PRESENT;
            // Sumber diambil dari master, bukan konstanta, supaya perusahaan
            // yang memakai beberapa jalur presensi tetap tercatat dengan benar.
            $record->source       = AttendanceSource::webCheckinCode();
            $record->period_year  = (int) $now->format('Y');
            $record->period_month = (int) $now->format('n');
            $record->flags        = $this->mergeFlags($record->flags, 'in', $geo['flags'], $payload, $now);

            $record->save();

            return $record;
        });

        return [
            'allowed' => true,
            'reason'  => 'Check-in recorded at ' . $now->format('H:i') . '.',
            'record'  => $record,
        ];
    }

    /**
     * @return array{allowed: bool, reason: string, record: ?AttendanceRecord}
     */
    public function checkOut(int $employeeId, array $payload): array
    {
        $settings = AttendanceSetting::current();
        $now      = now();
        $date     = $now->toDateString();

        $record = $this->recordFor($employeeId, $date);

        if (!$record || !$record->check_in_at) {
            return $this->deny('You have not checked in today, so there is nothing to check out from.');
        }

        if ($record->check_out_at) {
            return $this->deny('You have already checked out today at '
                . $record->check_out_at->format('H:i') . '.');
        }

        if ($now->lessThanOrEqualTo($record->check_in_at)) {
            return $this->deny('Check-out time must be later than the check-in time.');
        }

        if ($denial = $this->locationDenial($payload, $settings)) {
            return $denial;
        }

        $geo  = $this->evaluateLocation($employeeId, $payload, $settings);
        $mode = $this->resolveMode($geo, $settings);

        if ($mode === AttendanceSetting::GEOFENCE_ENFORCE && $this->geofence->isOutside($geo)) {
            return $this->deny($this->outsideMessage($geo));
        }

        $shift = $record->shift_id ? Shift::find($record->shift_id) : $this->activeShift($employeeId);

        $record = DB::transaction(function () use ($record, $now, $geo, $shift, $payload) {
            $record->fill($this->sideColumns('check_out', $now, $geo, $payload));

            $worked = $this->workMinutes($record->check_in_at, $now, $shift);

            $record->work_minutes        = $worked;
            $record->overtime_minutes    = $this->overtimeMinutes($worked, $shift);
            $record->early_leave_minutes = $this->earlyLeaveMinutes($now, $shift);
            $record->flags               = $this->mergeFlags($record->flags, 'out', $geo['flags'], $payload, $now);

            $record->save();

            return $record;
        });

        return [
            'allowed' => true,
            'reason'  => 'Check-out recorded at ' . $now->format('H:i') . '.',
            'record'  => $record,
        ];
    }

    // ── Pembacaan ───────────────────────────────────────────────────────────

    public function todayRecord(int $employeeId): ?AttendanceRecord
    {
        return $this->recordFor($employeeId, now()->toDateString());
    }

    /** Riwayat N hari terakhir, terbaru lebih dulu. */
    public function history(int $employeeId, int $days = 30): Collection
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->where('attendance_date', '>=', now()->subDays($days)->toDateString())
            ->with(['checkInBranch', 'checkOutBranch', 'checkInProjectSite', 'checkOutProjectSite'])
            ->orderByDesc('attendance_date')
            ->get();
    }

    /**
     * @return array{present:int, late:int, work_minutes:int, overtime_minutes:int}
     */
    public function monthlySummary(int $employeeId, int $year, int $month): array
    {
        $row = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->selectRaw('COUNT(*) AS present')
            ->selectRaw('SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) AS late')
            ->selectRaw('COALESCE(SUM(work_minutes), 0) AS work_minutes')
            ->selectRaw('COALESCE(SUM(overtime_minutes), 0) AS overtime_minutes')
            ->first();

        return [
            'present'          => (int) ($row->present ?? 0),
            'late'             => (int) ($row->late ?? 0),
            'work_minutes'     => (int) ($row->work_minutes ?? 0),
            'overtime_minutes' => (int) ($row->overtime_minutes ?? 0),
        ];
    }

    /**
     * Shift yang berlaku: penugasan eksplisit lebih dulu, kalau tidak ada
     * pakai shift default. Karyawan tanpa keduanya mendapat null — jam kerja
     * tidak terdefinisi dan keterlambatan tidak dihitung, persis seperti
     * "Shift not set" pada sistem referensi.
     */
    public function activeShift(int $employeeId, ?Carbon $at = null): ?Shift
    {
        $shifts = EmployeeShift::query()
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->with('shift')
            ->get()
            ->pluck('shift')
            ->filter(fn (?Shift $shift) => $shift && $shift->is_active)
            ->values();

        if ($shifts->isEmpty()) {
            return Shift::where('is_default', true)->where('is_active', true)->first();
        }

        if ($shifts->count() === 1) {
            return $shifts->first();
        }

        // Karyawan dengan LEBIH DARI SATU shift aktif: pilih yang jam masuknya
        // paling dekat dengan waktu punch.
        //
        // Alasannya sederhana dan sesuai akal sehat — orang yang datang jam
        // 22:10 jelas sedang menjalani shift malam, bukan shift pagi yang
        // membuatnya "terlambat 14 jam". Memilih shift pertama secara acak
        // akan menghasilkan angka keterlambatan yang tidak masuk akal dan
        // membanjiri HR dengan pengajuan koreksi.
        $reference = $at ?? now();

        return $shifts->sortBy(function (Shift $shift) use ($reference) {
            $scheduled = $reference->copy()->setTimeFromTimeString((string) $shift->check_in_time);
            $diff      = abs($scheduled->diffInMinutes($reference));

            // Shift lintas tengah malam juga dibandingkan terhadap hari
            // sebelumnya, supaya punch dini hari tetap dikenali sebagai
            // kelanjutan shift malam kemarin.
            if ($shift->crosses_midnight) {
                $diff = min($diff, abs($scheduled->copy()->subDay()->diffInMinutes($reference)));
            }

            return $diff;
        })->first();
    }

    /** Seluruh shift aktif karyawan — dipakai halaman penugasan untuk menghitung kuota. */
    public function activeShiftsFor(int $employeeId)
    {
        return EmployeeShift::query()
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->with('shift')
            ->get();
    }

    /**
     * Lokasi pembanding: SELURUH cabang aktif + lokasi proyek yang sedang
     * dijalani karyawan.
     *
     * Sengaja tidak memakai tabel penugasan karyawan->cabang. Konsultan di
     * perusahaan ini berpindah antar kantor dan antar lokasi klien, sehingga
     * penugasan tetap justru sering salah. Mengambil yang TERDEKAT selalu
     * benar tanpa data tambahan yang harus dipelihara.
     *
     * @return array<int, array{type:string, id:int, latitude:string, longitude:string, radius_meters:int, name:string}>
     */
    public function geofenceCandidates(int $employeeId): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'latitude', 'longitude', 'radius_meters', 'geofence_override'])
            ->map(fn ($b) => [
                'type'              => 'office',
                'id'                => $b->id,
                'name'              => $b->name,
                'latitude'          => $b->latitude,
                'longitude'         => $b->longitude,
                'radius_meters'     => $b->radius_meters,
                'geofence_override' => $b->geofence_override,
            ]);

        // Lokasi proyek karyawan. Sumber penugasannya `delivery_project_employee`
        // yang sudah ada — satu sumber kebenaran, tidak diduplikasi di sini.
        $today = now()->toDateString();

        $projectSites = ProjectSite::query()
            ->where('is_active', true)
            ->whereIn('delivery_projects_id', function ($query) use ($employeeId, $today) {
                $query->select('delivery_projects_id')
                    ->from('delivery_project_employee')
                    ->where('employee_id', $employeeId)
                    ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $today))
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today));
            })
            ->get(['id', 'name', 'latitude', 'longitude', 'radius_meters'])
            ->map(fn ($s) => [
                'type'              => 'project',
                'id'                => $s->id,
                'name'              => $s->name,
                'latitude'          => $s->latitude,
                'longitude'         => $s->longitude,
                'radius_meters'     => $s->radius_meters,
                'geofence_override' => null,
            ]);

        return $branches->concat($projectSites)->values()->all();
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Tolak presensi bila lokasi wajib tetapi perangkat tidak memberikannya.
     *
     * Dikendalikan `attendance_settings.require_location` (bawaan: wajib).
     *
     * Pemeriksaannya melihat KOORDINAT, bukan hasil evaluasi geofence. Kalau
     * yang diperiksa hasil evaluasi, perusahaan yang belum mendaftarkan satu
     * pun cabang akan memblokir presensi seluruh karyawannya — padahal
     * penyebabnya konfigurasi yang belum lengkap, bukan karyawannya.
     *
     * ⚠️ Menyalakan opsi ini berarti karyawan yang menolak izin lokasi, berada
     * di area tanpa sinyal GPS, atau membuka aplikasi lewat koneksi non-HTTPS
     * TIDAK DAPAT melakukan presensi sama sekali dan harus mengajukan koreksi.
     * Itu keputusan kebijakan, dan dapat dimatikan lewat halaman pengaturan.
     *
     * @return array{allowed: false, reason: string, record: null}|null
     */
    private function locationDenial(array $payload, AttendanceSetting $settings): ?array
    {
        if (!$settings->require_location) {
            return null;
        }

        if (isset($payload['latitude'], $payload['longitude'])) {
            return null;
        }

        return $this->deny(match ($payload['gps_status'] ?? null) {
            AttendanceRecord::GPS_PERMISSION_DENIED =>
                'Location access for this site is blocked, so attendance cannot be recorded. '
                . 'Click the icon on the left of the address bar, set Location to Allow, reload the page, and try again.',
            // Situs SUDAH diizinkan — yang memblokir adalah sistem operasi.
            // Mengarahkan pengguna ke pengaturan browser di sini justru
            // menyesatkan, karena di sana semuanya sudah benar.
            AttendanceRecord::GPS_SYSTEM_DENIED =>
                'Your browser is allowed to use location for this site, but Windows is blocking the browser itself. '
                . 'Open Windows Settings → Privacy & security → Location, turn on "Location services" AND '
                . '"Let apps access your location", then close and reopen the browser completely. '
                . 'If you cannot change that setting, submit an attendance correction instead.',
            AttendanceRecord::GPS_TIMEOUT =>
                'Getting your location took too long, so attendance was not recorded. '
                . 'Move to an area with a better signal and try again.',
            AttendanceRecord::GPS_UNAVAILABLE =>
                'Your device reported that it cannot determine a location, even though access was allowed. '
                . 'On Windows, open Settings → Privacy & security → Location and turn on both '
                . '"Location services" and "Let apps access your location", then reload this page. '
                . 'If it still fails, submit an attendance correction instead.',
            AttendanceRecord::GPS_INSECURE_CONTEXT =>
                'Location is only available over a secure (HTTPS) connection. '
                . 'Open this site using its https:// address and try again.',
            AttendanceRecord::GPS_UNSUPPORTED =>
                'This browser does not support location access. Try a different browser, '
                . 'or submit an attendance correction instead.',
            default =>
                'Your location could not be determined, so attendance was not recorded. '
                . 'Make sure location is enabled on your device and try again.',
        });
    }

    private function recordFor(int $employeeId, string $date): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->first();
    }

    private function evaluateLocation(int $employeeId, array $payload, AttendanceSetting $settings): array
    {
        $candidates = $this->geofenceCandidates($employeeId);

        $geo = $this->geofence->evaluate(
            isset($payload['latitude'])  ? (float) $payload['latitude']  : null,
            isset($payload['longitude']) ? (float) $payload['longitude'] : null,
            isset($payload['accuracy'])  ? (float) $payload['accuracy']  : null,
            $candidates,
            $settings->min_accuracy_meters
        );

        // Simpan nama & kebijakan lokasi terpilih untuk pesan penolakan dan
        // penentuan mode, tanpa query ulang.
        $geo['matched'] = collect($candidates)->first(function ($c) use ($geo) {
            return ($geo['branch_id'] !== null && $c['type'] === 'office'  && $c['id'] === $geo['branch_id'])
                || ($geo['project_site_id'] !== null && $c['type'] === 'project' && $c['id'] === $geo['project_site_id']);
        });

        return $geo;
    }

    /** Kebijakan per lokasi menang atas kebijakan global bila diisi. */
    private function resolveMode(array $geo, AttendanceSetting $settings): string
    {
        return $geo['matched']['geofence_override'] ?? $settings->geofence_mode;
    }

    private function outsideMessage(array $geo): string
    {
        $name     = $geo['matched']['name'] ?? 'the nearest registered location';
        $distance = (int) round((float) $geo['distance_m']);
        $radius   = (int) ($geo['radius_m'] ?? 0);

        return "You are {$distance} m from {$name}, which allows check-in within {$radius} m. "
             . 'Move closer to the location, or submit an attendance correction if you are working elsewhere.';
    }

    /**
     * Kolom-kolom untuk satu sisi punch (check_in atau check_out).
     *
     * @return array<string, mixed>
     */
    private function sideColumns(string $side, Carbon $at, array $geo, array $payload): array
    {
        return [
            // Waktu yang disimpan SELALU waktu server. Jam perangkat dapat
            // diubah pengguna dalam hitungan detik; mempercayainya berarti
            // seluruh catatan keterlambatan dapat dipalsukan tanpa keahlian
            // teknis apa pun. Waktu perangkat hanya dicatat sebagai pembanding.
            $side . '_at'              => $at,
            $side . '_latitude'        => $payload['latitude']  ?? null,
            $side . '_longitude'       => $payload['longitude'] ?? null,
            $side . '_accuracy_m'      => $payload['accuracy']  ?? null,
            $side . '_connection'      => $payload['connection'] ?? null,
            $side . '_gps_status'      => $this->gpsStatus($payload),
            $side . '_match_type'      => $geo['match_type'],
            $side . '_branch_id'       => $geo['branch_id'],
            $side . '_project_site_id' => $geo['project_site_id'],
            $side . '_distance_m'      => $geo['distance_m'],
            $side . '_ip'              => $payload['ip'] ?? null,
            $side . '_device'          => $this->deviceLabel($payload['user_agent'] ?? null),
            // Sumber dicatat PER SISI: check-in dan check-out dapat berasal
            // dari jalur berbeda, dan koreksi hanya menyentuh sisi yang diminta.
            $side . '_source'          => AttendanceSource::webCheckinCode(),
        ];
    }

    /**
     * Status GPS memakai kosakata yang sama persis dengan sistem referensi
     * (gps_ok / gps_timeout / gps_permission_denied) supaya hasil uji terima
     * dapat dibandingkan langsung tanpa penerjemahan.
     */
    private function gpsStatus(array $payload): string
    {
        if (!empty($payload['gps_status'])) {
            return in_array($payload['gps_status'], AttendanceRecord::GPS_STATUSES, true)
                ? $payload['gps_status']
                : AttendanceRecord::GPS_UNAVAILABLE;
        }

        return isset($payload['latitude'], $payload['longitude'])
            ? AttendanceRecord::GPS_OK
            : AttendanceRecord::GPS_UNAVAILABLE;
    }

    /**
     * Label perangkat ringkas dari user agent.
     *
     * AuthController::parseUserAgent() melakukan hal serupa dan jauh lebih
     * lengkap, tetapi method itu `private static` sehingga tidak dapat
     * dipanggil dari luar — dan AuthController termasuk berkas produksi yang
     * tidak boleh diubah dalam pekerjaan ini. Versi ringkas ini cukup untuk
     * kolom 150 karakter yang hanya dibaca manusia di layar rekap.
     */
    private function deviceLabel(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/')     => 'Edge',
            str_contains($userAgent, 'OPR/')     => 'Opera',
            str_contains($userAgent, 'Chrome/')  => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/')  => 'Safari',
            default                              => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Android')     => 'Android',
            str_contains($userAgent, 'iPhone')      => 'iOS',
            str_contains($userAgent, 'iPad')        => 'iPadOS',
            str_contains($userAgent, 'Windows')     => 'Windows',
            str_contains($userAgent, 'Mac OS X')    => 'macOS',
            str_contains($userAgent, 'Linux')       => 'Linux',
            default                                 => 'Unknown OS',
        };

        return mb_substr($browser . ' on ' . $os, 0, 150);
    }

    /**
     * Gabungkan flag lama dengan flag baru dari sisi ini.
     *
     * Flag dari geofence diberi awalan sisi ('in:' / 'out:') karena check-in
     * dan check-out punya kondisi masing-masing; tanpa awalan, akurasi buruk
     * saat pulang akan terbaca seolah terjadi saat datang.
     *
     * @param  string[]|null  $existing
     * @param  string[]  $newFlags
     * @return string[]
     */
    private function mergeFlags(?array $existing, string $side, array $newFlags, array $payload, Carbon $serverTime): array
    {
        $flags = collect($existing ?? []);

        foreach ($newFlags as $flag) {
            $flags->push(AttendanceRecord::sideFlag($side, $flag));
        }

        if ($this->hasClockSkew($payload, $serverTime)) {
            $flags->push(AttendanceRecord::sideFlag($side, AttendanceRecord::FLAG_CLOCK_SKEW));
        }

        return $flags->unique()->values()->all();
    }

    private function hasClockSkew(array $payload, Carbon $serverTime): bool
    {
        if (empty($payload['client_time'])) {
            return false;
        }

        try {
            $clientTime = Carbon::parse($payload['client_time']);
        } catch (\Throwable) {
            // Waktu kiriman yang tidak dapat diurai bukan alasan menggagalkan
            // presensi; cukup diabaikan sebagai sinyal.
            return false;
        }

        return abs((int) $clientTime->diffInSeconds($serverTime)) > self::CLOCK_SKEW_TOLERANCE_SECONDS;
    }

    private function lateMinutes(Carbon $at, ?Shift $shift, AttendanceSetting $settings): int
    {
        if (!$shift) {
            // Tanpa shift, "terlambat" tidak terdefinisi. Mengarang angka dari
            // jam default perusahaan akan menghasilkan tuduhan keterlambatan
            // pada karyawan yang jam kerjanya memang belum ditetapkan.
            return 0;
        }

        $scheduled = $at->copy()->setTimeFromTimeString((string) $shift->check_in_time)
            ->addMinutes($shift->late_tolerance_minutes);

        // Carbon 3 mengembalikan float dari diffInMinutes(); dibulatkan ke
        // bawah secara eksplisit supaya tidak ada konversi implisit yang
        // memicu deprecation dan supaya pembulatannya tidak merugikan karyawan.
        return $at->greaterThan($scheduled) ? (int) floor($scheduled->diffInMinutes($at)) : 0;
    }

    private function workMinutes(Carbon $checkIn, Carbon $checkOut, ?Shift $shift): int
    {
        // Dihitung dari selisih timestamp, sehingga shift lintas tengah malam
        // tertangani dengan sendirinya tanpa cabang logika terpisah.
        $gross = (int) floor($checkIn->diffInMinutes($checkOut));
        $break = $shift?->break_minutes ?? 0;

        return max(0, $gross - $break);
    }

    private function overtimeMinutes(int $workMinutes, ?Shift $shift): int
    {
        if (!$shift) {
            return 0;
        }

        return max(0, $workMinutes - $this->scheduledMinutes($shift));
    }

    private function earlyLeaveMinutes(Carbon $at, ?Shift $shift): int
    {
        if (!$shift || $shift->crosses_midnight) {
            return 0;
        }

        $scheduled = $at->copy()->setTimeFromTimeString((string) $shift->check_out_time);

        return $at->lessThan($scheduled) ? (int) floor($at->diffInMinutes($scheduled)) : 0;
    }

    /** Durasi kerja terjadwal satu hari, sudah dikurangi istirahat. */
    private function scheduledMinutes(Shift $shift): int
    {
        $in  = Carbon::parse('2000-01-01 ' . $shift->check_in_time);
        $out = Carbon::parse('2000-01-01 ' . $shift->check_out_time);

        if ($shift->crosses_midnight || $out->lessThanOrEqualTo($in)) {
            $out->addDay();
        }

        return max(0, (int) floor($in->diffInMinutes($out)) - $shift->break_minutes);
    }

    /** @return array{allowed: false, reason: string, record: null} */
    private function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason, 'record' => null];
    }
}
