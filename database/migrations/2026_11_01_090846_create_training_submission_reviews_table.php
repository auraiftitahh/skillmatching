<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_submission_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_submission_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('decision', 20);
            $t->text('note');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_submission_reviews');
    }
};
