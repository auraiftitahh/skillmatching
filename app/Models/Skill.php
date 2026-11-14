<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Skill extends Model {
 protected $table='skills';
 protected $fillable=["name", "category", "aliases", "is_active"];
 protected function casts(): array {return ['aliases'=>'array','is_active'=>'boolean'];}
 
}
