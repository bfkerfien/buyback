<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ore Refining · EveBuyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:18px;background:radial-gradient(ellipse at 50% -10%,#0d5967 0,transparent 52%),#060d13;min-height:100vh}main{max-width:1500px;margin:auto}.muted{color:#9bb5bf;font-size:12px;line-height:1.6}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:18px;margin:14px 0}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.top a{color:#62d9e9;text-decoration:none;border:1px solid #386675;border-radius:8px;padding:8px 12px}h1{font-size:26px;margin:8px 0}.profile{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.stat{background:#09151c;border-radius:10px;padding:12px}.stat span{display:block;color:#9bb5bf;font-size:12px}.stat strong{display:block;margin-top:6px;font-size:16px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1050px}th,td{padding:10px;border-bottom:1px solid #294a56;text-align:right;font-size:12px;white-space:nowrap}th{color:#9deaf0;position:sticky;top:0;background:#101e27}th:first-child,td:first-child{text-align:left}.ore{font-weight:700;color:#eaf7f8}.sell{color:#9de0b1}.refine{color:#ffb45f}.unknown{color:#e8c27c}.winner{font-weight:800}.details{background:#315665;color:#fff;border:0;border-radius:6px;padding:6px 9px;cursor:pointer}.materials{display:none;text-align:left;white-space:normal;padding:12px;background:#09151c}.materials.open{display:table-cell}.materials ul{margin:0;padding-left:18px;line-height:1.7}.notice{background:#172c35;border:1px solid #386675;border-radius:10px;padding:12px;color:#d9edf0;font-size:12px;line-height:1.6}@media(max-width:760px){body{padding:8px}.profile{grid-template-columns:repeat(2,1fr)}.panel{padding:12px}}

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
</div>
<p class="muted">Every published asteroid ore type is included, both compressed and uncompressed. Reprocessing uses complete SDE portions only; any units below the next portion size are not refined. Mineral output is calculated at the stated yield, then valued using Jita buy orders.</p>
</section>
<section class="panel">
<div class="notice">Jita IV - Moon 4 buy-order snapshots · {{ $rows->count() }} ore types · {{ $pricedCount }} with complete ore and mineral pricing. Latest ore-price snapshot: {{ $latestObservedAt ?: 'No Jita snapshots available' }}. Missing prices or SDE recipes are shown as incomplete, not estimated.</div>
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
<p class="muted">Values are estimates from the latest stored Jita buy-price snapshots, not guaranteed execution prices or available volume. Comparison excludes hauling, market fees, and slippage. Reprocessing output is based on CCP SDE portion recipes and the fixed maximum-skill Tatara profile shown above.</p>
</main></body></html>