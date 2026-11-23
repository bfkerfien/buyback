<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EveBuyback · Operations</title>
<style>
 :root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(2px 2px at 8% 16%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 18% 72%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 29% 38%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 43% 82%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 57% 14%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 69% 55%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 83% 27%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 94% 78%,#fff 45%,transparent 60%),radial-gradient(ellipse at 50% -10%,#0d5967 0,transparent 52%),radial-gradient(ellipse at 88% 82%,#3d2512 0,transparent 42%),radial-gradient(ellipse at 8% 90%,#0d2b38 0,transparent 40%),#060d13;min-height:100vh}main{max-width:1200px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:16px}.account-chip{display:flex;align-items:center;gap:9px;padding:5px 11px 5px 6px;border:1px solid #386675;border-radius:999px;background:#09151c;color:#eaf7f8;font-size:13px;font-weight:600;white-space:nowrap}.account-chip img{width:34px;height:34px;display:block;object-fit:cover;border-radius:50%;border:1px solid #68dbea}.muted{color:#9bb5bf;font-size:13px}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:20px;margin:18px 0}h1{font-size:30px}h2{font-size:19px;margin-top:0}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#09151c;padding:14px;border-radius:10px}.stat strong{display:block;font-size:22px;margin-top:8px}input,select,textarea{width:100%;background:#09151c;color:#eaf7f8;border:1px solid #315665;border-radius:8px;padding:10px;margin:6px 0 12px}textarea{min-height:70px}button{display:inline-block;background:#ff9b45;color:#111820;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #294a56;font-size:13px}.success{color:#ffb45f}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}form.inline{display:inline}form.inline select{width:auto;max-width:170px;padding:8px}@media(max-width:700px){.grid,.row{grid-template-columns:1fr}body{padding:12px}.top{align-items:flex-start}}
.pricing-settings{margin:18px 0;border:1px solid rgba(91,193,210,.25);border-radius:12px;background:rgba(5,16,23,.42);overflow:hidden}.pricing-settings>summary{cursor:pointer;list-style:none;padding:15px 18px;font-weight:800;color:#9deaf0;display:flex;align-items:center;justify-content:space-between;gap:12px}.pricing-settings>summary::-webkit-details-marker{display:none}.pricing-settings>summary:after{content:'+';font-size:20px;color:#ffb45f}.pricing-settings[open]>summary:after{content:'−'}.pricing-settings-content{padding:0 18px 18px}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0 16px;border-bottom:1px solid #294a56;padding-bottom:10px}.tab{background:#09151c;color:#c5dbe0;border:1px solid #315665}.tab.active{background:#ff9b45;color:#111820;border-color:#ff9b45}.tab-panel{display:none}.tab-panel.active{display:block}.tab-panel[hidden]{display:none}
.icon-actions{display:flex;align-items:center;gap:7px;white-space:nowrap}.icon-action{width:36px;height:36px;padding:0!important;border:1px solid #315665!important;border-radius:9px!important;background:#102630!important;color:#dffaff!important;box-shadow:none!important;font-size:18px;line-height:1}.icon-action.contract{color:#8de4a8!important}.icon-action.compare{color:#72deef!important}.contracted-label{display:inline-flex;align-items:center;gap:5px;color:#8de4a8;font-size:12px;font-weight:700}.compare-dialog{width:min(900px,94vw);max-height:88vh;overflow:auto;background:#0b1820;color:#eaf7f8;border:1px solid #386675;border-radius:16px;padding:22px;box-shadow:0 24px 100px #000b}.compare-dialog::backdrop{background:#02070bbd}.compare-dialog textarea{width:100%;min-height:190px;background:#07131a;color:#eaf7f8;border:1px solid #315665;border-radius:9px;padding:12px}.comparison-table{width:100%;border-collapse:collapse;margin-top:14px}.comparison-table th,.comparison-table td{padding:9px;border-bottom:1px solid #294a56;text-align:left;font-size:12px}.comparison-status{font-size:12px;font-weight:700}.comparison-status.match{color:#8de4a8}.comparison-status.diff{color:#ffb45f}.comparison-status.missing{color:#ff927c}.compare-blocks{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media(max-width:700px){.compare-blocks{grid-template-columns:1fr}}


/* EveBuyback visual system: orbital graphite, ion-cyan and molten amber */
:root{color-scheme:dark;--eb-bg:#071018;--eb-panel:#101e27;--eb-line:#294a56;--eb-cyan:#62d9e9;--eb-amber:#ff9b45;--eb-text:#eaf7f8;--eb-muted:#9bb5bf}
body{position:relative;color:var(--eb-text)!important;background:radial-gradient(ellipse at 12% 8%,rgba(29,185,205,.19),transparent 34%),radial-gradient(ellipse at 88% 14%,rgba(255,137,56,.13),transparent 30%),linear-gradient(135deg,#071018 0%,#0a1821 54%,#080f16 100%)!important;background-attachment:fixed!important}
body:before{content:"";position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(99,205,220,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(99,205,220,.035) 1px,transparent 1px);background-size:46px 46px;mask-image:linear-gradient(to bottom,black,transparent 88%)}
body>main,body>.wrap{position:relative;z-index:1}
.panel,.feature,.stat,.notice{background:linear-gradient(145deg,rgba(18,36,46,.97),rgba(9,21,29,.97))!important;border-color:rgba(91,193,210,.25)!important;box-shadow:0 18px 48px rgba(0,0,0,.25),inset 0 1px 0 rgba(255,255,255,.035)}
h1,h2{color:#f1fbfc;text-shadow:0 0 24px rgba(78,207,224,.12)}
.muted,.hint,.tag{color:var(--eb-muted)!important}
a{color:var(--eb-cyan)!important}
button{background:linear-gradient(115deg,#ffb45f,#ff793f)!important;color:#101820!important;box-shadow:0 7px 22px rgba(255,127,61,.2);transition:transform .16s ease,filter .16s ease,box-shadow .16s ease}
button:hover{transform:translateY(-1px);filter:brightness(1.08);box-shadow:0 10px 28px rgba(255,127,61,.28)}
.tab{background:linear-gradient(145deg,#122630,#0b1820)!important;color:#c5dbe0!important;border-color:#315665!important;box-shadow:none!important}
.tab.active{background:linear-gradient(110deg,#62d9e9,#ffad58)!important;color:#071018!important;border-color:transparent!important}
input,select,textarea{background:rgba(5,16,23,.88)!important;color:#eaf7f8!important;border-color:#315665!important}
input:focus,select:focus,textarea:focus{outline:2px solid rgba(98,217,233,.35);border-color:#62d9e9!important}
th{color:#9deaf0!important}td{border-color:rgba(91,193,210,.18)!important}
.account-chip{border-color:#386675!important;box-shadow:0 0 22px rgba(55,194,212,.08)}
@media(max-width:650px){body:before{background-size:34px 34px}}
 .eb-logo{display:block;height:auto;max-width:100%;object-fit:contain;filter:drop-shadow(0 0 18px rgba(18,216,242,.18))} .top .eb-logo{flex:0 1 auto} @media(max-width:650px){.eb-logo{max-width:72vw}}
.site-header{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;padding:12px 16px;margin:0 0 22px;background:rgba(7,19,27,.94);border:1px solid #315665;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.28);position:relative;z-index:40}.site-brand{display:block;flex:0 1 250px}.site-header .eb-logo{display:block;width:250px;max-width:100%;height:auto}.site-nav{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.site-nav>a{display:inline-flex;align-items:center;padding:9px 11px;border:1px solid #315665;border-radius:8px;text-decoration:none;color:#dffaff!important;font-size:13px;font-weight:700;background:#102630}.site-nav>a:hover{border-color:#62d9e9;background:#163440}.site-account{color:#9bb5bf;font-size:12px;padding:0 6px}.site-logout{margin:0}.site-logout button{padding:9px 12px;font-size:12px}@media(max-width:700px){.site-header{align-items:flex-start}.site-brand{flex-basis:180px}.site-header .eb-logo{width:180px}.site-nav{justify-content:flex-start;width:100%}}</style><link rel="icon" type="image/png" href="{{ asset('evebuyback-logo.png') }}">
</head><body><main>
@include('partials.site-header')
@php($activeCharacter = $user->characters->sortByDesc('last_authenticated_at')->first())

@if(session('status'))<p class="success">{{ session('status') }}</p>@endif
@if($errors->any())<div class="panel">{{ $errors->first() }}</div>@endif

<section class="tab-panel active" id="tab-appraisals" role="tabpanel">
<section class="panel">
<h2>Buyback instructions</h2>
<ol style="color:#c5dbe0;line-height:1.9;padding-left:24px;margin:0">
<li><strong>Currently accepting:</strong> Ore, Ice, PI Goods, and Exploration Loot only.</li>
<li><strong>Include your appraisal number on the contract.</strong> Reference the appraisal number in the contract description or reference field for faster service. If a contract is rejected, it may be due to the experimental nature of the Buyback evaluation. If you are logged in to the site when this happens, an email will follow. If you prefer not to log in, you can always get that information from me on Discord.</li>
</ol>
</section>
<section class="panel">
<h2>Create an appraisal</h2><p class="muted">Paste item names and quantities from your inventory. Supported formats include EVE tab-separated exports, CSV, and “quantity x item name”.</p><p class="muted">Uncommon items may take a little longer the first time while their Jita price is added to the refresh list. This is expected; there’s no need to worry.</p>
<form method="post" action="{{ route('appraisals.store') }}">@csrf
<label>Origin system (optional)<input name="origin_system" value="{{ old('origin_system') }}" placeholder="Jita, Amarr, etc."></label>
<label>Inventory list<textarea name="inventory" required placeholder="Tritanium&#9;100000&#10;Pyerite&#9;25000&#10;Mexallon&#9;5000">{{ old('inventory') }}</textarea></label>
<button type="submit">Calculate appraisal</button>
</form>
<p class="muted">Quotes are estimates, not accepted contracts or completed payouts. Unrecognized or unpriced items are reported rather than silently valued at zero.</p>
</section>
</section>
<section class="tab-panel" id="tab-contracts" role="tabpanel" hidden>
<section class="panel">
<h2>Latest 10 account appraisals</h2>
<p class="muted">Recent appraisals submitted by any EVE character linked to this Buyback account. Deleting an appraisal permanently removes its quote and item details.</p>
<div style="overflow-x:auto"><table><thead><tr><th>Appraisal</th><th>Character</th><th>Origin</th><th>Appraisal type</th><th>Payout</th><th>Created</th><th>Action</th></tr></thead><tbody>
@forelse($accountAppraisals as $appraisal)
<tr>
<td><a href="{{ route('appraisals.show',$appraisal->public_token) }}">#{{ substr($appraisal->public_token,0,12) }}</a></td>
<td>{{ $appraisal->character_name }}</td>
<td>{{ $appraisal->origin_system ?: '—' }}</td>
<td>{{ $appraisal->appraisal_kind === 'ship' ? 'Ship Buyback' : 'Standard' }}</td>
<td>{{ number_format((float)$appraisal->payout_value,2) }} ISK</td>
<td>{{ \Illuminate\Support\Carbon::parse($appraisal->created_at)->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
<td><form class="inline" method="post" action="{{ route('appraisals.destroy',$appraisal->id) }}" onsubmit="return confirm('Permanently delete this appraisal and its item details? This cannot be undone.')">@csrf @method('DELETE')<button type="submit" style="background:#8f3940;color:#fff">Delete</button></form></td>
</tr>
@empty
<tr><td colspan="7">No appraisals have been submitted by characters linked to this account.</td></tr>
@endforelse
</tbody></table></div>
</section>

<section class="panel">
<h2>Latest 10 contracts issued to My Eve Online</h2>
@if($contractCharacter)
<p class="muted">Showing contracts issued by your linked EVE character <strong>{{ $contractCharacter->name }}</strong> to My Eve Online, newest first.</p>
@if($contractBoardStatus !== 'ready')
<p class="muted">{{ $contractBoardMessage }}</p>
@if($contractBoardStatus === 'reauthorize')
<a href="{{ route('login.eve.link') }}">Reauthorize your linked EVE character</a>
@endif
@else
<div style="overflow-x:auto"><table><thead><tr><th>Description</th><th>Date Issued</th><th class="right">Price</th><th>Status</th></tr></thead><tbody>
@forelse($myEveContracts as $contract)
<tr>
<td>{{ $contract['description'] !== '' ? $contract['description'] : '—' }}</td>
<td>{{ !empty($contract['date_issued']) ? \Illuminate\Support\Carbon::parse($contract['date_issued'])->timezone(config('app.timezone'))->format('M j, Y g:i A') : '—' }}</td>
<td class="right">{{ number_format((float)$contract['price'],2) }} ISK</td>
<td>{{ $contract['status'] }}</td>
</tr>
@empty
<tr><td colspan="4">No contracts issued to My Eve Online were found for this character.</td></tr>
@endforelse
</tbody></table></div>
@endif
@else
<p class="muted">{{ $contractBoardMessage }}</p>
<a href="{{ route('login.eve.link') }}">Link your EVE character</a>
@endif</section>
</section>
@if($admin)
<section class="tab-panel" id="tab-admin" role="tabpanel" hidden>
<section class="panel">
<h2>Market data status</h2>
<div class="grid">
<div class="stat">Last full Jita price database refresh<strong style="font-size:17px">{{ $lastJitaPriceRefreshAt ? \Illuminate\Support\Carbon::parse($lastJitaPriceRefreshAt)->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') : 'Not recorded yet' }}</strong><div class="muted">This timestamp changes only after a successful full-market refresh, not a single-item refresh.</div></div>
<div class="stat">Item types with Jita orders in last refresh<strong>{{ number_format($lastJitaPriceRefreshCount) }}</strong><div class="muted">Types with live orders at Jita IV - Moon 4 saved during the last completed refresh.</div></div>
<div class="stat">Published EVE catalog types<strong>{{ number_format($catalogPublishedTypeCount) }}</strong><div class="muted">Published item types available to the appraisal engine.</div></div>
</div>
</section>
<section class="panel">
<h2>All appraisals</h2>
<p class="muted">Complete active appraisal queue, including expired appraisals and records hidden by their owners. Archived appraisals remain in the archive section.</p>
<div style="overflow-x:auto"><table><thead><tr><th>Appraisal</th><th>Owner</th><th>Origin</th><th>Offer</th><th>Created</th><th>Actions</th></tr></thead><tbody>
@forelse($adminQuotes as $quote)
<tr>
<td><a href="{{ route('appraisals.show',$quote->public_token) }}">#{{ substr($quote->public_token,0,12) }}</a><div class="muted">{{ $quote->appraisal_kind === 'ship' ? 'Ship hull buyback · 80%' : 'Standard appraisal' }}</div></td>
<td>{{ $quote->owner_name ?: ($quote->created_by ? 'User '.$quote->created_by : 'Public visitor') }}</td>
<td>{{ $quote->origin_system ?: '—' }}</td>
<td>{{ number_format((float)$quote->payout_value,2) }} ISK</td>
<td>{{ $quote->created_at }}</td>
<td><div class="icon-actions">
@if($quote->contracted_at)
<span class="contracted-label" title="Flagged {{ $quote->contracted_at }}">✓ Contracted</span>
@else
<form class="inline" method="post" action="{{ route('admin.appraisals.contract',$quote->id) }}">@csrf<button class="icon-action contract" type="submit" title="Mark as contracted" aria-label="Mark appraisal as contracted">✓</button></form>
@endif
<button class="icon-action compare-contract" type="button" data-quote-id="{{ $quote->id }}" title="Compare against pasted contract contents" aria-label="Compare appraisal with pasted contract">⇄</button>
<form class="inline" method="post" action="{{ route('admin.appraisals.archive',$quote->id) }}">@csrf<button type="submit" style="padding:7px 9px" onclick="return confirm('Archive this appraisal?')">Archive</button></form>
</div></td>
</tr>
@empty<tr><td colspan="6">No appraisals have been submitted.</td></tr>@endforelse
</tbody></table></div>
</section>
<section class="panel"><h2>Archived appraisals</h2><p class="muted">Archived records remain stored and can be restored at any time. Contracted status is retained when an appraisal is archived.</p><table><thead><tr><th>Quote</th><th>Owner ID</th><th>Origin</th><th>Payout</th><th>Contracted</th><th>Archived</th><th>Action</th></tr></thead><tbody>@forelse($archivedQuotes as $quote)<tr><td><a href="{{ route('appraisals.show',$quote->public_token) }}">{{ substr($quote->public_token,0,14) }}</a></td><td>{{ $quote->created_by ?? '—' }}</td><td>{{ $quote->origin_system ?: '—' }}</td><td>{{ number_format((float)$quote->payout_value,2) }} ISK</td><td>@if($quote->contracted_at)<span class="contracted-label" title="Flagged {{ $quote->contracted_at }}">✓ Contracted</span>@else<span class="muted">Not contracted</span>@endif</td><td>{{ $quote->archived_at }}</td><td><form class="inline" method="post" action="{{ route('admin.appraisals.restore',$quote->id) }}">@csrf<button>Restore</button></form></td></tr>@empty<tr><td colspan="7">No archived appraisals.</td></tr>@endforelse</tbody></table></section>


</section>
<section class="tab-panel" id="tab-admin-tools" role="tabpanel" hidden>
<section class="panel" style="border-color:#8f3940!important"><h2>Danger zone · Purge all appraisals</h2><p class="muted">Permanently deletes every appraisal, including active, expired, archived, and contracted appraisal records, plus their saved item lines. Runner jobs are retained with their appraisal links cleared. Users, EVE characters, contracts, finance ledger entries, pricing rules, and market data are not deleted.</p><form method="post" action="{{ route('admin.appraisals.purge') }}" onsubmit="return confirm('FINAL WARNING: permanently delete ALL appraisal records and their item details? This cannot be undone. Runner jobs and finance ledger entries will be retained.')">@csrf<button type="submit" style="background:#8f3940!important;color:#fff!important">Purge all appraisals</button></form></section>
<section class="panel" style="border-color:#a66b35!important"><h2>Archive cleanup · Purge archived appraisals</h2><p class="muted">Permanently deletes only appraisals currently in the archive and their saved item details. Active and expired non-archived appraisals are not touched. Runner jobs are retained with links to deleted archived appraisals cleared.</p><form method="post" action="{{ route('admin.appraisals.purge-archived') }}" onsubmit="return confirm('Permanently delete ONLY archived appraisals and their item details? Active and expired non-archived appraisals will be kept. This cannot be undone.')">@csrf<button type="submit" style="background:#a66b35!important;color:#fff!important">Purge archived appraisals only</button></form></section>
<section class="panel"><h2>Administration</h2><div class="grid"><div class="stat">Open jobs<strong>{{ $adminMetrics['open_jobs'] }}</strong></div><div class="stat">Registered users<strong>{{ $adminMetrics['users'] }}</strong></div></div>
<details class="pricing-settings"><summary>Ore reprocessing shipping cost</summary><div class="pricing-settings-content">
<p class="muted">Shipping is deducted from refined mineral output value based on each mineral's EVE volume.</p>
<form method="post" action="{{ route('admin.ore-shipping-cost.store') }}">@csrf
<label>Shipping cost per m³ (ISK)
<input name="cost_per_m3" type="number" min="0" max="1000000" step="0.01" value="{{ number_format((float) $oreShippingCostPerM3, 2, '.', '') }}" required style="max-width:220px">
</label>
<button type="submit">Save shipping cost</button>
</form>
</div></details>
<details class="pricing-settings"><summary>Standard mineral buyback settings</summary><div class="pricing-settings-content">
<p class="muted">Each mineral has its own buy percentage and cumulative Jita buy-order quantity. The calculator uses the price at that unit in the live Jita buy book. Save each mineral independently.</p>
@php($standardMineralDepths = ['Tritanium' => 150000000, 'Pyerite' => 30000000, 'Mexallon' => 3000000, 'Isogen' => 2000000, 'Nocxium' => 1000000, 'Zydrine' => 500000, 'Megacyte' => 500000, 'Morphite' => 45000])
<div style="overflow-x:auto"><table><thead><tr><th>Mineral</th><th>Buy percentage</th><th>Jita buy quantity (units)</th><th>Save</th></tr></thead><tbody>
@foreach($mineralTypes as $mineral)
@php($buyPercentKey = 'mineral_buy_percent_'.$mineral->type_id)
@php($buyDepthKey = 'mineral_buy_depth_'.$mineral->type_id)
<tr>
<td><form id="standard-mineral-{{ $mineral->type_id }}" method="post" action="{{ route('admin.mineral-pricing.store') }}">@csrf<input type="hidden" name="type_id" value="{{ $mineral->type_id }}">{{ $mineral->type_name }}</form></td>
<td><input form="standard-mineral-{{ $mineral->type_id }}" aria-label="{{ $mineral->type_name }} buy percentage" name="buy_percent" type="number" min="0.01" max="100" step="0.01" value="{{ $mineralPricingSettings->get($buyPercentKey, 87) }}" required style="max-width:130px;margin:0"></td>
<td><input form="standard-mineral-{{ $mineral->type_id }}" aria-label="{{ $mineral->type_name }} Jita cumulative unit depth" name="price_depth" type="number" min="1" step="1" value="{{ $mineralPricingSettings->get($buyDepthKey, $standardMineralDepths[$mineral->type_name] ?? 50000) }}" required style="max-width:190px;margin:0"></td>
<td><button form="standard-mineral-{{ $mineral->type_id }}" type="submit">Submit</button></td>
</tr>
@endforeach
</tbody></table></div>
</div></details>
<details class="pricing-settings"><summary>Moon-ore refined material settings</summary><div class="pricing-settings-content">
<p class="muted">Every material found in the SDE refining recipes for moon ores appears here. Configure its buy percentage and cumulative Jita quantity independently. Defaults are 87% and 50,000 units until changed.</p>
<div style="overflow-x:auto"><table><thead><tr><th>Refined material</th><th>Material group</th><th>Buy percentage</th><th>Jita unit depth</th><th>Save</th></tr></thead><tbody>
@forelse($moonMineralTypes as $material)
@php($buyPercentKey = 'mineral_buy_percent_'.$material->type_id)
@php($buyDepthKey = 'mineral_buy_depth_'.$material->type_id)
<tr>
<td><form id="moon-material-{{ $material->type_id }}" method="post" action="{{ route('admin.mineral-pricing.store') }}">@csrf<input type="hidden" name="type_id" value="{{ $material->type_id }}">{{ $material->type_name }}</form></td>
<td>{{ $material->group_name }}</td>
<td><input form="moon-material-{{ $material->type_id }}" aria-label="{{ $material->type_name }} buy percentage" name="buy_percent" type="number" min="0.01" max="100" step="0.01" value="{{ $mineralPricingSettings->get($buyPercentKey, 87) }}" required style="max-width:130px;margin:0"></td>
<td><input form="moon-material-{{ $material->type_id }}" aria-label="{{ $material->type_name }} Jita cumulative unit depth" name="price_depth" type="number" min="1" step="1" value="{{ $mineralPricingSettings->get($buyDepthKey, 50000) }}" required style="max-width:190px;margin:0"></td>
<td><button form="moon-material-{{ $material->type_id }}" type="submit">Submit</button></td>
</tr>
@empty<tr><td colspan="5">No moon-ore refining materials found. Confirm the CCP SDE moon-ore groups and reprocessing recipes are imported.</td></tr>@endforelse
</tbody></table></div>
</div></details>
<details class="pricing-settings"><summary>Ice & Ice Products buyback settings</summary>
<p class="muted">Every compressed ice type and each ice product has its own buy percentage and adjustable Jita buy quantity. The quantity is cumulative buy-order volume used to select the marginal Jita buy price. Default: 1,000 units. Changes apply to subsequent appraisals.</p>
@php($icePricingRows = $iceTypes->concat($iceProductTypes)->unique('type_id')->sortBy('type_name'))
<div style="display:grid;gap:8px">
@forelse($icePricingRows as $iceProduct)
@php($isCompressedIce = $iceTypes->contains(fn ($ice) => (int) $ice->type_id === (int) $iceProduct->type_id))
@php($productName = mb_strtolower($iceProduct->type_name))
@php($legacyRateKey = match ($productName) {
    'nitrogen isotopes' => 'ice_refined_nitrogen_percent',
    'liquid ozone' => 'ice_refined_ozone_percent',
    'strontium clathrates' => 'ice_refined_strontium_percent',
    default => null,
})
@php($defaultBuyPercent = $isCompressedIce ? 87 : ($productName === 'heavy water' ? 10 : 100))
@php($rateKey = 'ice_buy_percent_'.$iceProduct->type_id)
@php($depthKey = 'ice_buy_depth_'.$iceProduct->type_id)
@php($buyPercent = (float) $icePricingSettings->get($rateKey, $legacyRateKey ? $icePricingSettings->get($legacyRateKey, $defaultBuyPercent) : $defaultBuyPercent))
@php($priceSnapshot = $iceProductPrices->get($iceProduct->type_id))
@php($effectiveBuyPrice = $priceSnapshot ? (float) $priceSnapshot->buy_price * $buyPercent / 100 : null)
<form method="post" action="{{ route('admin.ice-pricing.store') }}" style="display:grid;grid-template-columns:minmax(180px,1.5fr) minmax(110px,.8fr) minmax(130px,1fr) minmax(130px,1fr) minmax(130px,1fr) auto;gap:12px;align-items:end;padding:10px;border-bottom:1px solid #294a56">
@csrf
<input type="hidden" name="type_id" value="{{ $iceProduct->type_id }}">
<strong style="align-self:center">{{ $iceProduct->type_name }}</strong>
<label class="muted">Buy percentage (%)
<input name="buy_percent" type="number" min="0.01" max="100" step="0.01" value="{{ $buyPercent }}" required style="margin:4px 0 0">
</label>
<label class="muted">Jita unit depth
<input name="price_depth" aria-label="{{ $iceProduct->type_name }} Jita buy quantity in units" type="number" min="1" max="2000000000" step="1" value="{{ $icePricingSettings->get($depthKey, 1000) }}" required style="margin:4px 0 0">
</label>
<div class="muted">Latest Jita buy<br><strong>{{ $priceSnapshot ? number_format((float) $priceSnapshot->buy_price, 2) . ' ISK' : 'No snapshot' }}</strong></div>
<div class="muted">Effective buy price<br><strong>{{ $effectiveBuyPrice !== null ? number_format($effectiveBuyPrice, 2) . ' ISK' : '—' }}</strong></div>
<button type="submit">Save</button>
</form>
@empty
<p class="muted">No compressed ice or supported ice products were found in the imported SDE.</p>
@endforelse
</div>
</div></details>
<details class="pricing-settings"><summary>Gas buyback settings</summary><div class="pricing-settings-content">
<p class="muted">All gas is priced from the equivalent compressed gas type's live Jita buy orders. Uncompressed gas uses the uncompressed percentage; compressed gas uses the compressed percentage. Both use the same cumulative Jita buy-order unit depth below.</p>
<form method="post" action="{{ route('admin.gas-pricing.store') }}">@csrf
<div class="row">
<label>Uncompressed gas buyback (%)<input name="uncompressed_percent" type="number" min="0.01" max="100" step="0.01" value="{{ $gasPricingSettings->get('gas_uncompressed_percent', 85) }}" required></label>
<label>Compressed gas buyback (%)<input name="compressed_percent" type="number" min="0.01" max="100" step="0.01" value="{{ $gasPricingSettings->get('gas_compressed_percent', 60) }}" required></label>
<label>Compressed gas Jita buy-order depth (units)<input name="price_depth" type="number" min="1" max="2000000000" step="1" value="{{ $gasPricingSettings->get('gas_buy_depth', 50000) }}" required></label>
</div>
<button type="submit">Save gas pricing settings</button>
</form>
@if($compressedGasTypes->isNotEmpty())
<p class="muted" style="margin-top:12px">Compressed gas types included in the reference-price lookup: {{ $compressedGasTypes->count() }}. Each uncompressed type is matched to its exact “Compressed [name]” counterpart.</p>
@else
<p class="muted" style="margin-top:12px">No compressed gas types found. Confirm the current CCP SDE types and groups have been imported.</p>
@endif
</div></details>
</section>
</section>
<section class="tab-panel" id="tab-ore-refining" role="tabpanel" hidden>
<iframe title="Ore Refining Comparison" src="{{ route('ore-values.index') }}" loading="lazy" style="display:block;width:100%;height:1900px;border:0;border-radius:12px;background:#060d13"></iframe>
</section>
@endif
@if($admin)
<section class="tab-panel" id="tab-appraisal-manifests" role="tabpanel" hidden>
<section class="panel">
<h2>Holo vs EveBuyback comparison</h2>
<p class="muted" style="line-height:1.7">Compare the same inventory across Holo and EveBuyback. Paste a share link or copied item list into each source. Where a source exposes a per-item price, the comparison shows the observed rate and a proposed EveBuyback percentage. Proposed rates are recommendations only and are never saved automatically.</p>
<p><a href="https://evebb.eu/" target="_blank" rel="noopener">Holo ↗</a></p>
<div class="compare-blocks">
<div><label for="holo-input">Holo link or item list</label><textarea id="holo-input" placeholder="Paste Holo appraisal link or copied items"></textarea></div>
<div><label for="buyback-input">EveBuyback link or item list</label><textarea id="buyback-input" placeholder="Paste your EveBuyback /q/ link or copied items"></textarea></div>
</div>
<button type="button" id="compare-appraisal-links">Compare Holo and EveBuyback</button>
<span id="appraisal-compare-status" class="muted" aria-live="polite" style="margin-left:10px"></span>
<div id="appraisal-compare-results" style="display:none;margin-top:16px"></div>
<input type="hidden" id="dashboard-csrf" value="{{ csrf_token() }}">
</section>
<section class="panel">
<h2>Copy appraisal inventory lists</h2>
<p class="muted" style="line-height:1.7">Each button copies published SDE item names with 1,000 units per type. The ore list contains compressed asteroid ore, compressed moon ore and compressed ice only.</p>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<button type="button" class="copy-appraisal-manifest" data-kind="ore">Copy Ores, Ice &amp; Moon Ores</button>
<button type="button" class="copy-appraisal-manifest" data-kind="pi">Copy PI</button>
<button type="button" class="copy-appraisal-manifest" data-kind="exploration">Copy All Exploration Loot</button>
</div>
<p id="manifest-copy-status" class="muted" aria-live="polite"></p>
<textarea id="appraisal-manifest-preview" readonly style="margin-top:12px;min-height:180px" placeholder="The selected inventory list will appear here."></textarea>
</section>
</section>
@endif

<section class="tab-panel" id="tab-how-to" role="tabpanel" hidden>
<section class="panel">
<h2>How to sell items through EveBuyback</h2>
<p style="color:#c5dbe0;line-height:1.8">Use the appraisal process first. An appraisal is a price estimate for review; it is not an accepted sale, a contract, or confirmation that payment has been made.</p>
<h2>1. Check what we accept</h2>
<p style="color:#c5dbe0;line-height:1.8">We are currently accepting <strong>Ore, Ice, PI Goods, and Exploration Loot</strong> only. Items outside these categories may not be accepted.</p>
<h2>2. Prepare your item list in EVE</h2>
<p style="color:#c5dbe0;line-height:1.8">Open the inventory containing the items you want appraised and copy or export the item names and quantities. Keep the item names and quantities together so the appraisal can identify each item correctly.</p>
<h2>3. Submit a new appraisal</h2>
<ol style="color:#c5dbe0;line-height:1.9;padding-left:24px">
<li>Open the <strong>New Appraisals</strong> tab.</li>
<li>Enter the system where the items are located, if applicable.</li>
<li>Paste the inventory list into the item list field. EVE tab-separated exports, CSV, and lines in the format “quantity x item name” are supported.</li>
<li>Select <strong>Calculate appraisal</strong> to generate the quote.</li>
</ol>
<h2>4. Review the quote and wait for approval</h2>
<p style="color:#c5dbe0;line-height:1.8">Open the appraisal result and check the listed items and estimated payout. The quote is an estimate only. Buyback price settings are currently fluctuating, so the team must review it and confirm whether the quoted values are approved.</p>
<h2>5. Do not create a contract yet</h2>
<p style="color:#c5dbe0;line-height:1.8"><strong>Appraisals only — do not create an in-game contract.</strong> Wait for the team to confirm approval and provide the next instructions before taking further action with the items. This is a temporary process.</p>
<h2>6. Keep track of your appraisal</h2>
<p style="color:#c5dbe0;line-height:1.8">Use <strong>Appraisals &amp; Contracts</strong> to find your recent appraisals. Appraisals older than 6 hours are moved to <strong>Expired Appraisals</strong>. An expired quote is no longer current; submit a new appraisal if you still want the items reviewed.</p>
</section>
</section>
<script>
const compressedCopy=document.getElementById('copy-compressed-ore');if(compressedCopy){compressedCopy.addEventListener('click',async()=>{const manifest=document.getElementById('compressed-ore-manifest').value;const status=document.getElementById('compressed-ore-copy-status');try{await navigator.clipboard.writeText(manifest);status.textContent='Copied '+manifest.trim().split(/\\r?\\n/).filter(Boolean).length+' asteroid ore types.';}catch(e){status.textContent='Clipboard access was blocked. Select and copy the manifest below.';}});}
document.querySelectorAll('.copy-appraisal-manifest').forEach(button=>button.addEventListener('click',async()=>{const status=document.getElementById('manifest-copy-status');const preview=document.getElementById('appraisal-manifest-preview');status.textContent='Loading current published SDE items…';try{const response=await fetch('{{ route('admin.appraisal-manifests') }}',{headers:{'Accept':'application/json'}});const payload=await response.json();if(!response.ok)throw new Error(payload.message||'Could not load item lists.');const kind=button.dataset.kind;const text=payload[kind]||'';preview.value=text;if(!text)throw new Error('No published items were found for this category.');await navigator.clipboard.writeText(text);status.textContent='Copied '+payload.counts[kind]+' item types at 1,000 units each.';}catch(error){status.textContent=error.message+' Select the preview and copy it manually if available.';}}));const compareAppraisals=document.getElementById('compare-appraisal-links');if(compareAppraisals){compareAppraisals.addEventListener('click',async()=>{const status=document.getElementById('appraisal-compare-status');const output=document.getElementById('appraisal-compare-results');status.textContent='Comparing Holo and EveBuyback…';output.style.display='none';try{const response=await fetch('{{ route('admin.appraisal-comparison') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.getElementById('dashboard-csrf').value},body:JSON.stringify({holo_input:document.getElementById('holo-input').value,buyback_input:document.getElementById('buyback-input').value})});const payload=await response.json();if(!response.ok)throw new Error(Object.values(payload.errors||{}).flat().join(' ')||payload.message||'Comparison failed.');const safe=s=>String(s).replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));const rows=payload.rows||[];status.textContent=payload.same_quantity+' rows have equal quantities · '+payload.differences+' quantity differences';output.style.display='block';const isk=v=>v===null||v===undefined?'Not provided':Number(v).toLocaleString(undefined,{maximumFractionDigits:2})+' ISK';output.innerHTML='<div class="stat">Holo: <strong>'+payload.holo_items+'</strong> item types &nbsp; EveBuyback: <strong>'+payload.buyback_items+'</strong><br>Equal quantities: <strong>'+payload.same_quantity+'</strong> · Quantity differences or missing items: <strong>'+payload.differences+'</strong></div><p class="muted">This is an inventory/quantity comparison only. Holo per-item payout is not exposed by the fetched page, so no price winner or proposed payout rate is calculated. EveBuyback payout below is the saved quote payout, not a Holo price match.</p><div style="overflow-x:auto"><table class="comparison-table"><thead><tr><th>Item</th><th>Holo quantity / price</th><th>EveBuyback quantity / payout</th><th>Quantity result</th></tr></thead><tbody>'+rows.map(row=>{const cls=row.status==='same_quantity'?'match':row.status==='quantity_mismatch'?'diff':'missing';const label=row.status==='same_quantity'?'Quantities match':row.status==='quantity_mismatch'?'Quantity mismatch':row.status==='missing_source'?'Missing from one source':'Not comparable';return '<tr><td>'+safe(row.name)+'</td><td>'+Number(row.holo_quantity).toLocaleString()+' / Not provided</td><td>'+Number(row.buyback_quantity).toLocaleString()+' / '+isk(row.buyback_unit_payout)+'</td><td class="comparison-status '+cls+'">'+label+'</td></tr>';}).join('')+'</tbody></table></div>'; }catch(e){status.textContent=e.message;}});}(()=>{const tabs=[...document.querySelectorAll('[data-tab]')];const panels=[...document.querySelectorAll('.tab-panel')];tabs.forEach(tab=>tab.addEventListener('click',()=>{const target=tab.dataset.tab;tabs.forEach(item=>{const active=item===tab;item.classList.toggle('active',active);item.setAttribute('aria-selected',active?'true':'false');});panels.forEach(panel=>{const active=panel.id===target;panel.classList.toggle('active',active);panel.hidden=!active;});}));const initial=new URLSearchParams(window.location.search).get('tab');if(initial){const tab=tabs.find(item=>item.dataset.tab===initial);if(tab){tab.click();}else{panels.forEach(panel=>{const active=panel.id===initial;panel.classList.toggle('active',active);panel.hidden=!active;});}}})();
</script>
<p class="muted">Internal ledger values are not confirmation of an in-game ISK transfer. Payments must be reconciled manually.</p><dialog id="contract-compare-dialog" class="compare-dialog">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px"><h2 id="contract-compare-title">Compare appraisal with contract</h2><button type="button" id="close-contract-compare" class="icon-action" aria-label="Close comparison">×</button></div>
<p class="muted">Paste the item list copied from the in-game contract. We'll compare every item name and quantity against the saved appraisal.</p>
<textarea id="contract-inventory-paste" placeholder="Paste contract contents here"></textarea>
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button type="button" id="run-contract-compare">Compare contents</button><span id="contract-compare-status" class="muted" aria-live="polite"></span></div>
<div id="contract-compare-results"></div>
</dialog>
<script type="application/json" id="admin-appraisal-items">@json($adminAppraisalItems->all())</script>
<script>
(()=> {
 const dataNode=document.getElementById('admin-appraisal-items');
 if(!dataNode)return;
 let allItems={};try{allItems=JSON.parse(dataNode.textContent||'{}')}catch(e){}
 const dialog=document.getElementById('contract-compare-dialog');
 const paste=document.getElementById('contract-inventory-paste');
 const status=document.getElementById('contract-compare-status');
 const results=document.getElementById('contract-compare-results');
 let currentQuote=null;
 const key=s=>String(s||'').trim().replace(/\s+/g,' ').toLocaleLowerCase();
 function parseList(raw){
   const merged=new Map();
   const add=(name,quantity)=>{
     name=String(name||'').trim().replace(/\s+/g,' ');
     const qty=Number(String(quantity??'').replace(/,/g,''));
     if(!name||!Number.isSafeInteger(qty)||qty<1)return;
     const k=key(name);
     const current=merged.get(k)||{name,quantity:0};
     current.quantity+=qty;
     merged.set(k,current);
   };

   const source=String(raw||'').replace(/\r/g,'');
   const allLines=source.split('\n');
   const itemLines=[];
   let inSaleSection=false;
   let hasSaleSection=false;

   for(const sourceLine of allLines){
     let line=sourceLine.trim();
     if(!line)continue;

     if(/Items\s+For\s+Sale/i.test(line)){
       hasSaleSection=true;
       inSaleSection=true;
       line=line.replace(/^.*?Items\s+For\s+Sale\s*:?\s*/i,'').trim();
       if(line)itemLines.push(line);
       continue;
     }

     if(inSaleSection){
       if(/^(Items\s+(Received|Wanted)|Collateral|Contract\s+Type|Description|Availability|Location|Price|Reward|Issuer|Acceptor|Date|Expires)\b/i.test(line)){
         inSaleSection=false;
         continue;
       }
       itemLines.push(line);
       continue;
     }

     if(!hasSaleSection&&!/^(Contract\s+Type|Description|Availability|Location|Price|Reward|Issuer|Acceptor|Date|Expires|Items\s+(Received|Wanted)|Collateral)\b/i.test(line)){
       itemLines.push(line);
     }
   }

   for(const originalLine of itemLines){
     let line=originalLine.trim();
     if(!line||/^\(?None\)?$/i.test(line))continue;

     // EVE contract clipboard output can concatenate entries without a newline:
     // "Hurricane Fleet Issue x 1Apocalypse Navy Issue x 1".
     const suffixPattern=/(.+?)\s*[x×]\s*([\d,]+)(?=\s*(?:[A-Z]|$))/g;
     const suffixMatches=[...line.matchAll(suffixPattern)];
     if(suffixMatches.length){
       for(const match of suffixMatches)add(match[1],match[2]);
       continue;
     }

     const prefix=line.match(/^([\d,]+)\s*[x×]\s*(.+)$/i);
     if(prefix){add(prefix[2],prefix[1]);continue;}

     const cells=line.split(/\t+|,\s*/).map(cell=>cell.trim()).filter(Boolean);
     if(cells.length>=2){
       const quantityIndex=cells.findIndex((cell,index)=>index>0&&/^[\d,]+$/.test(cell));
       if(quantityIndex>0){
         add(cells.slice(0,quantityIndex).join(' '),cells[quantityIndex]);
         continue;
       }
       if(/^[\d,]+$/.test(cells[0])){
         add(cells.slice(1).join(' '),cells[0]);
         continue;
       }
     }

     let match=line.match(/^([\d,]+)\s+(.+)$/);
     if(match){add(match[2],match[1]);continue;}
     match=line.match(/^(.+?)\s+([\d,]+)$/);
     if(match)add(match[1],match[2]);
   }

   return merged;
 }
 function safe(s){return String(s).replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));}
 document.querySelectorAll('.compare-contract').forEach(button=>button.addEventListener('click',()=>{
   currentQuote=button.dataset.quoteId;
   const items=allItems[currentQuote]||[];
   document.getElementById('contract-compare-title').textContent='Compare appraisal #'+currentQuote+' with contract';
   paste.value='';
   status.textContent=items.length+' appraised item types loaded.';
   results.innerHTML='';
   dialog.showModal();
 }));
 document.getElementById('close-contract-compare').addEventListener('click',()=>dialog.close());
 dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});
 document.getElementById('run-contract-compare').addEventListener('click',()=>{
   const saved=new Map((allItems[currentQuote]||[]).map(item=>[key(item.name),{name:item.name,quantity:Number(item.quantity)}]));
   const contract=parseList(paste.value);
   if(!contract.size){status.textContent='No contract item quantities recognized. Paste the copied contract item list.';return;}
   const names=new Set([...saved.keys(),...contract.keys()]);
   const rows=[...names].sort().map(k=>{
     const a=saved.get(k),b=contract.get(k),aq=a?.quantity||0,bq=b?.quantity||0;
     const state=!a?'Extra in contract':!b?'Missing from contract':aq===bq?'Match':'Quantity mismatch';
     const cls=state==='Match'?'match':state==='Extra in contract'||state==='Missing from contract'?'missing':'diff';
     return {name:a?.name||b?.name,aq,bq,state,cls};
   });
   const match=rows.filter(r=>r.state==='Match').length;
   const diff=rows.length-match;
   status.textContent=match+' matching item types · '+diff+' differences';
   results.innerHTML='<div class="stat" style="margin-top:12px">Saved appraisal vs contract: <strong>'+match+' matches / '+diff+' differences</strong></div><div style="overflow-x:auto"><table class="comparison-table"><thead><tr><th>Item</th><th>Appraisal qty</th><th>Contract qty</th><th>Result</th></tr></thead><tbody>'+rows.map(r=>'<tr><td>'+safe(r.name)+'</td><td>'+r.aq.toLocaleString()+'</td><td>'+r.bq.toLocaleString()+'</td><td class="comparison-status '+r.cls+'">'+r.state+'</td></tr>').join('')+'</tbody></table></div>';
 });
})();
</script>
</main><footer style="margin:28px auto 12px;text-align:center;color:#7f9ba5;font-size:12px">(c) 2027 My Eve Online</footer></body></html>
