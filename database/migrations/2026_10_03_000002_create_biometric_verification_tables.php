<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id')->unique();
            $table->enum('verification_method', ['fingerprint', 'face']);
            $table->text('public_key_pem');
            $table->string('device_name', 100);
            $table->timestamps();
        });

        Schema::create('biometric_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_credential_id')
                ->constrained('biometric_credentials')
                ->cascadeOnDelete();
            $table->string('nonce', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'expires_at']);
        });

        Schema::create('biometric_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_credential_id')
                ->constrained('biometric_credentials')
                ->cascadeOnDelete();
            $table->string('verification_method', 20);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_verifications');
        Schema::dropIfExists('biometric_challenges');
        Schema::dropIfExists('biometric_credentials');
    }
};
