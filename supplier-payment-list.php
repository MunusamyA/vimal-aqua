<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Supplier Payment List';

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

    <?php foreach ($headStyles as $url): ?>
        <link rel="stylesheet" href="<?php echo web_h($url); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach ($headScripts as $url): ?>
        <script src="<?php echo web_h($url); ?>"></script>
    <?php endforeach; ?>
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
    <div>
        <h1>Supplier Payments</h1>
        <p>Opening Balance, FIFO Overall and Invoice-wise supplier payment history.</p>
    </div>
    <a class="btn btn-primary" id="addPaymentButton" href="supplier-payment.php" hidden>
        <i data-lucide="plus"></i>Add Payment
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="receipt-indian-rupee"></i></div>
        <div><div class="kpi-label">Payments</div><div class="kpi-value" id="kpiPayments">0</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="wallet-cards"></i></div>
        <div><div class="kpi-label">Payment Amount</div><div class="kpi-value" id="kpiAmount">₹0.00</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="badge-percent"></i></div>
        <div><div class="kpi-label">Discount</div><div class="kpi-value" id="kpiDiscount">₹0.00</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="circle-check-big"></i></div>
        <div><div class="kpi-label">Settled</div><div class="kpi-value" id="kpiSettled">₹0.00</div></div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="paymentSearch">Search</label>
                <input class="input" id="paymentSearch" type="text" autocomplete="off"
                       placeholder="Payment no, supplier, invoice...">
            </div>

            <div class="field col-3">
                <label for="supplierFilter">Supplier</label>
                <select class="select" id="supplierFilter" data-placeholder="All Suppliers">
                    <option value="">All Suppliers</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="typeFilter">Payment For</label>
                <select class="select" id="typeFilter">
                    <option value="">All</option>
                    <option value="1">Opening Balance</option>
                    <option value="2">Overall</option>
                    <option value="3">Invoice</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="modeFilter">Payment Mode</label>
                <select class="select" id="modeFilter">
                    <option value="">All Modes</option>
                    <option value="1">Cash</option>
                    <option value="2">UPI</option>
                    <option value="3">Bank</option>
                    <option value="4">Cheque</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field col-6">
                <label for="dateFrom">From Date</label>
                <input class="input" id="dateFrom" type="date">
            </div>

            <div class="field col-6">
                <label for="dateTo">To Date</label>
                <input class="input" id="dateTo" type="date">
            </div>
        </div>
    </div>

    <div class="app-table-wrap">
        <table id="supplierPaymentTable" class="display data-table">
            <thead>
            <tr>
                <th>Payment No</th>
                <th>Date</th>
                <th>Supplier</th>
                <th>Payment For</th>
                <th>Against</th>
                <th>Mode</th>
                <th>Payment</th>
                <th>Discount</th>
                <th>Settled</th>
                <th>Manage</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($,window,document){
    'use strict';

    if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;

    var actions=[];
    var searchTimer=null;
    var suppliersLoaded=false;
    var supplierSelect=null;
    var has=AppDataTable.has;

    var ACTION_VIEW=1;
    var ACTION_CREATE=2;
    var ACTION_UPDATE=3;
    var ACTION_CANCEL=14;

    function setSummary(summary){
        summary=summary||{};
        document.getElementById('kpiPayments').textContent=Number(summary.total_payments||0).toLocaleString('en-IN');
        document.getElementById('kpiAmount').textContent='₹'+money(summary.payment_amount||0);
        document.getElementById('kpiDiscount').textContent='₹'+money(summary.discount_amount||0);
        document.getElementById('kpiSettled').textContent='₹'+money(summary.settled_amount||0);
    }

    function esc(value){
        return String(value==null?'':value)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
    }

    function money(value){
        return Number(value||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
    }

    function formatDate(value){
        if(!value)return '-';
        var parts=String(value).split('-');
        return parts.length===3?parts[2]+'/'+parts[1]+'/'+parts[0]:String(value);
    }

    function paymentType(value,type){
        var number=Number(value||0);
        if(type!=='display')return number;
        if(number===1)return '<span class="pill pending">Opening Balance</span>';
        if(number===2)return '<span class="pill active">Overall FIFO</span>';
        return '<span class="pill">Invoice</span>';
    }

    function fillSupplierFilter(rows){
        if(suppliersLoaded)return;
        suppliersLoaded=true;
        var select=document.getElementById('supplierFilter');
        select.innerHTML='<option value="">All Suppliers</option>';
        (rows||[]).forEach(function(r){
            var option=document.createElement('option');
            option.value=r.ref;
            option.textContent=r.supplier_code+' - '+r.supplier_name;
            select.appendChild(option);
        });
        if(window.GlobalSelect)supplierSelect=GlobalSelect.init(select,{placeholder:'All Suppliers'});
    }

    var table=AppDataTable.init('#supplierPaymentTable',{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,'desc']],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:'copyHtml5',text:'Copy',title:'Supplier Payment List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:'csvHtml5',text:'CSV',title:'Supplier Payment List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:'excelHtml5',text:'Excel',title:'Supplier Payment List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:'pdfHtml5',text:'PDF',title:'Supplier Payment List',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:'print',text:'Print',title:'Supplier Payment List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}}
        ],
        ajax:function(data,callback){
            var params=new URLSearchParams();
            params.set('datatable','1');
            params.set('draw',data.draw);
            params.set('start',data.start);
            params.set('length',data.length);
            params.set('search[value]',data.search.value||'');

            var supplier=document.getElementById('supplierFilter').value;
            var type=document.getElementById('typeFilter').value;
            var mode=document.getElementById('modeFilter').value;
            var from=document.getElementById('dateFrom').value;
            var to=document.getElementById('dateTo').value;

            if(supplier)params.set('supplier_ref',supplier);
            if(type)params.set('payment_type',type);
            if(mode)params.set('payment_mode',mode);
            if(from)params.set('date_from',from);
            if(to)params.set('date_to',to);

            if(data.order&&data.order[0]){
                params.set('order[0][column]',data.order[0].column);
                params.set('order[0][dir]',data.order[0].dir);
            }

            App.api('api/supplier-payments.php?'+params.toString())
                .then(function(result){
                    actions=(result.data.form_actions||[]).map(Number);
                    document.getElementById('addPaymentButton').hidden=!has(actions,ACTION_CREATE);
                    fillSupplierFilter(result.data.suppliers||[]);
                    setSummary(result.data.summary);
                    AppDataTable.applyExportPermissions(table,result.data.list_actions||[]);
                    callback(result.data.datatable);
                })
                .catch(function(error){
                    document.getElementById('addPaymentButton').hidden=true;
                    setSummary({});
                    App.showError(error,'Unable to load Supplier Payments.');
                    callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                });
        },
        columns:[
            {data:'payment_no',defaultContent:'-'},
            {data:'payment_date',defaultContent:'-',render:function(v,t){return t==='display'?formatDate(v):v;}},
            {data:'supplier_label',defaultContent:'-'},
            {data:'payment_type',render:paymentType},
            {data:'against_label',defaultContent:'-'},
            {data:'mode_label',defaultContent:'-',orderable:false},
            {data:'amount',className:'dt-body-right',render:function(v,t){return t==='display'?'₹'+money(v):Number(v||0);}},
            {data:'discount_amount',className:'dt-body-right',render:function(v,t){return t==='display'?'₹'+money(v):Number(v||0);}},
            {data:'settlement_amount',className:'dt-body-right',render:function(v,t){return t==='display'?'₹'+money(v):Number(v||0);}},
            {
                data:null,
                orderable:false,
                searchable:false,
                className:'table-action-icons',
                render:function(data,type,row){
                    if(type!=='display')return '';
                    var html=[];

                    if(has(actions,ACTION_VIEW)){
                        html.push(App.iconActionHtml({href:row.view_url,icon:'eye',label:'View Supplier Payment'}));
                    }
                    if(has(actions,ACTION_UPDATE)){
                        html.push(App.iconActionHtml({href:row.edit_url,icon:'pencil',label:'Edit Supplier Payment'}));
                    }
                    if(has(actions,ACTION_CANCEL)){
                        html.push(
                            '<button type="button" class="table-icon-action delete-payment" data-ref="'+esc(row.ref)+'" title="Delete Payment" aria-label="Delete Payment">'+
                            '<i data-lucide="trash-2"></i></button>'
                        );
                    }

                    return html.join(' ')||'<span class="muted">View only</span>';
                }
            }
        ],
        language:{
            emptyTable:'No Supplier Payments found.',
            zeroRecords:'No matching Supplier Payments found.'
        },
        drawCallback:function(){if(window.lucide)window.lucide.createIcons();}
    });

    (function removeDefaultSearchRow(){
        var tableElement=document.getElementById('supplierPaymentTable');
        var card=tableElement?tableElement.closest('.table-card'):null;
        var row=card?card.querySelector('.app-table-search-row'):null;
        if(row)row.remove();
    })();

    document.getElementById('paymentSearch').addEventListener('input',function(){
        var input=this;
        clearTimeout(searchTimer);
        searchTimer=setTimeout(function(){table.search(input.value.trim()).draw();},350);
    });

    ['supplierFilter','typeFilter','modeFilter','dateFrom','dateTo'].forEach(function(id){
        document.getElementById(id).addEventListener('change',function(){table.ajax.reload(null,true);});
    });

    document.getElementById('supplierPaymentTable').addEventListener('click',async function(event){
        var button=event.target.closest('.delete-payment');
        if(!button)return;

        if(!window.confirm('Delete this Supplier Payment? All remaining payments for this supplier will be replayed in date order and FIFO / Invoice balances will be recalculated automatically.'))return;

        button.disabled=true;
        try{
            var result=await App.api('api/supplier-payments.php',{
                method:'POST',
                body:{action:'delete',ref:button.dataset.ref}
            });
            if(window.showToast)showToast(result.message||'Supplier Payment deleted successfully.',{type:'success',duration:2});
            table.ajax.reload(null,false);
        }catch(error){
            button.disabled=false;
            App.showError(error,'Unable to delete Supplier Payment.');
        }
    });

    if(window.lucide)window.lucide.createIcons();
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
</body>
</html>
