<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_biometric_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('face_template')->nullable();
            $table->string('model_version')->default('dlib_face_recognition_v1');
            $table->json('enrollment_metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_biometric_credentials');
    }
};
