<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('relation', ['father', 'mother', 'spouse', 'child']);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('nid', 50)->nullable();
            $table->date('dob')->nullable();
            $table->string('occupation')->nullable();
            $table->boolean('is_alive')->default(true);
            $table->boolean('is_active_marriage')->nullable(); // For spouses only
            $table->string('birth_certificate_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};