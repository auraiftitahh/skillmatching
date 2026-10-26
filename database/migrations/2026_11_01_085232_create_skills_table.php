<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('skill_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('normalized_name', 150)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_names');
        Schema::dropIfExists('skills');
    }
};
