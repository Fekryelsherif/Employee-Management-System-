<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('target_participant_services', function (Blueprint $table) {
            $table->integer('achieved_services')->default(0);
            $table->integer('target_services')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void

    {
        Schema::table('target_participant_services', function (Blueprint $table) {
            $table->dropColumn('achieved_services');
            $table->dropColumn('target_services');
        });
        //
    }
};
