<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_deliverables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('doc_type', 50);           // IR, RCA, CR Form, FSD, TD, UAT, MOM, BAST, Other
            $table->text('body_text')->nullable();
            $table->string('file_name')->nullable();  // original filename
            $table->string('onedrive_file_id')->nullable();
            $table->string('onedrive_file_url', 1000)->nullable();
            $table->string('status', 20)->default('No Send'); // No Send | Sended
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->date('upload_date')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_deliverables');
    }
};
