<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approved_devices')) {
            // Update enum to include 'rejected' in MySQL
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE approved_devices MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'revoked') NOT NULL DEFAULT 'pending'");
            }

            // Add public_key column if it does not already exist
            Schema::table('approved_devices', function (Blueprint $table) {
                if (!Schema::hasColumn('approved_devices', 'public_key')) {
                    $table->text('public_key')->nullable()->after('device_os');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approved_devices')) {
            Schema::table('approved_devices', function (Blueprint $table) {
                if (Schema::hasColumn('approved_devices', 'public_key')) {
                    $table->dropColumn('public_key');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE approved_devices MODIFY COLUMN status ENUM('pending', 'approved', 'revoked') NOT NULL DEFAULT 'pending'");
            }
        }
    }
};
