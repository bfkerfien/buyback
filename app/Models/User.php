<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','email','role','active','last_login_at'];
 protected $hidden=['remember_token'];
 protected function casts():array{return ['active'=>'boolean','last_login_at'=>'datetime'];}
 public function characters(){return $this->hasMany(EveCharacter::class);}
 public function isAdmin():bool{return $this->role==='admin' && $this->active;}
}