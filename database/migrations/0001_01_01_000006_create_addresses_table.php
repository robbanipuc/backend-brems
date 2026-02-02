<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('type', ['present', 'permanent']);
            $table->string('division', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('upazila', 100)->nullable();
            $table->string('post_office', 100)->nullable();
            $table->string('house_no', 100)->nullable();
            $table->string('village_road')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};