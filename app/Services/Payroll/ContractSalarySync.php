<?php

namespace App\Services\Payroll;

use App\Models\EmployeeContract;
use App\Models\EmployeeSalaryComponent;
use App\Models\Recruitment\OfferComponent;
use App\Support\Contracts\ContractRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kontrak AKTIF → Salary Components (sumber gaji Payroll). Dipanggil ContractService setelah kontrak internal disimpan
 * dengan status Active oleh pemegang izin "View Salary".
 *
 * Aturan (perpanjangan kontrak = kontrak baru):
 *  - Baris dari kontrak: Basic Salary (`base`) = employee_contract.salary; tiap tunjangan tetap kontrak = `fixed`.
 *    Berlaku mulai tanggal MULAI kontrak, tanpa tanggal akhir (ditutup otomatis oleh kontrak berikutnya, jadi
 *    keterlambatan perpanjangan tidak membuat gaji kosong di Payroll).
 *  - Bila gaji kontrak SAMA dengan yang sedang berlaku (mis. perpanjangan tanpa kenaikan) → tidak ada perubahan.
 *  - Bila BEDA: baris base/fixed yang berlaku lebih awal ditutup sehari sebelum kontrak mulai (riwayat tetap ada, slip lama
 *    tidak berubah); baris base/fixed yang mulai pada/sesudah tanggal itu dinonaktifkan; lalu baris baru dibuat.
 *  - Menyimpan ulang kontrak yang sama memperbarui baris miliknya sendiri (contract_id), tidak menggandakan.
 *  - Tidak pernah menghapus baris, dan tidak menyentuh Other Income (variable) maupun potongan (deduction).
 *  - Kontrak EXTERNAL (konsultan) dan kontrak tanpa gaji dilewati.
 */
class ContractSalarySync
{
    /** @return string|null pesan ringkas untuk HR; null bila tidak ada yang dilakukan */
    public function sync(EmployeeContract $c): ?string
    {
        if ($c->contract_type === ContractRules::TYPE_EXTERNAL || $c->lifecycle_status !== ContractRules::STATUS_ACTIVE
            || $c->salary === null || (float) $c->salary <= 0 || !$c->start_date) {
            return null;
        }

        $start = $c->start_date instanceof Carbon ? $c->start_date->toDateString() : (string) $c->start_date;
        $lines = [['Basic Salary', OfferComponent::KIND_BASE, (float) $c->salary]];
        foreach (ContractRules::cleanComponents($c->salary_components) as $row) {
            if ($row['amount'] > 0) {
                $lines[] = [$row['name'], OfferComponent::KIND_FIXED, (float) $row['amount']];
            }
        }
        $kinds = [OfferComponent::KIND_BASE, OfferComponent::KIND_FIXED];
        $actor = 'Contract ' . $c->contract_number;
        $by = session('user.id') ? (int) session('user.id') : null;

        return DB::transaction(function () use ($c, $start, $lines, $kinds, $actor, $by) {
            $own = EmployeeSalaryComponent::where('employee_id', $c->employee_id)->where('contract_id', $c->contract_id)->get();

            // Baris base/fixed yang berlaku pada tanggal mulai (di luar milik kontrak ini), untuk dibandingkan.
            $current = EmployeeSalaryComponent::where('employee_id', $c->employee_id)->whereIn('kind', $kinds)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('contract_id')->orWhere('contract_id', '!=', $c->contract_id))
                ->where('effective_from', '<=', $start)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))->get();

            $key = fn ($kind, $name, $amount) => $kind . '|' . ($kind === OfferComponent::KIND_BASE ? '' : mb_strtolower(trim($name))) . '|' . number_format((float) $amount, 2, '.', '');
            $want = collect($lines)->map(fn ($l) => $key($l[1], $l[0], $l[2]))->sort()->values()->all();

            // Sama persis dengan yang berlaku (dan belum ada baris milik kontrak ini) → tidak perlu apa pun.
            if ($own->isEmpty() && $current->map(fn ($r) => $key($r->kind, $r->name, $r->amount))->sort()->values()->all() === $want) {
                return 'Salary is unchanged from the current Salary Components, so nothing was updated.';
            }

            // Tutup / nonaktifkan baris base/fixed lain yang bertabrakan.
            $closed = 0;
            $others = EmployeeSalaryComponent::where('employee_id', $c->employee_id)->whereIn('kind', $kinds)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('contract_id')->orWhere('contract_id', '!=', $c->contract_id))
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))->get();
            foreach ($others as $r) {
                $from = $r->effective_from?->toDateString();
                if ($from !== null && $from < $start) {
                    $r->update(['effective_to' => Carbon::parse($start)->subDay()->toDateString(), 'updated_by' => $by]);
                } else {
                    $r->update(['is_active' => false, 'notes' => trim(($r->notes ? $r->notes . ' · ' : '') . 'Replaced by ' . $actor), 'updated_by' => $by]);
                }
                $closed++;
            }

            // Baris milik kontrak ini: perbarui yang cocok (jenis+nama), tambah yang baru, nonaktifkan yang tak lagi ada.
            $used = [];
            $created = 0;
            foreach ($lines as [$name, $kind, $amount]) {
                $row = $own->first(fn ($r) => !in_array($r->id, $used, true) && $r->kind === $kind && ($kind === OfferComponent::KIND_BASE || mb_strtolower(trim($r->name)) === mb_strtolower(trim($name))));
                $attrs = ['name' => $name, 'kind' => $kind, 'amount' => $amount, 'effective_from' => $start, 'effective_to' => null, 'is_active' => true, 'updated_by' => $by];
                if ($row) {
                    $row->update($attrs);
                    $used[] = $row->id;
                } else {
                    $new = EmployeeSalaryComponent::create($attrs + [
                        'employee_id' => $c->employee_id, 'source' => EmployeeSalaryComponent::SOURCE_MANUAL, 'contract_id' => $c->contract_id,
                        'taxable' => true, 'bpjs_base' => true, 'notes' => 'From contract ' . $c->contract_number, 'created_by' => $actor,
                    ]);
                    $used[] = $new->id;
                    $created++;
                }
            }
            foreach ($own as $r) {
                if (!in_array($r->id, $used, true) && $r->is_active) {
                    $r->update(['is_active' => false, 'updated_by' => $by]);
                }
            }

            return sprintf('Salary Components updated from the contract: %d line(s) from %s%s.', count($lines), Carbon::parse($start)->format('d M Y'),
                $closed ? ", {$closed} earlier line(s) closed" : '');
        });
    }
}
