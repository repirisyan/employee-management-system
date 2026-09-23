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
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('work_start_time', 5)->default('08:00')->after('logo');
            $table->string('work_end_time', 5)->default('17:00')->after('work_start_time');
            $table->unsignedInteger('late_tolerance_minutes')->default(0)->after('work_end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['work_start_time', 'work_end_time', 'late_tolerance_minutes']);
        });
    }
};
