<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Holo Comparison · EveBuyback</title>
    <style>
        :root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;padding:22px;background:#071018;color:#eaf7f8;font:15px/1.55 system-ui,-apple-system,Segoe UI,sans-serif}main{max-width:1200px;margin:0 auto}.panel{background:rgba(10,27,36,.94);border:1px solid #315665;border-radius:14px;padding:20px;margin:18px 0;box-shadow:0 12px 32px #0005}h1,h2{margin-top:0;color:#eaf7f8}h1{font-size:26px}h2{font-size:20px}p{color:#b8cbd1}.muted{color:#9bb5bf}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:16px 0}.stat{padding:13px;background:#0b1820;border:1px solid #294a56;border-radius:10px;color:#9bb5bf}.stat strong{display:block;color:#eaf7f8;font-size:23px;margin-top:4px}button{cursor:pointer;border:0;border-radius:8px;padding:11px 16px;background:linear-gradient(110deg,#62d9e9,#ffad58);color:#071018;font-weight:800;margin:5px 8px 5px 0}button:disabled{opacity:.45;cursor:not-allowed}a{color:#72deef}textarea{display:block;width:100%;min-height:190px;padding:12px;background:#07131a;color:#eaf7f8;border:1px solid #315665;border-radius:9px;font:13px/1.5 ui-monospace,monospace}label{display:block;margin-bottom:7px;font-weight:700}.compare-blocks{display:grid;grid-template-columns:1fr 1fr;gap:14px}.comparison-table{width:100%;border-collapse:collapse;margin-top:14px}.comparison-table th,.comparison-table td{padding:9px;border-bottom:1px solid #294a56;text-align:left;font-size:12px}.comparison-status{font-size:12px;font-weight:700}.comparison-status.match{color:#8de4a8}.comparison-status.diff{color:#ffb45f}.comparison-status.missing{color:#ff927c}@media(max-width:700px){body{padding:12px}.compare-blocks{grid-template-columns:1fr}.panel{padding:14px}}
    </style>
</head>
<body>
<main>
    @include('partials.site-header')
    <h1>Holo Comparison</h1>
    <p class="muted">This standalone admin page loads only the inventory catalogs required for this comparison. It does not load the full dashboard, appraisal history, or unrelated pricing settings.</p>

    <section class="panel">
        <h2>Prepare the complete Holo test inventory</h2>
        <p>Copy the same test inventory into Holo and EveBuyback: every compressed asteroid ore, compressed ice, compressed gas and uncompressed gas type at 10,000 units each, plus every PI material from P0 through P4 at 1,000 units each.</p>
        <div class="grid">
            <div class="stat">Compressed ores<strong>{{ $compressedOreTypes->count() }}</strong></div>
            <div class="stat">Compressed ice<strong>{{ $iceTypes->count() }}</strong></div>
            <div class="stat">Compressed gas<strong>{{ $compressedGasTypes->count() }}</strong></div>
            <div class="stat">Uncompressed gas<strong>{{ $uncompressedGasTypes->count() }}</strong></div>
            <div class="stat">PI materials P0–P4<strong>{{ $piMaterialTypes->count() }}</strong></div>
        </div>
        <button type="button" id="copy-holo-test-inventory">Copy complete test inventory</button>
        <span id="holo-test-copy-status" class="muted" aria-live="polite"></span>
        <textarea id="holo-test-inventory" readonly>@foreach($compressedOreTypes as $item){{ $item->type_name }}&#9;10000
@endforeach
@foreach($iceTypes as $item){{ $item->type_name }}&#9;10000
@endforeach
@foreach($compressedGasTypes as $item){{ $item->type_name }}&#9;10000
@endforeach
@foreach($uncompressedGasTypes as $item){{ $item->type_name }}&#9;10000
@endforeach
@foreach($piMaterialTypes as $item){{ $item->type_name }}&#9;1000
@endforeach</textarea>
    </section>

    <section class="panel">
        <h2>Compare Holo's Buyback with EveBuyback</h2>
        <p><a href="https://evebb.eu/" target="_blank" rel="noopener">Open Holo's Buyback in a new tab ↗</a></p>
        <p>Run the same inventory through Holo's appraisal page, then paste Holo's copied item list or appraisal table below. Compare it against an EveBuyback appraisal link or a copied EveBuyback item list. This compares item names and quantities line by line, not payout values.</p>
        <div class="compare-blocks">
            <div><label for="holo-input">Holo appraisal or copied item list</label><textarea id="holo-input" placeholder="Paste Holo's copied appraisal items here"></textarea></div>
            <div><label for="holo-buyback-input">EveBuyback appraisal link or copied item list</label><textarea id="holo-buyback-input" placeholder="Paste an EveBuyback /q/ link or copied item list here"></textarea></div>
        </div>
        <button type="button" id="compare-holo-appraisals">Compare Holo with EveBuyback</button>
        <button type="button" id="export-holo-comparison" disabled>Export to Excel</button>
        <span id="holo-compare-status" class="muted" aria-live="polite"></span>
        <div id="holo-compare-results" style="display:none;margin-top:16px"></div>
    </section>
</main>
<script>
const csrf=document.querySelector('meta[name="csrf-token"]').content;
const copyButton=document.getElementById('copy-holo-test-inventory');
copyButton.addEventListener('click',async()=>{const manifest=document.getElementById('holo-test-inventory');const status=document.getElementById('holo-test-copy-status');try{await navigator.clipboard.writeText(manifest.value);status.textContent='Copied '+manifest.value.trim().split(/\\r?\\n/).filter(Boolean).length+' inventory types.';}catch(e){manifest.focus();manifest.select();status.textContent='Clipboard access was blocked. The manifest is selected; copy it manually.';}});
let comparison=null;
const exportButton=document.getElementById('export-holo-comparison');
function exportWorkbook(payload){
 const esc=value=>String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&apos;');
 const cell=value=>'<Cell><Data ss:Type="'+(typeof value==='number'&&Number.isFinite(value)?'Number':'String')+'">'+esc(value)+'</Data></Cell>';
 const sheet=(name,rows)=>'<Worksheet ss:Name="'+name+'"><Table>'+rows.map(row=>'<Row>'+row.map(cell).join('')+'</Row>').join('')+'</Table></Worksheet>';
 const summary=[['Metric','Value'],['Holo item types',payload.holo_items],['EveBuyback item types',payload.buyback_items],['Exact matches',payload.matched],['Differences',payload.differences]];
 const rows=[['Item','Holo quantity','EveBuyback quantity','Difference (EveBuyback - Holo)','Status'],...payload.rows.map(row=>[row.name,row.holo_quantity,row.buyback_quantity,row.difference,({match:'Match',quantity_mismatch:'Quantity mismatch',missing_holo:'Missing from Holo',missing_buyback:'Missing from EveBuyback'})[row.status]||row.status])];
 const xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'+sheet('Summary',summary)+sheet('Item Comparison',rows)+'</Workbook>';
 const blob=new Blob(['\\uFEFF',xml],{type:'application/vnd.ms-excel;charset=utf-8'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='Holo-EveBuyback-Inventory-Comparison.xls';document.body.appendChild(link);link.click();link.remove();URL.revokeObjectURL(url);
}
exportButton.addEventListener('click',()=>{if(comparison)exportWorkbook(comparison);});
document.getElementById('compare-holo-appraisals').addEventListener('click',async()=>{
 const status=document.getElementById('holo-compare-status');const output=document.getElementById('holo-compare-results');const button=document.getElementById('compare-holo-appraisals');
 status.textContent='Comparing Holo and EveBuyback contents…';output.style.display='none';exportButton.disabled=true;comparison=null;button.disabled=true;
 try{
  const response=await fetch('{{ route('admin.holo-comparison') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({holo_input:document.getElementById('holo-input').value,buyback_input:document.getElementById('holo-buyback-input').value})});
  const payload=await response.json();if(!response.ok)throw new Error(Object.values(payload.errors||{}).flat().join(' ')||payload.message||'Holo comparison failed.');
  comparison=payload;exportButton.disabled=false;const safe=s=>String(s).replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  status.textContent=payload.matched+' exact matches · '+payload.differences+' differences';output.style.display='block';
  output.innerHTML='<div class="stat">Holo item types: <strong>'+payload.holo_items+'</strong><br>EveBuyback item types: <strong>'+payload.buyback_items+'</strong><br>Exact matches: <strong>'+payload.matched+'</strong> · Differences: <strong>'+payload.differences+'</strong></div><div style="overflow-x:auto"><table class="comparison-table"><thead><tr><th>Item</th><th>Holo qty</th><th>EveBuyback qty</th><th>Difference</th><th>Result</th></tr></thead><tbody>'+payload.rows.map(row=>{const cls=row.status==='match'?'match':row.status==='quantity_mismatch'?'diff':'missing';const label=row.status==='match'?'Match':row.status==='quantity_mismatch'?'Quantity mismatch':row.status==='missing_holo'?'Missing from Holo':'Missing from EveBuyback';return '<tr><td>'+safe(row.name)+'</td><td>'+Number(row.holo_quantity).toLocaleString()+'</td><td>'+Number(row.buyback_quantity).toLocaleString()+'</td><td>'+Number(row.difference).toLocaleString()+'</td><td class="comparison-status '+cls+'">'+label+'</td></tr>';}).join('')+'</tbody></table></div>';
 }catch(error){status.textContent=error.message;}finally{button.disabled=false;}
});
</script>
</body>
</html>
