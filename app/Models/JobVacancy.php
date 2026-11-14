<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JobVacancy extends Model {
 protected $table='job_vacancies';
 protected $fillable=["title", "company", "description", "location", "employment_type", "salary_min", "source", "source_url", "fetched_at", "expires_at", "status"];
 protected function casts(): array {return ['fetched_at'=>'date','expires_at'=>'date'];}
 public function requirements(){return $this->hasMany(JobRequiredSkill::class,'job_vacancy_id');}
}
