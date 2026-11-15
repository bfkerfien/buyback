<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appraisal {{ $quote->public_token }} · EveBuyback</title>
<style>
 :root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(2px 2px at 8% 16%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 18% 72%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 29% 38%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 43% 82%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 57% 14%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 69% 55%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 83% 27%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 94% 78%,#fff 45%,transparent 60%),radial-gradient(ellipse at 50% -10%,#0d5967 0,transparent 52%),radial-gradient(ellipse at 88% 82%,#3d2512 0,transparent 42%),radial-gradient(ellipse at 8% 90%,#0d2b38 0,transparent 40%),#060d13;min-height:100vh}main{max-width:1050px;margin:auto}.muted{color:#9bb5bf}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:20px;margin:18px 0}h1{font-size:30px;margin:8px 0 20px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#09151c;padding:16px;border-radius:10px}.stat strong{display:block;font-size:21px;margin-top:8px}.money{font-size:27px!important;color:#ffb45f}table{width:100%;border-collapse:separate;border-spacing:0 7px}th{color:#9bb5bf;font-size:12px;text-align:left;padding:8px 12px}td{background:#172c35;padding:12px;font-size:13px}td:first-child{border-radius:8px 0 0 8px}td:last-child{border-radius:0 8px 8px 0}.right{text-align:right}.item{font-weight:700}.sub{display:block;color:#8faeb8;font-size:11px;margin-top:4px}.why{background:#176273;color:#eaf7f8;border:0;border-radius:6px;padding:7px 10px;cursor:pointer}.details{display:none;color:#aebdd0;font-size:12px;padding:10px 12px;background:#0b1820;border-radius:7px;margin-top:6px}.details.open{display:block}.rate{font-weight:700;color:#ffb45f}@media(max-width:700px){body{padding:12px}.panel{padding:14px}.grid{grid-template-columns:1fr}.table-wrap{overflow-x:auto}table{min-width:720px}}

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
<nav style="display:flex;justify-content:space-between;align-items:center;gap:12px"><img class="eb-logo" style="width:280px" src="{{ asset('evebuyback-logo.png') }}" alt="EveBuyback"><a href="{{ route('home') }}" style="color:#62d9e9;text-decoration:none;border:1px solid #386675;border-radius:8px;padding:9px 13px">Home</a></nav><h1>Appraisal result</h1>
<section class="panel"><div class="grid">
<div class="stat"><span class="muted">Gross market value</span><strong>{{ number_format((float)$quote->gross_value, 2) }} ISK</strong></div>
<div class="stat"><span class="muted">Service fees</span><strong>{{ number_format((float)$quote->fees, 2) }} ISK</strong></div>
<div class="stat"><span class="muted">Estimated buyback</span><strong class="money">{{ number_format((float)$quote->payout_value, 2) }} ISK</strong></div>
</div><p class="muted">Origin: {{ $quote->origin_system ?: 'Not specified' }} · Priced {{ $quote->priced_at }}</p></section>
<section class="panel"><h2>Appraised items</h2><div class="table-wrap"><table><thead><tr><th>Item</th><th class="right">Quantity</th><th class="right">Buyback price</th><th class="right">Effective rate</th><th class="right">Calculation</th></tr></thead><tbody>
@foreach($items as $item)
@php($source = json_decode($item->source_data ?? '{}', true) ?: [])
<tr><td><span class="item">{{ $item->type_name }}</span><span class="sub">@if(($source['valuation_mode'] ?? 'compressed') === 'reprocessed')Reprocessed as {{ number_format((int)($source['output_quantity'] ?? 0)) }} {{ $source['output_name'] ?? 'material' }} · @endif{{ $source['category_name'] ?? 'Uncategorized' }} · Jita {{ ucfirst($source['price_side'] ?? 'buy') }}</span></td>
<td class="right">{{ number_format($item->quantity) }}</td><td class="right">{{ number_format((float)$item->line_value * ((float)($source['payout_percent'] ?? 90) / 100), 2) }} ISK</td>
<td class="right"><span class="rate">{{ number_format((float)($source['payout_percent'] ?? 90), 2) }}%</span></td>
<td class="right"><button class="why" type="button" onclick="const d=document.getElementById('why-{{ $item->id }}');d.classList.toggle('open')">Why?</button></td></tr>
<tr><td colspan="5"><div class="details" id="why-{{ $item->id }}">@if(($source['valuation_mode'] ?? 'compressed') === 'reprocessed')Valuation: reprocessed output. {{ number_format($item->quantity) }} {{ $item->type_name }} × {{ number_format((float)($source['conversion_ratio'] ?? 0), 8) }} = {{ number_format((int)($source['output_quantity'] ?? 0)) }} {{ $source['output_name'] ?? 'material' }}. Jita {{ ucfirst($source['price_side'] ?? 'buy') }} price: {{ number_format((float)($source['output_unit_price'] ?? 0), 2) }} ISK per output unit. @else Valuation: compressed ore market price. @endif Category used for payout rate: {{ $source['category_name'] ?? 'Uncategorized' }}. Rate source: {{ ($source['rate_source'] ?? 'default') === 'category' ? 'Configured category rate' : 'Default buyback rate' }}. Effective value: {{ number_format((float)$item->unit_price, 2) }} ISK per submitted unit × {{ number_format($item->quantity) }} = {{ number_format((float)$item->line_value, 2) }} ISK market value. Buyback rate: {{ number_format((float)($source['payout_percent'] ?? 90), 2) }}%. Offer: {{ number_format((float)$item->line_value * ((float)($source['payout_percent'] ?? 90) / 100), 2) }} ISK. Price observed: {{ $source['observed_at'] ?? 'Not recorded' }}.</div></td></tr>
@endforeach
</tbody></table></div></section>
<p class="muted">Quote reference: {{ $quote->public_token }} · Estimate only; this is not a completed purchase or payout.</p>
</main><footer style="margin:28px auto 12px;text-align:center;color:#7f9ba5;font-size:12px">(c) 2027 My Eve Online</footer></body></html>
