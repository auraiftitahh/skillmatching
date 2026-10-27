<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_taxonomies', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('normalized_name', 150)->unique();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('job_taxonomy_skill', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_taxonomy_id')->constrained();
            $t->foreignId('skill_id')->constrained();
            $t->timestamps();
            $t->unique(['job_taxonomy_id', 'skill_id']);
        });
        Schema::table('user_experiences', function (Blueprint $t) {
            $t->foreignId('job_taxonomy_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_experiences', fn (Blueprint $t) => $t->dropConstrainedForeignId('job_taxonomy_id'));
        Schema::dropIfExists('job_taxonomy_skill');
        Schema::dropIfExists('job_taxonomies');
    }
};
