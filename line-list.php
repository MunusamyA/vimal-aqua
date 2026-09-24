<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Line List';
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
        <h1>Line List</h1>
        <p>Manage lines for the current branch.</p>
    </div>
    <button class="btn btn-primary" id="addButton" type="button" style="display:none">
        <i data-lucide="plus"></i>Add Line
    </button>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="route"></i></div><div><div class="kpi-label">Total Lines</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">Active Lines</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-off"></i></div><div><div class="kpi-label">Inactive Lines</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="list-filter"></i></div><div><div class="kpi-label">Matching Results</div><div class="kpi-value" id="kpiMatching">0</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-8">
                <label for="masterSearch">Search</label>
                <input id="masterSearch" type="text" autocomplete="off" placeholder="Search lines...">
            </div>
            <div class="field col-4">
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
        <thead><tr><th>Code</th><th>Line Name</th><th>Status</th><th>Manage</th></tr></thead>
    </table>
</div>

<?php require __DIR__ . '/model/line-form.php'; ?>

<script>
(function ($, window, document) {
    "use strict";
    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var allowedActions = [];
    var searchTimer = null;
    var statsRows = [];
    var masterSearch = document.getElementById("masterSearch");
    var statusFilter = document.getElementById("statusFilter");
    var addButton = document.getElementById("addButton");
    var has = AppDataTable.has;
    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_ACTIVATE = 27;
    var ACTION_DEACTIVATE = 28;


    function updateStats() {
        var q = (masterSearch.value || "").trim().toLowerCase();
        var status = statusFilter.value;

        var matching = statsRows.filter(function(row) {
            var text = [row.line_code || "", row.line_name || ""].join(" ").toLowerCase();
            if (q && text.indexOf(q) === -1) return false;
            if (status !== "" && String(row.status) !== String(status)) return false;
            return true;
        });

        var active = statsRows.filter(function(row){ return Number(row.status) === 1; }).length;
        var inactive = statsRows.filter(function(row){ return Number(row.status) !== 1; }).length;

        document.getElementById("kpiTotal").textContent = statsRows.length.toLocaleString("en-IN");
        document.getElementById("kpiActive").textContent = active.toLocaleString("en-IN");
        document.getElementById("kpiInactive").textContent = inactive.toLocaleString("en-IN");
        document.getElementById("kpiMatching").textContent = matching.length.toLocaleString("en-IN");
    }

    async function refreshStats() {
        try {
            var result = await App.api("api/line.php");
            statsRows = result.data.lines || [];
        } catch (error) {
            statsRows = [];
        }
        updateStats();
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
    }

    function statusHtml(value, type) {
        var active = Number(value) === 1;
        if (type !== "display") return Number(value);
        return active
            ? '<span class="dt-status active">Active</span>'
            : '<span class="dt-status inactive">Inactive</span>';
    }

    var table = AppDataTable.init("#masterTable", {
        serverSide: true,
        searching: true,
        searchDelay: 350,
        appSearch: false,
        appLoaderText: "Loading lines...",
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0,"asc"]],
        scrollX: true,
        autoWidth: false,
        buttons: [
            {extend:"copyHtml5",text:"Copy",title:"Line List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
            {extend:"csvHtml5",text:"CSV",title:"Line List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
            {extend:"excelHtml5",text:"Excel",title:"Line List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
            {extend:"pdfHtml5",text:"PDF",title:"Line List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}},
            {extend:"print",text:"Print",title:"Line List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2]}}
        ],
        ajax: function (data, callback) {
            var params = new URLSearchParams();
            params.set("datatable", "1");
            params.set("draw", data.draw);
            params.set("start", data.start);
            params.set("length", data.length);
            params.set("search[value]", data.search.value || "");
            if (statusFilter.value !== "") params.set("status", statusFilter.value);
            if (data.order && data.order[0]) {
                params.set("order[0][column]", data.order[0].column);
                params.set("order[0][dir]", data.order[0].dir);
            }
            App.api("api/line.php?" + params.toString()).then(function (result) {
                allowedActions = (result.data.allowed_actions || []).map(Number);
                addButton.style.display = has(allowedActions, ACTION_CREATE) ? "inline-flex" : "none";
                AppDataTable.applyExportPermissions(table, allowedActions);
                callback(result.data.datatable);
            }).catch(function (error) {
                addButton.style.display = "none";
                App.showError(error, "Unable to load lines.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns: [
            {data:"line_code",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"line_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"status",render:function(v,t){return statusHtml(v,t);}},
            {
                data:null, orderable:false, searchable:false, className:"table-action-icons",
                render:function(data,type,row){
                    if (type !== "display") return "";
                    var html = "";
                    if (has(allowedActions, ACTION_UPDATE)) {
                        html += '<button type="button" class="table-icon-action js-edit" data-id="'+Number(row.id)+'" title="Edit Line" aria-label="Edit Line"><i data-lucide="pencil"></i></button>';
                    }
                    if (Number(row.status) === 1 && has(allowedActions, ACTION_DEACTIVATE)) {
                        html += '<button type="button" class="table-icon-action danger js-status" data-id="'+Number(row.id)+'" data-status="0" title="Deactivate Line" aria-label="Deactivate Line"><i data-lucide="circle-off"></i></button>';
                    } else if (Number(row.status) !== 1 && has(allowedActions, ACTION_ACTIVATE)) {
                        html += '<button type="button" class="table-icon-action js-status" data-id="'+Number(row.id)+'" data-status="1" title="Activate Line" aria-label="Activate Line"><i data-lucide="circle-check"></i></button>';
                    }
                    return html || '<span class="muted">View only</span>';
                }
            }
        ],
        language:{emptyTable:"No lines found.",zeroRecords:"No matching lines found."},
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
        searchTimer=window.setTimeout(function(){table.search(masterSearch.value.trim()).draw();updateStats();},350);
    });
    statusFilter.addEventListener("change",function(){table.ajax.reload(null,true);updateStats();});

    addButton.addEventListener("click",function(){
        if (!has(allowedActions, ACTION_CREATE) || !window.AppLineForm) return;
        AppLineForm.openCreate({
            apiUrl:"api/line.php",
            onSaved:function(){table.ajax.reload(null,false);refreshStats();}
        });
    });

    document.addEventListener("click",function(event){
        var editButton=event.target.closest(".js-edit");
        if(editButton && has(allowedActions,ACTION_UPDATE) && window.AppLineForm) {
            AppLineForm.openEdit(Number(editButton.getAttribute("data-id")||0),{
                apiUrl:"api/line.php",
                onSaved:function(){table.ajax.reload(null,false);refreshStats();}
            });
            return;
        }

        var statusButton=event.target.closest(".js-status");
        if(!statusButton) return;
        var nextStatus=Number(statusButton.getAttribute("data-status"));
        var needed=nextStatus===1?ACTION_ACTIVATE:ACTION_DEACTIVATE;
        if(!has(allowedActions,needed)) return;
        statusButton.disabled=true;
        App.api("api/line.php",{
            method:"PATCH",
            body:{id:Number(statusButton.getAttribute("data-id")||0),status:nextStatus}
        }).then(function(result){
            if(window.showToast)showToast(result.message||"Status updated successfully.",{type:"success",duration:2});
            table.ajax.reload(null,false);
            refreshStats();
        }).catch(function(error){App.showError(error,"Unable to update Line status.");})
          .finally(function(){statusButton.disabled=false;});
    });
    refreshStats();
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
