<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_challenges', function (Blueprint $table) {
            $table->string('purpose', 32)
                ->default('attendance-time-in')
                ->after('biometric_credential_id');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_challenges', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
