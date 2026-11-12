<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

class PayoutRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'min:1'],
            'payout_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);
        $category = DB::table('eve_item_groups')->where('category_id', $data['category_id'])->orderBy('category_name')->first(['category_id','category_name']);
        if (!$category) throw ValidationException::withMessages(['category_id'=>['That EVE category was not found. Import the CCP SDE categories and groups first.']]);

        DB::table('appraisal_payout_rules')->updateOrInsert(
            ['category_id'=>$category->category_id],
            ['category_name'=>$category->category_name,'payout_percent'=>$data['payout_percent'],'updated_at'=>now(),'created_at'=>now()]
        );
        DB::table('audit_logs')->insert([
            'user_id'=>$request->user()->id,'action'=>'appraisal_category_rate.saved',
            'subject_type'=>'eve_item_category','subject_id'=>$category->category_id,
            'details'=>json_encode(['category'=>$category->category_name,'payout_percent'=>(float)$data['payout_percent']]),
            'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return back()->with('status','Buyback rate saved for the '.$category->category_name.' category.');
    }

    public function destroy(Request $request, int $categoryId): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        DB::table('appraisal_payout_rules')->where('category_id',$categoryId)->delete();
        return back()->with('status','Category rate removed. The default buyback rate will apply.');
    }
}
