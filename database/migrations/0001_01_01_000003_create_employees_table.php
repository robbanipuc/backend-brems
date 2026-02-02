<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys (Denormalized for Performance)
            $table->foreignId('designation_id')->constrained('designations');
            $table->foreignId('current_office_id')->constrained('offices');
            
            // Basic Identity
            $table->string('first_name');
            $table->string('last_name');
            $table->string('name_bn')->nullable();
            $table->string('nid_number')->unique();
            $table->string('phone')->nullable();
            
            // Bio Data
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('height', 20)->nullable();
            
            // Documents
            $table->string('passport', 50)->nullable();
            $table->string('birth_reg', 50)->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('nid_file_path')->nullable();
            $table->string('birth_file_path')->nullable();
            
            // Employment Status
            $table->date('joining_date')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->enum('status', ['active', 'released', 'retired'])->default('active');
            $table->date('released_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};