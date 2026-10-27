<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_prerequisites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_id')->constrained();
            $t->foreignId('prerequisite_id')->constrained('trainings');
            $t->unique(['training_id', 'prerequisite_id']);
            $t->timestamps();
        });
        Schema::create('catalog_locks', function (Blueprint $t) {
            $t->string('name')->primary();
        });
        DB::table('catalog_locks')->insert(['name' => 'training-prerequisites']);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_locks');
        Schema::dropIfExists('training_prerequisites');
    }
};
