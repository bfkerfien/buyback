<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appraisal {{ $quote->public_token }} · Triglavian Buyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#0b1018;color:#e8edf5}*{box-sizing:border-box}body{margin:0;padding:24px}main{max-width:1050px;margin:auto}.muted{color:#9aabc0}.panel{background:#111a27;border:1px solid #29374a;border-radius:14px;padding:20px;margin:18px 0}h1{font-size:30px;margin:8px 0 20px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#0b1420;padding:16px;border-radius:10px}.stat strong{display:block;font-size:21px;margin-top:8px}.money{font-size:27px!important;color:#e9c477}table{width:100%;border-collapse:separate;border-spacing:0 7px}th{color:#9aabc0;font-size:12px;text-align:left;padding:8px 12px}td{background:#1a2635;padding:12px;font-size:13px}td:first-child{border-radius:8px 0 0 8px}td:last-child{border-radius:0 8px 8px 0}.right{text-align:right}.item{font-weight:700}.sub{display:block;color:#91a3b9;font-size:11px;margin-top:4px}.why{background:#34465e;color:#e8edf5;border:0;border-radius:6px;padding:7px 10px;cursor:pointer}.details{display:none;color:#aebdd0;font-size:12px;padding:10px 12px;background:#101925;border-radius:7px;margin-top:6px}.details.open{display:block}.rate{font-weight:700;color:#e9c477}@media(max-width:700px){body{padding:12px}.panel{padding:14px}.grid{grid-template-columns:1fr}.table-wrap{overflow-x:auto}table{min-width:720px}}
</style>
</head>
<body><main>
<nav style="display:flex;justify-content:space-between;align-items:center;gap:12px"><p class="muted">TRIGLAVIAN CONSORTIUM · BUYBACK APPRAISAL</p><a href="{{ route('home') }}" style="color:#d5a84f;text-decoration:none;border:1px solid #43536a;border-radius:8px;padding:9px 13px">Home</a></nav><h1>Appraisal result</h1>
<section class="panel"><div class="grid">
<div class="stat"><span class="muted">Gross market value</span><strong>{{ number_format((float)$quote->gross_value, 2) }} ISK</strong></div>
<div class="stat"><span class="muted">Service fees</span><strong>{{ number_format((float)$quote->fees, 2) }} ISK</strong></div>
<div class="stat"><span class="muted">Estimated buyback</span><strong class="money">{{ number_format((float)$quote->payout_value, 2) }} ISK</strong></div>
</div><p class="muted">Origin: {{ $quote->origin_system ?: 'Not specified' }} · Priced {{ $quote->priced_at }}</p></section>
<section class="panel"><h2>Appraised items</h2><div class="table-wrap"><table><thead><tr><th>Item</th><th class="right">Quantity</th><th class="right">Buyback price</th><th class="right">Effective rate</th><th class="right">Calculation</th></tr></thead><tbody>
@foreach($items as $item)
@php($source = json_decode($item->source_data ?? '{}', true) ?: [])
<tr><td><span class="item">{{ $item->type_name }}</span><span class="sub">{{ $source['category_name'] ?? 'Uncategorized' }} · Jita {{ ucfirst($source['price_side'] ?? 'buy') }}</span></td>
<td class="right">{{ number_format($item->quantity) }}</td><td class="right">{{ number_format((float)$item->line_value * ((float)($source['payout_percent'] ?? 90) / 100), 2) }} ISK</td>
<td class="right"><span class="rate">{{ number_format((float)($source['payout_percent'] ?? 90), 2) }}%</span></td>
<td class="right"><button class="why" type="button" onclick="const d=document.getElementById('why-{{ $item->id }}');d.classList.toggle('open')">Why?</button></td></tr>
<tr><td colspan="5"><div class="details" id="why-{{ $item->id }}">Category: {{ $source['category_name'] ?? 'Uncategorized' }}. Rate source: {{ ($source['rate_source'] ?? 'default') === 'category' ? 'Configured category rate' : 'Default buyback rate' }}. Jita unit price: {{ number_format((float)$item->unit_price, 2) }} ISK × {{ number_format($item->quantity) }} = {{ number_format((float)$item->line_value, 2) }} ISK market value. Buyback rate: {{ number_format((float)($source['payout_percent'] ?? 90), 2) }}%. Offer: {{ number_format((float)$item->line_value * ((float)($source['payout_percent'] ?? 90) / 100), 2) }} ISK. Price observed: {{ $source['observed_at'] ?? 'Not recorded' }}.</div></td></tr>
@endforeach
</tbody></table></div></section>
<p class="muted">Quote reference: {{ $quote->public_token }} · Estimate only; this is not a completed purchase or payout.</p>
</main></body></html>
