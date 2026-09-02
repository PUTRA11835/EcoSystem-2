<?php

namespace App\Services\PurchaseRequest;

use App\Models\PurchaseRequest\PurchaseRequest;
use App\Models\PurchaseRequest\PurchaseRequestItem;

/**
 * Perhitungan kepala dokumen Purchase Request dari baris-baris itemnya.
 *
 * MURNI: tidak menyentuh database, tidak membaca sesi, tidak memanggil model
 * untuk mengambil data. Seluruh masukan diterima sebagai array biasa dan aturan
 * diterima sebagai parameter. Karena itu ia dapat diuji lengkap tanpa satu baris
 * data pun — pola yang sama dengan GeofenceService, OvertimeRateService, dan
 * ReimbursementTotalService.
 *
 * Kenapa dipisah dari PurchaseRequestService: meringkas kuantitas, menurunkan
 * pembebanan, dan menilai kelengkapan adalah tiga hal yang harus memberi jawaban
 * yang SAMA di tiga tempat berbeda — saat karyawan mengajukan, saat admin
 * membuat atas nama orang lain ("New PR"), dan saat HR mengedit. Satu-satunya
 * cara memastikan itu adalah satu fungsi yang dipanggil ketiganya.
 *
 * 🔴 BEDANYA DARI ReimbursementTotalService: di sana yang diringkas RUPIAH, di
 * sini KUANTITAS BERSATUAN. "10 PC + 5 SET" tidak dapat dijumlahkan menjadi satu
 * angka, jadi ringkasannya berupa teks per satuan — dan itulah alasan
 * `qty_summary` bertipe varchar, bukan decimal.
 */
class PurchaseRequestSummaryService
{
    /**
     * Hitung kepala dokumen dari baris-baris item.
     *
     * @param  array<int, array<string, mixed>>  $items
     *         Tiap item: qty, unit, use_date, period_from, period_to,
     *         cost_center_type, branch_id, delivery_project_id, cost_center_label
     * @param  array<string, mixed>  $rules
     *         unit_order (array), today (string Y-m-d)
     *
     * @return array{
     *     item_count: int, qty_summary: string,
     *     cost_center_type: ?string, charged_branch_id: ?int,
     *     charged_project_id: ?int, charged_to_label: string,
     *     flags: array<string>
     * }
     */
    public function evaluate(array $items, array $rules = []): array
    {
        $items   = array_values($items);
        $charged = $this->chargedTo($items);

        return [
            'item_count'         => count($items),
            'qty_summary'        => $this->qtySummary($items, $rules['unit_order'] ?? []),
            'cost_center_type'   => $charged['type'],
            'charged_branch_id'  => $charged['branch_id'],
            'charged_project_id' => $charged['project_id'],
            'charged_to_label'   => $charged['label'],
            'flags'              => $this->flags($items, $charged, $rules),
        ];
    }

    /**
     * Ringkasan kuantitas per satuan: "10 PC · 5 SET".
     *
     * Dikelompokkan per satuan karena satuan berbeda tidak dapat dijumlahkan —
     * "10 PC + 5 SET = 15" adalah angka yang tidak berarti apa pun. Urutannya
     * mengikuti `unit_order` (yaitu `allowed_units` di Settings) supaya dokumen
     * yang isinya sama selalu menghasilkan teks yang sama, apa pun urutan
     * pengetikan barisnya — kalau tidak, dua dokumen identik akan terlihat
     * berbeda di rekap.
     *
     * @param  array<string>  $unitOrder
     */
    public function qtySummary(array $items, array $unitOrder = []): string
    {
        $totals = [];

        foreach ($items as $item) {
            $unit = $this->unitOf($item);
            $qty  = $this->qtyOf($item);

            if ($unit === '' || $qty <= 0) {
                continue;
            }

            $totals[$unit] = ($totals[$unit] ?? 0.0) + $qty;
        }

        if ($totals === []) {
            return '';
        }

        $ordered = $this->orderUnits(array_keys($totals), $unitOrder);

        $parts = [];
        foreach ($ordered as $unit) {
            $parts[] = PurchaseRequestItem::formatQty(round($totals[$unit], 2)) . ' ' . $unit;
        }

        return implode(' · ', $parts);
    }

    /**
     * Pembebanan biaya dokumen, diturunkan dari itemnya (Keputusan D127).
     *
     * Nilainya bukan pilihan manusia melainkan cerminan apa yang dipilih di item:
     *   - seluruh item satu cabang   -> type branch,  id + label cabang itu
     *   - seluruh item satu proyek   -> type project, id + label proyek itu
     *   - selain itu                 -> type mixed,   label "Multiple cost centers"
     *
     * Labelnya diambil dari `cost_center_label` yang sudah dibekukan pada item,
     * bukan disusun ulang dari tabel sumbernya (Keputusan D105).
     *
     * @return array{type: ?string, branch_id: ?int, project_id: ?int, label: string}
     */
    public function chargedTo(array $items): array
    {
        $keys   = [];   // "branch:2" / "project:3"
        $labels = [];

        foreach ($items as $item) {
            $ref = $this->costCenterRef($item);

            if ($ref === null) {
                continue;
            }

            $keys[$ref['key']]   = $ref;
            $labels[$ref['key']] = trim((string) ($item['cost_center_label'] ?? ''));
        }

        if ($keys === []) {
            return ['type' => null, 'branch_id' => null, 'project_id' => null, 'label' => '—'];
        }

        if (count($keys) > 1) {
            return [
                'type'       => PurchaseRequest::COST_CENTER_MIXED,
                'branch_id'  => null,
                'project_id' => null,
                'label'      => PurchaseRequest::LABEL_MULTIPLE,
            ];
        }

        $ref   = reset($keys);
        $key   = array_key_first($keys);
        $label = $labels[$key] !== ''
            ? $labels[$key]
            : ucfirst($ref['type']) . ' #' . $ref['id'];

        return [
            'type'       => $ref['type'],
            'branch_id'  => $ref['type'] === PurchaseRequestItem::COST_CENTER_BRANCH ? $ref['id'] : null,
            'project_id' => $ref['type'] === PurchaseRequestItem::COST_CENTER_PROJECT ? $ref['id'] : null,
            'label'      => $label,
        ];
    }

    /**
     * Satu baris hanya boleh membebani SATU tempat.
     *
     * Penjagaan wajib server (lihat docblock migrasi item): tanpa ini satu baris
     * bisa mengisi cabang dan proyek sekaligus, dan tidak ada yang tahu mana yang
     * berlaku saat pengadaan berjalan. Dikembalikan sebagai
     * ['ok' => bool, 'reason' => string] supaya pesannya dapat dibaca pemohon —
     * itulah sebabnya aturan ini di lapisan validasi, bukan CHECK constraint.
     *
     * @return array{ok: bool, reason: string}
     */
    public function assertSingleCostCenter(array $item): array
    {
        $type      = strtolower(trim((string) ($item['cost_center_type'] ?? '')));
        $branchId  = $this->intOrNull($item['branch_id'] ?? null);
        $projectId = $this->intOrNull($item['delivery_project_id'] ?? null);

        if ($branchId !== null && $projectId !== null) {
            return [
                'ok'     => false,
                'reason' => 'An item can be charged to a branch or a project, not both.',
            ];
        }

        if ($type === PurchaseRequestItem::COST_CENTER_BRANCH && $projectId !== null) {
            return ['ok' => false, 'reason' => 'This item is set to Branch but carries a project.'];
        }

        if ($type === PurchaseRequestItem::COST_CENTER_PROJECT && $branchId !== null) {
            return ['ok' => false, 'reason' => 'This item is set to Project but carries a branch.'];
        }

        if ($type !== '' && !in_array($type, PurchaseRequestItem::COST_CENTER_TYPES, true)) {
            return ['ok' => false, 'reason' => 'Unknown cost center type: ' . $type];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Bersihkan dan urutkan ulang baris item.
     *
     * 🔴 Form mengirim item dengan kunci acak (`items[<uuid>][qty]`), BUKAN
     * indeks berurutan — kalau berurutan, menghapus baris di tengah membuat
     * indeksnya bolong dan baris berikutnya tertimpa. Nomor urut yang dilihat
     * pengguna dibangun DI SINI, dari urutan kedatangannya.
     *
     * Baris yang sepenuhnya kosong dibuang tanpa protes: form multi-baris selalu
     * meninggalkan satu baris kosong di bawah.
     *
     * Yang TIDAK dikerjakan di sini: membekukan `cost_center_label`. Label itu
     * memerlukan pembacaan tabel `branches` / `delivery_projects`, dan service
     * ini murni. PurchaseRequestService yang mengisinya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function normaliseItems(array $rawItems, string $defaultUnit = 'PC'): array
    {
        $items  = [];
        $lineNo = 1;

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $description = trim((string) ($raw['description'] ?? ''));
            $qty         = $this->qtyOf($raw);

            if ($description === '' && $qty <= 0) {
                continue;
            }

            $type = strtolower(trim((string) ($raw['cost_center_type'] ?? '')));
            if (!in_array($type, PurchaseRequestItem::COST_CENTER_TYPES, true)) {
                $type = PurchaseRequestItem::COST_CENTER_BRANCH;
            }

            $branchId  = $this->intOrNull($raw['branch_id'] ?? null);
            $projectId = $this->intOrNull($raw['delivery_project_id'] ?? null);

            // Kolom yang tidak sesuai tipenya DIPAKSA null, bukan dibiarkan
            // terbawa: pengguna yang berpindah dari Branch ke Project setelah
            // memilih cabang akan meninggalkan nilai lama di form.
            if ($type === PurchaseRequestItem::COST_CENTER_BRANCH) {
                $projectId = null;
            } else {
                $branchId = null;
            }

            $from = trim((string) ($raw['period_from'] ?? ''));
            $to   = trim((string) ($raw['period_to'] ?? ''));

            $items[] = [
                'line_no'             => $lineNo++,
                'description'         => $description,
                'qty'                 => $qty,
                'unit'                => $this->unitOf($raw) ?: strtoupper($defaultUnit),
                'period_from'         => $from !== '' ? $from : null,
                // Periode satu hari mengisi keduanya dengan tanggal yang sama,
                // bukan membiarkan `to` kosong — dengan begitu penyaringan
                // rentang tanggal tidak butuh cabang khusus.
                'period_to'           => $to !== '' ? $to : ($from !== '' ? $from : null),
                'use_date'            => trim((string) ($raw['use_date'] ?? '')) ?: null,
                'cost_center_type'    => $type,
                'branch_id'           => $branchId,
                'delivery_project_id' => $projectId,
                'cost_center_label'   => null,   // dibekukan oleh PurchaseRequestService
            ];
        }

        return $items;
    }

    // =======================================================================
    // internal
    // =======================================================================

    /**
     * Sinyal kelengkapan dokumen.
     *
     * Seluruhnya MENANDAI, tidak ada yang menolak. Penolakan dikerjakan
     * PurchaseRequestService karena menyangkut keadaan di luar item — periode
     * terkunci, langkah persetujuan, dan setelan yang menuntut kelengkapan.
     *
     * @return array<string>
     */
    private function flags(array $items, array $charged, array $rules): array
    {
        $flags = [];

        if ($items === []) {
            return $flags;
        }

        foreach ($items as $item) {
            if (trim((string) ($item['use_date'] ?? '')) === '') {
                $flags[] = PurchaseRequest::FLAG_MISSING_USE_DATE;
            }

            if (trim((string) ($item['period_from'] ?? '')) === '') {
                $flags[] = PurchaseRequest::FLAG_MISSING_PERIOD;
            }

            if ($this->costCenterRef($item) === null) {
                $flags[] = PurchaseRequest::FLAG_MISSING_COST_CENTER;
            }
        }

        // Penanda netral, bukan anomali: dokumen lintas cabang/proyek itu sah,
        // hanya perlu terlihat karena pembebanannya tidak dapat diringkas satu
        // nama.
        if ($charged['type'] === PurchaseRequest::COST_CENTER_MIXED) {
            $flags[] = PurchaseRequest::FLAG_MIXED_COST_CENTER;
        }

        // Barang yang diminta untuk tanggal yang sudah lewat: bukan kesalahan,
        // tetapi penyetuju perlu tahu bahwa pengadaannya sudah terlambat sejak
        // dokumennya dibuat.
        $today = trim((string) ($rules['today'] ?? ''));
        if ($today !== '' && $this->anyUseDateBefore($items, $today)) {
            $flags[] = PurchaseRequest::FLAG_USE_DATE_PASSED;
        }

        return array_values(array_unique($flags));
    }

    private function anyUseDateBefore(array $items, string $today): bool
    {
        $limit = strtotime($today);

        if ($limit === false) {
            return false;
        }

        foreach ($items as $item) {
            $useDate = trim((string) ($item['use_date'] ?? ''));

            if ($useDate === '') {
                continue;
            }

            $stamp = strtotime($useDate);

            if ($stamp !== false && $stamp < $limit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rujukan pembebanan satu baris, atau null bila tidak ada.
     *
     * @return array{key: string, type: string, id: int}|null
     */
    private function costCenterRef(array $item): ?array
    {
        $type = strtolower(trim((string) ($item['cost_center_type'] ?? '')));

        if ($type === PurchaseRequestItem::COST_CENTER_PROJECT) {
            $id = $this->intOrNull($item['delivery_project_id'] ?? null);

            return $id === null ? null : [
                'key'  => PurchaseRequestItem::COST_CENTER_PROJECT . ':' . $id,
                'type' => PurchaseRequestItem::COST_CENTER_PROJECT,
                'id'   => $id,
            ];
        }

        $id = $this->intOrNull($item['branch_id'] ?? null);

        return $id === null ? null : [
            'key'  => PurchaseRequestItem::COST_CENTER_BRANCH . ':' . $id,
            'type' => PurchaseRequestItem::COST_CENTER_BRANCH,
            'id'   => $id,
        ];
    }

    /**
     * Urutkan satuan mengikuti daftar di Settings, sisanya menyusul alfabetis.
     *
     * @param  array<string>  $units
     * @param  array<string>  $order
     * @return array<string>
     */
    private function orderUnits(array $units, array $order): array
    {
        $order = array_values(array_map(fn ($u) => strtoupper(trim((string) $u)), $order));

        $known   = [];
        $unknown = [];

        foreach ($units as $unit) {
            $position = array_search($unit, $order, true);

            if ($position === false) {
                $unknown[] = $unit;
            } else {
                $known[$position] = $unit;
            }
        }

        ksort($known);
        sort($unknown);

        return array_merge(array_values($known), $unknown);
    }

    /**
     * Kuantitas satu item sebagai angka.
     *
     * Menerima string karena nilainya dapat datang dari form maupun dari model
     * yang mengembalikan decimal sebagai string. Nilai negatif dipaksa nol:
     * permintaan pengadaan bernilai negatif hanya dapat lahir dari kesalahan
     * masukan.
     */
    private function qtyOf(array $item): float
    {
        return max(0.0, round((float) ($item['qty'] ?? 0), 2));
    }

    /** Satuan dinormalkan ke huruf besar supaya "pc" dan "PC" tidak terpisah. */
    private function unitOf(array $item): string
    {
        return strtoupper(trim((string) ($item['unit'] ?? '')));
    }

    private function intOrNull($value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}
