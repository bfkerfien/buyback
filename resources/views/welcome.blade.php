<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lentivirus Buyback · EVE Online</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#090713;color:#f0eaff}*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:30px;background:radial-gradient(1px 1px at 12% 18%,#fff9 50%,transparent 100%),radial-gradient(1px 1px at 78% 24%,#d9c4ffb3 50%,transparent 100%),radial-gradient(2px 2px at 42% 68%,#fff7 50%,transparent 100%),radial-gradient(ellipse at 50% -20%,#572b86 0,transparent 58%),radial-gradient(ellipse at 85% 75%,#29134d 0,transparent 48%),#090713}.wrap{max-width:1080px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;padding:14px 0 42px}.brand{font-weight:800;letter-spacing:.08em;font-size:13px;color:#d8c5ff}.tag{color:#b69bdc;font-size:12px;text-transform:uppercase;letter-spacing:.15em}.hero{max-width:760px;margin:30px auto 26px;text-align:center}.hero h1{font-size:clamp(38px,7vw,64px);line-height:1.02;letter-spacing:-.05em;margin:14px 0}.hero p{color:#aab8ca;line-height:1.7}.panel{background:#171126;border:1px solid #49366b;border-radius:18px;padding:26px;max-width:850px;margin:28px auto;box-shadow:0 25px 90px #0005}label{display:block;font-size:13px;color:#d4c3ef;margin:16px 0 8px}input,textarea{width:100%;border:1px solid #594477;background:#100b1d;color:#f0eaff;border-radius:10px;padding:13px;font:inherit}textarea{min-height:220px;resize:vertical;font-family:ui-monospace,monospace;font-size:13px}button{margin-top:18px;border:0;border-radius:10px;padding:14px 20px;background:#b58aff;color:#160d25;font-weight:800;font-size:14px;cursor:pointer}button:hover{filter:brightness(1.08)}.hint,.muted{color:#b69bdc;font-size:12px;line-height:1.6}.error{color:#ff9d9d;margin-top:8px}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:850px;margin:20px auto}.feature{border:1px solid #49366b;background:#130d21;border-radius:12px;padding:16px}.feature strong{display:block;margin-bottom:6px}.feature span{font-size:12px;color:#b69bdc;line-height:1.5}@media(max-width:650px){body{padding:16px}.panel{padding:18px}.features{grid-template-columns:1fr}.top{padding-bottom:22px}}
</style>
</head>
<body><div class="wrap">
<header class="top"><div class="brand">TRIGLAVIAN CONSORTIUM</div><div class="tag">Independent EVE logistics</div><a href="{{ route('login.eve') }}" style="color:#f0eaff;border:1px solid #654b8b;border-radius:9px;padding:9px 13px;text-decoration:none">Sign in with EVE</a></header>
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
<p class="muted" style="text-align:center"><a href="{{ route('login.eve') }}" style="color:#c6a4ff">Sign in with EVE SSO</a> to access runner and financier operations. · LP Exchange is not part of this release.</p>
</div></body></html>
