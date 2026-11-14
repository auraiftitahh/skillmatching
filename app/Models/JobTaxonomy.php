<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JobTaxonomy extends Model {
 protected $table='jobs_taxonomy';
 protected $fillable=["name", "is_active"];
 protected function casts(): array {return ['is_active'=>'boolean'];}
 
}
