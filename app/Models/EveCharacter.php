<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EveCharacter extends Model {
 protected $fillable=['user_id','eve_character_id','name','owner_hash','last_authenticated_at','access_token','refresh_token','token_expires_at','scopes'];
 protected $hidden=['access_token','refresh_token','owner_hash'];
 protected function casts():array{return ['access_token'=>'encrypted','refresh_token'=>'encrypted','scopes'=>'array','token_expires_at'=>'datetime','last_authenticated_at'=>'datetime'];}
 public function user(){return $this->belongsTo(User::class);}
}