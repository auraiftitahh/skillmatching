<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserSkill extends Model {
 protected $table='user_skills';
 protected $fillable=["user_id", "skill_id", "level", "verification_status"];
 
 
}
