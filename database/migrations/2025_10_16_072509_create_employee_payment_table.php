<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payment', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('employee_id')->constrained('employee','employee_id')->onDelete('cascade');

            $table->decimal('amount', 15, 2);
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // e.g., Transfer, Cash, E-Wallet
            $table->string('reference_number')->nullable()->unique();

            $table->string('payment_status')->default('Pending'); // e.g., Pending, Completed, Failed
            $table->date('valid_to')->nullable();

            $table->string('drive_link')->nullable(); // bukti pembayaran
            $table->string('verify_link')->nullable(); // tautan verifikasi slip pembayaran

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payment');
    }
};
