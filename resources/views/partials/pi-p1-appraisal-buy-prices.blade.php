<details class="panel p1-appraisal-buy-prices"><summary style="cursor:pointer;color:#62d9e9;font-weight:700">Individual {{ $tier }} appraisal buy prices and saved rules</summary><div style="padding-top:12px">
<h2>Individual {{ $tier }} appraisal buy prices</h2>
<p class="muted">Each {{ $tier }} commodity appears once. The recommended percentage is the lowest target calculated across every P2 recipe that uses that input. Press Set to use that percentage as the individual appraisal payout rate for this commodity, replacing the shared PI category rate. The setting is saved until changed.</p>
@if(session('status'))<div class="result" style="margin:10px 0">{{ session('status') }}</div>@endif
@if($tierBuyTargets->isEmpty())
<div class="empty">No {{ $tier }} buy targets are available yet. Check that {{ $tier }} Jita prices and all {{ $tier }} → P2 recipe prices are loaded.</div>
@else
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;min-width:650px">
<thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid #294a56">{{ $tier }} commodity</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Jita buy</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Lowest recipe target</th><th style="text-align:left;padding:10px;border-bottom:1px solid #294a56">Lowest-target recipe</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Current appraisal rate</th><th style="text-align:right;padding:10px;border-bottom:1px solid #294a56">Action</th></tr></thead>
<tbody>
@foreach($tierBuyTargets as $item)
<tr>
<td style="padding:10px;border-bottom:1px solid #294a56">{{ $item['name'] }} <span class="muted">({{ $item['recipes'] }} recipe{{ $item['recipes'] === 1 ? '' : 's' }})</span></td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">{{ $item['jita_buy'] !== null ? number_format($item['jita_buy'],2).' ISK' : 'No price' }}</td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">@if($item['lowest_percent'] !== null) {{ number_format($item['lowest_percent'],2) }}% @else <span class="muted">Unavailable</span> @endif</td>
<td style="padding:10px;text-align:left;border-bottom:1px solid #294a56">@if(!empty($item['lowest_recipes'])) {{ implode(', ', $item['lowest_recipes']) }} @else <span class="muted">No calculated recipe target</span> @endif @if(!$item['target_complete']) <div class="muted">Some recipe targets unavailable</div> @endif</td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">@if($item['is_set']) {{ number_format($item['saved_percent'],2) }}% @else <span class="muted">Shared PI rate</span> @endif</td>
<td style="padding:10px;text-align:right;border-bottom:1px solid #294a56">
<div style="display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap">
<form method="post" action="{{ route('pi-planner.p1-buy-rule') }}" style="margin:0">
@csrf
<input type="hidden" name="type_id" value="{{ $item['type_id'] }}">
<input type="hidden" name="buy_percent" value="{{ $item['can_set'] ? number_format($item['lowest_percent'],2,'.','') : '' }}">
<button type="submit" style="padding:7px 10px" {{ $item['can_set'] ? '' : 'disabled' }}>Calculated</button>
</form>
<form method="post" action="{{ route('pi-planner.p1-buy-rule') }}" style="margin:0">
@csrf
<input type="hidden" name="type_id" value="{{ $item['type_id'] }}">
<input type="hidden" name="buy_percent" value="80">
<button type="submit" style="padding:7px 10px">80% HI</button>
</form>
<form method="post" action="{{ route('pi-planner.p1-buy-rule') }}" style="margin:0">
@csrf
<input type="hidden" name="type_id" value="{{ $item['type_id'] }}">
<input type="hidden" name="buy_percent" value="92">
<button type="submit" style="padding:7px 10px">92% LOW</button>
</form>
</div>
</td>
</tr>
@endforeach
</tbody></table></div>
@endif
</div></details>