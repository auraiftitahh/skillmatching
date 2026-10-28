<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $t) {
            $t->id();
            $t->string('title', 190);
            $t->string('company', 190);
            $t->string('location', 190);
            $t->text('description');
            $t->string('employment_type', 30)->default('permanent');
            $t->string('source_url', 1000);
            $t->string('source_name', 190)->nullable();
            $t->date('fetched_at');
            $t->date('expires_at')->nullable();
            $t->string('status', 20)->default('draft')->index();
            $t->timestamps();
        });
        Schema::create('job_required_skills', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_vacancy_id')->constrained();
            $t->foreignId('skill_id')->constrained();
            $t->unsignedTinyInteger('minimum_level');
            $t->string('importance', 20);
            $t->unsignedTinyInteger('weight');
            $t->boolean('is_override')->default(false);
            $t->timestamps();
            $t->unique(['job_vacancy_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_required_skills');
        Schema::dropIfExists('job_vacancies');
    }
};
