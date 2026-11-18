<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Eve\CharacterContracts;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $admin = $user->isAdmin();
        $activeTab = $request->query('tab', 'tab-appraisals');
        // The dashboard is tabbed client-side, but each request should fetch
        // only the data required by the selected tab.
        $loadAdminQueue = $admin && $activeTab === 'tab-admin';
        $loadAdminTools = $admin && $activeTab === 'tab-admin-tools';
        $loadMarketbaiters = $admin && $activeTab === 'tab-marketbaiters';
        $loadHoloCatalog = $admin && $activeTab === 'tab-holo-compare';
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

        // Use the EVE character that most recently authenticated into this
        // Buyback account (for example, DivineReign DivineSpirit). Its contracts
        // addressed to My Eve Online are the customer's buyback submissions.
        $contractCharacter = $user->characters()
            ->orderByDesc('last_authenticated_at')
            ->first();
        $myEveContracts = collect();
        $contractBoardStatus = $contractCharacter ? 'loading' : 'missing_character';
        $contractBoardMessage = $contractCharacter
            ? null
            : 'Link your EVE character to this Buyback account to display contracts issued to My Eve Online.';

        // Contract discovery calls EVE ESI and may need to scan multiple pages.
        // Never block the normal dashboard (or unrelated admin tabs) on that
        // external request. Only load the contract board when it is selected,
        // and reuse the result briefly when users revisit it.
        if ($contractCharacter && $request->query('tab') === 'tab-contracts') {
            $contractResult = Cache::remember(
                'buyback:dashboard:contracts:' . $contractCharacter->id,
                now()->addMinutes(5),
                fn () => app(CharacterContracts::class)->forRecipient($contractCharacter, 'My Eve Online', 10)
            );
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

        $archivedQuotes = $loadAdminQueue
            ? DB::table('appraisal_quotes')->whereNotNull('archived_at')->orderByDesc('archived_at')->limit(100)->get()
            : collect();

        // The first Admin tab shows every non-archived appraisal, regardless of age
        // or whether its owner hid it from their own dashboard.
        $adminQuotes = $loadAdminQueue
            ? DB::table('appraisal_quotes')
                ->leftJoin('users', 'users.id', '=', 'appraisal_quotes.created_by')
                ->whereNull('appraisal_quotes.archived_at')
                ->orderByDesc('appraisal_quotes.created_at')
                ->select('appraisal_quotes.*', 'users.name as owner_name')
                ->get()
            : collect();

        $adminAppraisalItems = $loadAdminQueue && $adminQuotes->isNotEmpty()
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

        $adminMetrics = $loadAdminQueue ? [
            'open_jobs' => DB::table('runner_jobs')->where('status', 'open')->count(),
            'users' => DB::table('users')->count(),
        ] : [];
        $priceRefreshStatus = $loadAdminQueue
            ? DB::table('buyback_system_status')
                ->whereIn('status_key', ['jita_full_refresh_at', 'jita_full_refresh_items'])
                ->pluck('status_value', 'status_key')
            : collect();
        $lastJitaPriceRefreshAt = $loadAdminQueue ? $priceRefreshStatus->get('jita_full_refresh_at') : null;
        $lastJitaPriceRefreshCount = $loadAdminQueue ? (int) $priceRefreshStatus->get('jita_full_refresh_items', 0) : 0;
        $catalogPublishedTypeCount = $loadAdminQueue ? DB::table('eve_item_types')->where('published', true)->count() : 0;
        $payoutRules = $loadAdminTools ? DB::table('appraisal_payout_rules')->whereNotIn('category_name', ['Asteroid', 'Material'])->orderBy('category_name')->get() : collect();
        $eveCategories = $loadAdminTools ? DB::table('eve_item_groups')->whereNotIn('category_name', ['Asteroid', 'Material'])->select('category_id', 'category_name')->distinct()->orderBy('category_name')->get() : collect();
        $compressedOreTypes = $loadHoloCatalog ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_name', 'Asteroid')
            ->where('t.type_name', 'like', 'Compressed %')
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name']) : collect();
        $oreTypes = $loadMarketbaiters ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)->where('g.category_name', 'Asteroid')
            ->orderBy('t.type_name')->get(['t.type_id', 't.type_name']) : collect();
        // The standard asteroid-ore mineral list is exactly these eight materials.
        // Do not expose every SDE type assigned to the broader Mineral group.
        $standardMineralNames = [
            'Tritanium', 'Pyerite', 'Mexallon', 'Isogen',
            'Nocxium', 'Zydrine', 'Megacyte', 'Morphite',
        ];
        $mineralTypes = $loadAdminTools ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.category_name', 'Material')
            ->where('g.group_name', 'Mineral')
            ->whereIn('t.type_name', $standardMineralNames)
            ->orderByRaw("FIELD(t.type_name, 'Tritanium', 'Pyerite', 'Mexallon', 'Isogen', 'Nocxium', 'Zydrine', 'Megacyte', 'Morphite')")
            ->get(['t.type_id', 't.type_name']) : collect();
        $mineralPricingSettings = $loadAdminTools
            ? DB::table('buyback_system_status')->where('status_key', 'like', 'mineral_buy_%')->pluck('status_value', 'status_key')
            : collect();
        // Moon goo settings contain only the 20 published types in the
        // dedicated Moon Materials group. Do not infer moon materials from
        // every non-mineral output in a moon-ore recipe.
        $moonMineralTypes = $loadAdminTools ? DB::table('eve_item_types as material')
            ->join('eve_item_groups as mg', 'mg.group_id', '=', 'material.group_id')
            ->where('material.published', true)
            ->where('mg.category_name', 'Material')
            ->where('mg.group_name', 'Moon Materials')
            ->orderBy('material.type_name')
            ->get(['material.type_id', 'material.type_name', 'mg.group_name'])
            : collect();

        $iceTypes = ($loadHoloCatalog || $loadAdminTools) ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('t.type_name', 'like', 'Compressed %')
            ->where(function ($query) {
                $query->where('g.group_name', 'like', '%ice%')
                    ->orWhere('g.category_name', 'like', '%ice%')
                    ->orWhereIn('t.type_name', [
                        'Compressed Clear Icicle', 'Compressed Glacial Mass', 'Compressed Blue Ice',
                        'Compressed White Glaze', 'Compressed Enriched Clear Icicle',
                        'Compressed Smooth Glacial Mass', 'Compressed Thick Blue Ice',
                        'Compressed Pristine White Glaze',
                    ]);
            })
            ->orderBy('t.type_name')
            ->get(['t.type_id', 't.type_name'])
            : collect();
        $iceProductTypes = $loadAdminTools ? DB::table('eve_item_types as t')
            ->where('t.published', true)
            ->whereIn('t.type_name', [
                'Nitrogen Isotopes',
                'Heavy Water',
                'Strontium Clathrates',
                'Liquid Ozone',
            ])
            ->orderBy('t.type_name')
            ->get(['t.type_id', 't.type_name'])
            : collect();
        $icePricingSettings = $loadAdminTools
            ? DB::table('buyback_system_status')->where('status_key', 'like', 'ice_%')->pluck('status_value', 'status_key')
            : collect();
        $iceProductPrices = $loadAdminTools
            ? DB::table('market_price_snapshots as p')
                ->joinSub(
                    DB::table('market_price_snapshots')
                        ->selectRaw('type_id, MAX(id) as latest_id')
                        ->where('region_id', '10000002')
                        ->where('source', 'esi-jita-4-4')
                        ->groupBy('type_id'),
                    'latest_ice_prices',
                    fn ($join) => $join->on('latest_ice_prices.latest_id', '=', 'p.id')
                )
                ->get(['p.type_id', 'p.buy_price'])
                ->keyBy('type_id')
            : collect();
        $compressedGasTypes = ($loadHoloCatalog || $loadAdminTools) ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.group_name', 'Compressed Gas')
            ->orderBy('t.type_name')
            ->get(['t.type_id', 't.type_name'])
            : collect();
        $uncompressedGasTypes = $loadHoloCatalog ? DB::table('eve_item_types as t')
            ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
            ->where('t.published', true)
            ->where('g.group_name', 'Harvestable Cloud')
            ->where('t.type_name', 'not like', 'Compressed %')
            ->orderBy('t.type_name')
            ->get(['t.type_id', 't.type_name'])
            : collect();

        // PI test inventory is the complete set of published planetary commodities
        // appearing as either an input or output in the imported SDE schematics.
        $piTypeIds = $loadHoloCatalog
            ? DB::table('eve_planet_schematics')->select('product_type_id as type_id')
                ->union(DB::table('eve_planet_schematic_inputs')->select('type_id'))
                ->pluck('type_id')->unique()->values()
            : collect();
        $piMaterialTypes = $loadHoloCatalog && $piTypeIds->isNotEmpty()
            ? DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('g.category_id', 43)
                ->whereIn('t.type_id', $piTypeIds)
                ->orderBy('t.type_name')
                ->get(['t.type_id', 't.type_name'])
            : collect();

        $gasPricingSettings = $loadAdminTools
            ? DB::table('buyback_system_status')->where('status_key', 'like', 'gas_%')->pluck('status_value', 'status_key')
            : collect();

        return view('dashboard', compact('user', 'accountAppraisals', 'contractCharacter', 'myEveContracts', 'contractBoardStatus', 'contractBoardMessage', 'quotes', 'expiredQuotes', 'archivedQuotes', 'adminQuotes', 'adminAppraisalItems', 'admin', 'adminMetrics', 'lastJitaPriceRefreshAt', 'lastJitaPriceRefreshCount', 'catalogPublishedTypeCount', 'payoutRules', 'eveCategories', 'oreTypes', 'compressedOreTypes', 'mineralTypes', 'mineralPricingSettings', 'moonMineralTypes', 'iceTypes', 'iceProductTypes', 'icePricingSettings', 'iceProductPrices', 'compressedGasTypes', 'uncompressedGasTypes', 'piMaterialTypes', 'gasPricingSettings'));
    }

    /**
     * Lightweight, dedicated Holo comparison page.
     *
     * Keep this out of DashboardController::index(): the full dashboard loads
     * appraisal history, item rows, pricing rules and several unrelated SDE
     * catalogs. The Holo tool needs only its test-inventory catalogs.
     */
    public function holoComparison(): View
    {
        $catalog = Cache::remember('buyback:holo-comparison:catalog:v1', now()->addHour(), function () {
            $compressedOreTypes = DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('g.category_name', 'Asteroid')
                ->where('t.type_name', 'like', 'Compressed %')
                ->orderBy('t.type_name')
                ->get(['t.type_id', 't.type_name']);

            $iceTypes = DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('t.type_name', 'like', 'Compressed %')
                ->where(function ($query) {
                    $query->where('g.group_name', 'like', '%ice%')
                        ->orWhere('g.category_name', 'like', '%ice%')
                        ->orWhereIn('t.type_name', [
                            'Compressed Clear Icicle', 'Compressed Glacial Mass', 'Compressed Blue Ice',
                            'Compressed White Glaze', 'Compressed Enriched Clear Icicle',
                            'Compressed Smooth Glacial Mass', 'Compressed Thick Blue Ice',
                            'Compressed Pristine White Glaze',
                        ]);
                })
                ->orderBy('t.type_name')
                ->get(['t.type_id', 't.type_name']);

            $compressedGasTypes = DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('g.group_name', 'Compressed Gas')
                ->orderBy('t.type_name')
                ->get(['t.type_id', 't.type_name']);

            $uncompressedGasTypes = DB::table('eve_item_types as t')
                ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                ->where('t.published', true)
                ->where('g.group_name', 'Harvestable Cloud')
                ->where('t.type_name', 'not like', 'Compressed %')
                ->orderBy('t.type_name')
                ->get(['t.type_id', 't.type_name']);

            $piTypeIds = DB::table('eve_planet_schematics')
                ->select('product_type_id as type_id')
                ->union(DB::table('eve_planet_schematic_inputs')->select('type_id'))
                ->pluck('type_id')->unique()->values();

            $piMaterialTypes = $piTypeIds->isNotEmpty()
                ? DB::table('eve_item_types as t')
                    ->join('eve_item_groups as g', 'g.group_id', '=', 't.group_id')
                    ->where('t.published', true)
                    ->where('g.category_id', 43)
                    ->whereIn('t.type_id', $piTypeIds)
                    ->orderBy('t.type_name')
                    ->get(['t.type_id', 't.type_name'])
                : collect();

            return compact('compressedOreTypes', 'iceTypes', 'compressedGasTypes', 'uncompressedGasTypes', 'piMaterialTypes');
        });

        return view('holo-comparison', $catalog);
    }
}
