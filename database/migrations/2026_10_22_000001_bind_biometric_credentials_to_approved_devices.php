<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->foreignId('approved_device_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('approved_devices')
                ->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_device_id');
            $table->dropColumn('revoked_at');
        });
    }
};
