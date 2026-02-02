<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location');
            $table->timestamps();

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('offices')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};