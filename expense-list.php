<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Expense List';

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
        <h1>Expenses</h1>
        <p>Expense history with Cash, UPI, Bank and Cheque payment details.</p>
    </div>
    <a class="btn btn-primary" id="addExpenseButton" href="expense-form.php" hidden>
        <i data-lucide="plus"></i>Add Expense
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="receipt-text"></i></div>
        <div>
            <div class="kpi-label">Expenses</div>
            <div class="kpi-value" id="kpiExpenses">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div>
        <div>
            <div class="kpi-label">Active</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="indian-rupee"></i></div>
        <div>
            <div class="kpi-label">Expense Amount</div>
            <div class="kpi-value" id="kpiAmount">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="ban"></i></div>
        <div>
            <div class="kpi-label">Cancelled</div>
            <div class="kpi-value" id="kpiCancelled">0</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="expenseSearch">Search</label>
                <input class="input" id="expenseSearch" type="text" autocomplete="off"
                       placeholder="Expense no, name, account...">
            </div>

            <div class="field col-3">
                <label for="expenseNameFilter">Expense Name</label>
                <select class="select" id="expenseNameFilter" data-placeholder="All Expense Names">
                    <option value="">All Expense Names</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="paymentModeFilter">Payment Mode</label>
                <select class="select" id="paymentModeFilter">
                    <option value="">All Modes</option>
                    <option value="1">Cash</option>
                    <option value="2">UPI</option>
                    <option value="3">Bank</option>
                    <option value="4">Cheque</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Cancelled</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="accountFilter">Account</label>
                <select class="select" id="accountFilter" data-placeholder="All Accounts">
                    <option value="">All Accounts</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="dateFrom">From Date</label>
                <input class="input" id="dateFrom" type="date">
            </div>

            <div class="field col-2">
                <label for="dateTo">To Date</label>
                <input class="input" id="dateTo" type="date">
            </div>
        </div>
    </div>

    <div class="app-table-wrap">
        <table id="expenseTable" class="display data-table">
            <thead>
            <tr>
                <th>Expense No</th>
                <th>Date</th>
                <th>Expense Name</th>
                <th>Payment Mode</th>
                <th>Account</th>
                <th>Amount</th>
                <th>Remarks</th>
                <th>Status</th>
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
    var expenseNameSelect=null;
    var accountSelect=null;
    var table=null;
    var has=AppDataTable.has;

    var ACTION_CREATE=2;
    var ACTION_UPDATE=3;
    var ACTION_CANCEL=14;

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

    function setStats(summary){
        summary=summary||{};
        document.getElementById('kpiExpenses').textContent=
            Number(summary.expense_count||0).toLocaleString('en-IN');
        document.getElementById('kpiActive').textContent=
            Number(summary.active_count||0).toLocaleString('en-IN');
        document.getElementById('kpiAmount').textContent=
            '₹'+money(summary.active_amount);
        document.getElementById('kpiCancelled').textContent=
            Number(summary.cancelled_count||0).toLocaleString('en-IN');
    }

    function formatDate(value){
        if(!value)return '-';
        var parts=String(value).split('-');
        return parts.length===3?parts[2]+'/'+parts[1]+'/'+parts[0]:String(value);
    }

    function statusBadge(value,type){
        var number=Number(value||0);
        if(type!=='display')return number;
        return number===1
            ? '<span class="pill active">Active</span>'
            : '<span class="pill inactive">Cancelled</span>';
    }

    function fillExpenseNameFilter(rows){
        var select=document.getElementById('expenseNameFilter');
        select.innerHTML='<option value="">All Expense Names</option>';

        (rows||[]).forEach(function(row){
            var option=document.createElement('option');
            option.value=String(row.value||row.label||'');
            option.textContent=String(row.label||row.value||'');
            select.appendChild(option);
        });

        if(window.GlobalSelect){
            expenseNameSelect=GlobalSelect.init(select,{placeholder:'All Expense Names'});
        }
    }

    function fillAccountFilter(rows){
        var select=document.getElementById('accountFilter');
        select.innerHTML='<option value="">All Accounts</option>';

        (rows||[]).forEach(function(row){
            var option=document.createElement('option');
            option.value=String(row.id||'');
            option.textContent=row.label || ((row.account_code?row.account_code+' - ':'')+(row.account_name||''));
            select.appendChild(option);
        });

        if(window.GlobalSelect){
            accountSelect=GlobalSelect.init(select,{placeholder:'All Accounts'});
        }
    }

    function startTable(){
        table=AppDataTable.init('#expenseTable',{
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
                {extend:'copyHtml5',text:'Copy',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
                {extend:'csvHtml5',text:'CSV',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
                {extend:'excelHtml5',text:'Excel',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
                {extend:'pdfHtml5',text:'PDF',title:'Expense List',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
                {extend:'print',text:'Print',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}}
            ],
            ajax:function(data,callback){
                var params=new URLSearchParams();
                params.set('datatable','1');
                params.set('draw',data.draw);
                params.set('start',data.start);
                params.set('length',data.length);
                params.set('search[value]',data.search.value||'');

                var expenseName=document.getElementById('expenseNameFilter').value;
                var paymentMode=document.getElementById('paymentModeFilter').value;
                var status=document.getElementById('statusFilter').value;
                var account=document.getElementById('accountFilter').value;
                var from=document.getElementById('dateFrom').value;
                var to=document.getElementById('dateTo').value;

                if(expenseName)params.set('expense_name',expenseName);
                if(paymentMode)params.set('payment_mode',paymentMode);
                if(status!=='')params.set('status',status);
                if(account)params.set('account_id',account);
                if(from)params.set('date_from',from);
                if(to)params.set('date_to',to);

                if(data.order&&data.order[0]){
                    params.set('order[0][column]',data.order[0].column);
                    params.set('order[0][dir]',data.order[0].dir);
                }

                App.api('api/expenses.php?'+params.toString())
                    .then(function(result){
                        actions=(result.data.allowed_actions||actions).map(Number);
                        document.getElementById('addExpenseButton').hidden=!has(actions,ACTION_CREATE);
                        setStats(result.data.summary);
                        AppDataTable.applyExportPermissions(table,actions);
                        callback(result.data.datatable);
                    })
                    .catch(function(error){
                        document.getElementById('addExpenseButton').hidden=true;
                        App.showError(error,'Unable to load Expenses.');
                        callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                    });
            },
            columns:[
                {data:'expense_no',defaultContent:'-'},
                {data:'expense_date',defaultContent:'-',render:function(v,t){return t==='display'?formatDate(v):v;}},
                {data:'expense_name',defaultContent:'-'},
                {data:'payment_modes',defaultContent:'-',orderable:false},
                {data:'payment_accounts',defaultContent:'-',orderable:false},
                {data:'amount',className:'dt-body-right',render:function(v,t){return t==='display'?'₹'+money(v):Number(v||0);}},
                {data:'remarks',defaultContent:'-',render:function(v,t){return t==='display'?(v?esc(v):'<span class="muted">-</span>'):(v||'');}},
                {data:'status',render:statusBadge},
                {
                    data:null,
                    orderable:false,
                    searchable:false,
                    className:'table-action-icons',
                    render:function(data,type,row){
                        if(type!=='display')return '';
                        var html=[];

                        if(Number(row.status)===1&&has(actions,ACTION_UPDATE)){
                            html.push(App.iconActionHtml({href:row.edit_url,icon:'pencil',label:'Edit Expense'}));
                        }

                        if(Number(row.status)===1&&has(actions,ACTION_CANCEL)){
                            html.push(
                                '<button type="button" class="table-icon-action danger cancel-expense" data-ref="'+esc(row.ref)+'" title="Cancel Expense" aria-label="Cancel Expense">'+
                                '<i data-lucide="x-circle"></i></button>'
                            );
                        }

                        return html.join(' ')||'<span class="muted">View only</span>';
                    }
                }
            ],
            language:{
                emptyTable:'No Expenses found.',
                zeroRecords:'No matching Expenses found.'
            },
            drawCallback:function(){if(window.lucide)window.lucide.createIcons();}
        });

        (function removeDefaultSearchRow(){
            var tableElement=document.getElementById('expenseTable');
            var card=tableElement?tableElement.closest('.table-card'):null;
            var row=card?card.querySelector('.app-table-search-row'):null;
            if(row)row.remove();
        })();

        document.getElementById('expenseSearch').addEventListener('input',function(){
            var input=this;
            clearTimeout(searchTimer);
            searchTimer=setTimeout(function(){table.search(input.value.trim()).draw();},350);
        });

        ['expenseNameFilter','paymentModeFilter','statusFilter','accountFilter','dateFrom','dateTo'].forEach(function(id){
            document.getElementById(id).addEventListener('change',function(){
                table.ajax.reload(null,true);
            });
        });

        document.getElementById('expenseTable').addEventListener('click',async function(event){
            var button=event.target.closest('.cancel-expense');
            if(!button)return;

            if(!window.confirm('Cancel this Expense? All linked Money Out transactions will be reversed.'))return;

            button.disabled=true;
            try{
                var result=await App.api('api/expenses.php',{
                    method:'POST',
                    body:{action:'cancel',ref:button.dataset.ref}
                });

                if(window.showToast){
                    showToast(result.message||'Expense cancelled successfully.',{type:'success',duration:2});
                }

                table.ajax.reload(null,false);
            }catch(error){
                button.disabled=false;
                App.showError(error,'Unable to cancel Expense.');
            }
        });
    }

    async function load(){
        try{
            var options=await App.api('api/expenses.php?options=1');
            actions=(options.data.allowed_actions||[]).map(Number);
            document.getElementById('addExpenseButton').hidden=!has(actions,ACTION_CREATE);
            fillExpenseNameFilter(options.data.expense_names||[]);
            fillAccountFilter(options.data.accounts||[]);
            startTable();
            if(window.lucide)window.lucide.createIcons();
        }catch(error){
            document.getElementById('addExpenseButton').hidden=true;
            App.showError(error,'Unable to prepare Expense List.');
        }
    }

    load();
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
</body>
</html>
