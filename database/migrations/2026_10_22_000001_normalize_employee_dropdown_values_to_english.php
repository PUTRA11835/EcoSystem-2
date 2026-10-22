<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opsi dropdown Religion (Family) disamakan dengan Basic Data (Christian, Catholic, ...)
     * dan Document Type (Attachment) diganti ke bahasa Inggris. Data lama ikut dikonversi
     * supaya dropdown tetap terisi saat edit dan tabel attachment tidak campur bahasa.
     */
    private array $religion = [
        'Christianity' => 'Christian',
        'Catholicism'  => 'Catholic',
        'Hinduism'     => 'Hindu',
        'Buddhism'     => 'Buddhist',
        'Confucianism' => 'Confucian',
    ];

    private array $documentType = [
        'Ijazah'          => 'Diploma',
        'Transkrip Nilai' => 'Academic Transcript',
        'Sertifikat'      => 'Certificate',
        'Kontrak Kerja'   => 'Employment Contract',
        'SK Pengangkatan' => 'Appointment Letter',
        'Surat Referensi' => 'Reference Letter',
        'Akta Lahir'      => 'Birth Certificate',
        'Kartu Keluarga'  => 'Family Card (KK)',
        'Foto'            => 'Photo',
        'Lainnya'         => 'Other',
    ];

    public function up(): void
    {
        $this->remap('employee_family', 'religion', $this->religion);
        $this->remap('employee_attachment', 'document_type', $this->documentType);
    }

    public function down(): void
    {
        $this->remap('employee_family', 'religion', array_flip($this->religion));
        $this->remap('employee_attachment', 'document_type', array_flip($this->documentType));
    }

    private function remap(string $table, string $column, array $map): void
    {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        foreach ($map as $from => $to) {
            DB::table($table)->where($column, $from)->update([$column => $to]);
        }
    }
};
