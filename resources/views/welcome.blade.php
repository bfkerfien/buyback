<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>EveBuyback · EVE Online</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:30px;background:radial-gradient(2px 2px at 8% 16%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 18% 72%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 29% 38%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 43% 82%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 57% 14%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 69% 55%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 83% 27%,#67d9e8 45%,transparent 60%),radial-gradient(1px 1px at 94% 78%,#fff 45%,transparent 60%),radial-gradient(ellipse at 50% -10%,#0d5967 0,transparent 52%),radial-gradient(ellipse at 88% 82%,#3d2512 0,transparent 42%),radial-gradient(ellipse at 8% 90%,#0d2b38 0,transparent 40%),#060d13}.wrap{max-width:1080px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;padding:14px 0 42px}.brand{font-weight:800;letter-spacing:.08em;font-size:13px;color:#8deaf1}.tag{color:#9bb5bf;font-size:12px;text-transform:uppercase;letter-spacing:.15em}.hero{max-width:760px;margin:30px auto 26px;text-align:center}.hero h1{font-size:clamp(38px,7vw,64px);line-height:1.02;letter-spacing:-.05em;margin:14px 0}.hero p{color:#aab8ca;line-height:1.7}.panel{background:#101e27;border:1px solid #294a56;border-radius:18px;padding:26px;max-width:850px;margin:28px auto;box-shadow:0 25px 90px #0005}label{display:block;font-size:13px;color:#c5dbe0;margin:16px 0 8px}input,textarea{width:100%;border:1px solid #315665;background:#09151c;color:#eaf7f8;border-radius:10px;padding:13px;font:inherit}textarea{min-height:220px;resize:vertical;font-family:ui-monospace,monospace;font-size:13px}button{margin-top:18px;border:0;border-radius:10px;padding:14px 20px;background:#ff9b45;color:#111820;font-weight:800;font-size:14px;cursor:pointer}button:hover{filter:brightness(1.08)}.hint,.muted{color:#9bb5bf;font-size:12px;line-height:1.6}.error{color:#ff9f77;margin-top:8px}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:850px;margin:20px auto}.feature{border:1px solid #294a56;background:#0b1820;border-radius:12px;padding:16px}.feature strong{display:block;margin-bottom:6px}.feature span{font-size:12px;color:#9bb5bf;line-height:1.5}@media(max-width:650px){body{padding:16px}.panel{padding:18px}.features{grid-template-columns:1fr}.top{padding-bottom:22px}}

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
<body><div class="wrap">
<header class="top"><img class="eb-logo" style="width:360px" src="{{ asset('evebuyback-logo.png') }}" alt="EveBuyback"><div class="tag">EVE ONLINE · BUYBACK OPERATIONS</div><nav style="display:flex;gap:8px;align-items:center"><a href="{{ route('login.eve') }}" style="color:#eaf7f8;border:1px solid #386675;border-radius:9px;padding:9px 13px;text-decoration:none">Sign in with EVE</a></nav></header>
<section class="hero" style="max-width:850px;text-align:left;background:#101e27;border:1px solid #294a56;border-radius:18px;padding:26px;box-shadow:0 25px 90px #0005">
<h1 style="font-size:30px;letter-spacing:0;text-align:center;margin:0 0 18px">Buyback instructions</h1>
<ol style="color:#c5dbe0;line-height:1.9;padding-left:24px;margin:0">
<li><strong>Currently accepting:</strong> Ore, Ice, PI Goods, and Exploration Loot only.</li>
<li><strong>Appraisals only — do not create a contract.</strong> Buyback price settings are fluctuating. Submit your appraisal form and wait for us to review it and confirm whether the quoted values are approved before taking any further action. This is a temporary process.</li>
</ol>
</section>
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
<p class="muted" style="text-align:center"><a href="{{ route('login.eve') }}" style="color:#62d9e9">Sign in with EVE SSO</a> to access your EveBuyback dashboard.</p>
</div></body></html>
