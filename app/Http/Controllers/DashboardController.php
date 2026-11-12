<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $admin = $user->isAdmin();
        $characterIds = $user->characters()->pluck('id');

        $jobs = DB::table('runner_jobs')
            ->when(!$admin, fn ($q) => $q->where(function ($w) use ($user) {
                $w->where('status', 'open')->orWhere('assigned_user_id', $user->id);
            }))
            ->orderByDesc('created_at')->limit(50)->get();

        $quotes = DB::table('appraisal_quotes')
            ->when(!$admin, fn ($q) => $q->where('created_by', $user->id))
            ->whereNull('archived_at')
            ->whereNotExists(function ($q) use ($user, $admin) {
                if (!$admin) {
                    $q->select(DB::raw(1))->from('appraisal_quote_hidden')
                        ->whereColumn('appraisal_quote_hidden.appraisal_quote_id', 'appraisal_quotes.id')
                        ->where('appraisal_quote_hidden.user_id', $user->id);
                }
            })
            ->orderByDesc('created_at')->limit(100)->get();

        $archivedQuotes = $admin
            ? DB::table('appraisal_quotes')->whereNotNull('archived_at')->orderByDesc('archived_at')->limit(100)->get()
            : collect();

        $balance = (float) DB::table('ledger_entries')->whereIn('eve_character_id', $characterIds)->sum('amount_isk');
        $adminMetrics = $admin ? [
            'open_jobs' => DB::table('runner_jobs')->where('status', 'open')->count(),
            'users' => DB::table('users')->count(),
        ] : [];
        $payoutRules = $admin ? DB::table('appraisal_payout_rules')->orderBy('category_name')->get() : collect();
        $eveCategories = $admin ? DB::table('eve_item_groups')->select('category_id', 'category_name')->distinct()->orderBy('category_name')->get() : collect();

        return view('dashboard', compact('user', 'jobs', 'quotes', 'archivedQuotes', 'balance', 'admin', 'adminMetrics', 'payoutRules', 'eveCategories'));
    }
}
