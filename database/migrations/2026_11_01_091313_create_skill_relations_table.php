<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_relations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('skill_id')->constrained();
            $t->foreignId('related_skill_id')->constrained('skills');
            $t->text('reason');
            $t->timestamps();
            $t->unique(['skill_id', 'related_skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_relations');
    }
};
