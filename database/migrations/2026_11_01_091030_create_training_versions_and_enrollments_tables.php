<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_id')->constrained();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['training_id', 'version']);
        });
        Schema::create('training_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('training_id')->constrained();
            $t->unsignedInteger('training_version');
            $t->json('outcomes_snapshot')->nullable();
            $t->string('status', 30)->default('planned')->index();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('users');
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'training_id']);
        });
        DB::table('trainings')->orderBy('id')->chunkById(100, function ($trainings): void {
            foreach ($trainings as $training) {
                $snapshot = (array) $training;
                $snapshot['outcomes'] = DB::table('training_skills')->where('training_id', $training->id)->get(['skill_id', 'target_level'])->map(fn ($r) => (array) $r)->all();
                DB::table('training_versions')->insert(['training_id' => $training->id, 'version' => $training->version, 'snapshot' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_versions');
    }
};
