<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificationBody extends Model {
 protected $table='certification_bodies';
 protected $fillable=["user_id", "name", "contact", "document_path", "status", "verified_at"];
 protected function casts(): array {return ['verified_at'=>'datetime'];}
 
}
