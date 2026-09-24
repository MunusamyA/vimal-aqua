<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'GST Report';
$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css',
];
$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
<title><?php echo web_h((string)$pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
<?php render_frontend_config_script(); ?>
<script src="assets/js/runtime.js"></script>
<?php foreach ($headStyles as $url): ?><link rel="stylesheet" href="<?php echo web_h($url); ?>"><?php endforeach; ?>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<?php foreach ($headScripts as $url): ?><script src="<?php echo web_h($url); ?>"></script><?php endforeach; ?>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
<main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
<section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/datatable.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head"><div><h1>GST Report</h1><p>HSN and GST-rate summary for posted Sales Invoices and posted Purchases.</p></div></div>
<div class="kpi-grid">
<div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="arrow-up-right"></i></div><div><div class="kpi-label">Sales Taxable</div><div class="kpi-value" id="salesTaxable">₹0.00</div></div></div>
<div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="percent"></i></div><div><div class="kpi-label">Sales GST</div><div class="kpi-value" id="salesGst">₹0.00</div></div></div>
<div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="arrow-down-left"></i></div><div><div class="kpi-label">Purchase Taxable</div><div class="kpi-value" id="purchaseTaxable">₹0.00</div></div></div>
<div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="percent"></i></div><div><div class="kpi-label">Purchase GST</div><div class="kpi-value" id="purchaseGst">₹0.00</div></div></div>
</div>
<div class="card"><div class="card-header"><div><div class="card-title">GST Filters</div><div class="card-description">Current database stores total GST. CGST / SGST / IGST cannot be split reliably without transaction-level place-of-supply/component data.</div></div><div class="buttons"><button class="btn btn-primary" id="applyFilters" type="button"><i data-lucide="filter"></i>Apply Filters</button><button class="btn gray" id="resetFilters" type="button"><i data-lucide="rotate-ccw"></i>Reset</button></div></div><div class="card-body"><div class="form-row">
<div class="field col-4"><label for="gstSearch">Search</label><input class="input" id="gstSearch" type="text" placeholder="Product / HSN..."></div>
<div class="field col-2"><label for="gstType">Report Type</label><select class="select" id="gstType"><option value="both">Sales + Purchase</option><option value="sales">Sales</option><option value="purchase">Purchase</option></select></div>
<div class="field col-3"><label for="dateFrom">From Date</label><input class="input" id="dateFrom" type="date"></div><div class="field col-3"><label for="dateTo">To Date</label><input class="input" id="dateTo" type="date"></div>
</div></div></div>
<div class="card table-card"><div class="card-header"><div><div class="card-title">GST Summary</div><div class="card-description">Grouped by transaction type, HSN and GST rate.</div></div></div><div class="app-table-wrap"><table id="gstTable" class="display data-table"><thead><tr><th>Type</th><th>HSN</th><th>GST Rate</th><th>Documents</th><th>Taxable Value</th><th>GST Amount</th><th>Net Value</th></tr></thead></table></div></div>
<script>
(function($,window,document){'use strict';if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;var table=null,searchTimer=null;function money(v){return '₹'+Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}function sum(s){s=s||{};document.getElementById('salesTaxable').textContent=money(s.sales_taxable);document.getElementById('salesGst').textContent=money(s.sales_gst);document.getElementById('purchaseTaxable').textContent=money(s.purchase_taxable);document.getElementById('purchaseGst').textContent=money(s.purchase_gst);}function query(d){var p=new URLSearchParams();p.set('report','gst');p.set('datatable','1');p.set('draw',d.draw);p.set('start',d.start);p.set('length',d.length);p.set('search[value]',d.search.value||'');p.set('gst_type',document.getElementById('gstType').value||'both');if(document.getElementById('dateFrom').value)p.set('date_from',document.getElementById('dateFrom').value);if(document.getElementById('dateTo').value)p.set('date_to',document.getElementById('dateTo').value);if(d.order&&d.order[0]){p.set('order[0][column]',d.order[0].column);p.set('order[0][dir]',d.order[0].dir);}return p;}
function init(){table=AppDataTable.init('#gstTable',{serverSide:true,searching:true,appSearch:false,pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],order:[[0,'asc'],[1,'asc']],scrollX:true,autoWidth:false,buttons:['copyHtml5','csvHtml5','excelHtml5',{extend:'pdfHtml5',orientation:'landscape',pageSize:'A4'},'print'].map(function(b){var o=typeof b==='string'?{extend:b}:b;o.title='GST Report';o.action=AppDataTable.serverSideExportAction;o.exportOptions={columns:[0,1,2,3,4,5,6]};return o;}),ajax:function(d,cb){App.api('api/reports.php?'+query(d)).then(function(r){sum(r.data.summary);AppDataTable.applyExportPermissions(table,r.data.allowed_actions||[]);cb(r.data.datatable);}).catch(function(e){App.showError(e,'Unable to load GST Report.');cb({draw:d.draw,recordsTotal:0,recordsFiltered:0,data:[]});});},columns:[{data:'report_type',render:function(v,t){return t==='display'?(v==='Sales'?'<span class="pill active">Sales</span>':'<span class="pill pending">Purchase</span>'):v;}},{data:'hsn_code'},{data:'gst_rate',className:'dt-body-right',render:function(v,t){return t==='display'?Number(v||0).toFixed(2)+'%':Number(v||0);}},{data:'document_count',className:'dt-body-right'},{data:'taxable_value',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'gst_amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'net_value',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}}],language:{emptyTable:'No GST transactions found.',zeroRecords:'No matching GST transactions found.'}});var card=document.getElementById('gstTable').closest('.table-card'),dup=card?card.querySelector('.app-table-search-row'):null;if(dup)dup.remove();}
async function boot(){try{var r=await App.api('api/reports.php?report=gst&options=1'),d=r.data||{};GlobalSelect.init(document.getElementById('gstType'),{placeholder:'Report Type'});var today=d.today||new Date().toISOString().slice(0,10);document.getElementById('dateFrom').value=today.slice(0,8)+'01';document.getElementById('dateTo').value=today;init();}catch(e){App.showError(e,'Unable to load GST Report options.');}}
document.getElementById('gstSearch').addEventListener('input',function(){var self=this;clearTimeout(searchTimer);searchTimer=setTimeout(function(){if(table)table.search(self.value.trim()).draw();},350);});document.getElementById('applyFilters').onclick=function(){if(table)table.ajax.reload(null,true);};document.getElementById('resetFilters').onclick=function(){document.getElementById('gstSearch').value='';document.getElementById('gstType').value='both';if(document.getElementById('gstType')._globalSelect)document.getElementById('gstType')._globalSelect.refresh();var t=new Date().toISOString().slice(0,10);document.getElementById('dateFrom').value=t.slice(0,8)+'01';document.getElementById('dateTo').value=t;if(table){table.search('');table.ajax.reload(null,true);}};boot();
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
