<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->string('name');
                $table->string('description', 1000)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('employee_schedules')) {
            Schema::create('employee_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
                $table->date('effective_date');
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('notes', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'position_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('position_id')->nullable()->after('position')->constrained('positions')->nullOnDelete();
            });
        }

        if (Schema::hasTable('attendances') && !Schema::hasColumn('attendances', 'schedule_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->foreignId('schedule_id')->nullable()->after('employee_id')->constrained('schedules')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'schedule_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('schedule_id');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'position_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('position_id');
            });
        }

        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('positions');
    }
};
