<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_skills', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('skill_id')->constrained();
            $t->unsignedTinyInteger('self_reported_level')->default(0);
            $t->unsignedTinyInteger('level');
            $t->string('verification_status', 30)->default('self_reported');
            $t->timestamps();
            $t->unique(['user_id', 'skill_id']);
        });
        Schema::create('skill_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('skill_id')->constrained();
            $t->unsignedTinyInteger('level');
            $t->string('source_type', 30);
            $t->unsignedBigInteger('source_id');
            $t->foreignId('confirmed_by')->constrained('users');
            $t->timestamps();
            $t->unique(['source_type', 'source_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_evidence');
        Schema::dropIfExists('user_skills');
    }
};
