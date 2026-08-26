<?php

namespace App\Services\Reimbursement;

use App\Models\Reimbursement\ReimbursementRequest;

/**
 * Perhitungan kepala dokumen reimbursement dari baris-baris itemnya.
 *
 * MURNI: tidak menyentuh database, tidak membaca sesi, tidak memanggil model
 * untuk mengambil data. Seluruh masukan diterima sebagai array biasa dan aturan
 * diterima sebagai parameter. Karena itu ia dapat diuji lengkap tanpa satu baris
 * data pun — pola yang sama dengan OvertimeRateService dan GeofenceService.
 *
 * Kenapa dipisah dari ReimbursementService: menjumlahkan item, menentukan
 * pembebanan, dan menilai kewajaran adalah tiga hal yang harus memberi jawaban
 * yang SAMA di tiga tempat berbeda — saat submit, saat HR mengedit, dan saat
 * impor Excel. Satu-satunya cara memastikan itu adalah satu fungsi yang dipanggil
 * ketiganya (Keputusan D104).
 */
class ReimbursementTotalService
{
    /**
     * Hitung kepala dokumen dari baris-baris item.
     *
     * @param  array<int, array<string, mixed>>  $items
     *         Tiap item: amount, branch_id, cost_center_label, receipt_no
     * @param  array<string, mixed>  $rules
     *         max_request_amount, min_item_amount, require_receipt_no,
     *         require_supporting_url, has_supporting_url
     *
     * @return array{
     *     total: float, item_count: int, charged_branch_id: ?int,
     *     charged_to_label: string, flags: array<string>
     * }
     */
    public function evaluate(array $items, array $rules = []): array
    {
        $items = array_values($items);

        $total    = $this->sum($items);
        $charged  = $this->chargedTo($items);

        return [
            'total'             => $total,
            'item_count'        => count($items),
            'charged_branch_id' => $charged['branch_id'],
            'charged_to_label'  => $charged['label'],
            'flags'             => $this->flags($items, $total, $charged['branch_id'], $rules),
        ];
    }

    /**
     * Jumlah seluruh nominal item.
     *
     * 🔴 Tiap baris DIBULATKAN LEBIH DULU ke dua desimal, baru dijumlahkan —
     * bukan sebaliknya. Ini disengaja meski secara aritmetika menumpuk galat
     * pembulatan: kolom `amount` bertipe decimal(20,2), sehingga yang TERSIMPAN
     * dan yang TERCETAK di tiap baris dokumen sudah dua desimal. Menjumlahkan
     * nilai penuh lalu membulatkan di akhir dapat menghasilkan total yang tidak
     * sama dengan jumlah angka yang terbaca di dokumen itu sendiri — dan itu
     * persis hal yang akan ditemukan Finance saat mencocokkan.
     *
     * Total dokumen harus selalu sama dengan jumlah baris yang tercetak padanya.
     */
    public function sum(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $this->amountOf($item);
        }

        return round($total, 2);
    }

    /**
     * Pembebanan biaya dokumen, diturunkan dari itemnya (Keputusan D103).
     *
     * Ini yang menggantikan "Jenis Beban" pada aplikasi acuan. Nilainya bukan
     * pilihan manusia melainkan cerminan apa yang dipilih di item:
     *   - seluruh item satu cabang  -> id + nama cabang itu
     *   - lintas cabang             -> id NULL, label "Multiple branches"
     *
     * Labelnya diambil dari `cost_center_label` yang sudah dibekukan pada item,
     * bukan disusun ulang dari tabel `branches` (Keputusan D105).
     *
     * @return array{branch_id: ?int, label: string}
     */
    public function chargedTo(array $items): array
    {
        $branchIds = [];
        $labels    = [];

        foreach ($items as $item) {
            $branchId = $item['branch_id'] ?? null;

            if ($branchId !== null && $branchId !== '') {
                $branchIds[] = (int) $branchId;
                $labels[(int) $branchId] = (string) ($item['cost_center_label'] ?? '');
            }
        }

        $unique = array_values(array_unique($branchIds));

        if (count($unique) === 1) {
            $id = $unique[0];

            return [
                'branch_id' => $id,
                'label'     => $labels[$id] !== '' ? $labels[$id] : 'Branch #' . $id,
            ];
        }

        if ($unique === []) {
            return ['branch_id' => null, 'label' => '—'];
        }

        return [
            'branch_id' => null,
            'label'     => ReimbursementRequest::LABEL_MULTIPLE_BRANCHES,
        ];
    }

    /**
     * Apakah total melewati batas? Nilai 0 berarti tanpa batas.
     *
     * Dibuat method tersendiri karena dipakai dua kali dengan akibat berbeda:
     * untuk MENOLAK saat kebijakannya `block`, dan untuk MENANDAI saat
     * kebijakannya `flag` (Keputusan D107).
     */
    public function exceedsLimit(float $total, float|int|string|null $maxAmount): bool
    {
        $max = (float) $maxAmount;

        return $max > 0 && $total > $max;
    }

    /**
     * Sinyal kewajaran dokumen.
     *
     * Seluruhnya MENANDAI, tidak ada yang menolak. Penolakan dikerjakan
     * ReimbursementService karena menyangkut keadaan di luar item — periode
     * terkunci, langkah persetujuan, dan kebijakan `block`.
     *
     * @return array<string>
     */
    private function flags(array $items, float $total, ?int $chargedBranchId, array $rules): array
    {
        $flags = [];

        if ($this->exceedsLimit($total, $rules['max_request_amount'] ?? 0)) {
            $flags[] = ReimbursementRequest::FLAG_EXCEEDS_LIMIT;
        }

        // Dokumen tanpa tautan bukti tetap boleh masuk bila pengaturannya tidak
        // menuntutnya, tetapi keadaannya harus terlihat oleh penyetuju.
        if (empty($rules['has_supporting_url'])) {
            $flags[] = ReimbursementRequest::FLAG_MISSING_SUPPORTING_URL;
        }

        if (!empty($items) && $this->anyMissingReceiptNo($items)) {
            $flags[] = ReimbursementRequest::FLAG_MISSING_RECEIPT_NO;
        }

        $minItem = (float) ($rules['min_item_amount'] ?? 0);
        if ($minItem > 0 && $this->anyBelow($items, $minItem)) {
            $flags[] = ReimbursementRequest::FLAG_BELOW_MIN_ITEM;
        }

        // Penanda netral, bukan anomali: dokumen lintas cabang itu sah, hanya
        // perlu terlihat karena pembebanannya tidak dapat diringkas satu nama.
        if ($chargedBranchId === null && count($items) > 1) {
            $flags[] = ReimbursementRequest::FLAG_MULTI_BRANCH;
        }

        return array_values(array_unique($flags));
    }

    private function anyMissingReceiptNo(array $items): bool
    {
        foreach ($items as $item) {
            if (trim((string) ($item['receipt_no'] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private function anyBelow(array $items, float $minimum): bool
    {
        foreach ($items as $item) {
            if ($this->amountOf($item) < $minimum) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nominal satu item sebagai angka.
     *
     * Menerima string karena nilainya dapat datang dari form maupun dari model
     * yang mengembalikan decimal sebagai string. Nilai negatif dipaksa nol:
     * reimbursement adalah penggantian biaya, dan baris bernilai negatif hanya
     * dapat lahir dari kesalahan masukan.
     */
    private function amountOf(array $item): float
    {
        return max(0.0, round((float) ($item['amount'] ?? 0), 2));
    }
}
