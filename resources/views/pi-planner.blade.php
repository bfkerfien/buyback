<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EveBuyback · Planetary Industry</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(ellipse at 12% 8%,rgba(29,185,205,.19),transparent 34%),radial-gradient(ellipse at 88% 14%,rgba(255,137,56,.13),transparent 30%),linear-gradient(135deg,#071018,#0a1821 54%,#080f16);min-height:100vh}main{max-width:1200px;margin:auto}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:20px;margin:18px 0}h1,h2{color:#f1fbfc}.muted{color:#9bb5bf;font-size:13px}.global-tax-settings{position:sticky;top:8px;z-index:30;border:2px solid #ff9b45;box-shadow:0 12px 32px rgba(0,0,0,.45)}.global-tax-settings h2{margin-top:0;color:#ffbd75}.global-tax-fields{display:flex;gap:16px;flex-wrap:wrap;align-items:end}.global-tax-fields label{display:flex;flex-direction:column;gap:6px;color:#eaf7f8;font-size:13px;font-weight:700}.global-tax-fields .qty{min-width:170px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0 16px;border-bottom:1px solid #294a56;padding-bottom:10px}.tab,button{background:#ff9b45;color:#111820;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}.tab{background:#09151c;color:#c5dbe0;border:1px solid #315665}.tab.active{background:#ff9b45;color:#111820}.tab-panel{display:none}.tab-panel.active{display:block}.recipe{background:#09151c;border:1px solid #294a56;border-radius:10px;padding:15px;margin:12px 0}.recipe-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.inputs{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin:12px 0}.material{padding:10px;background:#102630;border-radius:8px;font-size:13px}.material-head{display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap}.material strong{display:block;margin-top:5px}.target-percent{color:#ffbd75;font-weight:700;white-space:nowrap}.result{color:#8de4a8;font-weight:700}.qty{max-width:180px;background:#09151c;color:#eaf7f8;border:1px solid #315665;border-radius:8px;padding:9px}.empty{padding:18px;border:1px dashed #52717b;border-radius:10px;color:#9bb5bf}.recipe-details{margin-top:12px;border-top:1px solid #294a56;padding-top:10px}.recipe-details summary{cursor:pointer;color:#62d9e9;font-weight:700}.recipe-details .detail-body{padding:10px 0;line-height:1.8}.recipe-summary{font-size:14px;color:#c5dbe0}.target-profit{color:#8de4a8;font-weight:800;font-size:15px}@media(max-width:650px){body{padding:12px}}
.site-header{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;padding:12px 16px;margin:0 0 22px;background:rgba(7,19,27,.94);border:1px solid #315665;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.28);position:relative;z-index:40}.site-brand{display:block;flex:0 1 250px}.site-header .eb-logo{display:block;width:250px;max-width:100%;height:auto}.site-nav{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.site-nav>a{display:inline-flex;align-items:center;padding:9px 11px;border:1px solid #315665;border-radius:8px;text-decoration:none;color:#dffaff!important;font-size:13px;font-weight:700;background:#102630}.site-nav>a:hover{border-color:#62d9e9;background:#163440}.site-account{color:#9bb5bf;font-size:12px;padding:0 6px}.site-logout{margin:0}.site-logout button{padding:9px 12px;font-size:12px}@media(max-width:700px){.site-header{align-items:flex-start}.site-brand{flex-basis:180px}.site-header .eb-logo{width:180px}.site-nav{justify-content:flex-start;width:100%}}</style></head><body><main>
@include('partials.site-header')

@if(session('status'))<div class="panel">{{ session('status') }}</div>@endif
@if($errors->any())<div class="panel">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="panel global-tax-settings" id="global-pi-tax-settings">
<h2>GLOBAL PI TAX SETTINGS — ALL RECIPES</h2>
<p class="muted">These settings apply to every P1 → P2, P2 → P3, and P3 → P4 recipe. Changing either tax rate automatically recalculates each input's allowable Jita buy percentage so the finished product retains your target profit after import tax, export tax, and 320 ISK/m³ shipping.</p>
<form method="post" action="{{ route('ore-values.pi-taxes') }}" class="global-tax-fields">
@csrf
<input type="hidden" name="return_to" value="pi-planner">
<label>Input / Import tax %
<input class="qty" type="number" name="import_tax_percent" min="0" max="100" step="0.01" required value="{{ number_format($importTaxPercent,2,'.','') }}"></label>
<label>Output / Export tax %
<input class="qty" type="number" name="export_tax_percent" min="0" max="100" step="0.01" required value="{{ number_format($exportTaxPercent,2,'.','') }}"></label>
<label>Target profit %
<input class="qty" type="number" name="target_profit_percent" min="0.01" max="99.99" step="0.01" required value="{{ number_format($targetProfitPercent,2,'.','') }}"></label>
<button type="submit">Recalculate all recipes</button>
</form>
</section>
<nav class="tabs" role="tablist" aria-label="PI production stages">
<button class="tab active" type="button" data-stage="p1-p2">P1 → P2</button>
<button class="tab" type="button" data-stage="p2-p3">P2 → P3</button>
<button class="tab" type="button" data-stage="p3-p4">P3 → P4</button>
</nav>
@php
$stages = [
'p1-p2' => ['title' => 'P1 → P2', 'group' => 'Refined Commodities - Tier 2', 'description' => 'Combine P1 basic commodities into P2 refined commodities.'],
'p2-p3' => ['title' => 'P2 → P3', 'group' => 'Specialized Commodities - Tier 3', 'description' => 'Combine P2 refined commodities into P3 specialized commodities.'],
'p3-p4' => ['title' => 'P3 → P4', 'group' => 'Advanced Commodities - Tier 4', 'description' => 'Combine P3 specialized commodities into P4 advanced commodities.'],
];
@endphp
@foreach($stages as $key => $stage)
<section class="tab-panel {{ $loop->first ? 'active' : '' }}" id="stage-{{ $key }}">
<div class="panel"><h2>{{ $stage['title'] }}</h2><p class="muted">{{ $stage['description'] }}</p>
@php
$stageRecipes = $recipes->filter(function ($recipe) use ($stage) {
    return strcasecmp($recipe['output_group'], $stage['group']) === 0;
})->values();
@endphp
@if($stageRecipes->isEmpty())
<div class="empty">No recipes found for {{ $stage['group'] }}. Confirm that the CCP SDE schematic import and item taxonomy are current.</div>
@else
<p class="muted">{{ $stageRecipes->count() }} production recipes</p>
@foreach($stageRecipes as $recipe)
<article class="recipe" data-recipe>
<div class="recipe-head"><div><strong>{{ $recipe['output_name'] }}</strong><div class="muted">{{ $recipe['name'] }} · {{ $recipe['cycle_time'] }} second cycle</div></div>
<label class="muted">Desired output<br><input class="qty target-quantity" type="number" min="1" step="1" value="{{ $recipe['output_quantity'] * 10 }}"></label></div>
<div class="inputs">
@foreach($recipe['inputs'] as $input)
<div class="material" data-input data-quantity="{{ $input['quantity'] }}" data-name="{{ $input['name'] }}">
<div class="material-head"><strong style="margin:0">{{ $input['name'] }}</strong><span class="target-percent">@if(isset($input['target_buy_percent'])) {{ number_format($input['target_buy_percent'],2) }}% of Jita buy @else Target unavailable @endif</span></div>
<strong class="input-total">{{ number_format($input['quantity'] * 10) }}</strong>
<span class="muted">{{ $input['quantity'] }} per run</span>
<div class="muted" style="margin-top:6px">Jita buy: @if($input['buy_price'] !== null) {{ number_format($input['buy_price'],2) }} ISK each @else price unavailable @endif</div>
<div class="muted">Material cost per run: @if($input['run_cost'] !== null) {{ number_format($input['run_cost'],2) }} ISK @else unavailable @endif</div>
</div>
@endforeach
</div>
<div class="recipe-summary">Runs: <strong class="runs">{{ 10 }}</strong> · Output: <strong class="output-total">{{ number_format($recipe['output_quantity'] * 10) }}</strong> {{ $recipe['output_name'] }}</div>
@if($recipe['profit_loss'] !== null)
<div class="target-profit">Target profit: {{ number_format($recipe['target_profit_percent'],2) }}% · {{ number_format($recipe['target_profit_value'],2) }} ISK per run</div>
@else
<div class="muted" style="color:#ffbd75">Target cannot be reached with current taxes and shipping: fixed expenses exceed the amount available for materials.</div>
@endif
<details class="recipe-details">
<summary>Show pricing, taxes, shipping and calculation details</summary>
<div class="detail-body">
<div class="muted">Input materials at Jita buy orders: @if($recipe['input_cost'] !== null) {{ number_format($recipe['input_cost'], 2) }} ISK @else missing input buy price @endif</div>
<div class="muted">Import tax ({{ number_format($importTaxPercent,2) }}% configured; EVE 50% multiplier): @if($recipe['import_tax'] !== null) {{ number_format($recipe['import_tax'], 2) }} ISK @else unavailable @endif</div>
<div class="muted">Finished {{ $recipe['output_name'] }} Jita buy price: @if($recipe['output_buy_price'] !== null) {{ number_format($recipe['output_buy_price'], 2) }} ISK each @else price unavailable @endif</div>
<div class="muted">Gross value of {{ $recipe['output_quantity'] }} finished units: @if($recipe['output_buy_value'] !== null) {{ number_format($recipe['output_buy_value'], 2) }} ISK @else price unavailable @endif</div>
<div class="muted">Export tax ({{ number_format($exportTaxPercent,2) }}% configured): @if($recipe['export_tax'] !== null) {{ number_format($recipe['export_tax'], 2) }} ISK @else unavailable @endif</div>
<div class="muted">Outbound transport (320 ISK/m³ × {{ $recipe['output_volume'] !== null ? number_format($recipe['output_volume'], 3) : 'volume unavailable' }} m³): @if($recipe['transport_fee'] !== null) {{ number_format($recipe['transport_fee'], 2) }} ISK @else unavailable @endif</div>
<div class="muted">Allowable total material budget after fixed expenses: @if($recipe['target_material_budget'] !== null && $recipe['target_material_budget'] >= 0) {{ number_format($recipe['target_material_budget'],2) }} ISK per run @else not achievable @endif</div>
<div class="muted">Target profit is calculated against the gross Jita buy value. The input percentages are the proportional share of the remaining material budget, after both customs taxes and outbound shipping are deducted.</div>
</div>
</details>
</article>
@endforeach
@endif
@include('partials.pi-p1-appraisal-buy-prices')
</div></section>
@endforeach
</main>
<script>
(()=> {
const tabs=[...document.querySelectorAll('[data-stage]')];
tabs.forEach(tab=>tab.addEventListener('click',()=>{tabs.forEach(t=>t.classList.toggle('active',t===tab));document.querySelectorAll('.tab-panel').forEach(p=>p.classList.toggle('active',p.id==='stage-'+tab.dataset.stage));}));
document.querySelectorAll('[data-recipe]').forEach(card=>{
 const target=card.querySelector('.target-quantity');
 const update=()=>{const desired=Math.max(1,Math.floor(Number(target.value)||1));const outputPerRun=Number(card.querySelector('.output-total').dataset.perRun||0)||Number(card.querySelector('.output-total').textContent.replace(/,/g,''))/10;const output=Number(card.querySelector('.output-total').dataset.base||outputPerRun);const runs=Math.ceil(desired/output);card.querySelector('.runs').textContent=runs.toLocaleString();card.querySelector('.output-total').textContent=(runs*output).toLocaleString();card.querySelectorAll('[data-input]').forEach(input=>{input.querySelector('.input-total').textContent=(runs*Number(input.dataset.quantity)).toLocaleString();});};
 const outputNode=card.querySelector('.output-total');const initial=Number(outputNode.textContent.replace(/,/g,''));outputNode.dataset.perRun=String(initial/10);outputNode.dataset.base=String(initial/10);target.addEventListener('input',update);update();
});
})();
</script></body></html>