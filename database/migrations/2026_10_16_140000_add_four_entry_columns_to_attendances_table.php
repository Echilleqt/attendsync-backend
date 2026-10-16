<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'am_time_in')) {
                $table->dateTime('am_time_in')->nullable()->after('time_in');
            }
            if (!Schema::hasColumn('attendances', 'am_time_out')) {
                $table->dateTime('am_time_out')->nullable()->after('am_time_in');
            }
            if (!Schema::hasColumn('attendances', 'pm_time_in')) {
                $table->dateTime('pm_time_in')->nullable()->after('am_time_out');
            }
            if (!Schema::hasColumn('attendances', 'pm_time_out')) {
                $table->dateTime('pm_time_out')->nullable()->after('pm_time_in');
            }
            if (!Schema::hasColumn('attendances', 'am_time_in_photo_path')) {
                $table->string('am_time_in_photo_path')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'am_time_out_photo_path')) {
                $table->string('am_time_out_photo_path')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'pm_time_in_photo_path')) {
                $table->string('pm_time_in_photo_path')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'pm_time_out_photo_path')) {
                $table->string('pm_time_out_photo_path')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'am_time_in_verification_method')) {
                $table->string('am_time_in_verification_method', 30)->nullable();
            }
            if (!Schema::hasColumn('attendances', 'am_time_out_verification_method')) {
                $table->string('am_time_out_verification_method', 30)->nullable();
            }
            if (!Schema::hasColumn('attendances', 'pm_time_in_verification_method')) {
                $table->string('pm_time_in_verification_method', 30)->nullable();
            }
            if (!Schema::hasColumn('attendances', 'pm_time_out_verification_method')) {
                $table->string('pm_time_out_verification_method', 30)->nullable();
            }
            if (!Schema::hasColumn('attendances', 'undertime_minutes')) {
                $table->integer('undertime_minutes')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $columns = [
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'am_time_in_photo_path',
                'am_time_out_photo_path',
                'pm_time_in_photo_path',
                'pm_time_out_photo_path',
                'am_time_in_verification_method',
                'am_time_out_verification_method',
                'pm_time_in_verification_method',
                'pm_time_out_verification_method',
                'undertime_minutes',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
