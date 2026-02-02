<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->unsignedBigInteger('from_office_id')->nullable(); // NULL for first posting
            $table->foreignId('to_office_id')->constrained('offices');
            $table->date('transfer_date');
            $table->string('order_number', 100)->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->foreign('from_office_id')
                  ->references('id')
                  ->on('offices')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_histories');
    }
};