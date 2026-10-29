<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_configurations', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('version')->unique();
            $t->unsignedTinyInteger('required_weight');
            $t->unsignedTinyInteger('preferred_weight');
            $t->decimal('related_factor', 4, 3);
            $t->decimal('threshold', 5, 2);
            $t->text('reason');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('weight_configuration_state', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->foreignId('active_configuration_id')->constrained('weight_configurations');
            $t->timestamps();
        });
        $id = DB::table('weight_configurations')->insertGetId(['version' => 1, 'required_weight' => 3, 'preferred_weight' => 1, 'related_factor' => 0.5, 'threshold' => 40, 'reason' => 'Konfigurasi awal sistem.', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('weight_configuration_state')->insert(['id' => 1, 'active_configuration_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_configuration_state');
        Schema::dropIfExists('weight_configurations');
    }
};
