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
       Schema::create('target_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained('targets')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
           // $table->integer('target_quantity')->default(0);
            $table->unsignedInteger('progress')->default(0); // نسبة تنفيذ (0-100)
            $table->timestamps();
            $table->unique(['target_id','employee_id'],'idx_target_participant');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_participants');
    }
};
