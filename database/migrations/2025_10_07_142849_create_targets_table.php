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
        Schema::create('targets', function (Blueprint $table) {
            $table->id();

            // فرع التارجت
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');

            // الموظف (لو التارجت خاص بموظف)
            $table->foreignId('employee_id')->nullable()->constrained('users')->onDelete('cascade');

            // عنوان التارجت
            $table->string('title');

            // فترة التارجت
            $table->date('start_date');
            $table->date('end_date');

            // ملاحظات اختيارية
            $table->text('notes')->nullable();

            // الحالة
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');

            // لو التارجت ده معمول كنسخة (recreate) من تارجت رئيسي
            $table->unsignedBigInteger('recreated_from_target_id')->nullable();

            // المبلغ المطلوب تحقيقه للتارجت المعاد إنشاؤه
            $table->decimal('recreated_goal_amount', 10, 2)->nullable();

            // الخدمات المطلوبة والمحققة
            $table->integer('target_services')->default(0);
            $table->integer('achieved_services')->default(0);

            // نسبة التقدم progress
            $table->decimal('progress', 5, 2)->default(0);

            $table->timestamps();

            // ربط التارجت الفرعي بالرئيسي
            $table->foreign('recreated_from_target_id')->references('id')->on('targets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
