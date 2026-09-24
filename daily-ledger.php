<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Daily Ledger';
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

<div class="page-head"><div><h1>Daily Ledger</h1><p>Day-wise money in and money out across payment accounts.</p></div></div>
<div class="kpi-grid">
<div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="circle-dot"></i></div><div><div class="kpi-label">Opening Balance</div><div class="kpi-value" id="kpiOpening">₹0.00</div></div></div>
<div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="arrow-down-left"></i></div><div><div class="kpi-label">Money In</div><div class="kpi-value" id="kpiIn">₹0.00</div></div></div>
<div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="arrow-up-right"></i></div><div><div class="kpi-label">Money Out</div><div class="kpi-value" id="kpiOut">₹0.00</div></div></div>
<div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="wallet-cards"></i></div><div><div class="kpi-label">Closing Balance</div><div class="kpi-value" id="kpiClosing">₹0.00</div></div></div>
</div>
<div class="card"><div class="card-header"><div><div class="card-title">Ledger Filters</div><div class="card-description">Select a date and optionally one account.</div></div><div class="buttons"><button class="btn btn-primary" id="applyFilters" type="button"><i data-lucide="filter"></i>Apply Filters</button><button class="btn gray" id="resetFilters" type="button"><i data-lucide="rotate-ccw"></i>Today</button></div></div><div class="card-body"><div class="form-row">
<div class="field col-4"><label for="ledgerSearch">Search</label><input class="input" id="ledgerSearch" type="text" placeholder="Account, reference, remarks..."></div><div class="field col-4"><label for="accountFilter">Account</label><select class="select" id="accountFilter" data-placeholder="All Accounts"><option value="">All Accounts</option></select></div><div class="field col-4"><label for="ledgerDate">Ledger Date</label><input class="input" id="ledgerDate" type="date"></div>
</div></div></div>
<div class="card table-card"><div class="card-header"><div><div class="card-title">Daily Transactions</div><div class="card-description">Opening and closing are calculated from account transactions.</div></div></div><div class="app-table-wrap"><table id="dailyLedgerTable" class="display data-table"><thead><tr><th>Date / Time</th><th>Account</th><th>Type</th><th>Source</th><th>Reference</th><th>Remarks</th><th>Money In</th><th>Money Out</th><th>Balance</th></tr></thead></table></div></div>
<script>
(function($,window,document){'use strict';if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;var table=null,searchTimer=null,accountSelect=null,today='';function money(v){return '₹'+Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}function dt(v){if(!v)return '-';var x=String(v).replace(' ','T'),d=new Date(x);return isNaN(d)?String(v):d.toLocaleString('en-IN');}function summary(s){s=s||{};document.getElementById('kpiOpening').textContent=money(s.opening_balance);document.getElementById('kpiIn').textContent=money(s.money_in);document.getElementById('kpiOut').textContent=money(s.money_out);document.getElementById('kpiClosing').textContent=money(s.closing_balance);}function query(d){var p=new URLSearchParams();p.set('report','daily_ledger');p.set('datatable','1');p.set('draw',d.draw);p.set('start',d.start);p.set('length',d.length);p.set('search[value]',d.search.value||'');p.set('ledger_date',document.getElementById('ledgerDate').value||today);var a=document.getElementById('accountFilter').value;if(a)p.set('account_ref',a);if(d.order&&d.order[0]){p.set('order[0][column]',d.order[0].column);p.set('order[0][dir]',d.order[0].dir);}return p;}
function init(){table=AppDataTable.init('#dailyLedgerTable',{serverSide:true,searching:true,appSearch:false,pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],order:[[0,'asc']],scrollX:true,autoWidth:false,buttons:['copyHtml5','csvHtml5','excelHtml5',{extend:'pdfHtml5',orientation:'landscape',pageSize:'A4'},'print'].map(function(b){var o=typeof b==='string'?{extend:b}:b;o.title='Daily Ledger';o.action=AppDataTable.serverSideExportAction;o.exportOptions={columns:[0,1,2,3,4,5,6,7,8]};return o;}),ajax:function(d,cb){App.api('api/reports.php?'+query(d)).then(function(r){summary(r.data.summary);AppDataTable.applyExportPermissions(table,r.data.allowed_actions||[]);cb(r.data.datatable);}).catch(function(e){App.showError(e,'Unable to load Daily Ledger.');cb({draw:d.draw,recordsTotal:0,recordsFiltered:0,data:[]});});},columns:[{data:'transaction_date',render:function(v,t){return t==='display'?dt(v):v;}},{data:'account_label'},{data:'account_type_label'},{data:'source_label'},{data:'reference'},{data:'remarks',defaultContent:'-'},{data:'money_in',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'money_out',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'running_balance',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}}],language:{emptyTable:'No Ledger transactions found.',zeroRecords:'No matching Ledger transactions found.'}});var card=document.getElementById('dailyLedgerTable').closest('.table-card'),dup=card?card.querySelector('.app-table-search-row'):null;if(dup)dup.remove();}
async function boot(){try{var r=await App.api('api/reports.php?report=daily_ledger&options=1'),d=r.data||{};today=d.today||new Date().toISOString().slice(0,10);document.getElementById('ledgerDate').value=today;accountSelect=GlobalSelect.init(document.getElementById('accountFilter'),{placeholder:'All Accounts'});accountSelect.setOptions((d.accounts||[]).map(function(x){return{value:x.ref,text:x.account_code+' - '+x.account_name+' · '+x.account_type_label};}),'');init();}catch(e){App.showError(e,'Unable to load Daily Ledger options.');}}
document.getElementById('ledgerSearch').addEventListener('input',function(){var self=this;clearTimeout(searchTimer);searchTimer=setTimeout(function(){if(table)table.search(self.value.trim()).draw();},350);});document.getElementById('applyFilters').onclick=function(){if(table)table.ajax.reload(null,true);};document.getElementById('resetFilters').onclick=function(){document.getElementById('ledgerSearch').value='';document.getElementById('accountFilter').value='';document.getElementById('ledgerDate').value=today;if(document.getElementById('accountFilter')._globalSelect)document.getElementById('accountFilter')._globalSelect.refresh();if(table){table.search('');table.ajax.reload(null,true);}};boot();
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
