<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
