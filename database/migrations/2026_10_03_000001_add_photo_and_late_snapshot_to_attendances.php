<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateDates = DB::table('attendance_settings')
            ->select('effective_date')
            ->groupBy('effective_date')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateDates) {
            throw new RuntimeException(
                'Duplicate attendance setting effective dates exist; resolve them before applying this migration.'
            );
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('status');
            $table->time('late_starting_time_snapshot')->nullable()->after('photo_path');
        });

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unique('effective_date', 'attendance_settings_effective_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropUnique('attendance_settings_effective_date_unique');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'late_starting_time_snapshot']);
        });
    }
};
