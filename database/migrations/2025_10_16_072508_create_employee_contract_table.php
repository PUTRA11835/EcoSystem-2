<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contract', function (Blueprint $table) {
            $table->id('contract_id');
            $table->foreignId('employee_id')->constrained('employee','employee_id')->onDelete('cascade');

            $table->string('contract_number')->unique();
            $table->string('contract_name')->nullable();
            $table->string('contract_type')->nullable(); // e.g., Permanent, Internship, Probation
            $table->string('contract_date')->nullable();
            $table->string('position')->nullable();
            $table->decimal('salary', 15, 2)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->boolean('is_active')->default(true); // menandakan kontrak masih berjalan

            $table->string('drive_link')->nullable(); // lampiran file kontrak
            $table->string('verify_link')->nullable(); // link verifikasi digital

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contract');
    }
};
