<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_designation_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            $table->foreignId('designation_id')->constrained('designations')->cascadeOnDelete();
            $table->unsignedInteger('total_posts')->default(1)->comment('Sanctioned strength for this designation at this office');
            $table->timestamps();

            $table->unique(['office_id', 'designation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_designation_posts');
    }
};
