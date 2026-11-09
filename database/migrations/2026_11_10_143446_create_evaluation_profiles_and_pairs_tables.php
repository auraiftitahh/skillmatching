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
        Schema::create('evaluation_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('group', 100)->nullable();
            $table->json('skills_json');
            $table->timestamps();
        });
        Schema::create('evaluation_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_profile_id')->constrained();
            $table->foreignId('job_vacancy_id')->constrained();
            $table->foreignId('weight_configuration_id')->constrained();
            $table->foreignId('reviewed_by')->constrained('users');
            $table->string('job_title', 190);
            $table->string('company', 190);
            $table->decimal('system_score', 5, 2);
            $table->unsignedTinyInteger('expert_rating');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_pairs');
        Schema::dropIfExists('evaluation_profiles');
    }
};
