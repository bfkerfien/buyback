<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $appName }} · EVE Online Buyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#0b1018;color:#e8edf5}*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:30px;background:radial-gradient(ellipse at 50% -20%,#1d3550 0,transparent 55%),#0b1018}.wrap{max-width:1080px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;padding:14px 0 42px}.brand{font-weight:800;letter-spacing:.08em;font-size:13px;color:#b9cce2}.tag{color:#8fa7c3;font-size:12px;text-transform:uppercase;letter-spacing:.15em}.hero{max-width:760px;margin:30px auto 26px;text-align:center}.hero h1{font-size:clamp(38px,7vw,64px);line-height:1.02;letter-spacing:-.05em;margin:14px 0}.hero p{color:#aab8ca;line-height:1.7}.panel{background:#111a27;border:1px solid #29374a;border-radius:18px;padding:26px;max-width:850px;margin:28px auto;box-shadow:0 25px 90px #0005}label{display:block;font-size:13px;color:#b8c8da;margin:16px 0 8px}input,textarea{width:100%;border:1px solid #34465e;background:#0b1420;color:#edf4fc;border-radius:10px;padding:13px;font:inherit}textarea{min-height:220px;resize:vertical;font-family:ui-monospace,monospace;font-size:13px}button{margin-top:18px;border:0;border-radius:10px;padding:14px 20px;background:#d5a84f;color:#17130b;font-weight:800;font-size:14px;cursor:pointer}button:hover{filter:brightness(1.08)}.hint,.muted{color:#8fa1b7;font-size:12px;line-height:1.6}.error{color:#ff9d9d;margin-top:8px}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:850px;margin:20px auto}.feature{border:1px solid #263448;background:#101925;border-radius:12px;padding:16px}.feature strong{display:block;margin-bottom:6px}.feature span{font-size:12px;color:#8fa1b7;line-height:1.5}@media(max-width:650px){body{padding:16px}.panel{padding:18px}.features{grid-template-columns:1fr}.top{padding-bottom:22px}}
</style>
</head>
<body><div class="wrap">
<header class="top"><div class="brand">TRIGLAVIAN CONSORTIUM</div><div class="tag">Independent EVE logistics</div><a href="{{ route('login.eve') }}" style="color:#e8edf5;border:1px solid #43536a;border-radius:9px;padding:9px 13px;text-decoration:none">Sign in with EVE</a></header>
<section class="hero"><div class="tag">Buyback · Hauling · Finance</div><h1>Turn your inventory<br>into ISK.</h1><p>Paste an EVE inventory export to calculate an indicative buyback value using recorded Jita market prices. Your quote keeps the prices and calculation settings used.</p></section>
<section class="panel">
<h2 style="margin:0 0 4px">Create an appraisal</h2><p class="muted">Paste item names and quantities from your inventory. Supported formats include EVE tab-separated exports, CSV, and “quantity x item name”.</p>
@if($errors->any())<div class="error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="post" action="{{ route('appraisals.store') }}">@csrf
<label for="origin_system">Origin system (optional)</label><input id="origin_system" name="origin_system" value="{{ old('origin_system') }}" placeholder="Jita, Amarr, etc.">
<label for="inventory">Inventory list</label><textarea id="inventory" name="inventory" required placeholder="Tritanium&#9;100000&#10;Pyerite&#9;25000&#10;Mexallon&#9;5000">{{ old('inventory') }}</textarea>
<button type="submit">Calculate appraisal</button>
</form>
<p class="hint">Quotes are estimates, not accepted contracts or completed payouts. Unrecognized or unpriced items are reported rather than silently valued at zero.</p>
</section>
<section class="features">
<div class="feature"><strong>Transparent pricing</strong><span>Each quote records its Jita price source and timestamp.</span></div>
<div class="feature"><strong>Shareable quotes</strong><span>Every appraisal receives a unique reference link.</span></div>
<div class="feature"><strong>Independent platform</strong><span>Standalone service, separate from SeAT.</span></div>
</section>
<p class="muted" style="text-align:center"><a href="{{ route('login.eve') }}" style="color:#d5a84f">Sign in with EVE SSO</a> to access runner and financier operations. · LP Exchange is not part of this release.</p>
</div></body></html>
