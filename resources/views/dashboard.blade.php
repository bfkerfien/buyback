<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lentivirus Buyback · Operations</title>
<style>
 :root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#090713;color:#f0eaff}*{box-sizing:border-box}body{margin:0;padding:24px;background:radial-gradient(2px 2px at 8% 16%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 18% 72%,#d9c4ff 45%,transparent 60%),radial-gradient(1px 1px at 29% 38%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 43% 82%,#d9c4ff 45%,transparent 60%),radial-gradient(1px 1px at 57% 14%,#fff 45%,transparent 60%),radial-gradient(1px 1px at 69% 55%,#fff 45%,transparent 60%),radial-gradient(2px 2px at 83% 27%,#d9c4ff 45%,transparent 60%),radial-gradient(1px 1px at 94% 78%,#fff 45%,transparent 60%),radial-gradient(ellipse at 50% -10%,#7137a8 0,transparent 52%),radial-gradient(ellipse at 88% 82%,#38205f 0,transparent 42%),radial-gradient(ellipse at 8% 90%,#21143f 0,transparent 40%),#070510;min-height:100vh}main{max-width:1200px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:16px}.account-chip{display:flex;align-items:center;gap:9px;padding:5px 11px 5px 6px;border:1px solid #654b8b;border-radius:999px;background:#100b1d;color:#f0eaff;font-size:13px;font-weight:600;white-space:nowrap}.account-chip img{width:34px;height:34px;display:block;object-fit:cover;border-radius:50%;border:1px solid #8c69bd}.muted{color:#b69bdc;font-size:13px}.panel{background:#171126;border:1px solid #49366b;border-radius:14px;padding:20px;margin:18px 0}h1{font-size:30px}h2{font-size:19px;margin-top:0}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#100b1d;padding:14px;border-radius:10px}.stat strong{display:block;font-size:22px;margin-top:8px}input,select,textarea{width:100%;background:#100b1d;color:#e8edf5;border:1px solid #594477;border-radius:8px;padding:10px;margin:6px 0 12px}textarea{min-height:70px}button{display:inline-block;background:#b58aff;color:#160d25;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #49366b;font-size:13px}.success{color:#d5b7ff}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}form.inline{display:inline}form.inline select{width:auto;max-width:170px;padding:8px}@media(max-width:700px){.grid,.row{grid-template-columns:1fr}body{padding:12px}.top{align-items:flex-start}}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0 16px;border-bottom:1px solid #49366b;padding-bottom:10px}.tab{background:#100b1d;color:#d9cbea;border:1px solid #594477}.tab.active{background:#b58aff;color:#160d25;border-color:#b58aff}.tab-panel{display:none}.tab-panel.active{display:block}.tab-panel[hidden]{display:none}
</style></head><body><main>
@php($activeCharacter = $user->characters->sortByDesc('last_authenticated_at')->first())
<header class="top"><div><div class="muted">LENTIVIRUS BUYBACK</div><h1>Operations dashboard</h1><div class="muted">Signed in as {{ $user->name }} · {{ ucfirst($user->role) }} · {{ $user->characters->pluck('name')->join(', ') }} · <a href="{{ route('login.eve.link') }}" style="color:#c6a4ff">Link another character</a></div></div><nav style="display:flex;align-items:center;gap:10px"><div class="account-chip">@if($activeCharacter)<img src="https://images.evetech.net/characters/{{ $activeCharacter->eve_character_id }}/portrait?size=64" alt="{{ $activeCharacter->name }} portrait">@endif<span>{{ $activeCharacter?->name ?? $user->name }}</span></div><form method="post" action="{{ route('logout') }}">@csrf<button>Sign out</button></form></nav></header>
@if(session('status'))<p class="success">{{ session('status') }}</p>@endif
@if($errors->any())<div class="panel">{{ $errors->first() }}</div>@endif
<nav class="tabs" role="tablist" aria-label="Buyback sections">
<button type="button" class="tab active" role="tab" aria-selected="true" aria-controls="tab-appraisals" data-tab="tab-appraisals">New Appraisals</button>
<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-contracts" data-tab="tab-contracts">Appraisals &amp; Contracts</button>
@if($admin)
<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-marketbaiters" data-tab="tab-marketbaiters">Marketbaiters Comparison</button>
<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-admin" data-tab="tab-admin">Admin</button>
<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-admin-tools" data-tab="tab-admin-tools">Admin</button>
<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-ore-refining" data-tab="tab-ore-refining">Ore Refining</button>
@endif

<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-how-to" data-tab="tab-how-to">How To</button>
</nav>
<section class="tab-panel active" id="tab-appraisals" role="tabpanel">
<section class="panel">
<h2>Buyback instructions</h2>
<ol style="color:#d9cbea;line-height:1.9;padding-left:24px;margin:0">
<li><strong>Currently accepting:</strong> Ore, Ice, PI Goods, and Exploration Loot only.</li>
<li><strong>Appraisals only — do not create a contract.</strong> Buyback price settings are fluctuating. Submit your appraisal form and wait for us to review it and confirm whether the quoted values are approved before taking any further action. This is a temporary process.</li>
</ol>
</section>
<section class="panel">
<h2>Create an appraisal</h2><p class="muted">Paste item names and quantities from your inventory. Supported formats include EVE tab-separated exports, CSV, and “quantity x item name”.</p>
<form method="post" action="{{ route('appraisals.store') }}">@csrf
<label>Origin system (optional)<input name="origin_system" value="{{ old('origin_system') }}" placeholder="Jita, Amarr, etc."></label>
<label>Inventory list<textarea name="inventory" required placeholder="Tritanium&#9;100000&#10;Pyerite&#9;25000&#10;Mexallon&#9;5000">{{ old('inventory') }}</textarea></label>
<button type="submit">Calculate appraisal</button>
</form>
<p class="muted">Quotes are estimates, not accepted contracts or completed payouts. Unrecognized or unpriced items are reported rather than silently valued at zero.</p>
</section>
</section>
<section class="tab-panel" id="tab-contracts" role="tabpanel" hidden>
<div class="grid"><div class="stat">Your internal ledger balance<strong>{{ number_format($balance,2) }} ISK</strong></div><div class="stat">Visible runner jobs<strong>{{ $jobs->count() }}</strong></div></div>
<section class="panel"><h2>Runner board</h2><p class="muted">Accept the job here using the pilot that will haul it. Then accept the matching courier contract in EVE. After EVE shows the contract as completed, return here and mark delivery for ESI verification.</p><table><thead><tr><th>Route</th><th>Status</th><th>Reward</th><th>Action</th></tr></thead><tbody>
@forelse($jobs as $job)<tr><td>{{ $job->origin }} → {{ $job->destination }}<div class="muted">EVE courier contract #{{ $job->contract_id }} · {{ $job->notes }}</div></td><td>{{ ucfirst($job->status) }}</td><td>{{ number_format((float)$job->reward_isk,2) }} ISK</td><td>
@if($job->status==='open')<form class="inline" method="post" action="{{ route('runner.jobs.accept',$job->id) }}">@csrf<select name="character_id" required>@foreach($user->characters as $character)<option value="{{ $character->id }}">{{ $character->name }}</option>@endforeach</select><button>Accept</button></form>
@elseif($job->status==='accepted' && $job->assigned_user_id===$user->id)<form class="inline" method="post" action="{{ route('runner.jobs.deliver',$job->id) }}">@csrf<button>Mark delivered</button></form>@endif
</td></tr>@empty<tr><td colspan="4">No runner jobs available.</td></tr>@endforelse
</tbody></table></section>
<section class="panel"><h2>{{ $admin ? 'All appraisals' : 'Your recent appraisals' }}</h2><p class="muted">{{ $admin ? 'All player appraisals are visible here, including appraisals hidden by their owners.' : 'Hide an appraisal to remove it from your dashboard. This never deletes its underlying data.' }}</p><table><thead><tr><th>Quote</th><th>Owner</th><th>Origin</th><th>Payout</th><th>Created</th><th>Action</th></tr></thead><tbody>@forelse($quotes as $quote)<tr><td><a href="{{ route('appraisals.show',$quote->public_token) }}">{{ substr($quote->public_token,0,14) }}</a></td><td>{{ $admin ? ($quote->created_by ?? '—') : $user->name }}</td><td>{{ $quote->origin_system ?: '—' }}</td><td>{{ number_format((float)$quote->payout_value,2) }} ISK</td><td>{{ $quote->created_at }}</td><td>@if($admin)<form class="inline" method="post" action="{{ route('admin.appraisals.archive',$quote->id) }}">@csrf<button style="background:#34465e;color:#e8edf5" onclick="return confirm('Move this appraisal to the archive?')">Archive</button></form>@else<form class="inline" method="post" action="{{ route('appraisals.hide',$quote->id) }}">@csrf @method('DELETE')<button style="background:#34465e;color:#e8edf5" onclick="return confirm('Hide this appraisal from your dashboard?')">Hide</button></form>@endif</td></tr>@empty<tr><td colspan="6">No appraisals to display.</td></tr>@endforelse</tbody></table></section>
<section class="panel"><h2>Expired appraisals</h2><p class="muted">Appraisals older than 6 hours are listed here and are no longer in the current appraisal list. Their quote details remain available.</p><table><thead><tr><th>Quote</th><th>Owner</th><th>Origin</th><th>Payout</th><th>Created</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($expiredQuotes as $quote)<tr><td><a href="{{ route('appraisals.show',$quote->public_token) }}">{{ substr($quote->public_token,0,14) }}</a></td><td>{{ $admin ? ($quote->created_by ?? '—') : $user->name }}</td><td>{{ $quote->origin_system ?: '—' }}</td><td>{{ number_format((float)$quote->payout_value,2) }} ISK</td><td>{{ $quote->created_at }}</td><td><strong style="color:#f0b36d">Expired</strong></td><td>@if($admin)<form class="inline" method="post" action="{{ route('admin.appraisals.archive',$quote->id) }}">@csrf<button style="background:#34465e;color:#e8edf5" onclick="return confirm('Move this appraisal to the archive?')">Archive</button></form>@else<form class="inline" method="post" action="{{ route('appraisals.hide',$quote->id) }}">@csrf @method('DELETE')<button style="background:#34465e;color:#e8edf5" onclick="return confirm('Hide this appraisal from your dashboard?')">Hide</button></form>@endif</td></tr>@empty<tr><td colspan="7">No expired appraisals.</td></tr>@endforelse</tbody></table></section>
@if($admin)<section class="panel"><h2>Archived appraisals</h2><p class="muted">Archived records remain stored and can be restored at any time.</p><table><thead><tr><th>Quote</th><th>Owner ID</th><th>Origin</th><th>Payout</th><th>Archived</th><th>Action</th></tr></thead><tbody>@forelse($archivedQuotes as $quote)<tr><td><a href="{{ route('appraisals.show',$quote->public_token) }}">{{ substr($quote->public_token,0,14) }}</a></td><td>{{ $quote->created_by ?? '—' }}</td><td>{{ $quote->origin_system ?: '—' }}</td><td>{{ number_format((float)$quote->payout_value,2) }} ISK</td><td>{{ $quote->archived_at }}</td><td><form class="inline" method="post" action="{{ route('admin.appraisals.restore',$quote->id) }}">@csrf<button>Restore</button></form></td></tr>@empty<tr><td colspan="6">No archived appraisals.</td></tr>@endforelse</tbody></table></section>@endif
</section>
@if($admin)
<section class="tab-panel" id="tab-admin" role="tabpanel" hidden>
@if($admin)<section class="panel"><h2>Administration</h2><div class="grid"><div class="stat">Open jobs<strong>{{ $adminMetrics['open_jobs'] }}</strong></div><div class="stat">Registered users<strong>{{ $adminMetrics['users'] }}</strong></div></div>
<h2 style="margin-top:24px">Category buyback rates</h2>
<p class="muted">Set the percentage of Jita value paid for each EVE item category. Items inherit their category rate; categories without a rule use the default rate from BUYBACK_PAYOUT_PERCENT (currently {{ number_format((float)config('buyback.payout_percent'),2) }}%).</p>
<form method="post" action="{{ route('admin.appraisal-rates.store') }}">@csrf
<div class="row"><label>EVE item category<select name="category_id" required><option value="">Select category</option>@foreach($eveCategories as $category)<option value="{{ $category->category_id }}">{{ $category->category_name }}</option>@endforeach</select></label><label>Buyback rate (%)<input name="payout_percent" type="number" min="0.01" max="100" step="0.01" placeholder="80.00" required></label></div><button>Save category rate</button></form>
<table><thead><tr><th>Category</th><th>Buyback rate</th><th>Action</th></tr></thead><tbody>@forelse($payoutRules as $rule)<tr><td>{{ $rule->category_name }}</td><td>{{ number_format((float)$rule->payout_percent,2) }}%</td><td><form class="inline" method="post" action="{{ route('admin.appraisal-rates.destroy',$rule->category_id) }}">@csrf @method('DELETE')<button style="background:#34465e;color:#e8edf5">Remove</button></form></td></tr>@empty<tr><td colspan="3">No category-specific rates. All items use the default rate.</td></tr>@endforelse</tbody></table>
<p class="muted">CCP SDE category data must be imported once before categories appear in this list.</p>
<h2 style="margin-top:24px">Ore valuation rules</h2>
<p class="muted">Choose the valuation basis per ore. Compressed price uses the ore's own Jita buy price. Reprocessed price converts the submitted ore quantity into the configured output material, prices that material in Jita, and applies the output material's effective category/default buyback rate. Ore without an override uses compressed price.</p>
<form method="post" action="{{ route('admin.appraisal-ore-rules.store') }}">@csrf
<div class="row">
<label>Ore<select name="ore_type_id" required><option value="">Select ore</option>@foreach($oreTypes as $ore)<option value="{{ $ore->type_id }}">{{ $ore->type_name }}</option>@endforeach</select></label>
<label>Valuation basis<select name="valuation_mode" id="ore-valuation-mode" required><option value="compressed">Compressed price</option><option value="reprocessed">Reprocessed material price</option></select></label>
<label>Reprocessed output material<select name="output_type_id" id="ore-output-type"><option value="">Select mineral</option>@foreach($mineralTypes as $mineral)<option value="{{ $mineral->type_id }}">{{ $mineral->type_name }}</option>@endforeach</select></label>
<label>Output units per 1 ore unit<input name="output_quantity_per_input" id="ore-output-ratio" type="number" min="0.00000001" max="1000000" step="0.00000001" placeholder="1.2688"></label>
</div><button>Save ore valuation rule</button></form>
<table><thead><tr><th>Ore</th><th>Valuation basis</th><th>Output conversion</th><th>Action</th></tr></thead><tbody>
@forelse($oreValuationRules as $oreRule)<tr><td>{{ $oreRule->ore_name }}</td><td>{{ $oreRule->valuation_mode === 'reprocessed' ? 'Reprocessed price' : 'Compressed price' }}</td><td>{{ $oreRule->valuation_mode === 'reprocessed' ? number_format((float)$oreRule->output_quantity_per_input, 8).' × '.$oreRule->output_name.' per ore unit' : '—' }}</td><td><form class="inline" method="post" action="{{ route('admin.appraisal-ore-rules.destroy',$oreRule->ore_type_id) }}">@csrf @method('DELETE')<button style="background:#34465e;color:#e8edf5">Remove</button></form></td></tr>@empty<tr><td colspan="4">No ore-specific overrides. All ores use compressed pricing.</td></tr>@endforelse
</tbody></table>
@if(!$oreTypes->count())<p class="muted">Import CCP SDE types and taxonomy first. Ore options are loaded from the Asteroid category.</p>@endif
<script>
(()=>{const mode=document.getElementById('ore-valuation-mode');const output=document.getElementById('ore-output-type');const ratio=document.getElementById('ore-output-ratio');const sync=()=>{const active=mode.value==='reprocessed';output.required=active;ratio.required=active;output.disabled=!active;ratio.disabled=!active;};mode.addEventListener('change',sync);sync();})();
</script>
<h2 style="margin-top:24px">Publish runner contract</h2><form method="post" action="{{ route('runner.jobs.create') }}">@csrf<div class="row"><label>Origin<input name="origin" required maxlength="100"></label><label>Destination<input name="destination" required maxlength="100"></label><label>EVE courier contract ID<input name="contract_id" type="number" min="1" required></label><label>Runner reward (ISK)<input name="reward_isk" type="number" min="0.01" step="0.01" required></label><label>Appraisal quote ID (optional)<input name="appraisal_quote_id" type="number"></label></div><label>Notes<textarea name="notes"></textarea></label><button>Publish job</button></form></section>@endif
</section>
<section class="tab-panel" id="tab-admin-tools" role="tabpanel" hidden>
</section>
<section class="tab-panel" id="tab-ore-refining" role="tabpanel" hidden>
<iframe title="Ore Refining Comparison" src="{{ route('ore-values.index') }}" loading="lazy" style="display:block;width:100%;height:1900px;border:0;border-radius:12px;background:#070510"></iframe>
</section>
@endif
@if($admin)
<section class="tab-panel" id="tab-marketbaiters" role="tabpanel" hidden>
<section class="panel">
<h2>Marketbaiters appraisal comparison</h2>
<p style="color:#d9cbea;line-height:1.8">Compare one of your Lentivirus Buyback quotes with a Marketbaiters appraisal. Marketbaiters currently requires you to enter the cargo manifest and choose a pickup location on its own website. Until a supported integration is confirmed, enter the external total here after obtaining it there.</p>
<p><a href="https://marketbaiters.com/" target="_blank" rel="noopener" style="color:#c6a4ff;font-weight:700">Open Marketbaiters in a new tab ↗</a></p>
<label for="marketbaiters-quote">Your appraisal</label>
<select id="marketbaiters-quote">
<option value="">Select a Lentivirus appraisal</option>
@foreach($quotes->concat($expiredQuotes)->sortByDesc('created_at') as $quote)
<option value="{{ $quote->id }}" data-value="{{ (float)$quote->payout_value }}" data-url="{{ route('appraisals.show',$quote->public_token) }}">#{{ substr($quote->public_token,0,12) }} · {{ $quote->created_at }} · {{ number_format((float)$quote->payout_value,2) }} ISK</option>
@endforeach
</select>
<p><a id="marketbaiters-open-quote" href="#" target="_blank" rel="noopener" style="display:none;color:#c6a4ff">Open selected appraisal details to copy the item list ↗</a></p>
<label for="marketbaiters-value">Marketbaiters appraisal total (ISK)</label>
<input id="marketbaiters-value" type="number" min="0" step="0.01" placeholder="Enter the total shown by Marketbaiters">
<button type="button" id="marketbaiters-compare">Compare values</button>
<div id="marketbaiters-result" class="stat" style="display:none;margin-top:16px" aria-live="polite"></div>
<p class="muted" style="line-height:1.7">Workflow: select your quote, open its details and copy the item list, open Marketbaiters, paste the cargo manifest, choose the matching pickup location, and run its appraisal. Enter the Marketbaiters total above to calculate the difference. This first version is a manual comparison; it does not submit data to Marketbaiters or retrieve its result automatically.</p>
</section>
</section>
@endif
<section class="tab-panel" id="tab-how-to" role="tabpanel" hidden>
<section class="panel">
<h2>How to sell items through Lentivirus Buyback</h2>
<p style="color:#d9cbea;line-height:1.8">Use the appraisal process first. An appraisal is a price estimate for review; it is not an accepted sale, a contract, or confirmation that payment has been made.</p>
<h2>1. Check what we accept</h2>
<p style="color:#d9cbea;line-height:1.8">We are currently accepting <strong>Ore, Ice, PI Goods, and Exploration Loot</strong> only. Items outside these categories may not be accepted.</p>
<h2>2. Prepare your item list in EVE</h2>
<p style="color:#d9cbea;line-height:1.8">Open the inventory containing the items you want appraised and copy or export the item names and quantities. Keep the item names and quantities together so the appraisal can identify each item correctly.</p>
<h2>3. Submit a new appraisal</h2>
<ol style="color:#d9cbea;line-height:1.9;padding-left:24px">
<li>Open the <strong>New Appraisals</strong> tab.</li>
<li>Enter the system where the items are located, if applicable.</li>
<li>Paste the inventory list into the item list field. EVE tab-separated exports, CSV, and lines in the format “quantity x item name” are supported.</li>
<li>Select <strong>Calculate appraisal</strong> to generate the quote.</li>
</ol>
<h2>4. Review the quote and wait for approval</h2>
<p style="color:#d9cbea;line-height:1.8">Open the appraisal result and check the listed items and estimated payout. The quote is an estimate only. Buyback price settings are currently fluctuating, so the team must review it and confirm whether the quoted values are approved.</p>
<h2>5. Do not create a contract yet</h2>
<p style="color:#d9cbea;line-height:1.8"><strong>Appraisals only — do not create an in-game contract.</strong> Wait for the team to confirm approval and provide the next instructions before taking further action with the items. This is a temporary process.</p>
<h2>6. Keep track of your appraisal</h2>
<p style="color:#d9cbea;line-height:1.8">Use <strong>Appraisals &amp; Contracts</strong> to find your recent appraisals. Appraisals older than 6 hours are moved to <strong>Expired Appraisals</strong>. An expired quote is no longer current; submit a new appraisal if you still want the items reviewed.</p>
</section>
</section>
<script>
const quoteSelect=document.getElementById('marketbaiters-quote');const quoteLink=document.getElementById('marketbaiters-open-quote');const externalInput=document.getElementById('marketbaiters-value');const compareButton=document.getElementById('marketbaiters-compare');const result=document.getElementById('marketbaiters-result');if(quoteSelect){quoteSelect.addEventListener('change',()=>{const option=quoteSelect.selectedOptions[0];const url=option?.dataset.url||'';quoteLink.href=url;quoteLink.style.display=url?'inline':'none';result.style.display='none';});compareButton.addEventListener('click',()=>{const option=quoteSelect.selectedOptions[0];const ours=Number(option?.dataset.value);const theirs=Number(externalInput.value);if(!option?.value||!Number.isFinite(ours)||!externalInput.value||!Number.isFinite(theirs)||theirs<0){result.style.display='block';result.textContent='Select an appraisal and enter a valid Marketbaiters total.';return;}const diff=theirs-ours;const pct=ours>0?(diff/ours)*100:0;const direction=diff>0?'higher':diff<0?'lower':'the same as';const fmt=n=>n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});result.style.display='block';result.innerHTML='<div class="muted">Lentivirus Buyback payout</div><strong>'+fmt(ours)+' ISK</strong><div class="muted" style="margin-top:10px">Marketbaiters appraisal</div><strong>'+fmt(theirs)+' ISK</strong><div style="margin-top:12px">Marketbaiters is <strong>'+fmt(Math.abs(diff))+' ISK '+direction+'</strong> ('+Math.abs(pct).toFixed(2)+'%).</div>';});}
(()=>{const tabs=[...document.querySelectorAll('[data-tab]')];const panels=[...document.querySelectorAll('.tab-panel')];tabs.forEach(tab=>tab.addEventListener('click',()=>{const target=tab.dataset.tab;tabs.forEach(item=>{const active=item===tab;item.classList.toggle('active',active);item.setAttribute('aria-selected',active?'true':'false');});panels.forEach(panel=>{const active=panel.id===target;panel.classList.toggle('active',active);panel.hidden=!active;});}));})();
</script>
<p class="muted">Internal ledger values are not confirmation of an in-game ISK transfer. Payments must be reconciled manually.</p></main></body></html>