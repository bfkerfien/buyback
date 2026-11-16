<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EveBuyback · Planetary Industry</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(ellipse at 12% 8%,rgba(29,185,205,.19),transparent 34%),radial-gradient(ellipse at 88% 14%,rgba(255,137,56,.13),transparent 30%),linear-gradient(135deg,#071018,#0a1821 54%,#080f16);min-height:100vh}main{max-width:1200px;margin:auto}.panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:20px;margin:18px 0}h1,h2{color:#f1fbfc}.muted{color:#9bb5bf;font-size:13px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0 16px;border-bottom:1px solid #294a56;padding-bottom:10px}.tab,button{background:#ff9b45;color:#111820;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}.tab{background:#09151c;color:#c5dbe0;border:1px solid #315665}.tab.active{background:#ff9b45;color:#111820}.tab-panel{display:none}.tab-panel.active{display:block}.recipe{background:#09151c;border:1px solid #294a56;border-radius:10px;padding:15px;margin:12px 0}.recipe-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.inputs{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin:12px 0}.material{padding:10px;background:#102630;border-radius:8px;font-size:13px}.material strong{display:block;margin-top:5px}.result{color:#8de4a8;font-weight:700}.qty{max-width:180px;background:#09151c;color:#eaf7f8;border:1px solid #315665;border-radius:8px;padding:9px}.empty{padding:18px;border:1px dashed #52717b;border-radius:10px;color:#9bb5bf}@media(max-width:650px){body{padding:12px}}
</style></head><body><main>
<header style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><h1>Planetary Industry Planner</h1><p class="muted">Production recipes imported from the CCP EVE SDE. Select a product quantity to calculate exact schematic input requirements.</p></div><a href="{{ route('dashboard') }}" style="color:#62d9e9">← Back to Buyback</a></header>
<nav class="tabs" role="tablist" aria-label="PI production stages">
<button class="tab active" type="button" data-stage="p1-p2">P1 → P2</button>
<button class="tab" type="button" data-stage="p2-p3">P2 → P3</button>
<button class="tab" type="button" data-stage="p3-p4">P3 → P4</button>
</nav>
@php
$stages = [
'p1-p2' => ['title' => 'P1 → P2', 'group' => 'Specialized Commodities', 'description' => 'Combine P1 refined commodities into P2 specialized commodities.'],
'p2-p3' => ['title' => 'P2 → P3', 'group' => 'Advanced Commodities', 'description' => 'Combine P2 specialized commodities into P3 advanced commodities.'],
'p3-p4' => ['title' => 'P3 → P4', 'group' => 'High-Tech Commodities', 'description' => 'Combine P3 advanced commodities into P4 high-tech commodities.'],
];
@endphp
@foreach($stages as $key => $stage)
<section class="tab-panel {{ $loop->first ? 'active' : '' }}" id="stage-{{ $key }}">
<div class="panel"><h2>{{ $stage['title'] }}</h2><p class="muted">{{ $stage['description'] }}</p>
@php($stageRecipes = $recipes->filter(fn($recipe) => strcasecmp($recipe['output_group'], $stage['group']) === 0)->values())
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
<div class="material" data-input data-quantity="{{ $input['quantity'] }}" data-name="{{ $input['name'] }}">{{ $input['name'] }}<strong class="input-total">{{ number_format($input['quantity'] * 10) }}</strong><span class="muted">per 10 runs basis</span></div>
@endforeach
</div>
<div class="result">Runs required: <span class="runs">{{ 10 }}</span> · Output: <span class="output-total">{{ number_format($recipe['output_quantity'] * 10) }}</span> {{ $recipe['output_name'] }}</div>
</article>
@endforeach
@endif
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