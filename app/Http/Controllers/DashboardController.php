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

        $visibleQuotes = DB::table('appraisal_quotes')
            ->when(!$admin, fn ($q) => $q->where('created_by', $user->id))
            ->whereNull('archived_at')
            ->when(!$admin, fn ($q) => $q->whereNotExists(function ($hidden) use ($user) {
                $hidden->select(DB::raw(1))->from('appraisal_quote_hidden')
                    ->whereColumn('appraisal_quote_hidden.appraisal_quote_id', 'appraisal_quotes.id')
                    ->where('appraisal_quote_hidden.user_id', $user->id);
            }));

        $expiryCutoff = now()->subHours(6);
        $quotes = (clone $visibleQuotes)
            ->where('created_at', '>=', $expiryCutoff)
            ->orderByDesc('created_at')->limit(100)->get();

        $expiredQuotes = (clone $visibleQuotes)
            ->where('created_at', '<', $expiryCutoff)
            ->orderByDesc('created_at')->limit(100)->get();

        $archivedQuotes = $admin
            ? DB::table('appraisal_quotes')->whereNotNull('archived_at')->orderByDesc('archived_at')->limit(100)->get()
            : collect();

        // The first Admin tab shows every non-archived appraisal, regardless of age
        // or whether its owner hid it from their own dashboard.
        $adminQuotes = $admin
            ? DB::table('appraisal_quotes')
                ->leftJoin('users', 'users.id', '=', 'appraisal_quotes.created_by')
                ->whereNull('appraisal_quotes.archived_at')
                ->orderByDesc('appraisal_quotes.created_at')
                ->select('appraisal_quotes.*', 'users.name as owner_name')
                ->get()
            : collect();

        $adminAppraisalItems = $admin && $adminQuotes->isNotEmpty()
            ? DB::table('appraisal_quote_items')
                ->whereIn('appraisal_quote_id', $adminQuotes->pluck('id'))
                ->orderBy('type_name')
                ->get(['appraisal_quote_id', 'type_name', 'quantity'])
                ->groupBy('appraisal_quote_id')
                ->map(fn ($items) => $items->map(fn ($item) => [
                    'name' => $item->type_name,
                    'quantity' => (int) $item->quantity,
                ])->values())
            : collect();

        $balance = (float) DB::table('ledger_entries')->whereIn('eve_character_id', $characterIds)->sum('amount_isk');
        $adminMetrics = $admin ? [
            'open_jobs' => DB::table('runner_jobs')->where('status', 'open')->count(),
            'users' => DB::table('users')->count(),
        ] : [];
        $payoutRules = $admin ? DB::table('appraisal_payout_rules')->orderBy('category_name')->get() : collect();
        $eveCategories = $admin ? DB::table('eve_item_groups')->select('category_id', 'category_name')->distinct()->orderBy('category_name')->get() : collect();
        $compressedOreTypes = $admin ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_name', 'Asteroid')
            ->where('t.type_name', 'like', 'Compressed %')
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name']) : collect();
        $oreTypes = $admin ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_name', 'Asteroid')
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name']) : collect();
        $mineralTypes = $admin ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_name', 'Material')->where('g.group_name', 'Mineral')
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name']) : collect();
        $oreValuationRules = $admin ? DB::table('appraisal_ore_valuation_rules')->orderBy('ore_name')->get() : collect();

        return view('dashboard', compact('user', 'jobs', 'quotes', 'expiredQuotes', 'archivedQuotes', 'adminQuotes', 'adminAppraisalItems', 'balance', 'admin', 'adminMetrics', 'payoutRules', 'eveCategories', 'oreTypes', 'compressedOreTypes', 'mineralTypes', 'oreValuationRules'));
    }
}
