<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Sales Report';
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

<div class="page-head">
  <div><h1>Sales Report</h1><p>Sales invoice totals, collections, outstanding and GST.</p></div>
</div>
<div class="kpi-grid">
  <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="receipt-text"></i></div><div><div class="kpi-label">Sales</div><div class="kpi-value" id="kpiSales">0</div></div></div>
  <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="indian-rupee"></i></div><div><div class="kpi-label">Grand Total</div><div class="kpi-value" id="kpiGrand">₹0.00</div></div></div>
  <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="badge-check"></i></div><div><div class="kpi-label">Paid</div><div class="kpi-value" id="kpiPaid">₹0.00</div></div></div>
  <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="clock-3"></i></div><div><div class="kpi-label">Outstanding</div><div class="kpi-value" id="kpiBalance">₹0.00</div></div></div>
  <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="percent"></i></div><div><div class="kpi-label">GST</div><div class="kpi-value" id="kpiTax">₹0.00</div></div></div>
</div>
<div class="card">
  <div class="card-header"><div><div class="card-title">Report Filters</div><div class="card-description">Filter sales invoices by customer, tax, payment and date.</div></div><div class="buttons"><button class="btn btn-primary" id="applyFilters" type="button"><i data-lucide="filter"></i>Apply Filters</button><button class="btn gray" id="resetFilters" type="button"><i data-lucide="rotate-ccw"></i>Reset</button></div></div>
  <div class="card-body"><div class="form-row">
    <div class="field col-3"><label for="salesSearch">Search</label><input class="input" id="salesSearch" type="text" autocomplete="off" placeholder="Sale no, customer, remarks..."></div>
    <div class="field col-3"><label for="customerFilter">Customer</label><select class="select" id="customerFilter" data-placeholder="All Customers"><option value="">All Customers</option></select></div>
    <div class="field col-2"><label for="documentFilter">Document</label><select class="select" id="documentFilter"><option value="">All Documents</option><option value="1">Quotation</option><option value="2" selected>Sales Invoice</option><option value="3">Customer Order</option></select></div>
    <div class="field col-2"><label for="taxFilter">Tax Mode</label><select class="select" id="taxFilter"><option value="">All</option><option value="1">GST</option><option value="0">Non-GST</option></select></div>
    <div class="field col-2"><label for="paymentFilter">Payment</label><select class="select" id="paymentFilter"><option value="">All</option><option value="1">Unpaid</option><option value="2">Partially Paid</option><option value="3">Paid</option></select></div>
    <div class="field col-2"><label for="statusFilter">Status</label><select class="select" id="statusFilter"><option value="">All</option><option value="1">Draft</option><option value="2" selected>Posted</option><option value="3">Cancelled</option></select></div>
    <div class="field col-2"><label for="dateFrom">From Date</label><input class="input" id="dateFrom" type="date"></div>
    <div class="field col-2"><label for="dateTo">To Date</label><input class="input" id="dateTo" type="date"></div>
  </div></div>
</div>
<div class="card table-card">
  <div class="card-header"><div><div class="card-title">Sales Details</div><div class="card-description">Posted Sales Invoice is selected by default.</div></div></div>
  <div class="app-table-wrap"><table id="salesReportTable" class="display data-table"><thead><tr><th>Sale</th><th>Date</th><th>Customer</th><th>Document</th><th>Tax</th><th>Grand Total</th><th>Paid</th><th>Outstanding</th><th>Payment</th><th>Status</th><th>View</th><th>Sale Type</th><th>Subtotal</th><th>Discount</th><th>GST</th><th>Other Charges</th><th>Round Off</th></tr></thead></table></div>
</div>
<script>
(function($,window,document){'use strict';
if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;
var table=null,actions=[],searchTimer=null,customerSelect=null;
var has=AppDataTable.has;
function money(v){return '₹'+Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function dateText(v){if(!v)return '-';var p=String(v).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:v;}
function refreshSelect(id){var n=document.getElementById(id);if(n&&n._globalSelect&&typeof n._globalSelect.refresh==='function')n._globalSelect.refresh();}
function statusPill(v){v=Number(v||0);return v===2?'<span class="pill active">Posted</span>':(v===3?'<span class="pill inactive">Cancelled</span>':'<span class="pill pending">Draft</span>');}
function paymentPill(v){v=Number(v||0);return v===3?'<span class="pill active">Paid</span>':(v===2?'<span class="pill pending">Partially Paid</span>':'<span class="pill inactive">Unpaid</span>');}
function setSummary(s){s=s||{};document.getElementById('kpiSales').textContent=Number(s.sale_count||0).toLocaleString('en-IN');document.getElementById('kpiGrand').textContent=money(s.grand_total);document.getElementById('kpiPaid').textContent=money(s.paid_amount);document.getElementById('kpiBalance').textContent=money(s.balance_amount);document.getElementById('kpiTax').textContent=money(s.tax_amount);}
function params(data){var p=new URLSearchParams();p.set('report','sales');p.set('datatable','1');p.set('draw',data.draw);p.set('start',data.start);p.set('length',data.length);p.set('search[value]',data.search.value||'');[['customer_ref','customerFilter'],['document_type','documentFilter'],['tax_mode','taxFilter'],['payment_status','paymentFilter'],['status','statusFilter'],['date_from','dateFrom'],['date_to','dateTo']].forEach(function(x){var v=document.getElementById(x[1]).value;if(v!=='')p.set(x[0],v);});if(data.order&&data.order[0]){p.set('order[0][column]',data.order[0].column);p.set('order[0][dir]',data.order[0].dir);}return p;}
function initTable(){table=AppDataTable.init('#salesReportTable',{serverSide:true,searching:true,searchDelay:350,appSearch:false,pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],order:[[1,'desc']],scrollX:true,autoWidth:false,columnDefs:[{targets:[11,12,13,14,15,16],visible:false}],buttons:[
{extend:'copyHtml5',text:'Copy',title:'Sales Report',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]}},
{extend:'csvHtml5',text:'CSV',title:'Sales Report',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]}},
{extend:'excelHtml5',text:'Excel',title:'Sales Report',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]}},
{extend:'pdfHtml5',text:'PDF',title:'Sales Report',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]}},
{extend:'print',text:'Print',title:'Sales Report',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]}}
],ajax:function(data,cb){App.api('api/reports.php?'+params(data).toString()).then(function(r){actions=(r.data.allowed_actions||[]).map(Number);setSummary(r.data.summary);AppDataTable.applyExportPermissions(table,actions);cb(r.data.datatable);}).catch(function(e){App.showError(e,'Unable to load Sales Report.');cb({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});});},columns:[
{data:'sale_no',defaultContent:'-'},{data:'sale_date',render:function(v,t){return t==='display'?dateText(v):v;}},{data:'customer_label',defaultContent:'-'},{data:'document_type_label',defaultContent:'-'},{data:'tax_mode',render:function(v,t){return t==='display'?(Number(v)===1?'<span class="pill active">GST</span>':'<span class="pill pending">Non-GST</span>'):Number(v);}},
{data:'grand_total',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'paid_amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'balance_amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'payment_status',render:function(v,t){return t==='display'?paymentPill(v):Number(v||0);}},{data:'status',render:function(v,t){return t==='display'?statusPill(v):Number(v||0);}},
{data:null,orderable:false,searchable:false,className:'table-action-icons',render:function(d,t,row){if(t!=='display')return '';return has(actions,1)&&row.view_url?App.iconActionHtml({href:row.view_url,icon:'eye',label:'View Sale'}):'<span class="muted">-</span>'; }},
{data:'sale_type_label',defaultContent:'-'},{data:'subtotal',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'discount_amount',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'tax_amount',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'other_charges',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'round_off',render:function(v,t){return t==='display'?money(v):Number(v||0);}}
],language:{emptyTable:'No Sales found.',zeroRecords:'No matching Sales found.'},drawCallback:function(){if(window.lucide)window.lucide.createIcons();}});
var card=document.getElementById('salesReportTable').closest('.table-card'),dup=card?card.querySelector('.app-table-search-row'):null;if(dup)dup.remove();}
async function boot(){try{var r=await App.api('api/reports.php?report=sales&options=1'),d=r.data||{};var items=(d.customers||[]).map(function(x){return{value:x.ref,text:x.customer_code+' - '+x.customer_name};});customerSelect=GlobalSelect.init(document.getElementById('customerFilter'),{placeholder:'All Customers'});customerSelect.setOptions(items,'');['documentFilter','taxFilter','paymentFilter','statusFilter'].forEach(function(id){GlobalSelect.init(document.getElementById(id),{placeholder:'All'});});var today=d.today||new Date().toISOString().slice(0,10),first=today.slice(0,8)+'01';document.getElementById('dateFrom').value=first;document.getElementById('dateTo').value=today;initTable();}catch(e){App.showError(e,'Unable to load Sales Report options.');}}
document.getElementById('salesSearch').addEventListener('input',function(){var self=this;clearTimeout(searchTimer);searchTimer=setTimeout(function(){if(table)table.search(self.value.trim()).draw();},350);});
document.getElementById('applyFilters').addEventListener('click',function(){if(table)table.ajax.reload(null,true);});
document.getElementById('resetFilters').addEventListener('click',function(){document.getElementById('salesSearch').value='';document.getElementById('customerFilter').value='';document.getElementById('documentFilter').value='2';document.getElementById('taxFilter').value='';document.getElementById('paymentFilter').value='';document.getElementById('statusFilter').value='2';var today=new Date().toISOString().slice(0,10);document.getElementById('dateFrom').value=today.slice(0,8)+'01';document.getElementById('dateTo').value=today;['customerFilter','documentFilter','taxFilter','paymentFilter','statusFilter'].forEach(refreshSelect);if(table){table.search('');table.ajax.reload(null,true);}});
boot();
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
