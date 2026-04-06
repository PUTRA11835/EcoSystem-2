<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket', function (Blueprint $table) {
            $table->id('ticket_id');

            // Relasi: satu tiket bisa punya satu customer & satu PIC (employee)
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customer', 'customer_id')
                ->onDelete('set null');

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employee', 'employee_id')
                ->onDelete('set null');

            // Data utama tiket
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Informasi status & progress
            $table->enum('ticket_priority', ['Low', 'Medium', 'High'])->nullable();
            $table->string('jarvies_status')->nullable();  // author action, sent to sap, in proccess, closed
            $table->enum('status', ['open', 'in_progress','hold','cancel', 'closed', 'reply'])->default('open');
            $table->float('wait_close')->nullable();       // waktu tunggu penyelesaian (jam/hari)
            $table->string('type')->nullable();            //mo,ams,ats,dll
            $table->float('man_days')->nullable();         // effort dalam satuan hari
            $table->string('folder')->nullable();         // link gdrive
            $table->string('file_log')->nullable();         // Timestamps

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket');
    }
};
