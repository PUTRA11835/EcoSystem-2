<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_attachment', function (Blueprint $table) {
            $table->id('attachment_id');
            $table->foreignId('customer_id')->nullable()->constrained('customer','customer_id')->onDelete('set null');
            $table->string('document_type', 100)->nullable();
            $table->string('document_title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('uploaded_by', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_attachment');
    }
};
