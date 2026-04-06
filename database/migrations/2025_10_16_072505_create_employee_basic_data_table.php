<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_basic_data', function (Blueprint $table) {
            $table->id('basic_data_id');
            $table->foreignId('employee_id')->unique()->constrained('employee', 'employee_id')->onDelete('cascade');

            // 🧍 IDENTITAS PRIBADI
            $table->string('title')->nullable(); // Mr, Mrs, dll
            $table->string('nick_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('religion')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('search_term_1')->nullable();
            $table->string('search_term_2')->nullable();
            $table->string('marital_status')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('since_date')->nullable(); // field "Since"

            // 🧾 INFORMASI PENCATATAN
            $table->string('created_by')->nullable(); // HR Admin (S10000)
            $table->timestamp('created_on')->nullable();
            $table->string('last_changed_by')->nullable();
            $table->timestamp('last_changed_on')->nullable();

            // 💼 INFORMASI KEPEGAWAIAN
            $table->string('personnel_area')->nullable();
            $table->string('personnel_subarea')->nullable();
            $table->string('employee_group')->nullable();
            $table->string('employee_subgroup')->nullable();
            $table->string('position')->nullable();
            $table->string('division')->nullable();
            $table->string('department')->nullable();
            $table->string('direct_supervision')->nullable();
            $table->string('manager')->nullable();
            $table->string('authorization_group')->nullable();

            // ⚙️ STATUS ADMINISTRASI
            $table->boolean('block')->default(false);
            $table->boolean('deletion_flag')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_basic_data');
    }
};
