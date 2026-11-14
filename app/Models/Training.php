<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Training extends Model {
 protected $table='trainings';
 protected $fillable=["title", "provider", "description", "mode", "duration_hours", "weekly_hours", "cost", "source", "source_url", "fetched_at", "status", "certification_body_id"];
 protected function casts(): array {return ['cost'=>'decimal:2','fetched_at'=>'date'];}
 public function taught(){return $this->hasMany(TrainingSkill::class);}
}
