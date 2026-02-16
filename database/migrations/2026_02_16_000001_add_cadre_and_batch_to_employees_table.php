<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('cadre_type', 20)->nullable()->after('status')->comment('cadre or non_cadre');
            $table->string('batch_no', 50)->nullable()->after('cadre_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['cadre_type', 'batch_no']);
        });
    }
};
