<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lapisan sunting User Guide.
 *
 * Topik bawaan tetap berupa berkas di resources/guide. Satu baris di tabel ini
 * adalah salinan penuh satu topik untuk satu bahasa: menimpa topik berkas yang
 * key-nya sama, atau menjadi topik baru bila key-nya tidak ada di berkas.
 * Menghapus baris = kembali ke versi berkas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guide_topics', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('locale', 5);
            $table->string('chapter', 50);
            $table->unsignedInteger('sort_order')->default(100);
            $table->string('title', 200);
            $table->string('summary', 500)->default('');
            $table->json('requires')->nullable();
            $table->longText('body');
            $table->date('updated_on')->nullable();
            // md5 isi berkas saat topik pertama kali disunting; null untuk topik baru.
            // Dipakai untuk memberi tahu admin bila berkas bawaan berubah setelah itu.
            $table->char('base_hash', 32)->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guide_topics');
    }
};
