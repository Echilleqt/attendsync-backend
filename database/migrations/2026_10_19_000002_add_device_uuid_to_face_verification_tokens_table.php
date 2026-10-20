<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('face_verification_tokens')) {
            Schema::table('face_verification_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('face_verification_tokens', 'device_uuid')) {
                    $table->string('device_uuid')->nullable()->after('employee_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('face_verification_tokens')) {
            Schema::table('face_verification_tokens', function (Blueprint $table) {
                if (Schema::hasColumn('face_verification_tokens', 'device_uuid')) {
                    $table->dropColumn('device_uuid');
                }
            });
        }
    }
};
