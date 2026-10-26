<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('certification_body_id')->constrained();
            $t->string('title', 190);
            $t->text('description');
            $t->string('mode', 20);
            $t->unsignedInteger('duration_hours');
            $t->unsignedSmallInteger('weekly_hours');
            $t->decimal('cost', 12, 2);
            $t->string('source_url', 1000);
            $t->string('status', 20)->default('active')->index();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('training_skills', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_id')->constrained()->cascadeOnDelete();
            $t->foreignId('skill_id')->constrained();
            $t->unsignedTinyInteger('target_level');
            $t->unique(['training_id', 'skill_id']);
            $t->timestamps();
        });
        Schema::create('training_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('certification_body_id')->constrained();
            $t->foreignId('training_id')->nullable()->constrained();
            $t->unsignedInteger('base_version')->nullable();
            $t->string('status', 20)->default('draft')->index();
            $t->json('payload');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_submissions');
        Schema::dropIfExists('training_skills');
        Schema::dropIfExists('trainings');
    }
};
