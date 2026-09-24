<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Vehicle List';
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
        <h1>Vehicle List</h1>
        <p>Manage branch vehicles used for Aqua delivery and supply.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="vehicle-form.php" style="display:none">
        <i data-lucide="truck"></i>Add Vehicle
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="truck"></i></div><div><div class="kpi-label">Total Vehicles</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">Active Vehicles</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-off"></i></div><div><div class="kpi-label">Inactive Vehicles</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="list-filter"></i></div><div><div class="kpi-label">Matching Results</div><div class="kpi-value" id="kpiMatching">0</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">
            <div class="field col-8">
                <label for="vehicleSearch">Search</label>
                <input class="input" id="vehicleSearch" type="text" autocomplete="off" placeholder="Vehicle no. or vehicle name...">
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
        <table id="vehicleTable" class="display data-table" style="width:100%">
            <thead><tr><th>Vehicle No.</th><th>Vehicle Name</th><th>Status</th><th>Actions</th></tr></thead>
        </table>
    </div>
</div>

<script>
(function($,window,document){
'use strict';
if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;

var listActions=[];
var has=AppDataTable.has;
var table=null;
var searchTimer=null;

function escapeHtml(value){return $("<div>").text(value==null?"":String(value)).html();}
function setSummary(summary){
    summary=summary||{};
    document.getElementById("kpiTotal").textContent=Number(summary.total_vehicles||0).toLocaleString("en-IN");
    document.getElementById("kpiActive").textContent=Number(summary.active_vehicles||0).toLocaleString("en-IN");
    document.getElementById("kpiInactive").textContent=Number(summary.inactive_vehicles||0).toLocaleString("en-IN");
    document.getElementById("kpiMatching").textContent=Number(summary.matching_vehicles||0).toLocaleString("en-IN");
}
function renderActions(row){
    var actions=[];
    if(has(listActions,3)){
        actions.push(App.iconActionHtml({href:row.edit_url,icon:"pencil",label:"Edit vehicle"}));
    }
    if(Number(row.status)===1&&has(listActions,28)){
        actions.push('<button type="button" class="table-icon-action js-vehicle-status" data-ref="'+escapeHtml(row.ref)+'" data-status="2" title="Deactivate vehicle" aria-label="Deactivate vehicle"><i data-lucide="circle-pause"></i></button>');
    }else if(Number(row.status)===2&&has(listActions,27)){
        actions.push('<button type="button" class="table-icon-action js-vehicle-status" data-ref="'+escapeHtml(row.ref)+'" data-status="1" title="Activate vehicle" aria-label="Activate vehicle"><i data-lucide="circle-play"></i></button>');
    }
    return actions.length?actions.join(""):'<span class="muted">View only</span>';
}

table=AppDataTable.init("#vehicleTable",{
    serverSide:true,
    searching:true,
    searchDelay:350,
    appSearch:false,
    appLoaderText:"Loading vehicles...",
    pageLength:10,
    lengthMenu:[[10,25,50,100],[10,25,50,100]],
    order:[[0,"asc"]],
    scrollX:true,
    autoWidth:false,
    buttons:[
        {extend:"copyHtml5",text:"Copy",title:"Vehicle List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
        {extend:"csvHtml5",text:"CSV",title:"Vehicle List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}}
    ],
    ajax:function(data,callback){
        var params=new URLSearchParams();
        params.set("datatable","1");
        params.set("draw",data.draw);
        params.set("start",data.start);
        params.set("length",data.length);
        params.set("search[value]",data.search.value||"");

        var status=document.getElementById("statusFilter").value;
        if(status!=="")params.set("status",status);

        if(data.order&&data.order[0]){
            params.set("order[0][column]",data.order[0].column);
            params.set("order[0][dir]",data.order[0].dir);
        }

        App.api("api/vehicles.php?"+params.toString()).then(function(result){
            listActions=(result.data.allowed_actions||[]).map(Number);
            document.getElementById("addButton").style.display=has(listActions,2)?"inline-flex":"none";
            setSummary(result.data.summary);
            AppDataTable.applyExportPermissions(table,listActions);
            callback(result.data.datatable);
            if(window.lucide)window.lucide.createIcons();
        }).catch(function(error){
            setSummary({});
            App.showError(error,"Unable to load vehicles.");
            callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
        });
    },
    columns:[
        {data:"vehicle_no",defaultContent:"-"},
        {data:"vehicle_name",defaultContent:"-",render:function(value,type){return value||"-";}},
        {data:"status",render:function(value,type){
            if(type!=="display")return Number(value);
            return Number(value)===1
                ?'<span class="dt-status active">Active</span>'
                :'<span class="dt-status inactive">Inactive</span>';
        }},
        {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(data,type,row){
            return type==="display"?renderActions(row):"";
        }}
    ],
    drawCallback:function(){if(window.lucide)window.lucide.createIcons();},
    language:{emptyTable:"No vehicles found.",zeroRecords:"No matching vehicles found.",processing:"Loading vehicles..."}
});

(function removeDefaultSearch(){
    var card=document.getElementById("vehicleTable").closest(".table-card");
    var row=card?card.querySelector(".app-table-search-row"):null;
    if(row)row.remove();
})();

document.getElementById("vehicleSearch").addEventListener("input",function(){
    var field=this;
    clearTimeout(searchTimer);
    searchTimer=setTimeout(function(){if(table)table.search(field.value.trim()).draw();},350);
});
document.getElementById("statusFilter").addEventListener("change",function(){
    if(table)table.ajax.reload(null,true);
});

$(document).on("click",".js-vehicle-status",async function(){
    var button=this;
    var ref=button.getAttribute("data-ref")||"";
    var targetStatus=Number(button.getAttribute("data-status")||0);
    if(!ref||(targetStatus!==1&&targetStatus!==2))return;
    if(!window.confirm(targetStatus===1?"Activate this vehicle?":"Deactivate this vehicle?"))return;

    button.disabled=true;
    try{
        var result=await App.api("api/vehicles.php",{method:"PATCH",body:{ref:ref,status:targetStatus}});
        if(typeof window.showToast==="function"){
            showToast(result.message||"Vehicle status updated.",{type:"success",duration:2});
        }
        table.ajax.reload(null,false);
    }catch(error){
        App.showError(error,"Unable to update vehicle status.");
        button.disabled=false;
    }
});

if(window.lucide)window.lucide.createIcons();
})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
