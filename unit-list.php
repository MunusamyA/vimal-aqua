<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Unit List';
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
<script src="assets/js/validation.js"></script>

<div class="page-head">
    <div>
        <h1>Unit List</h1>
        <p>Manage units for the current branch.</p>
    </div>
    <button class="btn btn-primary" id="addButton" type="button" style="display:none">
        <i data-lucide="plus"></i>Add Unit
    </button>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="ruler"></i></div><div><div class="kpi-label">Total Units</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">Active Units</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-off"></i></div><div><div class="kpi-label">Inactive Units</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="list-filter"></i></div><div><div class="kpi-label">Matching Results</div><div class="kpi-value" id="kpiMatching">0</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">
            <div class="field col-8">
                <label for="masterSearch">Search</label>
                <input class="input" id="masterSearch" type="text" autocomplete="off" placeholder="Unit name or short name...">
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
            <thead><tr><th>Unit Name</th><th>Short Name</th><th>Status</th><th>Manage</th></tr></thead>
        </table>
    </div>
</div>

<?php require __DIR__ . '/model/unit-form.php'; ?>

<script>
(function($,window,document){
'use strict';
if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;

var allowedActions=[];
var searchTimer=null;
var masterSearch=document.getElementById("masterSearch");
var statusFilter=document.getElementById("statusFilter");
var addButton=document.getElementById("addButton");
var has=AppDataTable.has;
var ACTION_CREATE=2,ACTION_UPDATE=3,ACTION_ACTIVATE=27,ACTION_DEACTIVATE=28;

function escapeHtml(value){
    return String(value===null||value===undefined?"":value)
        .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}
function statusHtml(value,type){
    if(type!=="display")return Number(value);
    return Number(value)===1
        ?'<span class="dt-status active">Active</span>'
        :'<span class="dt-status inactive">Inactive</span>';
}
function setSummary(summary){
    summary=summary||{};
    document.getElementById("kpiTotal").textContent=Number(summary.total_units||0).toLocaleString("en-IN");
    document.getElementById("kpiActive").textContent=Number(summary.active_units||0).toLocaleString("en-IN");
    document.getElementById("kpiInactive").textContent=Number(summary.inactive_units||0).toLocaleString("en-IN");
    document.getElementById("kpiMatching").textContent=Number(summary.matching_units||0).toLocaleString("en-IN");
}

var table=AppDataTable.init("#masterTable",{
    serverSide:true,
    searching:true,
    searchDelay:350,
    appSearch:false,
    appLoaderText:"Loading units...",
    pageLength:10,
    lengthMenu:[[10,25,50,100],[10,25,50,100]],
    order:[[0,"asc"]],
    scrollX:true,
    autoWidth:false,
    buttons:[
        {extend:"copyHtml5",text:"Copy",title:"Unit List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
        {extend:"csvHtml5",text:"CSV",title:"Unit List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
        {extend:"excelHtml5",text:"Excel",title:"Unit List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
        {extend:"pdfHtml5",text:"PDF",title:"Unit List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
        {extend:"print",text:"Print",title:"Unit List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}}
    ],
    ajax:function(data,callback){
        var params=new URLSearchParams();
        params.set("datatable","1");
        params.set("draw",data.draw);
        params.set("start",data.start);
        params.set("length",data.length);
        params.set("search[value]",data.search.value||"");
        if(statusFilter.value!=="")params.set("status",statusFilter.value);

        if(data.order&&data.order[0]){
            params.set("order[0][column]",data.order[0].column);
            params.set("order[0][dir]",data.order[0].dir);
        }

        App.api("api/unit.php?"+params.toString()).then(function(result){
            allowedActions=(result.data.allowed_actions||[]).map(Number);
            addButton.style.display=has(allowedActions,ACTION_CREATE)?"inline-flex":"none";
            setSummary(result.data.summary);
            AppDataTable.applyExportPermissions(table,allowedActions);
            callback(result.data.datatable);
        }).catch(function(error){
            addButton.style.display="none";
            setSummary({});
            App.showError(error,"Unable to load units.");
            callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
        });
    },
    columns:[
        {data:"unit_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
        {data:"short_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
        {data:"status",render:function(v,t){return statusHtml(v,t);}},
        {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(data,type,row){
            if(type!=="display")return "";
            var html="";
            if(has(allowedActions,ACTION_UPDATE)){
                html+='<button type="button" class="table-icon-action js-edit" data-id="'+Number(row.id)+'" title="Edit Unit" aria-label="Edit Unit"><i data-lucide="pencil"></i></button>';
            }
            if(Number(row.status)===1&&has(allowedActions,ACTION_DEACTIVATE)){
                html+='<button type="button" class="table-icon-action danger js-status" data-id="'+Number(row.id)+'" data-status="2" title="Deactivate Unit" aria-label="Deactivate Unit"><i data-lucide="circle-off"></i></button>';
            }else if(Number(row.status)!==1&&has(allowedActions,ACTION_ACTIVATE)){
                html+='<button type="button" class="table-icon-action js-status" data-id="'+Number(row.id)+'" data-status="1" title="Activate Unit" aria-label="Activate Unit"><i data-lucide="circle-check"></i></button>';
            }
            return html||'<span class="muted">View only</span>';
        }}
    ],
    language:{emptyTable:"No units found.",zeroRecords:"No matching units found.",processing:"Loading units..."},
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
statusFilter.addEventListener("change",function(){if(table)table.ajax.reload(null,true);});

addButton.addEventListener("click",function(){
    if(!has(allowedActions,ACTION_CREATE)||!window.AppUnitForm)return;
    AppUnitForm.openCreate({apiUrl:"api/unit.php",onSaved:function(){table.ajax.reload(null,false);}});
});

document.addEventListener("click",function(event){
    var editButton=event.target.closest(".js-edit");
    if(editButton&&has(allowedActions,ACTION_UPDATE)&&window.AppUnitForm){
        AppUnitForm.openEdit(Number(editButton.getAttribute("data-id")||0),{
            apiUrl:"api/unit.php",
            onSaved:function(){table.ajax.reload(null,false);}
        });
        return;
    }

    var statusButton=event.target.closest(".js-status");
    if(!statusButton)return;

    var nextStatus=Number(statusButton.getAttribute("data-status"));
    var needed=nextStatus===1?ACTION_ACTIVATE:ACTION_DEACTIVATE;
    if(!has(allowedActions,needed))return;

    statusButton.disabled=true;
    App.api("api/unit.php",{
        method:"PATCH",
        body:{id:Number(statusButton.getAttribute("data-id")||0),status:nextStatus}
    }).then(function(result){
        if(window.showToast)showToast(result.message||"Status updated successfully.",{type:"success",duration:2});
        table.ajax.reload(null,false);
    }).catch(function(error){
        App.showError(error,"Unable to update Unit status.");
    }).finally(function(){
        statusButton.disabled=false;
    });
});
})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
<script src="assets/js/appearance.js"></script>
</body>
</html>
