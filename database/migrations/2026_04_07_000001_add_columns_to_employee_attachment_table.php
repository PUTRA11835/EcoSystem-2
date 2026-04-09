<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attachment', function (Blueprint $table) {
            $table->string('document_type', 100)->nullable()->after('employee_id');
            $table->string('document_title')->nullable()->after('document_type');
            $table->text('description')->nullable()->after('document_title');
            $table->string('file_name')->nullable()->after('description');
            $table->string('file_path', 500)->nullable()->after('file_name');
            $table->bigInteger('file_size')->nullable()->after('file_path');
            $table->string('mime_type', 100)->nullable()->after('file_size');
            $table->string('uploaded_by', 100)->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_attachment', function (Blueprint $table) {
            $table->dropColumn([
                'document_type', 'document_title', 'description',
                'file_name', 'file_path', 'file_size', 'mime_type', 'uploaded_by',
            ]);
        });
    }
};
