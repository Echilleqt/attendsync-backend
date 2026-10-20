<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approved_devices')) {
            Schema::create('approved_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('device_uuid')->index();
                $table->string('device_name')->nullable();
                $table->string('device_model')->nullable();
                $table->string('device_os')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'revoked'])->default('pending');
                $table->boolean('verified_via_face')->default(false);
                $table->timestamp('registered_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['employee_id', 'device_uuid']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_devices');
    }
};
