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
        Schema::create('recommendation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_vacancy_id')->constrained();
            $table->foreignId('weight_configuration_id')->constrained();
            $table->string('rating', 20);
            $table->text('comment')->nullable();
            $table->unique(['user_id', 'job_vacancy_id', 'weight_configuration_id'], 'feedback_user_job_version_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_feedback');
    }
};
