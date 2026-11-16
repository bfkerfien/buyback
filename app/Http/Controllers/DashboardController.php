<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Eve\CharacterContracts;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $admin = $user->isAdmin();
        $characterIds = $user->characters()->pluck('id');

        $visibleQuotes = DB::table('appraisal_quotes')
            ->when(!$admin, fn ($q) => $q->where('created_by', $user->id))
            ->whereNull('archived_at')
            ->when(!$admin, fn ($q) => $q->whereNotExists(function ($hidden) use ($user) {
                $hidden->select(DB::raw(1))->from('appraisal_quote_hidden')
                    ->whereColumn('appraisal_quote_hidden.appraisal_quote_id', 'appraisal_quotes.id')
                    ->where('appraisal_quote_hidden.user_id', $user->id);
            }));

        $accountAppraisals = $characterIds->isNotEmpty()
            ? DB::table('appraisal_quotes')
                ->join('eve_characters', 'eve_characters.id', '=', 'appraisal_quotes.eve_character_id')
                ->whereIn('appraisal_quotes.eve_character_id', $characterIds)
                ->whereNull('appraisal_quotes.archived_at')
                ->orderByDesc('appraisal_quotes.created_at')
                ->limit(10)
                ->get([
                    'appraisal_quotes.id',
                    'appraisal_quotes.public_token',
                    'appraisal_quotes.origin_system',
                    'appraisal_quotes.appraisal_kind',
                    'appraisal_quotes.payout_value',
                    'appraisal_quotes.status',
                    'appraisal_quotes.created_at',
                    'eve_characters.name as character_name',
                ])
            : collect();

        // Contracts are shown for the account's linked character named exactly
        // "My Eve Online". This keeps the board tied to a verified EVE SSO identity.
        $contractCharacter = $user->characters()
            ->whereRaw('LOWER(name) = ?', ['my eve online'])
            ->first();
        $myEveContracts = collect();
        $contractBoardStatus = 'missing_character';
        $contractBoardMessage = 'Link the EVE character named My Eve Online to this account to display its latest contracts.';

        if ($contractCharacter) {
            $contractResult = app(CharacterContracts::class)->latest($contractCharacter, 10);
            $myEveContracts = collect($contractResult['contracts']);
            $contractBoardStatus = $contractResult['status'];
            $contractBoardMessage = $contractResult['message'];
        }

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

        $adminMetrics = $admin ? [
            'open_jobs' => DB::table('runner_jobs')->where('status', 'open')->count(),
            'users' => DB::table('users')->count(),
        ] : [];
        $priceRefreshStatus = $admin
            ? DB::table('buyback_system_status')
                ->whereIn('status_key', ['jita_full_refresh_at', 'jita_full_refresh_items'])
                ->pluck('status_value', 'status_key')
            : collect();
        $lastJitaPriceRefreshAt = $admin ? $priceRefreshStatus->get('jita_full_refresh_at') : null;
        $lastJitaPriceRefreshCount = $admin ? (int) $priceRefreshStatus->get('jita_full_refresh_items', 0) : 0;
        $catalogPublishedTypeCount = $admin ? DB::table('eve_item_types')->where('published', true)->count() : 0;
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
        $mineralPricingSettings = $admin
            ? DB::table('buyback_system_status')->where('status_key', 'like', 'mineral_buy_%')->pluck('status_value', 'status_key')
            : collect();
        $moonMineralTypes = $admin ? DB::table('eve_type_materials as m')
            ->join('eve_item_types as ore', 'ore.type_id', '=', 'm.type_id')
            ->join('eve_item_groups as og', 'og.group_id', '=', 'ore.group_id')
            ->join('eve_item_types as material', 'material.type_id', '=', 'm.material_type_id')
            ->join('eve_item_groups as mg', 'mg.group_id', '=', 'material.group_id')
            ->where('ore.published', true)->where('material.published', true)
            ->where('og.group_name', 'like', '%Moon%')
            ->where('mg.group_name', '<>', 'Mineral')
            ->distinct()->orderBy('material.type_name')
            ->get(['material.type_id', 'material.type_name', 'mg.group_name'])
            : collect();

        return view('dashboard', compact('user', 'accountAppraisals', 'contractCharacter', 'myEveContracts', 'contractBoardStatus', 'contractBoardMessage', 'quotes', 'expiredQuotes', 'archivedQuotes', 'adminQuotes', 'adminAppraisalItems', 'admin', 'adminMetrics', 'lastJitaPriceRefreshAt', 'lastJitaPriceRefreshCount', 'catalogPublishedTypeCount', 'payoutRules', 'eveCategories', 'oreTypes', 'compressedOreTypes', 'mineralTypes', 'oreValuationRules', 'mineralPricingSettings', 'moonMineralTypes'));
    }
}
