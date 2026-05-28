<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_sounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('filename')->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Seed the existing sound as the default entry
        DB::table('notification_sounds')->insert([
            'name'       => 'Software Ping',
            'filename'   => 'mixkit-software-interface-back-2575.wav',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_sounds');
    }
};
