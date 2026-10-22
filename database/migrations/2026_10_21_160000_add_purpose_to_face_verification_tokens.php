<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('face_verification_tokens')
            && !Schema::hasColumn('face_verification_tokens', 'purpose')) {
            Schema::table('face_verification_tokens', function (Blueprint $table) {
                $table->string('purpose', 32)->default('attendance')->after('device_uuid');
                $table->index(['employee_id', 'purpose', 'expires_at'], 'face_tokens_purpose_expiry_idx');
            });
            DB::table('face_verification_tokens')
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('face_verification_tokens')
            && Schema::hasColumn('face_verification_tokens', 'purpose')) {
            Schema::table('face_verification_tokens', function (Blueprint $table) {
                $table->dropIndex('face_tokens_purpose_expiry_idx');
                $table->dropColumn('purpose');
            });
        }
    }
};
