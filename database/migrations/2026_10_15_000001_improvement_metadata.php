<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',fn(Blueprint $t)=>$t->string('domicile',150)->nullable());
  foreach(['feedback','evaluation_pairs'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->foreignId('weight_configuration_id')->nullable()->constrained('weight_configurations')->nullOnDelete());
  Schema::create('vacancy_bookmarks',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('job_vacancy_id')->constrained()->cascadeOnDelete();$t->timestamps();$t->unique(['user_id','job_vacancy_id']);});
 }
 public function down():void {
  Schema::dropIfExists('vacancy_bookmarks');
  foreach(['feedback','evaluation_pairs'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->dropConstrainedForeignId('weight_configuration_id'));
  Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('domicile'));
 }
};
