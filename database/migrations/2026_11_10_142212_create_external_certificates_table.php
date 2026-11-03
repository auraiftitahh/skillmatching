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
        Schema::create('external_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('certification_body_id')->nullable()->constrained();
            $table->foreignId('skill_id')->constrained();
            $table->string('title', 190);
            $table->string('issuer_name', 190);
            $table->unsignedTinyInteger('level');
            $table->string('file_path');
            $table->string('status', 30);
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->index(['certification_body_id', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_certificates');
    }
};
