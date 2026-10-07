<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->date('attendance_date');

            $table->dateTime('time_in')->nullable();

            $table->dateTime('time_out')->nullable();

            $table->enum('status', [
                'present',
                'late',
                'absent',
                'on_leave',
            ])->default('present');

            $table->timestamps();

            $table->unique([
                'employee_id',
                'attendance_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};