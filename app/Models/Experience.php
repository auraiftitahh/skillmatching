<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Experience extends Model {
 protected $table='user_experiences';
 protected $fillable=["user_id", "type", "organization", "title", "start_date", "end_date", "description", "job_taxonomy_id"];
 protected function casts(): array {return ['start_date'=>'date','end_date'=>'date'];}
 
}
