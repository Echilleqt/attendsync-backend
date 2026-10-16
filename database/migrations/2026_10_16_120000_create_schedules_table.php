<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->time('am_time_in')->default('08:00:00');
                $table->time('am_time_out')->default('12:00:00');
                $table->time('pm_time_in')->default('13:00:00');
                $table->time('pm_time_out')->default('17:00:00');
                $table->time('late_threshold_am')->default('08:00:00');
                $table->time('late_threshold_pm')->default('13:00:00');
                $table->integer('grace_period_minutes')->default(0);
                $table->json('work_days')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
