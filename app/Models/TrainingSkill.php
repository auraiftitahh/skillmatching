<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrainingSkill extends Model { protected $fillable=['training_id','skill_id','target_level']; public function skill(){return $this->belongsTo(Skill::class);} }
