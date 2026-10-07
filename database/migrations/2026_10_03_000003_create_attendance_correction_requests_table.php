<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('request_type', 40);
            $table->time('requested_time_in')->nullable();
            $table->time('requested_time_out')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->text('admin_review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('original_values')->nullable();
            $table->json('approved_values')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'attendance_date', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
