<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\View\View;
class DashboardController extends Controller {
 public function index(Request $request):View {
  $user=$request->user();$admin=$user->isAdmin();$characterIds=$user->characters()->pluck('id');
  $jobs=DB::table('runner_jobs')->when(!$admin,fn($q)=>$q->where(function($w)use($user){$w->where('status','open')->orWhere('assigned_user_id',$user->id);}))->orderByDesc('created_at')->limit(50)->get();
  $requests=DB::table('financier_requests as fr')->leftJoin('eve_characters as ec','ec.id','=','fr.financier_character_id')->select('fr.*','ec.name as character_name')->when(!$admin,fn($q)=>$q->where('fr.user_id',$user->id))->orderByDesc('fr.created_at')->limit(50)->get();
  $quotes=DB::table('appraisal_quotes')->where('created_by',$user->id)->orderByDesc('created_at')->limit(20)->get();
  $balance=(float)DB::table('ledger_entries')->whereIn('eve_character_id',$characterIds)->sum('amount_isk');
  $adminMetrics=$admin?['open_jobs'=>DB::table('runner_jobs')->where('status','open')->count(),'pending_finance'=>DB::table('financier_requests')->where('status','pending')->count(),'users'=>DB::table('users')->count()]:[];\n  $payoutRules=$admin?DB::table('appraisal_payout_rules')->orderBy('category_name')->get():collect();\n  $eveCategories=$admin?DB::table('eve_item_groups')->select('category_id','category_name')->distinct()->orderBy('category_name')->get():collect();
  return view('dashboard',compact('user','jobs','requests','quotes','balance','admin','adminMetrics','payoutRules','eveCategories'));
 }
}