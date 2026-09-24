<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Purchase List';
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

<div class="page-heading">
    <div>
        <h1>Purchase List</h1>
        <p>Manage supplier purchases for the current branch.</p>
    </div>

    <div class="heading-actions">
        <a class="btn btn-primary" id="addButton" href="purchase-form.php" hidden>
            <i data-lucide="plus"></i>
            Add Purchase
        </a>
    </div>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="shopping-cart"></i></div>
        <div><div class="kpi-label">Purchases</div><div class="kpi-value" id="kpiPurchases">0</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="indian-rupee"></i></div>
        <div><div class="kpi-label">Grand Total</div><div class="kpi-value" id="kpiGrandTotal">₹0.00</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="badge-check"></i></div>
        <div><div class="kpi-label">Paid</div><div class="kpi-value" id="kpiPaid">₹0.00</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="circle-dollar-sign"></i></div>
        <div><div class="kpi-label">Balance</div><div class="kpi-value" id="kpiBalance">₹0.00</div></div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="masterSearch">Search</label>
                <input class="input" id="masterSearch" type="text" autocomplete="off"
                       placeholder="Purchase no, supplier, invoice...">
            </div>

            <div class="field col-3">
                <label for="supplierFilter">Supplier</label>
                <select class="select" id="supplierFilter">
                    <option value="">All Suppliers</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Draft</option>
                    <option value="2">Posted</option>
                    <option value="3">Cancelled</option>
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

    <table id="purchaseTable" class="display data-table">
        <thead>
        <tr>
            <th>Purchase No</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Supplier Invoice</th>
            <th>Grand Total</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Payment</th>
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

    var actions = [];
    var searchTimer = null;
    var masterSearch = document.getElementById("masterSearch");
    var supplierFilter = document.getElementById("supplierFilter");
    var statusFilter = document.getElementById("statusFilter");
    var dateFrom = document.getElementById("dateFrom");
    var dateTo = document.getElementById("dateTo");
    var addButton = document.getElementById("addButton");
    var has = AppDataTable.has;

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_CANCEL = 14;

    function setSummary(summary) {
        summary = summary || {};
        document.getElementById("kpiPurchases").textContent = Number(summary.total_purchases || 0).toLocaleString("en-IN");
        document.getElementById("kpiGrandTotal").textContent = money(summary.grand_total || 0);
        document.getElementById("kpiPaid").textContent = money(summary.paid_amount || 0);
        document.getElementById("kpiBalance").textContent = money(summary.balance_amount || 0);
    }

    function loadFilterOptions() {
        App.api("api/purchases.php?options=1").then(function(result){
            var suppliers = (result.data && result.data.suppliers) || [];
            supplierFilter.innerHTML = '<option value="">All Suppliers</option>';
            suppliers.forEach(function(row){
                var option = document.createElement("option");
                option.value = String(row.id);
                option.textContent = row.supplier_code + " - " + row.supplier_name;
                supplierFilter.appendChild(option);
            });
        }).catch(function(){});
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function money(value) {
        var amount = Number(value || 0);
        if (!Number.isFinite(amount)) amount = 0;
        return "₹" + amount.toLocaleString("en-IN", {
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function purchaseStatus(value, type) {
        var status = Number(value || 0);
        if (type !== "display") return status;
        if (status === 2) return '<span class="pill active">Posted</span>';
        if (status === 3) return '<span class="pill inactive">Cancelled</span>';
        return '<span class="pill pending">Draft</span>';
    }

    function paymentStatus(value, type) {
        var status = Number(value || 0);
        if (type !== "display") return status;
        if (status === 3) return '<span class="pill active">Paid</span>';
        if (status === 2) return '<span class="pill pending">Partially Paid</span>';
        return '<span class="pill info">Unpaid</span>';
    }

    var table = AppDataTable.init("#purchaseTable", {
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading purchases...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,"desc"]],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"Purchase List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:"csvHtml5",text:"CSV",title:"Purchase List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:"excelHtml5",text:"Excel",title:"Purchase List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:"pdfHtml5",text:"PDF",title:"Purchase List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
            {extend:"print",text:"Print",title:"Purchase List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}}
        ],
        ajax:function(data, callback) {
            var params = new URLSearchParams();
            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");

            if (supplierFilter.value !== "") params.set("supplier_id",supplierFilter.value);
            if (statusFilter.value !== "") params.set("status",statusFilter.value);
            if (dateFrom.value !== "") params.set("date_from",dateFrom.value);
            if (dateTo.value !== "") params.set("date_to",dateTo.value);

            if (data.order && data.order[0]) {
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/purchases.php?" + params.toString()).then(function(result) {
                actions = (result.data.allowed_actions || []).map(Number);
                addButton.hidden = !has(actions,ACTION_CREATE);
                setSummary(result.data.summary);
                AppDataTable.applyExportPermissions(table,actions);
                callback(result.data.datatable);
            }).catch(function(error) {
                addButton.hidden = true;
                setSummary({});
                App.showError(error,"Unable to load purchases.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:"purchase_no",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"purchase_date",defaultContent:"-"},
            {data:"supplier_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"supplier_invoice_no",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"grand_total",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
            {data:"paid_amount",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
            {data:"balance_amount",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
            {data:"payment_status",render:function(v,t){return paymentStatus(v,t);}},
            {data:"status",render:function(v,t){return purchaseStatus(v,t);}},
            {
                data:null,
                orderable:false,
                searchable:false,
                className:"table-action-icons",
                render:function(data,type,row){
                    if(type!=="display") return "";

                    var html="";
                    var status=Number(row.status||0);

                    if(status===1 && has(actions,ACTION_UPDATE)){
                        html+=App.iconActionHtml({
                            href:row.edit_url,
                            icon:"pencil",
                            label:"Edit Purchase"
                        });
                    }else{
                        html+=App.iconActionHtml({
                            href:row.view_url,
                            icon:"eye",
                            label:"View Purchase"
                        });
                    }

                    if(status!==3 && has(actions,ACTION_CANCEL)){
                        html+='<button type="button" class="table-icon-action danger js-cancel" ' +
                            'data-ref="'+escapeHtml(row.ref)+'" ' +
                            'title="Cancel Purchase" aria-label="Cancel Purchase">' +
                            '<i data-lucide="ban"></i></button>';
                    }

                    return html;
                }
            }
        ],
        language:{
            emptyTable:"No purchases found.",
            zeroRecords:"No matching purchases found."
        },
        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        }
    });

    (function removeDefaultSearchRow(){
        var tableElement=document.getElementById("purchaseTable");
        var card=tableElement?tableElement.closest(".table-card"):null;
        var searchRow=card?card.querySelector(".app-table-search-row"):null;
        if(searchRow) searchRow.remove();
    })();

    masterSearch.addEventListener("input",function(){
        window.clearTimeout(searchTimer);
        searchTimer=window.setTimeout(function(){
            table.search(masterSearch.value.trim()).draw();
        },350);
    });

    [supplierFilter,statusFilter,dateFrom,dateTo].forEach(function(filter){
        filter.addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    loadFilterOptions();

    document.addEventListener("click",function(event){
        var button=event.target.closest(".js-cancel");
        if(!button) return;
        if(!has(actions,ACTION_CANCEL)) return;

        if(!window.confirm("Cancel this Purchase?")) return;

        button.disabled=true;

        App.api("api/purchases.php",{
            method:"PATCH",
            body:{
                ref:button.getAttribute("data-ref"),
                action:"cancel"
            }
        }).then(function(result){
            if(window.showToast){
                showToast(result.message||"Purchase cancelled.",{type:"success",duration:2});
            }
            table.ajax.reload(null,false);
        }).catch(function(error){
            App.showError(error,"Unable to cancel Purchase.");
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
