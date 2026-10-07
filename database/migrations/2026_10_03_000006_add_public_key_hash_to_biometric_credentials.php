<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->char('public_key_hash', 64)->nullable()->after('public_key_pem');
        });

        DB::table('biometric_credentials')
            ->select(['id', 'public_key_pem'])
            ->orderBy('id')
            ->chunkById(100, function ($credentials): void {
                foreach ($credentials as $credential) {
                    $publicKey = openssl_pkey_get_public($credential->public_key_pem);
                    $details = $publicKey ? openssl_pkey_get_details($publicKey) : false;

                    if ($details && isset($details['key'])) {
                        DB::table('biometric_credentials')
                            ->where('id', $credential->id)
                            ->update([
                                'public_key_hash' => hash('sha256', $details['key']),
                            ]);
                    }
                }
            });

        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->unique('public_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_credentials', function (Blueprint $table) {
            $table->dropUnique(['public_key_hash']);
            $table->dropColumn('public_key_hash');
        });
    }
};
