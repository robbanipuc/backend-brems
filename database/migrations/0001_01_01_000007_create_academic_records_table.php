<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('exam_name', [
                'SSC / Dakhil',
                'HSC / Alim',
                'Bachelor (Honors)',
                'Masters',
                'Diploma'
            ]);
            $table->string('institute');
            $table->string('passing_year', 10);
            $table->string('result', 50);
            $table->string('certificate_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};