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
        \DB::statement("ALTER TABLE documents MODIFY document_type VARCHAR(100) NOT NULL");
    }

    public function down(): void
    {
        \DB::statement("ALTER TABLE documents MODIFY document_type ENUM('BAST/BAPP','Contract','Justification','PR/PO','Others') NOT NULL");
    }
};
