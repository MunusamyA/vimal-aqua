<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Supplier List';
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
<?php foreach ($headStyles as $styleUrl): ?><link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>"><?php endforeach; ?>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<?php foreach ($headScripts as $scriptUrl): ?><script src="<?php echo web_h($scriptUrl); ?>"></script><?php endforeach; ?>
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
        <h1>Supplier List</h1>
        <p>Manage suppliers for the current branch.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="supplier-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Supplier
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="building-2"></i></div><div><div class="kpi-label">Total Suppliers</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">Active Suppliers</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-off"></i></div><div><div class="kpi-label">Inactive Suppliers</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="indian-rupee"></i></div><div><div class="kpi-label">Opening Balance</div><div class="kpi-value" id="kpiOpening">₹0.00</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">
            <div class="field col-4">
                <label for="masterSearch">Search</label>
                <input class="input" id="masterSearch" type="text" autocomplete="off" placeholder="Supplier, code, contact, GSTIN...">
            </div>
            <div class="field col-4">
                <label for="gstFilter">GSTIN</label>
                <select class="select" id="gstFilter">
                    <option value="">All Suppliers</option>
                    <option value="with">With GSTIN</option>
                    <option value="without">Without GSTIN</option>
                </select>
            </div>
            <div class="field col-4">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="2">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="app-table-wrap">
        <table id="masterTable" class="display data-table" style="width:100%">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Supplier</th>
                    <th>Contact Person</th>
                    <th>Contact</th>
                    <th>GSTIN</th>
                    <th>Opening Balance</th>
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

var allowedActions=[];
var searchTimer=null;
var masterSearch=document.getElementById("masterSearch");
var gstFilter=document.getElementById("gstFilter");
var statusFilter=document.getElementById("statusFilter");
var addButton=document.getElementById("addButton");
var has=AppDataTable.has;
var ACTION_CREATE=2,ACTION_UPDATE=3,ACTION_ACTIVATE=27,ACTION_DEACTIVATE=28;

function escapeHtml(value){
    return String(value===null||value===undefined?"":value)
        .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}
function money(value){
    var number=Number(value||0);
    if(!Number.isFinite(number))number=0;
    return "₹"+number.toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});
}
function statusHtml(value,type){
    if(type!=="display")return Number(value);
    return Number(value)===1
        ?'<span class="dt-status active">Active</span>'
        :'<span class="dt-status inactive">Inactive</span>';
}
function setSummary(summary){
    summary=summary||{};
    document.getElementById("kpiTotal").textContent=Number(summary.total_suppliers||0).toLocaleString("en-IN");
    document.getElementById("kpiActive").textContent=Number(summary.active_suppliers||0).toLocaleString("en-IN");
    document.getElementById("kpiInactive").textContent=Number(summary.inactive_suppliers||0).toLocaleString("en-IN");
    document.getElementById("kpiOpening").textContent=money(summary.opening_balance);
}

var table=AppDataTable.init("#masterTable",{
    serverSide:true,
    searching:true,
    searchDelay:350,
    appSearch:false,
    appLoaderText:"Loading suppliers...",
    pageLength:10,
    lengthMenu:[[10,25,50,100],[10,25,50,100]],
    order:[[0,"asc"]],
    scrollX:true,
    autoWidth:false,
    buttons:[
        {extend:"copyHtml5",text:"Copy",title:"Supplier List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
        {extend:"csvHtml5",text:"CSV",title:"Supplier List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
        {extend:"excelHtml5",text:"Excel",title:"Supplier List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
        {extend:"pdfHtml5",text:"PDF",title:"Supplier List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
        {extend:"print",text:"Print",title:"Supplier List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}}
    ],
    ajax:function(data,callback){
        var params=new URLSearchParams();
        params.set("datatable","1");
        params.set("draw",data.draw);
        params.set("start",data.start);
        params.set("length",data.length);
        params.set("search[value]",data.search.value||"");
        if(gstFilter.value!=="")params.set("gst_filter",gstFilter.value);
        if(statusFilter.value!=="")params.set("status",statusFilter.value);

        if(data.order&&data.order[0]){
            params.set("order[0][column]",data.order[0].column);
            params.set("order[0][dir]",data.order[0].dir);
        }

        App.api("api/suppliers.php?"+params.toString()).then(function(result){
            allowedActions=(result.data.allowed_actions||[]).map(Number);
            addButton.style.display=has(allowedActions,ACTION_CREATE)?"inline-flex":"none";
            setSummary(result.data.summary);
            AppDataTable.applyExportPermissions(table,allowedActions);
            callback(result.data.datatable);
        }).catch(function(error){
            addButton.style.display="none";
            setSummary({});
            App.showError(error,"Unable to load suppliers.");
            callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
        });
    },
    columns:[
        {data:"supplier_code",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
        {data:"supplier_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
        {data:"contact_person",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
        {data:null,orderable:false,render:function(data,type,row){
            var text=[row.mobile,row.email].filter(Boolean).join(" · ")||"-";
            return type==="display"?escapeHtml(text):text;
        }},
        {data:"gstin",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
        {data:"opening_balance",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
        {data:"status",render:function(v,t){return statusHtml(v,t);}},
        {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(data,type,row){
            if(type!=="display")return "";
            var html="";
            if(has(allowedActions,ACTION_UPDATE)){
                html+=App.iconActionHtml({href:row.edit_url,icon:"pencil",label:"Edit Supplier"});
            }
            if(Number(row.status)===1&&has(allowedActions,ACTION_DEACTIVATE)){
                html+='<button type="button" class="table-icon-action danger js-status" data-ref="'+escapeHtml(row.ref)+'" data-status="2" title="Deactivate Supplier" aria-label="Deactivate Supplier"><i data-lucide="circle-off"></i></button>';
            }else if(Number(row.status)!==1&&has(allowedActions,ACTION_ACTIVATE)){
                html+='<button type="button" class="table-icon-action js-status" data-ref="'+escapeHtml(row.ref)+'" data-status="1" title="Activate Supplier" aria-label="Activate Supplier"><i data-lucide="circle-check"></i></button>';
            }
            return html||'<span class="muted">View only</span>';
        }}
    ],
    language:{emptyTable:"No suppliers found.",zeroRecords:"No matching suppliers found.",processing:"Loading suppliers..."},
    drawCallback:function(){if(window.lucide)window.lucide.createIcons();}
});

(function removeDefaultSearch(){
    var card=document.getElementById("masterTable").closest(".table-card");
    var row=card?card.querySelector(".app-table-search-row"):null;
    if(row)row.remove();
})();

masterSearch.addEventListener("input",function(){
    var field=this;
    clearTimeout(searchTimer);
    searchTimer=setTimeout(function(){if(table)table.search(field.value.trim()).draw();},350);
});
["gstFilter","statusFilter"].forEach(function(id){
    document.getElementById(id).addEventListener("change",function(){if(table)table.ajax.reload(null,true);});
});

document.addEventListener("click",function(event){
    var button=event.target.closest(".js-status");
    if(!button)return;

    var status=Number(button.getAttribute("data-status"));
    var requiredAction=status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE;
    if(!has(allowedActions,requiredAction))return;
    if(!window.confirm(status===1?"Activate this Supplier?":"Deactivate this Supplier?"))return;

    button.disabled=true;
    App.api("api/suppliers.php",{
        method:"PATCH",
        body:{ref:button.getAttribute("data-ref"),status:status}
    }).then(function(result){
        if(window.showToast)showToast(result.message||"Supplier status updated.",{type:"success",duration:2});
        table.ajax.reload(null,false);
    }).catch(function(error){
        App.showError(error,"Unable to change Supplier status.");
    }).finally(function(){
        button.disabled=false;
    });
});
})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
