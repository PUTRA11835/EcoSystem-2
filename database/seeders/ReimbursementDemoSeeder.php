<?php

namespace Database\Seeders;

use App\Models\Attendance\Branch;
use App\Models\Employee;
use App\Models\Reimbursement\ReimbursementRequest;
use App\Models\Reimbursement\ReimbursementRequestApproval;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Database\Seeder;

/**
 * Dokumen reimbursement contoh untuk menguji layar daftar, detail, dan alur
 * persetujuan: submitted, in review, approved, rejected, USD, dengan dan tanpa
 * direktur pilihan.
 *
 * Dokumen dibuat lewat ReimbursementService::submit(), jadi nomor, langkah
 * persetujuan, dan flag-nya sama dengan dokumen sungguhan. Status akhirnya
 * diatur langsung pada baris persetujuan, tidak bergantung pada hak akses.
 *
 * Aman dijalankan ulang: dokumen demo sebelumnya (judul berawalan "[DEMO]")
 * dihapus permanen lebih dulu.
 *
 *   php artisan db:seed --class=ReimbursementDemoSeeder
 */
class ReimbursementDemoSeeder extends Seeder
{
    private const PREFIX = '[DEMO] ';

    public function run(ReimbursementService $service): void
    {
        $employees = Employee::where('is_active', 1)->orderBy('employee_id')->limit(6)->pluck('employee_id')->all();
        $branches  = $service->branchOptions();

        if (count($employees) < 2) {
            $this->command?->warn('Needs at least 2 active employees — nothing seeded.');
            return;
        }

        // Tanpa cabang, form reimbursement tidak dapat dipakai sama sekali.
        // Cabang demo dibuat HANYA bila belum ada satu pun cabang, dengan
        // geofence dimatikan (override `off`) supaya tidak memengaruhi Attendance.
        if ($branches->isEmpty() && Branch::withTrashed()->count() === 0) {
            Branch::create([
                'code'              => 'DEMO',
                'name'              => 'Demo Office',
                'city'              => 'Jakarta',
                'latitude'          => -6.2000000,
                'longitude'         => 106.8166660,
                'radius_meters'     => 100,
                'geofence_override' => Branch::GEOFENCE_OFF,
                'is_active'         => true,
            ]);

            $branches = $service->branchOptions();
            $this->command?->info('No branch existed; created "DEMO – Demo Office" (geofence off). Rename or delete it in Settings → Branches.');
        }

        if ($branches->isEmpty()) {
            $this->command?->warn('No active branch (existing branches are all inactive). Activate one in Settings → Branches and run again.');
            return;
        }

        if ($service->activeSteps()->isEmpty()) {
            $this->command?->warn('No active reimbursement approval step — nothing seeded.');
            return;
        }

        $this->purgePreviousDemo();

        $settings  = ReimbursementSetting::current();
        $directors = $settings->directorIds();

        if ($directors === []) {
            $directors = array_slice($employees, 0, 2);
            $settings->update(['director_employee_ids' => $directors]);
            ReimbursementSetting::forgetCache();
            $this->command?->info('Selectable Directors was empty; set to employee ids ' . implode(', ', $directors) . '.');
        }

        $branchIds = $branches->pluck('id')->all();

        // [title, currency, director?, final state, [description, amount][]]
        $scenarios = [
            ['Office supplies',          'IDR', true,  'submitted', [['Printer paper', 185000], ['Stationery', 92500]]],
            ['Client visit transport',   'IDR', false, 'submitted', [['Taxi to client', 240000]]],
            ['Team lunch',               'IDR', true,  'in_review', [['Lunch meeting', 650000]]],
            ['Software subscription',    'USD', true,  'in_review', [['Design tool, annual', 120.50], ['Plugin', 19.99]]],
            ['Training registration',    'IDR', true,  'approved',  [['Course fee', 1500000]]],
            ['Conference travel',        'SGD', false, 'approved',  [['Flight', 420.00], ['Hotel, 2 nights', 380.00]]],
            ['Parking and toll',         'IDR', false, 'rejected',  [['Toll', 78000], ['Parking', 45000]]],
            ['Equipment purchase',       'USD', true,  'rejected',  [['USB hub', 35.00]]],
        ];

        $created = 0;

        foreach ($scenarios as $i => [$title, $currency, $withDirector, $state, $lines]) {
            $employeeId = $employees[$i % count($employees)];
            $date       = now()->subDays($i % 5)->toDateString();

            $items = [];
            foreach ($lines as $n => [$description, $amount]) {
                $items['seed' . $i . $n] = [
                    'description'       => $description,
                    'branch_id'         => $branchIds[($i + $n) % count($branchIds)],
                    'receipt_no'        => 'DEMO-' . ($i + 1) . '-' . ($n + 1),
                    'receipt_date_from' => $date,
                    'receipt_date_to'   => '',
                    'amount'            => $amount,
                ];
            }

            // Dokumen "tanpa direktur" meniru dokumen lama: dibuat selagi daftar
            // direktur kosong, lalu daftarnya dikembalikan.
            if (!$withDirector) {
                $this->setDirectors([]);
            }

            $result = $service->submit($employeeId, [
                'request_date'   => $date,
                'title'          => self::PREFIX . $title,
                'supporting_url' => 'https://drive.google.com/demo/' . ($i + 1),
                'currency'       => $currency,
                'approver_id'    => $withDirector ? $directors[$i % count($directors)] : null,
                'items'          => $items,
            ]);

            if (!$withDirector) {
                $this->setDirectors($directors);
            }

            if (!$result['allowed']) {
                $this->command?->warn("Skipped \"{$title}\": {$result['reason']}");
                continue;
            }

            $this->moveTo($result['request'], $state, $employees[0]);
            $created++;
        }

        $this->command?->info("Seeded {$created} demo reimbursement document(s).");
    }

    private function setDirectors(array $ids): void
    {
        ReimbursementSetting::current()->update(['director_employee_ids' => $ids]);
        ReimbursementSetting::forgetCache();
    }

    private function moveTo(ReimbursementRequest $request, string $state, int $actorId): void
    {
        $approvals = $request->approvals()->orderBy('order_seq')->get();

        if ($state === 'submitted' || $approvals->isEmpty()) {
            return;
        }

        $act = fn (ReimbursementRequestApproval $a, string $status, ?string $notes = null) => $a->update([
            'status'   => $status,
            'acted_by' => $actorId,
            'acted_at' => now(),
            'notes'    => $notes,
        ]);

        if ($state === 'rejected') {
            $first = $approvals->first();
            $act($first, ReimbursementRequestApproval::STATUS_REJECTED, 'Demo: receipt is unreadable.');

            $approvals->slice(1)->each(fn ($a) => $a->update(['status' => ReimbursementRequestApproval::STATUS_SKIPPED]));

            $request->update([
                'status'             => ReimbursementRequest::STATUS_REJECTED,
                'current_step_order' => null,
                'completed_at'       => now(),
            ]);

            return;
        }

        if ($state === 'in_review') {
            if ($approvals->count() < 2) {
                return;
            }

            $act($approvals[0], ReimbursementRequestApproval::STATUS_APPROVED);

            $request->update([
                'status'             => ReimbursementRequest::STATUS_IN_REVIEW,
                'current_step_order' => $approvals[1]->order_seq,
            ]);

            return;
        }

        // approved
        $approvals->each(fn ($a) => $act($a, ReimbursementRequestApproval::STATUS_APPROVED));

        $request->update([
            'status'             => ReimbursementRequest::STATUS_APPROVED,
            'current_step_order' => null,
            'completed_at'       => now(),
        ]);
    }

    private function purgePreviousDemo(): void
    {
        ReimbursementRequest::withTrashed()
            ->where('title', 'like', self::PREFIX . '%')
            ->get()
            ->each(function (ReimbursementRequest $request) {
                $request->approvals()->delete();
                $request->items()->delete();
                $request->forceDelete();
            });
    }
}
