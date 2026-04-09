<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel sementara untuk menyimpan file attachment yang dikirim customer
 * via JARVIES web form (non-OAuth, multipart/form-data) bersama staging ticket.
 *
 * Flow:
 *   Customer upload file → staging_attachments (file disimpan di storage)
 *   Admin approve staging → file dipindahkan ke ticket_attachment
 *   Admin reject staging  → file dihapus via cleanup job / cascade
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_attachments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('staging_id');
            $table->foreign('staging_id')
                ->references('id')->on('staging_tickets')
                ->cascadeOnDelete();

            $table->string('file_name');
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('original_name')->nullable();

            $table->timestamps();

            $table->index('staging_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_attachments');
    }
};
