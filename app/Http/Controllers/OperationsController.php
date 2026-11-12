<?php
namespace App\Http\Controllers;
use Illuminate\Http\RedirectResponse;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;
class OperationsController extends Controller {
 public function createJob(Request $r):RedirectResponse {
  abort_unless($r->user()->isAdmin(),403);$d=$r->validate(['origin'=>'required|string|max:100','destination'=>'required|string|max:100','reward_isk'=>'required|numeric|min:0.01|max:1000000000000','appraisal_quote_id'=>'nullable|integer|exists:appraisal_quotes,id','notes'=>'nullable|string|max:2000']);
  $id=DB::table('runner_jobs')->insertGetId($d+['created_by'=>$r->user()->id,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);$this->audit($r,'runner_job.created','runner_job',$id,$d);return back()->with('status','Runner job published.');
 }
 public function acceptJob(Request $r,int $job):RedirectResponse {
  $character=$r->user()->characters()->firstOrFail();$n=DB::table('runner_jobs')->where('id',$job)->where('status','open')->whereNull('assigned_user_id')->update(['status'=>'accepted','runner_character_id'=>$character->id,'assigned_user_id'=>$r->user()->id,'accepted_at'=>now(),'updated_at'=>now()]);
  abort_unless($n===1,409,'This job is no longer available.');$this->audit($r,'runner_job.accepted','runner_job',$job,['character_id'=>$character->id]);return back()->with('status','Runner job accepted.');
 }
 public function deliverJob(Request $r,int $job):RedirectResponse {
  $row=DB::table('runner_jobs')->where('id',$job)->first();abort_unless($row&&($row->assigned_user_id===$r->user()->id||$r->user()->isAdmin()),403);abort_unless($row->status==='accepted',409,'Only accepted jobs can be delivered.');
  DB::transaction(function()use($row,$r,$job){$changed=DB::table('runner_jobs')->where('id',$job)->where('status','accepted')->update(['status'=>'delivered','delivered_at'=>now(),'updated_at'=>now()]);abort_unless($changed===1,409,'Job status changed before delivery could be recorded.');
   if((float)$row->reward_isk>0&&$row->runner_character_id)DB::table('ledger_entries')->insertOrIgnore(['eve_character_id'=>$row->runner_character_id,'entry_type'=>'runner_reward','source_type'=>'runner_job','source_id'=>$job,'amount_isk'=>$row->reward_isk,'idempotency_key'=>'runner-job-'.$job,'metadata'=>json_encode(['status'=>'delivered']),'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   $this->audit($r,'runner_job.delivered','runner_job',$job,[]);});
  return back()->with('status','Delivery recorded. The reward is in the internal ledger pending manual ISK payment.');
 }
 public function createFinanceRequest(Request $r):RedirectResponse {
  $character=$r->user()->characters()->firstOrFail();$d=$r->validate(['request_type'=>'required|in:funding,withdrawal','amount_isk'=>'required|numeric|min:1|max:1000000000000','external_reference'=>'nullable|string|max:190','notes'=>'nullable|string|max:2000']);
  $id=DB::table('financier_requests')->insertGetId($d+['user_id'=>$r->user()->id,'financier_character_id'=>$character->id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);$this->audit($r,'financier_request.created','financier_request',$id,$d);return back()->with('status','Finance request submitted for admin review.');
 }
 public function reviewFinanceRequest(Request $r,int $id):RedirectResponse {
  abort_unless($r->user()->isAdmin(),403);$d=$r->validate(['decision'=>'required|in:approved,rejected','notes'=>'nullable|string|max:2000']);$row=DB::table('financier_requests')->where('id',$id)->first();abort_unless($row&&$row->status==='pending',409,'Request already reviewed.');
  DB::transaction(function()use($r,$row,$d,$id){if($d['decision']==='approved' && $row->request_type==='withdrawal' && $row->financier_character_id){$balance=(float)DB::table('ledger_entries')->where('eve_character_id',$row->financier_character_id)->lockForUpdate()->get(['amount_isk'])->sum('amount_isk');abort_unless($balance >= (float)$row->amount_isk,422,'Insufficient internal ledger balance for this withdrawal.');}$n=DB::table('financier_requests')->where('id',$id)->where('status','pending')->update(['status'=>$d['decision'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now(),'notes'=>$d['notes']??null,'updated_at'=>now()]);abort_unless($n===1,409);
   if($d['decision']==='approved'&&$row->financier_character_id)DB::table('ledger_entries')->insertOrIgnore(['eve_character_id'=>$row->financier_character_id,'entry_type'=>'finance_'.$row->request_type,'source_type'=>'financier_request','source_id'=>$id,'amount_isk'=>$row->request_type==='withdrawal'?-abs($row->amount_isk):abs($row->amount_isk),'idempotency_key'=>'financier-request-'.$id,'metadata'=>json_encode(['reviewed_by'=>$r->user()->id]),'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   $this->audit($r,'financier_request.'.$d['decision'],'financier_request',$id,$d);});
  return back()->with('status','Finance request reviewed. Complete any actual wallet transfer separately.');
 }
 private function audit(Request $r,string $action,string $type,int $id,array $details):void{DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'details'=>json_encode($details),'ip_address'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);}
}