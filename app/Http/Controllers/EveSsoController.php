<?php
namespace App\Http\Controllers;
use App\Models\EveCharacter;use App\Models\User;
use Illuminate\Http\RedirectResponse;use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Http;use Illuminate\Support\Str;
class EveSsoController extends Controller {
 public function redirect():RedirectResponse {
  abort_unless(config('services.eve.client_id') && config('services.eve.client_secret') && config('services.eve.callback_url'),503,'EVE SSO is not configured.');
  $state=Str::random(48);session(['eve_sso_state'=>$state]);
  $query=http_build_query(['response_type'=>'code','redirect_uri'=>config('services.eve.callback_url'),'client_id'=>config('services.eve.client_id'),'scope'=>implode(' ',config('services.eve.scopes',['publicData'])),'state'=>$state]);
  return redirect('https://login.eveonline.com/v2/oauth/authorize/?'.$query);
 }
 public function callback(Request $request):RedirectResponse {
  $expected=(string)session('eve_sso_state');$provided=(string)$request->query('state');
  abort_unless($request->filled('code') && $expected!=='' && hash_equals($expected,$provided),419,'Invalid EVE SSO state.');
  $request->session()->forget('eve_sso_state');
  $tokens=Http::asForm()->withBasicAuth((string)config('services.eve.client_id'),(string)config('services.eve.client_secret'))
   ->post('https://login.eveonline.com/v2/oauth/token',['grant_type'=>'authorization_code','code'=>$request->query('code')])->throw()->json();
  $verify=Http::withToken($tokens['access_token'])->get('https://login.eveonline.com/oauth/verify')->throw()->json();
  $id=(int)($verify['CharacterID']??0);abort_unless($id>0,403,'EVE did not return a valid character identity.');
  $identity=Http::get("https://esi.evetech.net/latest/characters/{$id}/")->throw()->json();
  $user=DB::transaction(function()use($id,$verify,$tokens,$identity){
   $character=EveCharacter::where('eve_character_id',$id)->lockForUpdate()->first();
   $user=Auth::user() ?? $character?->user;\n   if($character && $user && $character->user_id && $character->user_id !== $user->id) abort(409,'This EVE character is already linked to another Buyback account.');
   if(!$user)$user=User::create(['name'=>$identity['name']??'EVE Pilot','role'=>in_array($id,config('services.eve.admin_character_ids',[]),true)?'admin':'member','active'=>true]);
   if(in_array($id,config('services.eve.admin_character_ids',[]),true)&&$user->role!=='admin')$user->forceFill(['role'=>'admin'])->save();
   $character??=new EveCharacter(['eve_character_id'=>$id]);
   $character->fill(['user_id'=>$user->id,'name'=>$identity['name']??'EVE Pilot','owner_hash'=>$verify['CharacterOwnerHash']??null,
    'access_token'=>$tokens['access_token'],'refresh_token'=>$tokens['refresh_token']??null,
    'token_expires_at'=>now()->addSeconds((int)($tokens['expires_in']??1200)),'scopes'=>$verify['Scopes']??[],
    'last_authenticated_at'=>now()])->save();
   $user->forceFill(['name'=>$identity['name']??$user->name,'last_login_at'=>now()])->save();return $user;
  });
  abort_unless($user->active,403,'This account has been disabled.');
  Auth::login($user,true);$request->session()->regenerate();return redirect()->intended(route('dashboard'));
 }
 public function logout(Request $request):RedirectResponse {Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('home');}
}