<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Certificate extends Model {
 protected $table='certificates';
 protected $fillable=["user_id", "certification_body_id", "training_id", "skill_id", "title", "file_path", "level", "status", "reason", "processed_at"];
 protected function casts(): array {return ['processed_at'=>'datetime'];}
 
}
