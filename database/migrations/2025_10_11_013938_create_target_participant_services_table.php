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
        Schema::create('target_participant_services', function (Blueprint $table) {
          $table->id();
            $table->foreignId('target_participant_id')->constrained('target_participants')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->integer('target_quantity')->default(0);
            $table->integer('sold')->default(0); // ما باعه هذا الموظف من هذه الخدمة في هذا التارجت
            $table->timestamps();
            $table->unique(['target_participant_id','service_id'],'idx_participant_service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_participant_services');
    }
};
