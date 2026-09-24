<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Customer Payments';
$headStyles=[
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts=[
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
<title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
<?php render_frontend_config_script(); ?>
<script src="assets/js/runtime.js"></script>
<?php foreach($headStyles as $url): ?><link rel="stylesheet" href="<?php echo web_h($url); ?>"><?php endforeach; ?>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<?php foreach($headScripts as $url): ?><script src="<?php echo web_h($url); ?>"></script><?php endforeach; ?>
</head>
<body>
<div class="app-shell">
<?php require __DIR__.'/include/sidebar.php'; ?>
<main class="main-stage">
<?php require __DIR__.'/include/topbar.php'; ?>
<section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/datatable.js"></script>

<div class="page-heading">
    <div>
        <h1>Customer Payments</h1>
        <p>Customer receipts with automatic Opening Balance and Invoice FIFO allocation.</p>
    </div>
    <div class="heading-actions">
        <a class="btn gray" href="sales-list.php"><i data-lucide="receipt-text"></i>Sales</a>
        <a class="btn btn-primary" id="addButton" href="customer-payment-form.php" hidden><i data-lucide="plus"></i>New Payment</a>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-8">
                <label for="masterSearch">Search</label>
                <input class="input" id="masterSearch" type="text" placeholder="Search receipt, customer, code or mobile...">
            </div>
            <div class="field col-4">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Cancelled</option>
                </select>
            </div>
        </div>
    </div>
    <table id="paymentTable" class="display data-table">
        <thead>
            <tr>
                <th>Receipt No</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Cash</th>
                <th>UPI</th>
                <th>Bank</th>
                <th>Cheque</th>
                <th>Received</th>
                <th>Discount</th>
                <th>Settlement</th>
                <th>Remarks</th>
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
    var timer = null;
    var has = AppDataTable.has;
    var ACTION_UPDATE = 3;
    var ACTION_CANCEL = 14;
    var ACTION_RECEIVE_PAYMENT = 29;

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function money(value) {
        return "₹" + Number(value || 0).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function refreshIcons() { if (window.lucide) lucide.createIcons(); }

    var table = AppDataTable.init("#paymentTable", {
        serverSide: true,
        searching: true,
        scrollX: true,
        autoWidth: false,
        order: [[1, "desc"]],
        buttons: [
            {extend:"copyHtml5",text:"Copy",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}},
            {extend:"csvHtml5",text:"CSV",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}},
            {extend:"excelHtml5",text:"Excel",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}},
            {extend:"pdfHtml5",text:"PDF",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}},
            {extend:"print",text:"Print",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}}
        ],
        ajax: function (data, callback) {
            var query = new URLSearchParams();
            query.set("datatable", "1");
            query.set("draw", data.draw);
            query.set("start", data.start);
            query.set("length", data.length);
            query.set("search[value]", data.search.value || "");
            if (byId("statusFilter").value !== "") query.set("status", byId("statusFilter").value);
            App.api("api/customer-payments.php?" + query.toString()).then(function (result) {
                actions = (result.data.allowed_actions || []).map(Number);
                byId("addButton").hidden = !(has(actions, ACTION_RECEIVE_PAYMENT));
                AppDataTable.applyExportPermissions(table, actions);
                callback(result.data.datatable);
            }).catch(function (error) {
                App.showError(error, "Unable to load Customer Payments.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns: [
            {data:"payment_no",render:function(v,t){return t==="display"?esc(v):v;}},
            {data:"payment_date"},
            {data:null,render:function(v,t,row){var x=(row.customer_code?row.customer_code+" - ":"")+row.customer_name;return t==="display"?esc(x):x;}},
            {data:"cash_amount",className:"dt-body-right",render:function(v,t){return t==="display"?money(v):Number(v||0);}},
            {data:"upi_amount",className:"dt-body-right",render:function(v,t){return t==="display"?money(v):Number(v||0);}},
            {data:"bank_amount",className:"dt-body-right",render:function(v,t){return t==="display"?money(v):Number(v||0);}},
            {data:"cheque_amount",className:"dt-body-right",render:function(v,t){return t==="display"?money(v):Number(v||0);}},
            {data:"amount",className:"dt-body-right",render:function(v,t){return t==="display"?"<strong>"+money(v)+"</strong>":Number(v||0);}},
            {data:"discount_amount",className:"dt-body-right",render:function(v,t,row){
                if(t!=="display") return Number(v||0);
                var amount=money(v);
                if(Number(row.discount_type)===2 && Number(row.discount_value||0)>0) amount += " ("+Number(row.discount_value).toFixed(2)+"%)";
                return amount;
            }},
            {data:"settlement_amount",className:"dt-body-right",render:function(v,t){return t==="display"?"<strong>"+money(v)+"</strong>":Number(v||0);}},
            {data:"remarks",render:function(v,t){var x=v||"—";return t==="display"?esc(x):x;}},
            {data:"status",render:function(v,t){var active=Number(v)===1;var x=active?"Active":"Cancelled";return t==="display"?'<span class="pill '+(active?'active':'danger')+'">'+x+'</span>':x;}},
            {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(d,t,row){
                if(t!=="display") return "";
                var active=Number(row.status)===1;
                var canEdit=active&&has(actions,ACTION_UPDATE)&&has(actions,ACTION_RECEIVE_PAYMENT);
                var html=App.iconActionHtml({href:row.edit_url,icon:canEdit?"pencil":"eye",label:canEdit?"Edit Payment":"View Payment"});
                if(active&&has(actions,ACTION_CANCEL)&&has(actions,ACTION_RECEIVE_PAYMENT)){
                    html+='<button type="button" class="table-icon-action danger js-cancel-payment" data-ref="'+esc(row.ref)+'" title="Delete / Cancel Payment" aria-label="Delete / Cancel Payment"><i data-lucide="trash-2"></i></button>';
                }
                return html;
            }}
        ],
        drawCallback: refreshIcons
    });

    function byId(id) { return document.getElementById(id); }

    var card = byId("paymentTable").closest(".table-card");
    var defaultSearch = card ? card.querySelector(".app-table-search-row") : null;
    if (defaultSearch) defaultSearch.remove();

    byId("masterSearch").addEventListener("input", function () {
        clearTimeout(timer);
        timer = setTimeout(function () { table.search(byId("masterSearch").value.trim()).draw(); }, 350);
    });
    byId("statusFilter").addEventListener("change", function () { table.ajax.reload(null, true); });

    document.addEventListener("click", async function (event) {
        var button = event.target.closest(".js-cancel-payment");
        if (!button) return;
        var ref = button.getAttribute("data-ref") || "";
        if (!ref || !window.confirm("Delete / cancel this Customer Payment? Account ledger and FIFO allocations will be recalculated.")) return;
        button.disabled = true;
        try {
            var result = await App.api("api/customer-payments.php", { method:"POST", body:{action:"cancel",ref:ref} });
            if (window.showToast) showToast(result.message || "Customer Payment cancelled.", "success");
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to cancel Customer Payment.");
            button.disabled = false;
        }
    });

    refreshIcons();
})(jQuery, window, document);
</script>
</section>
<?php require __DIR__.'/include/footer.php'; ?>
</main>
</div>
</body>
</html>
