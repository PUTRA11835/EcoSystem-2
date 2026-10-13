<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ketentuan komersial kontrak konsultan External (menu External → External Contract).
 *
 * KENAPA TABEL TERPISAH: kontrak konsultan tetap satu baris di `employee_contract` (jenis EXTERNAL) supaya nomor, template,
 * dokumen PDF, dan riwayatnya memakai mesin menu Contract yang sama — tidak ada sumber kebenaran kedua. Tabel `employee_contract`
 * (dibaca ± banyak berkas, termasuk Master Employee) tidak diubah; isian khas ESH (skema, tarif per kontrak, satuan tagihan,
 * jam per manday, prorata, termin pembayaran) ditaruh di sini, satu baris per kontrak.
 *
 * TARIF = data sensitif: hanya dibaca/ditulis pemegang slug `general.contracts.salary` (sama seperti gaji kontrak internal).
 *
 * Hanya menambah tabel; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_contract_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');

            // Nilai skema = kunci EngagementRules::SCHEMES (mandays, monthly, fixed_price, hourly); dibatasi di kode, bukan ENUM.
            $table->string('engagement_scheme', 20)->nullable();
            $table->decimal('rate', 15, 2)->nullable();            // SENSITIF
            $table->string('currency', 3)->default('IDR');
            $table->string('billing_unit', 30)->nullable();        // mis. mandays, months, package
            $table->decimal('hours_per_manday', 4, 1)->default(8);
            $table->string('proration', 20)->default('working_days'); // working_days | calendar_days | none (skema bulanan)
            $table->unsignedSmallInteger('payment_term_days')->default(14);
            $table->timestamps();

            $table->unique('contract_id', 'uq_external_contract_terms_contract');
            $table->foreign('contract_id', 'fk_external_contract_terms_contract')
                ->references('contract_id')->on('employee_contract')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_contract_terms');
    }
};
