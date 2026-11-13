<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ore Refining Comparison · Lentivirus Buyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#090713;color:#f0eaff}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(ellipse at 50% -10%,#7137a8 0,transparent 52%),#070510;min-height:100vh}main{max-width:1400px;margin:auto}.muted{color:#b69bdc;font-size:12px;line-height:1.6}.panel{background:#171126;border:1px solid #49366b;border-radius:14px;padding:20px;margin:18px 0}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.top a{color:#c6a4ff;text-decoration:none;border:1px solid #654b8b;border-radius:8px;padding:9px 13px}h1{font-size:30px;margin:12px 0}.profile{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.stat{background:#100b1d;border-radius:10px;padding:14px}.stat span{display:block;color:#b69bdc;font-size:12px}.stat strong{display:block;margin-top:7px;font-size:18px}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:1050px}th,td{padding:10px 12px;border-bottom:1px solid #49366b;text-align:right;font-size:12px;white-space:nowrap}th{color:#cbb6e8;position:sticky;top:0;background:#171126}th:first-child,td:first-child{text-align:left}.ore{font-weight:700;color:#f0eaff}.winner{font-weight:800}.sell{color:#9de0b1}.refine{color:#d8b5ff}.unknown{color:#e8c27c}.materials{display:none;text-align:left;white-space:normal;padding:12px;background:#100b1d}.materials.open{display:table-cell}.materials ul{margin:0;padding-left:18px;line-height:1.7}.details{background:#4b3767;color:#fff;border:0;border-radius:6px;padding:6px 9px;cursor:pointer}.notice{background:#241936;border:1px solid #634a88;border-radius:10px;padding:12px;color:#e0d0f4;font-size:12px;line-height:1.6}@media(max-width:760px){body{padding:12px}.profile{grid-template-columns:repeat(2,1fr)}.summary{grid-template-columns:1fr}.panel{padding:14px}}
</style>
</head>
<body><main>
<header class="top"><div><div class="muted">LENTIVIRUS BUYBACK · MARKET TOOLS</div><h1>Ore Refining Comparison</h1></div><a href="{{ route('home') }}">Back to Buyback</a></header>
<section class="panel">
<h2 style="margin:0 0 12px">Your refining profile</h2>
<div class="profile">
<div class="stat"><span>Structure</span><strong>Tatara · T2 rig</strong></div>
<div class="stat"><span>Security</span><strong>Null-sec</strong></div>
<div class="stat"><span>Skills / implant</span><strong>All V · RX-804</strong></div>
<div class="stat"><span>Yield / tax</span><strong>{{ number_format($yieldPercent,3) }}% / {{ number_format($taxPercent,2) }}%</strong></div>
</div>
<p class="muted">Fixed comparison profile supplied for this tool. Mineral output is calculated from CCP SDE reprocessing materials, applying the yield to each material and rounding down. The 0.01% structure tax is deducted from refined mineral value.</p>
</section>
<section class="panel">
<div class="summary">
<div class="stat"><span>Compressed ore types</span><strong>{{ number_format($rows->count()) }}</strong></div>
<div class="stat"><span>Currently comparable</span><strong>{{ number_format($pricedCount) }}</strong></div>
<div class="stat"><span>Reprocess / sell compressed</span><strong><span class="refine">{{ $reprocessCount }}</span> / <span class="sell">{{ $sellCompressedCount }}</span></strong></div>
</div>
<p class="notice" style="margin-top:14px">Prices use the latest Jita IV - Moon 4 buy-order snapshots already collected by Buyback. This page does not create contracts or appraisals. Missing ore or mineral buy prices are marked as incomplete rather than guessed. Price freshness: {{ $latestObservedAt ?: 'No Jita snapshots available' }}.</p>
</section>
<section class="panel">
<h2 style="margin:0 0 6px">Compressed asteroid ore: sell or reprocess?</h2>
<p class="muted">Values are per one SDE portion of the compressed type. Compare the compressed ore's immediate Jita buy value against the minerals produced at your configured refining yield, less the Tatara tax. Expand a row to inspect the mineral breakdown.</p>
<div class="table-wrap"><table>
<thead><tr><th>Compressed ore</th><th>Portion size</th><th>Jita buy · ore</th><th>Mineral value</th><th>Refining tax</th><th>Refined net</th><th>Difference</th><th>Better option</th><th>Breakdown</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
<td class="ore">{{ $row['name'] }}</td>
<td>{{ number_format($row['portion_size']) }}</td>
<td>{{ $row['ore_buy_price'] !== null ? number_format($row['ore_buy_price'],2).' ISK' : '—' }}</td>
<td>{{ $row['has_complete_mineral_prices'] ? number_format($row['refined_gross'],2).' ISK' : 'Incomplete' }}</td>
<td>{{ $row['has_complete_mineral_prices'] ? number_format($row['refined_tax'],2).' ISK' : '—' }}</td>
<td>{{ $row['has_complete_mineral_prices'] ? number_format($row['refined_net'],2).' ISK' : '—' }}</td>
<td class="{{ $row['difference'] === null ? 'unknown' : ($row['difference'] > 0 ? 'refine' : 'sell') }}">{{ $row['difference'] !== null ? (($row['difference'] > 0 ? '+' : '').number_format($row['difference'],2).' ISK') : '—' }}</td>
<td class="winner {{ $row['winner'] === 'reprocess' ? 'refine' : ($row['winner'] === 'sell compressed' ? 'sell' : 'unknown') }}">{{ strtoupper($row['winner']) }}</td>
<td><button class="details" type="button" onclick="document.getElementById('materials-{{ $row['type_id'] }}').classList.toggle('open')">Minerals</button></td>
</tr>
<tr><td colspan="9" class="materials" id="materials-{{ $row['type_id'] }}">
@if(count($row['materials']))
<ul>@foreach($row['materials'] as $material)
<li>{{ $material['name'] }}: {{ number_format($material['base_quantity']) }} base → {{ number_format($material['yielded_quantity']) }} at yield · {{ $material['buy_price'] !== null ? number_format($material['buy_price'],2).' ISK buy · '.number_format($material['value'],2).' ISK value' : 'No Jita buy price' }}</li>
@endforeach</ul>
@else
No CCP reprocessing material data found for this type.
@endif
</td></tr>
@empty
<tr><td colspan="9" style="text-align:left">No compressed asteroid ore records found. Import the CCP SDE type materials and item taxonomy first.</td></tr>
@endforelse
</tbody></table></div>
</section>
<p class="muted">Jita buy orders are snapshots, not guaranteed execution prices or available volume. Comparison excludes hauling, market fees, and any slippage from selling more units than the visible buy orders can absorb. CCP SDE reprocessing recipes are per portion; yield and structure tax are applied by this page.</p>
</main></body></html>
