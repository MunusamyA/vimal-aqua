<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Stock Details Report';
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

<div class="page-head"><div><h1>Stock Details Report</h1><p>Opening stock, period movement, closing stock and stock value.</p></div></div>
<div class="kpi-grid">
<div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="package"></i></div><div><div class="kpi-label">Products</div><div class="kpi-value" id="kpiProducts">0</div></div></div>
<div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">In Stock</div><div class="kpi-value" id="kpiInStock">0</div></div></div>
<div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-alert"></i></div><div><div class="kpi-label">Zero Stock</div><div class="kpi-value" id="kpiZero">0</div></div></div>
<div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="indian-rupee"></i></div><div><div class="kpi-label">Stock Value</div><div class="kpi-value" id="kpiValue">₹0.00</div></div></div>
</div>
<div class="card"><div class="card-header"><div><div class="card-title">Report Filters</div><div class="card-description">Date range shows opening before From Date and movements within the selected period.</div></div><div class="buttons"><button class="btn btn-primary" id="applyFilters" type="button"><i data-lucide="filter"></i>Apply Filters</button><button class="btn gray" id="resetFilters" type="button"><i data-lucide="rotate-ccw"></i>Reset</button></div></div><div class="card-body"><div class="form-row">
<div class="field col-3"><label for="reportSearch">Search</label><input class="input" id="reportSearch" type="text" placeholder="Product or category..."></div><div class="field col-3"><label for="productFilter">Product</label><select class="select" id="productFilter" data-placeholder="All Products"><option value="">All Products</option></select></div><div class="field col-2"><label for="categoryFilter">Category</label><select class="select" id="categoryFilter" data-placeholder="All Categories"><option value="">All Categories</option></select></div><div class="field col-2"><label for="typeFilter">Product Type</label><select class="select" id="typeFilter"><option value="">All</option><option value="1">Raw Material</option><option value="2">Finished Product</option><option value="3">Consumable</option></select></div><div class="field col-2"><label for="statusFilter">Status</label><select class="select" id="statusFilter"><option value="">All</option><option value="1" selected>Active</option><option value="2">Inactive</option></select></div><div class="field col-2"><label for="dateFrom">From Date</label><input class="input" id="dateFrom" type="date"></div><div class="field col-2"><label for="dateTo">To Date</label><input class="input" id="dateTo" type="date"></div>
</div></div></div>
<div class="card table-card"><div class="card-header"><div><div class="card-title">Stock Details</div><div class="card-description">Closing = Opening + Stock In − Stock Out.</div></div></div><div class="app-table-wrap"><table id="stockTable" class="display data-table"><thead><tr><th>Code</th><th>Product</th><th>Category</th><th>Type</th><th>Unit</th><th>Opening</th><th>Stock In</th><th>Stock Out</th><th>Closing</th><th>Purchase In</th><th>Production In</th><th>Sale Out</th><th>Purchase Price</th><th>Stock Value</th></tr></thead></table></div></div>
<script>
(function($,window,document){'use strict';if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;var table=null,searchTimer=null,productSelect=null,categorySelect=null;function money(v){return '₹'+Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}function qty(v){return Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:3,maximumFractionDigits:3});}function refresh(id){var n=document.getElementById(id);if(n&&n._globalSelect&&typeof n._globalSelect.refresh==='function')n._globalSelect.refresh();}function summary(s){s=s||{};document.getElementById('kpiProducts').textContent=Number(s.product_count||0).toLocaleString('en-IN');document.getElementById('kpiInStock').textContent=Number(s.in_stock_count||0).toLocaleString('en-IN');document.getElementById('kpiZero').textContent=Number(s.zero_stock_count||0).toLocaleString('en-IN');document.getElementById('kpiValue').textContent=money(s.stock_value);}function query(d){var p=new URLSearchParams();p.set('report','stock');p.set('datatable','1');p.set('draw',d.draw);p.set('start',d.start);p.set('length',d.length);p.set('search[value]',d.search.value||'');[['product_ref','productFilter'],['category_ref','categoryFilter'],['product_type','typeFilter'],['status','statusFilter'],['date_from','dateFrom'],['date_to','dateTo']].forEach(function(x){var v=document.getElementById(x[1]).value;if(v!=='')p.set(x[0],v);});if(d.order&&d.order[0]){p.set('order[0][column]',d.order[0].column);p.set('order[0][dir]',d.order[0].dir);}return p;}
function init(){table=AppDataTable.init('#stockTable',{serverSide:true,searching:true,appSearch:false,pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],order:[[1,'asc']],scrollX:true,autoWidth:false,buttons:['copyHtml5','csvHtml5','excelHtml5',{extend:'pdfHtml5',orientation:'landscape',pageSize:'A3'},'print'].map(function(b){var o=typeof b==='string'?{extend:b}:b;o.title='Stock Details Report';o.action=AppDataTable.serverSideExportAction;o.exportOptions={columns:[0,1,2,3,4,5,6,7,8,9,10,11,12,13]};return o;}),ajax:function(d,cb){App.api('api/reports.php?'+query(d)).then(function(r){summary(r.data.summary);AppDataTable.applyExportPermissions(table,r.data.allowed_actions||[]);cb(r.data.datatable);}).catch(function(e){App.showError(e,'Unable to load Stock Details Report.');cb({draw:d.draw,recordsTotal:0,recordsFiltered:0,data:[]});});},columns:[{data:'product_code'},{data:'product_name'},{data:'category_name'},{data:'product_type_label'},{data:'primary_unit',defaultContent:'-'},{data:'opening_qty',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'stock_in',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'stock_out',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'closing_qty',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'purchase_in',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'production_in',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'sale_out',className:'dt-body-right',render:function(v,t){return t==='display'?qty(v):Number(v||0);}},{data:'purchase_price',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'stock_value',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}}],language:{emptyTable:'No Stock details found.',zeroRecords:'No matching Stock details found.'}});var card=document.getElementById('stockTable').closest('.table-card'),dup=card?card.querySelector('.app-table-search-row'):null;if(dup)dup.remove();}
async function boot(){try{var r=await App.api('api/reports.php?report=stock&options=1'),d=r.data||{};productSelect=GlobalSelect.init(document.getElementById('productFilter'),{placeholder:'All Products'});productSelect.setOptions((d.products||[]).map(function(x){return{value:x.ref,text:x.product_code+' - '+x.product_name};}),'');categorySelect=GlobalSelect.init(document.getElementById('categoryFilter'),{placeholder:'All Categories'});categorySelect.setOptions((d.categories||[]).map(function(x){return{value:x.ref,text:x.category_code+' - '+x.category_name};}),'');['typeFilter','statusFilter'].forEach(function(id){GlobalSelect.init(document.getElementById(id),{placeholder:'All'});});var today=d.today||new Date().toISOString().slice(0,10);document.getElementById('dateFrom').value=today.slice(0,8)+'01';document.getElementById('dateTo').value=today;init();}catch(e){App.showError(e,'Unable to load Stock Details options.');}}
document.getElementById('reportSearch').addEventListener('input',function(){var self=this;clearTimeout(searchTimer);searchTimer=setTimeout(function(){if(table)table.search(self.value.trim()).draw();},350);});document.getElementById('applyFilters').onclick=function(){if(table)table.ajax.reload(null,true);};document.getElementById('resetFilters').onclick=function(){document.getElementById('reportSearch').value='';document.getElementById('productFilter').value='';document.getElementById('categoryFilter').value='';document.getElementById('typeFilter').value='';document.getElementById('statusFilter').value='1';var t=new Date().toISOString().slice(0,10);document.getElementById('dateFrom').value=t.slice(0,8)+'01';document.getElementById('dateTo').value=t;['productFilter','categoryFilter','typeFilter','statusFilter'].forEach(refresh);if(table){table.search('');table.ajax.reload(null,true);}};boot();
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
