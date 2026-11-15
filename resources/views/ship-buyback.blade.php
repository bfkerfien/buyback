<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ship Buyback · EveBuyback</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8;--cyan:#62d9e9;--amber:#ff9b45}*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:26px;background:radial-gradient(ellipse at 12% 8%,rgba(29,185,205,.19),transparent 34%),radial-gradient(ellipse at 88% 14%,rgba(255,137,56,.13),transparent 30%),linear-gradient(135deg,#071018 0%,#0a1821 54%,#080f16 100%)}.wrap{max-width:1050px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:18px;margin:8px 0 28px}.eb-logo{width:320px;max-width:55vw;height:auto;object-fit:contain;filter:drop-shadow(0 0 18px rgba(18,216,242,.18))}.links{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.links a{color:var(--cyan);text-decoration:none;border:1px solid #386675;border-radius:9px;padding:9px 12px;font-size:13px}.hero{background:linear-gradient(145deg,rgba(18,36,46,.97),rgba(9,21,29,.97));border:1px solid rgba(91,193,210,.25);border-radius:18px;padding:26px;margin:18px 0;box-shadow:0 18px 48px #0005}.eyebrow{color:var(--amber);font-weight:800;font-size:12px;letter-spacing:.14em;text-transform:uppercase}.hero h1{font-size:clamp(28px,5vw,42px);margin:9px 0}.hero p,.muted{color:#9bb5bf;font-size:13px;line-height:1.7}.rate{color:#ffbb70;font-weight:800}label{display:block;color:#c5dbe0;font-size:13px;margin:16px 0 7px}input,textarea{width:100%;background:#07131a;color:#eaf7f8;border:1px solid #315665;border-radius:9px;padding:12px;font:inherit}textarea{min-height:240px;resize:vertical;font-family:ui-monospace,monospace;font-size:13px}button{margin-top:16px;border:0;border-radius:9px;padding:13px 18px;background:linear-gradient(115deg,#ffb45f,#ff793f);color:#101820;font-weight:800;cursor:pointer}.error{margin:14px 0;padding:12px;border:1px solid #824b42;background:#351d1c;color:#ffc0ab;border-radius:9px}.footer{text-align:center;margin:28px auto 12px;color:#7f9ba5;font-size:12px}@media(max-width:650px){body{padding:14px}.top{align-items:flex-start;flex-direction:column}.eb-logo{width:270px;max-width:80vw}.hero{padding:18px}}
</style><link rel="icon" type="image/png" href="{{ asset('evebuyback-logo.png') }}">
</head><body><div class="wrap">
<header class="top"><a href="{{ route('home') }}"><img class="eb-logo" src="{{ asset('evebuyback-logo.png') }}" alt="EveBuyback"></a><nav class="links"><a href="{{ route('home') }}">Standard Buyback</a><a href="{{ route('login.eve') }}">Sign in with EVE</a></nav></header>
<section class="hero">
<div class="eyebrow">Public · No EVE login required</div>
<h1>Ship Buyback Appraisal</h1>
<p>Submit ship hulls for a separate buyback appraisal at <span class="rate">80% of hull value</span>. Values use the current stored Jita market-price snapshots.</p>
<p><strong>Hull-only appraisal:</strong> paste ship names and quantities. Modules, rigs, drones, ammunition, cargo, and all other non-ship entries are simply ignored. Only published EVE ship hulls are included in the quote.</p>
</section>
<section class="hero">
<h2 style="margin:0">Appraise ship hulls</h2>
<p class="muted">Paste the copied list from an inventory, hangar, or contract. Supported formats include EVE tab-separated lists, CSV, and “quantity x ship name”. Non-hull entries are ignored automatically.</p>
@if($errors->any())<div class="error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="post" action="{{ route('ship-buyback.store') }}">@csrf
<label for="origin_system">Origin system (optional)</label><input id="origin_system" name="origin_system" maxlength="100" value="{{ old('origin_system') }}" placeholder="BKG-Q2, Jita, etc.">
<label for="inventory">Ship hull list</label><textarea id="inventory" name="inventory" required placeholder="Rifter&#9;10&#10;Drake&#9;2&#10;Rifter Fleet Issue&#9;1">{{ old('inventory') }}</textarea>
<button type="submit">Calculate 80% ship buyback</button>
</form>
<p class="muted">This is a quote estimate only. It does not create a contract or transfer ISK. Modules and other non-hull rows do not contribute to the appraisal.</p>
</section>
</div><footer class="footer">(c) 2027 My Eve Online</footer></body></html>
