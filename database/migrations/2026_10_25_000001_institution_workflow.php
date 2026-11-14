<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('training_enrollments',fn(Blueprint $t)=>$t->json('outcomes_snapshot')->nullable());
        Schema::create('skill_name_registry', function (Blueprint $t) {
            $t->id(); $t->string('normalized_name',190)->unique();
            $t->foreignId('skill_id')->constrained('skills');
        });
        foreach (DB::table('skills')->orderBy('id')->get() as $skill) {
            $name=mb_strtolower(preg_replace('/\s+/u',' ',trim($skill->name)));
            DB::table('skill_name_registry')->insertOrIgnore(['normalized_name'=>$name,'skill_id'=>$skill->id]);
        }
        Schema::create('training_submissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('certification_body_id')->constrained();
            $t->foreignId('training_id')->nullable()->constrained()->nullOnDelete();
            $t->json('payload'); $t->string('status')->default('draft')->index();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
        Schema::create('external_certificates', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained();
            $t->foreignId('certification_body_id')->nullable()->constrained();
            $t->string('issuer_name'); $t->string('title'); $t->string('file_path');
            $t->foreignId('skill_id')->constrained(); $t->unsignedTinyInteger('level');
            $t->string('status')->default('unverified')->index(); $t->text('reason')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users');
            $t->timestamp('processed_at')->nullable(); $t->timestamps();
        });
        Schema::create('skill_evidence', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->foreignId('skill_id')->constrained();
            $t->unsignedTinyInteger('level'); $t->string('source_type'); $t->unsignedBigInteger('source_id');
            $t->foreignId('confirmed_by')->nullable()->constrained('users'); $t->timestamps();
            $t->unique(['source_type','source_id','skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_evidence'); Schema::dropIfExists('external_certificates');
        Schema::dropIfExists('training_submissions'); Schema::dropIfExists('skill_name_registry');
        Schema::table('training_enrollments',fn(Blueprint $t)=>$t->dropColumn('outcomes_snapshot'));
    }
};
