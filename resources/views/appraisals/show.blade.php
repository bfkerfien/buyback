<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appraisal {{ $quote->public_token }} · Triglavian Buyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#0b1018;color:#e8edf5}*{box-sizing:border-box}body{margin:0;padding:28px}main{max-width:1000px;margin:auto}.panel{background:#111a27;border:1px solid #29374a;border-radius:16px;padding:24px;margin:18px 0}h1{font-size:32px;margin:6px 0 22px}.muted{color:#9aabc0}.money{font-size:32px;font-weight:750}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#0b1420;padding:16px;border-radius:12px}.stat strong{display:block;font-size:20px;margin-top:8px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 8px;border-bottom:1px solid #29374a}td:last-child,th:last-child{text-align:right}.right{text-align:right}@media(max-width:650px){.grid{grid-template-columns:1fr}body{padding:14px}.panel{padding:16px}}
</style>
</head>
<body><main>
<p class="muted">TRIGLAVIAN CONSORTIUM · BUYBACK APPRAISAL</p>
<h1>Appraisal result</h1>
<section class="panel">
<div class="grid">
<div class="stat"><span class="muted">Gross market value</span><strong>{{ number_format((float)$quote->gross_value, 2) }} ISK</strong></div>
<div class="stat"><span class="muted">Service fee</span><strong>{{ number_format((float)$quote->fees, 2) }} ISK</strong></div>
<div class="stat"><span class="muted">Estimated payout</span><strong class="money">{{ number_format((float)$quote->payout_value, 2) }} ISK</strong></div>
</div>
<p class="muted">Origin: {{ $quote->origin_system ?: 'Not specified' }} · Priced {{ $quote->priced_at }}</p>
</section>
<section class="panel">
<h2>Items</h2>
<table><thead><tr><th>Item</th><th class="right">Quantity</th><th class="right">Unit price</th><th class="right">Total</th></tr></thead><tbody>
@foreach($items as $item)
<tr><td>{{ $item->type_name }}</td><td class="right">{{ number_format($item->quantity) }}</td><td class="right">{{ number_format((float)$item->unit_price, 2) }}</td><td class="right">{{ number_format((float)$item->line_value, 2) }}</td></tr>
@endforeach
</tbody></table>
</section>
<p class="muted">Quote reference: {{ $quote->public_token }} · This is an estimate, not a completed purchase or payout.</p>
</main></body></html>
