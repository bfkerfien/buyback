<?php
namespace App\Http\Controllers;
use App\Models\EveCharacter;
use App\Services\Eve\CharacterContractVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationsController extends Controller {
 public function createJob(Request $r):RedirectResponse {
  abort_unless($r->user()->isAdmin(),403);
  $d=$r->validate(['origin'=>'required|string|max:100','destination'=>'required|string|max:100','reward_isk'=>'required|numeric|min:0.01|max:1000000000000','appraisal_quote_id'=>'nullable|integer|exists:appraisal_quotes,id','contract_id'=>'required|integer|min:1|unique:runner_jobs,contract_id','notes'=>'nullable|string|max:2000']);
  $id=DB::table('runner_jobs')->insertGetId($d+['created_by'=>$r->user()->id,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);
  $this->audit($r,'runner_job.created','runner_job',$id,$d);return back()->with('status','Runner job published.');
 }
 public function acceptJob(Request $r,int $job):RedirectResponse {
  $validated=$r->validate(['character_id'=>['required','integer',Rule::exists('eve_characters','id')->where('user_id',$r->user()->id)]]);
  $character=$r->user()->characters()->findOrFail($validated['character_id']);
  $n=DB::table('runner_jobs')->where('id',$job)->where('status','open')->whereNull('assigned_user_id')->update(['status'=>'accepted','runner_character_id'=>$character->id,'assigned_user_id'=>$r->user()->id,'accepted_at'=>now(),'updated_at'=>now()]);
  abort_unless($n===1,409,'This job is no longer available.');
  $this->audit($r,'runner_job.accepted','runner_job',$job,['character_id'=>$character->id]);return back()->with('status','Runner job accepted.');
 }
 public function deliverJob(Request $r,int $job,CharacterContractVerifier $verifier):RedirectResponse {
  $row=DB::table('runner_jobs')->where('id',$job)->first();
  abort_unless($row&&($row->assigned_user_id===$r->user()->id||$r->user()->isAdmin()),403);
  abort_unless($row->status==='accepted',409,'Only accepted jobs can be delivered.');
  abort_unless($row->contract_id && $row->runner_character_id,422,'This runner job is missing its EVE contract or runner character.');
  $character=EveCharacter::findOrFail($row->runner_character_id);
  $verification=$verifier->verifyCourierDelivery($character,(int)$row->contract_id);
  DB::transaction(function()use($row,$r,$job,$verification){
   $changed=DB::table('runner_jobs')->where('id',$job)->where('status','accepted')->update(['status'=>'delivered','delivered_at'=>now(),'verification_data'=>json_encode($verification),'updated_at'=>now()]);
   abort_unless($changed===1,409,'Job status changed before delivery could be recorded.');
   if((float)$row->reward_isk>0&&$row->runner_character_id)DB::table('ledger_entries')->insertOrIgnore(['eve_character_id'=>$row->runner_character_id,'entry_type'=>'runner_reward','source_type'=>'runner_job','source_id'=>$job,'amount_isk'=>$row->reward_isk,'idempotency_key'=>'runner-job-'.$job,'metadata'=>json_encode(['status'=>'delivered','contract_id'=>$row->contract_id]),'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   $this->audit($r,'runner_job.delivered','runner_job',$job,$verification);
  });
  return back()->with('status','EVE verified courier delivery. Runner reward recorded in the internal ledger; ISK payment remains manual.');
 }
 private function audit(Request $r,string $action,string $type,int $id,array $details):void {
  DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'details'=>json_encode($details),'ip_address'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
 }
}