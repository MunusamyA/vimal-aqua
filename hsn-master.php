<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'HSN Master';
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
<script src="assets/js/validation.js"></script>

<div class="page-head">
    <div>
        <h1>HSN Master</h1>
        <p>Manage branch-wise HSN and GST rates.</p>
    </div>
    <button class="btn btn-primary" id="addButton" type="button" hidden>
        <i data-lucide="plus"></i>Add HSN
    </button>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="receipt-text"></i></div>
        <div><div class="kpi-label">Total HSN</div><div class="kpi-value" id="kpiTotal">0</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div>
        <div><div class="kpi-label">Active HSN</div><div class="kpi-value" id="kpiActive">0</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="circle-off"></i></div>
        <div><div class="kpi-label">Inactive HSN</div><div class="kpi-value" id="kpiInactive">0</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="list-filter"></i></div>
        <div><div class="kpi-label">Matching Results</div><div class="kpi-value" id="kpiMatching">0</div></div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-4">
                <label for="masterSearch">Search</label>
                <input id="masterSearch" type="text" autocomplete="off" placeholder="HSN code or description...">
            </div>
            <div class="field col-4">
                <label for="gstRateFilter">GST Rate</label>
                <select id="gstRateFilter">
                    <option value="">All GST Rates</option>
                </select>
            </div>
            <div class="field col-4">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="2">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <table id="masterTable" class="display data-table">
        <thead>
        <tr>
            <th>HSN Code</th>
            <th>Description</th>
            <th>GST %</th>
            <th>CGST %</th>
            <th>SGST %</th>
            <th>IGST %</th>
            <th>Cess %</th>
            <th>Status</th>
            <th>Manage</th>
        </tr>
        </thead>
    </table>
</div>

<?php require __DIR__ . '/model/hsn-form.php'; ?>

<script>
(function ($, window, document) {
    "use strict";
    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var allowedActions = [];
    var searchTimer = null;
    var masterSearch = document.getElementById("masterSearch");
    var gstRateFilter = document.getElementById("gstRateFilter");
    var statusFilter = document.getElementById("statusFilter");
    var addButton = document.getElementById("addButton");
    var has = AppDataTable.has;

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_ACTIVATE = 27;
    var ACTION_DEACTIVATE = 28;

    function setSummary(summary) {
        summary = summary || {};
        document.getElementById("kpiTotal").textContent = Number(summary.total_count || 0).toLocaleString("en-IN");
        document.getElementById("kpiActive").textContent = Number(summary.active_count || 0).toLocaleString("en-IN");
        document.getElementById("kpiInactive").textContent = Number(summary.inactive_count || 0).toLocaleString("en-IN");
        document.getElementById("kpiMatching").textContent = Number(summary.matching_count || 0).toLocaleString("en-IN");
    }

    function loadFilterOptions() {
        App.api("api/hsn.php?options=1").then(function(result){
            var rates = (result.data && result.data.gst_rates) || [];
            gstRateFilter.innerHTML = '<option value="">All GST Rates</option>';
            rates.forEach(function(value){
                var option = document.createElement("option");
                option.value = String(value);
                option.textContent = Number(value).toFixed(2) + "%";
                gstRateFilter.appendChild(option);
            });
        }).catch(function(){});
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
    }

    function rate(value) {
        return Number(value || 0).toFixed(2);
    }

    function statusHtml(value, type) {
        var active = Number(value) === 1;
        if (type !== "display") return Number(value);
        return active
            ? '<span class="dt-status active">Active</span>'
            : '<span class="dt-status inactive">Inactive</span>';
    }

    var table = AppDataTable.init("#masterTable", {
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading HSN...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[0,"asc"]],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"csvHtml5",text:"CSV",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"excelHtml5",text:"Excel",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"pdfHtml5",text:"PDF",title:"HSN Master",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"print",text:"Print",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}}
        ],
        ajax:function(data,callback){
            var params=new URLSearchParams();
            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value||"");
            if(gstRateFilter.value!=="")params.set("gst_rate",gstRateFilter.value);
            if(statusFilter.value!=="")params.set("status",statusFilter.value);
            if(data.order&&data.order[0]){
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/hsn.php?"+params.toString()).then(function(result){
                allowedActions=(result.data.allowed_actions||[]).map(Number);
                addButton.hidden=!has(allowedActions,ACTION_CREATE);
                setSummary(result.data.summary);
                AppDataTable.applyExportPermissions(table,allowedActions);
                callback(result.data.datatable);
            }).catch(function(error){
                addButton.hidden=true;
                setSummary({});
                App.showError(error,"Unable to load HSN records.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:"hsn_code",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"description",defaultContent:"-",orderable:false,render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"gst_rate",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(rate(v)):Number(v||0);}},
            {data:"cgst_rate",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(rate(v)):Number(v||0);}},
            {data:"sgst_rate",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(rate(v)):Number(v||0);}},
            {data:"igst_rate",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(rate(v)):Number(v||0);}},
            {data:"cess_rate",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(rate(v)):Number(v||0);}},
            {data:"status",render:function(v,t){return statusHtml(v,t);}},
            {
                data:null,orderable:false,searchable:false,className:"table-action-icons",
                render:function(data,type,row){
                    if(type!=="display")return "";
                    var html="";
                    if(has(allowedActions,ACTION_UPDATE)){
                        html+='<button type="button" class="table-icon-action js-edit" data-id="'+Number(row.id)+'" title="Edit HSN" aria-label="Edit HSN"><i data-lucide="pencil"></i></button>';
                    }
                    if(Number(row.status)===1&&has(allowedActions,ACTION_DEACTIVATE)){
                        html+='<button type="button" class="table-icon-action danger js-status" data-id="'+Number(row.id)+'" data-status="2" title="Deactivate HSN" aria-label="Deactivate HSN"><i data-lucide="circle-off"></i></button>';
                    }else if(Number(row.status)!==1&&has(allowedActions,ACTION_ACTIVATE)){
                        html+='<button type="button" class="table-icon-action js-status" data-id="'+Number(row.id)+'" data-status="1" title="Activate HSN" aria-label="Activate HSN"><i data-lucide="circle-check"></i></button>';
                    }
                    return html||'<span class="muted">View only</span>';
                }
            }
        ],
        language:{emptyTable:"No HSN records found.",zeroRecords:"No matching HSN records found."},
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

    [gstRateFilter,statusFilter].forEach(function(filter){
        filter.addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    loadFilterOptions();

    addButton.addEventListener("click",function(){
        if(!has(allowedActions,ACTION_CREATE))return;
        AppHSNForm.openCreate({
            apiUrl:"api/hsn.php",
            onSaved:function(){table.ajax.reload(null,false);}
        });
    });

    document.addEventListener("click",function(event){
        var editButton=event.target.closest(".js-edit");
        if(editButton){
            if(!has(allowedActions,ACTION_UPDATE))return;
            AppHSNForm.openEdit(Number(editButton.getAttribute("data-id")),{
                apiUrl:"api/hsn.php",
                onSaved:function(){table.ajax.reload(null,false);}
            });
            return;
        }

        var statusButton=event.target.closest(".js-status");
        if(!statusButton)return;
        var status=Number(statusButton.getAttribute("data-status"));
        var requiredAction=status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE;
        if(!has(allowedActions,requiredAction))return;
        if(!window.confirm(status===1?"Activate this HSN?":"Deactivate this HSN?"))return;

        statusButton.disabled=true;
        App.api("api/hsn.php",{
            method:"PATCH",
            body:{id:Number(statusButton.getAttribute("data-id")),status:status}
        }).then(function(result){
            if(window.showToast)showToast(result.message||"HSN status updated.",{type:"success",duration:2});
            table.ajax.reload(null,false);
        }).catch(function(error){
            App.showError(error,"Unable to change HSN status.");
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
</body>
</html>
