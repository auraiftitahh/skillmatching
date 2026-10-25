<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_body_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('body_name');
            $table->string('owner_email');
            $table->string('actor_name');
            $table->enum('action', ['approve', 'reject', 'activate', 'deactivate']);
            $table->enum('before_status', ['pending', 'active', 'rejected', 'disabled']);
            $table->enum('after_status', ['pending', 'active', 'rejected', 'disabled']);
            $table->text('reason');
            $table->timestamps();
            $table->index(['certification_body_id', 'created_at'], 'institution_changes_body_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_status_changes');
    }
};
