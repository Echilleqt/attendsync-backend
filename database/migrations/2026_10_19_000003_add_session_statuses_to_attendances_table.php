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
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'am_status')) {
                $table->string('am_status', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('attendances', 'pm_status')) {
                $table->string('pm_status', 30)->nullable()->after('am_status');
            }
            if (Schema::hasColumn('attendances', 'status')) {
                $table->string('status', 30)->default('present')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'pm_status')) {
                $table->dropColumn('pm_status');
            }
            if (Schema::hasColumn('attendances', 'am_status')) {
                $table->dropColumn('am_status');
            }
        });
    }
};
