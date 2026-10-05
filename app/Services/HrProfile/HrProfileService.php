<?php

namespace App\Services\HrProfile;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeHrProfile;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Profil HR karyawan: baca, simpan (dengan aturan per peran), foto & tanda tangan.
 *
 * Yang TIDAK ditangani di sini: izin akses (CheckEmployeeSectionAccess di rute) dan siapa yang
 * boleh mengunggah tanda tangan (controller — hanya pemilik). Service ini hanya menjaga ISI:
 * field mana yang diterima menurut peran, validasi nilai, riwayat perubahan status, dan berkas
 * gambar di disk PRIVAT (tidak pernah di disk publik).
 */
class HrProfileService
{
    private const DISK = 'local'; // storage/app/private — tidak dapat dibuka lewat URL

    /** Panjang maksimum teks bebas (selaras dengan kolom di migrasi). */
    private const MAX_LEN = [
        'mother_maiden_name' => 150, 'emergency_contact_name' => 150,
        'emergency_contact_relation' => 50, 'emergency_contact_phone' => 30, 'hr_notes' => 2000,
    ];

    /**
     * Data untuk layar. Nilai tersembunyi (catatan HR) hanya disertakan untuk peran HR.
     *
     * @return array<string,mixed>
     */
    public function forEmployee(int $employeeId, bool $includeHrOnly): array
    {
        $p = EmployeeHrProfile::where('employee_id', $employeeId)->first();
        $type = DB::table('employee_basic_data')->where('employee_id', $employeeId)->value('employee_type') ?: 'Internal';

        $fields = [
            'employment_status' => $p?->employment_status,
            'grade_id'          => $p?->grade_id,
            'probation_end_date' => $p?->probation_end_date?->format('Y-m-d'),
            'blood_type'        => $p?->blood_type,
            'mother_maiden_name' => $p?->mother_maiden_name,
            'emergency_contact_name'     => $p?->emergency_contact_name,
            'emergency_contact_relation' => $p?->emergency_contact_relation,
            'emergency_contact_phone'    => $p?->emergency_contact_phone,
            'hr_notes' => $includeHrOnly ? $p?->hr_notes : null,
        ];

        return [
            'employee_type' => $type,
            'fields'        => $fields,
            'has_photo'     => (bool) ($p?->photo_path),
            'has_signature' => (bool) ($p?->signature_path),
            'updated_at'    => $p?->updated_at?->timestamp,
            'status_options' => EmploymentStatus::options(),
            'grades'        => Grade::where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->all(),
        ];
    }

    /**
     * Simpan field menurut peran ('self' | 'hr').
     *
     * @param  array<string,mixed>  $input
     * @return array{ok: bool, errors: array<string,string>, rejected: string[]}
     */
    public function save(int $employeeId, array $input, string $actor, int $actorId, ?string $statusReason = null): array
    {
        $split    = HrProfileFieldPolicy::filter($input, $actor);
        $rejected = $split['rejected'];
        $data     = $split['allowed'];

        // Penolakan keras: ada field terlarang → seluruh permintaan ditolak (bukan dibuang diam-diam).
        if ($rejected) {
            return ['ok' => false, 'errors' => ['_' => 'You are not allowed to change: ' . implode(', ', $rejected)], 'rejected' => $rejected];
        }

        if (!Employee::where('employee_id', $employeeId)->exists()) {
            return ['ok' => false, 'errors' => ['_' => 'Employee not found.'], 'rejected' => []];
        }

        $current = EmployeeHrProfile::where('employee_id', $employeeId)->first();
        [$clean, $errors] = $this->clean($data, $current);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'rejected' => []];
        }
        if (!$clean) {
            return ['ok' => true, 'errors' => [], 'rejected' => []]; // tidak ada yang dikirim
        }

        DB::transaction(function () use ($employeeId, $clean, $current, $actorId, $statusReason) {
            $profile = $current ?: new EmployeeHrProfile(['employee_id' => $employeeId]);
            $oldStatus = $profile->employment_status;

            $profile->fill($clean);
            $profile->forceFill(['updated_by' => $actorId]);
            $profile->save();

            if (array_key_exists('employment_status', $clean) && $clean['employment_status'] !== $oldStatus) {
                EmployeeHistory::create([
                    'employee_id'  => $employeeId,
                    'action'       => 'Employment status changed',
                    'description'  => EmploymentStatus::changeDescription($oldStatus, $clean['employment_status'], $statusReason),
                    'performed_by' => $actorId,
                    'performed_at' => now(),
                ]);
            }
        });

        return ['ok' => true, 'errors' => [], 'rejected' => []];
    }

    /**
     * Validasi + normalisasi. Hanya kunci yang dikirim yang diproses.
     *
     * @param  array<string,mixed>  $data
     * @return array{0: array<string,mixed>, 1: array<string,string>}
     */
    private function clean(array $data, ?EmployeeHrProfile $current): array
    {
        $clean  = [];
        $errors = [];

        foreach ($data as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            if ($value === '' || $value === null) {
                $clean[$key] = null;
                continue;
            }

            switch ($key) {
                case 'blood_type':
                    $bt = HrProfileFieldPolicy::normalizeBloodType((string) $value);
                    $bt === null ? $errors[$key] = 'Blood type must be A, B, AB or O.' : $clean[$key] = $bt;
                    break;

                case 'employment_status':
                    EmploymentStatus::isValid((string) $value)
                        ? $clean[$key] = (string) $value
                        : $errors[$key] = 'Choose a valid employment status.';
                    break;

                case 'grade_id':
                    Grade::where('id', (int) $value)->where('is_active', 1)->exists()
                        ? $clean[$key] = (int) $value
                        : $errors[$key] = 'Choose a valid grade.';
                    break;

                case 'probation_end_date':
                    $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
                    ($d && $d->format('Y-m-d') === $value)
                        ? $clean[$key] = (string) $value
                        : $errors[$key] = 'Enter a valid date.';
                    break;

                case 'emergency_contact_phone':
                    preg_match('/^[0-9+()\-\s.]{5,30}$/', (string) $value)
                        ? $clean[$key] = (string) $value
                        : $errors[$key] = 'Enter a valid phone number (digits, +, -, spaces).';
                    break;

                default: // teks bebas
                    $max = self::MAX_LEN[$key] ?? 255;
                    if (!is_string($value) || mb_strlen($value) > $max) {
                        $errors[$key] = "Maximum {$max} characters.";
                    } elseif (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                        $errors[$key] = 'Contains invalid characters.';
                    } else {
                        $clean[$key] = $value;
                    }
            }
        }

        // Aturan lintas-field: status Probation wajib tanggal akhir probation (nilai akhir setelah simpan).
        $finalStatus = array_key_exists('employment_status', $clean) ? $clean['employment_status'] : $current?->employment_status;
        $finalEnd    = array_key_exists('probation_end_date', $clean) ? $clean['probation_end_date'] : $current?->probation_end_date?->format('Y-m-d');
        if (!isset($errors['employment_status']) && !isset($errors['probation_end_date'])
            && (array_key_exists('employment_status', $clean) || array_key_exists('probation_end_date', $clean))) {
            $errors += EmploymentStatus::validate($finalStatus, $finalEnd);
        }

        return [$clean, $errors];
    }

    // ───────────────────────── Foto & tanda tangan ─────────────────────────

    /**
     * Simpan foto/tanda tangan dari berkas sementara (setelah dibersihkan). Berkas lama dihapus.
     *
     * @throws InvalidArgumentException pesan aman untuk pengguna
     */
    public function storeImage(int $employeeId, string $kind, string $tmpPath, int $actorId): void
    {
        $clean = ImageSanitizer::sanitize($tmpPath, $kind);

        $path = sprintf('hr-profile/%s/%d-%s.%s', $kind, $employeeId, Str::random(24), $clean['extension']);
        if (!Storage::disk(self::DISK)->put($path, $clean['bytes'])) {
            throw new InvalidArgumentException('The image could not be saved. Please try again.');
        }

        $column = $kind === 'photo' ? 'photo_path' : 'signature_path';
        $old = null;

        try {
            DB::transaction(function () use ($employeeId, $column, $path, $actorId, &$old, $kind) {
                $profile = EmployeeHrProfile::where('employee_id', $employeeId)->lockForUpdate()->first()
                    ?: new EmployeeHrProfile(['employee_id' => $employeeId]);
                $old = $profile->{$column};
                $profile->forceFill([$column => $path, 'updated_by' => $actorId])->save();
                $this->history($employeeId, $actorId, ucfirst($kind) . ' uploaded');
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path); // jangan menyisakan berkas yatim
            throw $e;
        }

        if ($old) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public function deleteImage(int $employeeId, string $kind, int $actorId): bool
    {
        $column = $kind === 'photo' ? 'photo_path' : 'signature_path';
        $old = null;

        DB::transaction(function () use ($employeeId, $column, $actorId, &$old, $kind) {
            $profile = EmployeeHrProfile::where('employee_id', $employeeId)->lockForUpdate()->first();
            if (!$profile || !$profile->{$column}) {
                return;
            }
            $old = $profile->{$column};
            $profile->forceFill([$column => null, 'updated_by' => $actorId])->save();
            $this->history($employeeId, $actorId, ucfirst($kind) . ' removed');
        });

        if ($old) {
            Storage::disk(self::DISK)->delete($old);
        }

        return $old !== null;
    }

    /** @return array{path: string, mime: string}|null */
    public function imageFile(int $employeeId, string $kind): ?array
    {
        $column = $kind === 'photo' ? 'photo_path' : 'signature_path';
        $rel = EmployeeHrProfile::where('employee_id', $employeeId)->value($column);
        if (!$rel || !str_starts_with($rel, 'hr-profile/') || str_contains($rel, '..')) {
            return null;
        }
        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($rel)) {
            return null;
        }

        return ['path' => $disk->path($rel), 'mime' => str_ends_with($rel, '.png') ? 'image/png' : 'image/jpeg'];
    }

    private function history(int $employeeId, int $actorId, string $action): void
    {
        // Hanya peristiwa — isi gambar dan nilai sensitif tidak pernah dicatat.
        EmployeeHistory::create([
            'employee_id'  => $employeeId,
            'action'       => $action,
            'description'  => 'HR profile: ' . strtolower($action),
            'performed_by' => $actorId,
            'performed_at' => now(),
        ]);
    }
}
