<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','email','password','role','is_active','email_verified_at','employment_status','phone','domicile'];
 // Samakan nilai atribut model yang baru dibuat dengan DEFAULT TRUE pada kolom users.is_active.
 // Nilai eksplisit false tetap dihormati untuk akun yang dinonaktifkan admin.
 protected $attributes = ['is_active' => true];
 protected $hidden=['password','remember_token'];
 protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean']; }
 public function certificationBody(){ return $this->hasOne(CertificationBody::class); }
 public function userSkills(){ return $this->hasMany(UserSkill::class); }
 public function experiences(){ return $this->hasMany(Experience::class); }
}
