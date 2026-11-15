<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ore Refining · Lentivirus Buyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#090713;color:#f0eaff}*{box-sizing:border-box}body{margin:0;padding:18px;background:radial-gradient(ellipse at 50% -10%,#7137a8 0,transparent 52%),#070510;min-height:100vh}main{max-width:1500px;margin:auto}.muted{color:#b69bdc;font-size:12px;line-height:1.6}.panel{background:#171126;border:1px solid #49366b;border-radius:14px;padding:18px;margin:14px 0}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.top a{color:#c6a4ff;text-decoration:none;border:1px solid #654b8b;border-radius:8px;padding:8px 12px}h1{font-size:26px;margin:8px 0}.profile{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.stat{background:#100b1d;border-radius:10px;padding:12px}.stat span{display:block;color:#b69bdc;font-size:12px}.stat strong{display:block;margin-top:6px;font-size:16px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1050px}th,td{padding:10px;border-bottom:1px solid #49366b;text-align:right;font-size:12px;white-space:nowrap}th{color:#cbb6e8;position:sticky;top:0;background:#171126}th:first-child,td:first-child{text-align:left}.ore{font-weight:700;color:#f0eaff}.sell{color:#9de0b1}.refine{color:#d8b5ff}.unknown{color:#e8c27c}.winner{font-weight:800}.details{background:#4b3767;color:#fff;border:0;border-radius:6px;padding:6px 9px;cursor:pointer}.materials{display:none;text-align:left;white-space:normal;padding:12px;background:#100b1d}.materials.open{display:table-cell}.materials ul{margin:0;padding-left:18px;line-height:1.7}.notice{background:#241936;border:1px solid #634a88;border-radius:10px;padding:12px;color:#e0d0f4;font-size:12px;line-height:1.6}@media(max-width:760px){body{padding:8px}.profile{grid-template-columns:repeat(2,1fr)}.panel{padding:12px}}
</style>
</head>
<body><main>
<header class="top"><div><div class="muted">LENTIVIRUS BUYBACK · MARKET TOOLS</div><h1>Ore Refining Comparison</h1></div><a href="{{ route('home') }}">Back to Buyback</a></header>
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