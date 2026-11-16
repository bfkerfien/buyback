<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EveBuyback · Planetary Industry</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(ellipse at 12% 8%,rgba(29,185,205,.19),transparent 34%),radial-gradient(ellipse at 88% 14%,rgba(255,137,56,.13),transparent 30%),linear-gradient(135deg,#071018,#0a1821 54%,#080f16);min-height:100vh}main{max-width:1200px;margin:auto}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:20px;margin:18px 0}h1,h2{color:#f1fbfc}.muted{color:#9bb5bf;font-size:13px}.global-tax-settings{position:sticky;top:8px;z-index:30;border:2px solid #ff9b45;box-shadow:0 12px 32px rgba(0,0,0,.45)}.global-tax-settings h2{margin-top:0;color:#ffbd75}.global-tax-fields{display:flex;gap:16px;flex-wrap:wrap;align-items:end}.global-tax-fields label{display:flex;flex-direction:column;gap:6px;color:#eaf7f8;font-size:13px;font-weight:700}.global-tax-fields .qty{min-width:170px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0 16px;border-bottom:1px solid #294a56;padding-bottom:10px}.tab,button{background:#ff9b45;color:#111820;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}.tab{background:#09151c;color:#c5dbe0;border:1px solid #315665}.tab.active{background:#ff9b45;color:#111820}.tab-panel{display:none}.tab-panel.active{display:block}.recipe{background:#09151c;border:1px solid #294a56;border-radius:10px;padding:15px;margin:12px 0}.recipe-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.inputs{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin:12px 0}.material{padding:10px;background:#102630;border-radius:8px;font-size:13px}.material-head{display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap}.material strong{display:block;margin-top:5px}.target-percent{color:#ffbd75;font-weight:700;white-space:nowrap}.result{color:#8de4a8;font-weight:700}.qty{max-width:180px;background:#09151c;color:#eaf7f8;border:1px solid #315665;border-radius:8px;padding:9px}.empty{padding:18px;border:1px dashed #52717b;border-radius:10px;color:#9bb5bf}.recipe-details{margin-top:12px;border-top:1px solid #294a56;padding-top:10px}.recipe-details summary{cursor:pointer;color:#62d9e9;font-weight:700}.recipe-details .detail-body{padding:10px 0;line-height:1.8}.recipe-summary{font-size:14px;color:#c5dbe0}.target-profit{color:#8de4a8;font-weight:800;font-size:15px}@media(max-width:650px){body{padding:12px}}
</style></head><body><main>
<header style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><h1>Planetary Industry Planner</h1><p class="muted">Production recipes imported from the CCP EVE SDE. Select a product quantity to calculate exact schematic input requirements.</p></div><a href="{{ route('dashboard') }}" style="color:#62d9e9">← Back to Buyback</a></header>
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
</div></section>
@endforeach
<details class="panel" id="p1-appraisal-buy-prices"><summary style="cursor:pointer;color:#62d9e9;font-weight:700">Individual P1 appraisal buy prices and saved rules</summary><div style="padding-top:12px">
<h2>Individual P1 appraisal buy prices</h2>
<p class="muted">Each P1 commodity appears once. The recommended percentage is the lowest target calculated across every P2 recipe that uses that input. Press Set to use that percentage as the individual appraisal payout rate for this commodity, replacing the shared PI category rate. The setting is saved until changed.</p>
@if(session('status'))<div class="result" style="margin:10px 0">{{ session('status') }}</div>@endif
@if($p1BuyTargets->isEmpty())
<div class="empty">No P1 buy targets are available yet. Check that P1 Jita prices and all P1 → P2 recipe prices are loaded.</div>
@else
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;min-width:650px">
<thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid #294a56">P1 commodity</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Jita buy</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Lowest recipe target</th><th style="text-align:left;padding:10px;border-bottom:1px solid #294a56">Lowest-target recipe</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Current appraisal rate</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Action</th></tr></thead>
<tbody>
@foreach($p1BuyTargets as $item)
<tr>
<td style="padding:10px;border-bottom:1px solid #294a56">{{ $item['name'] }} <span class="muted">({{ $item['recipes'] }} recipe{{ $item['recipes'] === 1 ? '' : 's' }})</span></td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">{{ $item['jita_buy'] !== null ? number_format($item['jita_buy'],2).' ISK' : 'No price' }}</td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">@if($item['lowest_percent'] !== null) {{ number_format($item['lowest_percent'],2) }}% @else <span class="muted">Unavailable</span> @endif</td>
<td style="padding:10px;text-align:left;border-bottom:1px solid #294a56">@if(!empty($item['lowest_recipes'])) {{ implode(', ', $item['lowest_recipes']) }} @else <span class="muted">No calculated recipe target</span> @endif @if(!$item['target_complete']) <div class="muted">Some recipe targets unavailable</div> @endif</td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">@if($item['is_set']) {{ number_format($item['saved_percent'],2) }}% @else <span class="muted">Shared PI rate</span> @endif</td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">
<form method="post" action="{{ route('pi-planner.p1-buy-rule') }}" style="margin:0">
@csrf
<input type="hidden" name="type_id" value="{{ $item['type_id'] }}">
<input type="hidden" name="buy_percent" value="{{ $item['can_set'] ? number_format($item['lowest_percent'],2,'.','') : '' }}">
<button type="submit" style="padding:7px 12px" {{ $item['can_set'] ? '' : 'disabled' }}>{{ $item['is_set'] ? 'Update' : 'Set' }}</button>
</form>
</td>
</tr>
@endforeach
</tbody></table></div>
@endif
</div></details>
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