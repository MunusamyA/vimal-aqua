<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Customer List';
$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
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
    <?php foreach ($headStyles as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ($headScripts as $scriptUrl): ?>
    <script src="<?php echo web_h($scriptUrl); ?>"></script>
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

<div class="page-head">
    <div>
        <h1>Customer List</h1>
        <p>Manage customers for the current branch.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="customer-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Customer
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="users"></i></div><div><div class="kpi-label">Total Customers</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="user-check"></i></div><div><div class="kpi-label">Active Customers</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="user-x"></i></div><div><div class="kpi-label">Inactive Customers</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="indian-rupee"></i></div><div><div class="kpi-label">Opening Balance</div><div class="kpi-value" id="kpiOpening">₹0.00</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="masterSearch">Search</label>
                <input id="masterSearch" type="text" autocomplete="off" placeholder="Code, customer, mobile...">
            </div>
            <div class="field col-3">
                <label for="lineFilter">Line</label>
                <select id="lineFilter">
                    <option value="">All Lines</option>
                </select>
            </div>
            <div class="field col-3">
                <label for="priceLevelFilter">Price Level</label>
                <select id="priceLevelFilter">
                    <option value="">All Price Levels</option>
                </select>
            </div>
            <div class="field col-3">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <table id="masterTable" class="display data-table" style="width:100%">
        <thead>
        <tr>
            <th>Code</th>
            <th>Customer</th>
            <th>Mobile</th>
            <th>Line</th>
            <th>Sequence</th>
            <th>Price Level</th>
            <th>Credit Limit</th>
            <th>Opening Balance</th>
            <th>Can Balance</th>
            <th>Status</th>
            <th>Manage</th>
        </tr>
        </thead>
    </table>
</div>

<script>
(function ($, window, document) {
    "use strict";
    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var allowedActions = [];
    var searchTimer = null;
    var masterSearch = document.getElementById("masterSearch");
    var lineFilter = document.getElementById("lineFilter");
    var priceLevelFilter = document.getElementById("priceLevelFilter");
    var statusFilter = document.getElementById("statusFilter");
    var addButton = document.getElementById("addButton");
    var has = AppDataTable.has;

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_ACTIVATE = 27;
    var ACTION_DEACTIVATE = 28;


    function setStats(rows){
        rows=rows||[];
        var active=0,inactive=0,opening=0;
        rows.forEach(function(row){
            if(Number(row.status)===1) active++; else inactive++;
            opening+=Number(row.opening_balance||0);
        });
        document.getElementById("kpiTotal").textContent=rows.length.toLocaleString("en-IN");
        document.getElementById("kpiActive").textContent=active.toLocaleString("en-IN");
        document.getElementById("kpiInactive").textContent=inactive.toLocaleString("en-IN");
        document.getElementById("kpiOpening").textContent=money(opening);
    }

    async function refreshStats(){
        try{
            var result=await App.api("api/customers.php");
            setStats(result.data.customers||[]);
        }catch(error){
            setStats([]);
        }
    }

    async function loadFilterOptions(){
        try{
            var result=await App.api("api/customers.php?options=1");
            var data=result.data||{};

            (data.lines||[]).forEach(function(row){
                var option=document.createElement("option");
                option.value=String(row.id);
                option.textContent=(row.line_code?row.line_code+" - ":"")+row.line_name;
                lineFilter.appendChild(option);
            });

            (data.price_levels||[]).forEach(function(row){
                var option=document.createElement("option");
                option.value=String(row.id);
                option.textContent=row.price_level_name;
                priceLevelFilter.appendChild(option);
            });
        }catch(error){
            App.showError(error,"Unable to load Customer filters.");
        }
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
    }

    function money(value) {
        var number=Number(value||0);
        if(!Number.isFinite(number)) number=0;
        return "₹"+number.toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});
    }

    function qty(value) {
        var number=Number(value||0);
        if(!Number.isFinite(number)) number=0;
        return number.toLocaleString("en-IN",{minimumFractionDigits:0,maximumFractionDigits:3});
    }

    function statusHtml(value,type) {
        var active=Number(value)===1;
        if(type!=="display") return Number(value);
        return active
            ? '<span class="dt-status active">Active</span>'
            : '<span class="dt-status inactive">Inactive</span>';
    }

    var table=AppDataTable.init("#masterTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading customers...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[0,"asc"]],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"Customer List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"csvHtml5",text:"CSV",title:"Customer List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"excelHtml5",text:"Excel",title:"Customer List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"pdfHtml5",text:"PDF",title:"Customer List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"print",text:"Print",title:"Customer List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}
        ],
        ajax:function(data,callback){
            var params=new URLSearchParams();
            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value||"");
            if(lineFilter.value!=="") params.set("line_id",lineFilter.value);
            if(priceLevelFilter.value!=="") params.set("price_level_id",priceLevelFilter.value);
            if(statusFilter.value!=="") params.set("status",statusFilter.value);

            if(data.order&&data.order[0]){
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/customers.php?"+params.toString()).then(function(result){
                allowedActions=(result.data.allowed_actions||[]).map(Number);
                addButton.style.display=has(allowedActions,ACTION_CREATE)?"inline-flex":"none";
                AppDataTable.applyExportPermissions(table,allowedActions);
                callback(result.data.datatable);
            }).catch(function(error){
                addButton.style.display="none";
                App.showError(error,"Unable to load customers.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:"customer_code",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"customer_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"mobile",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"line_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"line_sequence",className:"dt-body-right",render:function(v,t){var x=v==null?"-":String(v);return t==="display"?escapeHtml(x):Number(v||0);}},
            {data:"price_level_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"credit_limit",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
            {data:"opening_balance",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
            {data:"opening_can_balance",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(qty(v)):Number(v||0);}},
            {data:"status",render:function(v,t){return statusHtml(v,t);}},
            {
                data:null,orderable:false,searchable:false,className:"table-action-icons",
                render:function(data,type,row){
                    if(type!=="display") return "";
                    var html="";
                    if(has(allowedActions,ACTION_UPDATE)){
                        html+=App.iconActionHtml({href:row.edit_url,icon:"pencil",label:"Edit Customer"});
                    }
                    if(Number(row.status)===1 && has(allowedActions,ACTION_DEACTIVATE)){
                        html+='<button type="button" class="table-icon-action danger js-status" data-ref="'+escapeHtml(row.ref)+'" data-status="0" title="Deactivate Customer" aria-label="Deactivate Customer"><i data-lucide="circle-off"></i></button>';
                    }else if(Number(row.status)!==1 && has(allowedActions,ACTION_ACTIVATE)){
                        html+='<button type="button" class="table-icon-action js-status" data-ref="'+escapeHtml(row.ref)+'" data-status="1" title="Activate Customer" aria-label="Activate Customer"><i data-lucide="circle-check"></i></button>';
                    }
                    return html || '<span class="muted">View only</span>';
                }
            }
        ],
        language:{emptyTable:"No customers found.",zeroRecords:"No matching customers found."},
        drawCallback:function(){if(window.lucide)window.lucide.createIcons();}
    });

    (function removeDefaultSearchRow(){
        var tableElement=document.getElementById("masterTable");
        var card=tableElement?tableElement.closest(".table-card"):null;
        var searchRow=card?card.querySelector(".app-table-search-row"):null;
        if(searchRow)searchRow.remove();
    })();

    masterSearch.addEventListener("input",function(){
        window.clearTimeout(searchTimer);
        searchTimer=window.setTimeout(function(){
            table.search(masterSearch.value.trim()).draw();
        },350);
    });

    [lineFilter,priceLevelFilter,statusFilter].forEach(function(filter){
        filter.addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    document.addEventListener("click",function(event){
        var button=event.target.closest(".js-status");
        if(!button) return;

        var status=Number(button.getAttribute("data-status"));
        var requiredAction=status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE;
        if(!has(allowedActions,requiredAction)) return;

        if(!window.confirm(status===1?"Activate this Customer?":"Deactivate this Customer?")) return;

        button.disabled=true;
        App.api("api/customers.php",{
            method:"PATCH",
            body:{ref:button.getAttribute("data-ref"),status:status}
        }).then(function(result){
            if(window.showToast)showToast(result.message||"Customer status updated.",{type:"success",duration:2});
            table.ajax.reload(null,false);
            refreshStats();
        }).catch(function(error){
            App.showError(error,"Unable to change Customer status.");
        }).finally(function(){
            button.disabled=false;
        });
    });
    loadFilterOptions();
    refreshStats();
})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
