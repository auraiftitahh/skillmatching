<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JobRequiredSkill extends Model { protected $fillable=['job_vacancy_id','skill_id','minimum_level','importance','weight']; public function skill(){return $this->belongsTo(Skill::class);} }
