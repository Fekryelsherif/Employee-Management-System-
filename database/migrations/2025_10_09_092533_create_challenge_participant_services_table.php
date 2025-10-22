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
         Schema::create('challenge_participant_services', function (Blueprint $table) {
           $table->id();
            $table->foreignId('challenge_participant_id')->constrained('challenge_participants')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->integer('target_quantity')->default(0);
            $table->integer('sold')->default(0);
            $table->timestamps();

            $table->unique(['challenge_participant_id', 'service_id'], 'ch_part_srv_unique');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_participant_services');
    }
};