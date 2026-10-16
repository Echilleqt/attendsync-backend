<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_restriction_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('authorized_ssid')->nullable();
            $table->text('authorized_bssids')->nullable();
            $table->string('network_description')->nullable();
            $table->string('verification_method')->default('client_assisted');
            $table->text('trusted_ip_ranges')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_restriction_settings');
    }
};
