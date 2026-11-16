<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ore Refining · EveBuyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:18px;background:radial-gradient(ellipse at 50% -10%,#0d5967 0,transparent 52%),#060d13;min-height:100vh}main{max-width:1500px;margin:auto}.muted{color:#9bb5bf;font-size:12px;line-height:1.6}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:18px;margin:14px 0}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.top a{color:#62d9e9;text-decoration:none;border:1px solid #386675;border-radius:8px;padding:8px 12px}h1{font-size:26px;margin:8px 0}.profile{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:9px}.stat{background:#09151c;border-radius:10px;padding:12px}.stat span{display:block;color:#9bb5bf;font-size:12px}.stat strong{display:block;margin-top:6px;font-size:16px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1050px}th,td{padding:10px;border-bottom:1px solid #294a56;text-align:right;font-size:12px;white-space:nowrap}th{color:#9deaf0;position:sticky;top:0;background:#101e27}th:first-child,td:first-child{text-align:left}.ore{font-weight:700;color:#eaf7f8}.sell{color:#9de0b1}.refine{color:#ffb45f}.unknown{color:#e8c27c}.winner{font-weight:800}.details{background:#315665;color:#fff;border:0;border-radius:6px;padding:6px 9px;cursor:pointer}.materials{display:none;text-align:left;white-space:normal;padding:12px;background:#09151c}.materials.open{display:table-cell}.materials ul{margin:0;padding-left:18px;line-height:1.7}.pi-tabs{display:flex;gap:8px;margin:12px 0}.pi-tabs .tab{border:1px solid #315665;border-radius:8px;padding:9px 18px;cursor:pointer}.pi-chart{display:grid;gap:9px}.pi-chart-row{display:grid;grid-template-columns:minmax(140px,220px) minmax(180px,1fr) 110px;align-items:center;gap:12px}.pi-chart-name{font-weight:700;font-size:12px}.pi-chart-track{height:14px;background:#1a3039;border-radius:10px;position:relative;overflow:hidden}.pi-chart-zero{position:absolute;left:50%;top:0;bottom:0;width:1px;background:#d8e9eb;z-index:2}.pi-chart-bar{position:absolute;top:0;height:100%;border-radius:3px}.pi-chart-bar.positive{background:#54c7a0}.pi-chart-bar.negative{background:#e07d5a}.pi-chart-value{text-align:right;font-weight:800;font-size:12px}.notice{background:#172c35;border:1px solid #386675;border-radius:10px;padding:12px;color:#d9edf0;font-size:12px;line-height:1.6}@media(max-width:760px){body{padding:8px}.profile{grid-template-columns:repeat(2,1fr)}.panel{padding:12px}}

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
</style>
<link rel="icon" type="image/png" href="{{ asset('evebuyback-logo.png') }}">
</head>
<body><main>
<header class="top"><div><img class="eb-logo" style="width:300px" src="{{ asset('evebuyback-logo.png') }}" alt="EveBuyback"><h1>Ore Refining Comparison</h1></div><a href="{{ route('home') }}">Back to Buyback</a></header>
<section class="panel">
<h2 style="margin:0 0 10px">Refining profile</h2>
<div class="profile">
<div class="stat"><span>Comparison quantity</span><strong>{{ number_format($quantity) }} units of each ore</strong></div>
<div class="stat"><span>Structure</span><strong>Null-sec Tatara · T2 rig</strong></div>
<div class="stat"><span>Skills / implant</span><strong>All skills V · RX-804</strong></div>
<div class="stat"><span>Reprocessing yield / tax</span><strong>{{ number_format($yieldPercent,3) }}% / {{ number_format($taxPercent,2) }}%</strong></div>
<div class="stat"><span>Ore sale basket · all priced ore</span><strong>{{ number_format($oreSaleValueTotal,2) }} ISK</strong></div>
<div class="stat"><span>PI basket · 1,000 of each</span><strong>{{ number_format($piBasketValue,2) }} ISK</strong><span>{{ number_format($piPricedCount) }} / {{ number_format($piItems->count()) }} commodities priced</span></div>
<div class="stat"><span>Combined basket · ore + PI</span><strong>{{ number_format($combinedSaleValueTotal,2) }} ISK</strong></div>
</div>
<p class="muted">Regular published asteroid ore is included, compressed and uncompressed; mission-specific ores are excluded. Reprocessing uses complete SDE portions only. The PI basket values 1,000 units of every published Planetary Commodity at Jita buy prices. Items without a stored price are excluded from basket totals.</p>
</section>
<section class="panel">
<div class="notice">Jita IV - Moon 4 buy-order snapshots · {{ $rows->count() }} regular ore types · {{ $pricedCount }} with complete ore and mineral pricing. {{ $piItems->count() }} Planetary Commodities loaded, {{ $piPricedCount }} priced. Combined priced sell basket: <strong>{{ number_format($combinedSaleValueTotal,2) }} ISK</strong> (10,000 units of each ore plus 1,000 units of each PI item). Missing prices and incomplete SDE recipes are not estimated.</div>
</section>
<section class="panel">
<h2 style="margin:0 0 6px">All ore · value of {{ number_format($quantity) }} units each</h2>
<div class="table-wrap"><table>
<thead><tr><th>Ore</th><th>Quantity</th><th>Refine batches</th><th>Jita buy / unit</th><th>Sell ore · total</th><th>Refined minerals · net</th><th>Difference</th><th>Better value</th><th>Minerals</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
<td class="ore">{{ $row['name'] }}</td>
<td>{{ number_format($row['quantity']) }}@if($row['leftover_quantity'] > 0)<div class="muted">{{ number_format($row['leftover_quantity']) }} below a full portion</div>@endif</td>
<td>{{ number_format($row['batches']) }} <span class="muted">({{ number_format($row['processed_quantity']) }} units)</span></td>
<td>{{ $row['ore_buy_price'] !== null ? number_format($row['ore_buy_price'],2).' ISK' : 'No price' }}</td>
<td>{{ $row['ore_market_value'] !== null ? number_format($row['ore_market_value'],2).' ISK' : 'No price' }}</td>
<td>{{ $row['has_complete_mineral_prices'] ? number_format($row['refined_net'],2).' ISK' : 'Incomplete' }}</td>
<td class="{{ $row['difference'] === null ? 'unknown' : ($row['difference'] > 0 ? 'refine' : 'sell') }}">{{ $row['difference'] !== null ? (($row['difference'] > 0 ? '+' : '').number_format($row['difference'],2).' ISK') : '—' }}</td>
<td class="winner {{ $row['winner'] === 'refine' ? 'refine' : ($row['winner'] === 'sell ore' ? 'sell' : 'unknown') }}">{{ strtoupper($row['winner']) }}</td>
<td><button class="details" type="button" onclick="document.getElementById('materials-{{ $row['type_id'] }}').classList.toggle('open')">View</button></td>
</tr>
<tr><td colspan="9" class="materials" id="materials-{{ $row['type_id'] }}">
@if(count($row['materials']))
<strong>{{ $row['name'] }} · mineral output from {{ number_format($row['processed_quantity']) }} ore units</strong>
<ul>@foreach($row['materials'] as $material)
<li>{{ $material['name'] }}: {{ number_format($material['yielded_quantity']) }} units · {{ $material['buy_price'] !== null ? number_format($material['buy_price'],2).' ISK buy each · '.number_format($material['value'],2).' ISK total' : 'No Jita buy price' }}</li>
@endforeach</ul>
@else
No CCP SDE reprocessing recipe found for this ore.
@endif
</td></tr>
@empty
<tr><td colspan="9" style="text-align:left">No published asteroid ore types found. Import the CCP SDE types, taxonomy, and typeMaterials datasets first.</td></tr>
@endforelse
</tbody></table></div>
</section>
<section class="panel">
<h2 style="margin:0 0 6px">Planetary Interaction · value of {{ number_format($piQuantity) }} units each</h2>
<p class="muted">Every published Planetary Commodity is listed across P1, P2, P3, and P4. Each commodity is valued as a 1,000-unit lot using the stored Jita buy price.</p>
<div class="table-wrap"><table style="min-width:760px">
<thead><tr><th>Commodity</th><th>PI tier</th><th>Quantity</th><th>Jita buy / unit</th><th>Value · {{ number_format($piQuantity) }} units</th></tr></thead>
<tbody>
@forelse($piItems as $item)
<tr>
<td class="ore">{{ $item['name'] }}</td>
<td>{{ $item['tier'] }}</td>
<td>{{ number_format($item['quantity']) }}</td>
<td>{{ $item['unit_buy_price'] !== null ? number_format($item['unit_buy_price'],2).' ISK' : 'No price' }}</td>
<td>{{ $item['market_value'] !== null ? number_format($item['market_value'],2).' ISK' : 'No price' }}</td>
</tr>
@empty
<tr><td colspan="5" style="text-align:left">No Planetary Commodities found. Import the CCP SDE types and taxonomy datasets first.</td></tr>
@endforelse
</tbody>
<tfoot><tr><th colspan="4">Total PI basket · priced commodities only</th><th>{{ number_format($piBasketValue,2) }} ISK</th></tr></tfoot>
</table></div>
<div class="notice" style="margin-top:12px">Combined basket: {{ number_format($oreSaleValueTotal,2) }} ISK for 10,000 units of each priced regular ore, plus {{ number_format($piBasketValue,2) }} ISK for 1,000 units of each priced PI commodity = <strong>{{ number_format($combinedSaleValueTotal,2) }} ISK</strong>. Commodities without a Jita buy price are not included.</div>
</section>
<section class="panel" id="pi-profitability">
<h2 style="margin:0 0 6px">PI production profitability · admin</h2>
<p class="muted">Each recipe is valued per schematic cycle. Input materials are costed at Jita buy, and the output is valued at its Jita buy price. Import tax is applied to total input cost; export tax is applied to output value. Profit percentage is net profit divided by input cost plus import tax.</p>
@if(session('status'))<div class="notice" style="margin:10px 0">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice" style="margin:10px 0">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="post" action="{{ route('ore-values.pi-taxes') }}" class="panel" style="margin:12px 0;padding:14px">
@csrf
<input type="hidden" name="tier" id="pi-tax-tier" value="{{ $activePiTier }}">
<div class="profile">
<label class="stat"><span>Import tax · % of input Jita value</span><input type="number" name="import_tax_percent" min="0" max="100" step="0.01" required value="{{ number_format($importTaxPercent,2,'.','') }}" style="width:100%;padding:9px;border-radius:7px;border:1px solid #315665;margin-top:8px"></label>
<label class="stat"><span>Export tax · % of output Jita value</span><input type="number" name="export_tax_percent" min="0" max="100" step="0.01" required value="{{ number_format($exportTaxPercent,2,'.','') }}" style="width:100%;padding:9px;border-radius:7px;border:1px solid #315665;margin-top:8px"></label>
<div class="stat" style="display:flex;align-items:end"><button type="submit" style="border:0;border-radius:8px;padding:10px 16px;cursor:pointer;font-weight:800">Save tax settings</button></div>
</div>
</form>
<div class="pi-tabs" role="tablist" aria-label="PI production tier">
<button type="button" class="tab {{ $activePiTier === 'P2' ? 'active' : '' }}" id="pi-tab-P2" onclick="showPiTier('P2')">P2</button>
<button type="button" class="tab {{ $activePiTier === 'P3' ? 'active' : '' }}" id="pi-tab-P3" onclick="showPiTier('P3')">P3</button>
<button type="button" class="tab {{ $activePiTier === 'P4' ? 'active' : '' }}" id="pi-tab-P4" onclick="showPiTier('P4')">P4</button>
</div>
@foreach(['P2','P3','P4'] as $tier)
<div class="pi-tier" id="pi-tier-{{ $tier }}" style="{{ $activePiTier === $tier ? '' : 'display:none' }}">
<h3 style="margin:14px 0 8px">{{ $tier }} profitability · per production cycle</h3>
@if(count($piProfitability[$tier]))
<div class="pi-chart">
@foreach($piProfitability[$tier] as $row)
<div class="pi-chart-row">
<div class="pi-chart-name">{{ $row['name'] }}</div>
<div class="pi-chart-track"><span class="pi-chart-zero"></span>
@if($row['profit_percent'] !== null)
<span class="pi-chart-bar {{ $row['is_profit'] ? 'positive' : 'negative' }}" style="{{ $row['is_profit'] ? 'left:50%' : 'right:50%' }};width:{{ number_format($row['chart_width'],2,'.','') }}%"></span>
@endif
</div>
<div class="pi-chart-value {{ $row['profit_percent'] === null ? 'unknown' : ($row['is_profit'] ? 'sell' : 'refine') }}">{{ $row['profit_percent'] !== null ? number_format($row['profit_percent'],2).'%' : 'Missing price' }}</div>
</div>
@endforeach
</div>
<div class="table-wrap" style="margin-top:16px"><table style="min-width:1100px">
<thead><tr><th>Product</th><th>Output / cycle</th><th>Required inputs / cycle</th><th>Input cost</th><th>Import tax</th><th>Output value</th><th>Export tax</th><th>Net profit / loss</th><th>ROI</th></tr></thead>
<tbody>
@foreach($piProfitability[$tier] as $row)
<tr>
<td class="ore">{{ $row['name'] }}</td>
<td>{{ number_format($row['quantity']) }}</td>
<td style="text-align:left;white-space:normal;min-width:260px">@foreach($row['inputs'] as $input)<div>{{ number_format($input['quantity']) }} × {{ $input['name'] }} · {{ $input['unit_price'] !== null ? number_format($input['unit_price'],2).' ISK each' : 'No Jita price' }} · {{ $input['line_cost'] !== null ? number_format($input['line_cost'],2).' ISK' : '—' }}</div>@endforeach</td>
<td>{{ number_format($row['inputs_cost'],2) }} ISK</td>
<td>{{ $row['import_tax'] !== null ? number_format($row['import_tax'],2).' ISK' : '—' }}</td>
<td>{{ $row['output_revenue'] !== null ? number_format($row['output_revenue'],2).' ISK' : 'No Jita price' }}</td>
<td>{{ $row['export_tax'] !== null ? number_format($row['export_tax'],2).' ISK' : '—' }}</td>
<td class="{{ $row['net_profit'] === null ? 'unknown' : ($row['net_profit'] >= 0 ? 'sell' : 'refine') }}">{{ $row['net_profit'] !== null ? number_format($row['net_profit'],2).' ISK' : 'Incomplete price data' }}</td>
<td class="{{ $row['profit_percent'] === null ? 'unknown' : ($row['profit_percent'] >= 0 ? 'sell' : 'refine') }}">{{ $row['profit_percent'] !== null ? number_format($row['profit_percent'],2).'%' : '—' }}</td>
</tr>
@endforeach
</tbody></table></div>
@else
<div class="notice">No {{ $tier }} recipes are loaded. Import the CCP planetSchematics.jsonl file after deploying the schematic migration.</div>
@endif
</div>
@endforeach
</section>
<p class="muted">Values are estimates from the latest stored Jita buy-price snapshots, not guaranteed execution prices or available volume. Ore comparison excludes hauling, market fees, and slippage. PI profitability compares buying each recipe's inputs and selling its output at Jita buy prices, less the configured import and export taxes; it excludes hauling and facility costs.</p>
<script>
function showPiTier(tier){
  ['P2','P3','P4'].forEach(function(key){
    var panel=document.getElementById('pi-tier-'+key);
    var tab=document.getElementById('pi-tab-'+key);
    if(panel) panel.style.display=key===tier?'block':'none';
    if(tab) tab.classList.toggle('active',key===tier);
  });
  var hidden=document.getElementById('pi-tax-tier');
  if(hidden) hidden.value=tier;
}
</script>
</main><footer style="margin:28px auto 12px;text-align:center;color:#7f9ba5;font-size:12px">(c) 2027 My Eve Online</footer></body></html>