<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WeightConfiguration extends Model {
 protected $table='weight_configurations';
 protected $fillable=["version", "required_weight", "preferred_weight", "related_factor", "threshold", "reason", "created_by", "is_active"];
 protected function casts(): array {return ['is_active'=>'boolean'];}
 
}
