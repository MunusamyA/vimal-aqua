<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Purchase Report';

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
        <h1>Purchase Report</h1>
        <p>Supplier purchase value, actual payment, settlement discount and outstanding analysis.</p>
    </div>
    <a class="btn gray" href="purchase-list.php">
        <i data-lucide="shopping-cart"></i>
        Purchase List
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="receipt-text"></i></span>
        <div>
            <div class="kpi-label">Purchases</div>
            <div class="kpi-value" id="kpiPurchaseCount">0</div>
            <div class="kpi-meta"><span>Filtered purchases</span></div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Grand Total</div>
            <div class="kpi-value" id="kpiGrandTotal">₹0.00</div>
            <div class="kpi-meta"><span>Total purchase value</span></div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Actual Paid</div>
            <div class="kpi-value" id="kpiActualPaid">₹0.00</div>
            <div class="kpi-meta"><span>Money paid to suppliers</span></div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="badge-percent"></i></span>
        <div>
            <div class="kpi-label">Settlement Discount</div>
            <div class="kpi-value" id="kpiSettlementDiscount">₹0.00</div>
            <div class="kpi-meta"><span>Discount received</span></div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-dollar-sign"></i></span>
        <div>
            <div class="kpi-label">Outstanding</div>
            <div class="kpi-value" id="kpiOutstanding">₹0.00</div>
            <div class="kpi-meta"><span>Pending payable</span></div>
        </div>
    </article>
</div>

<div class="card form-card">
    <div class="card-header">
        <div>
            <h2>Report Filters</h2>
            <p>Filter purchases by supplier, workflow, payment status and purchase date.</p>
        </div>
        <button class="btn gray" id="resetFilters" type="button">
            <i data-lucide="rotate-ccw"></i>
            Reset
        </button>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label for="purchaseSearch">Search</label>
                <input class="input" id="purchaseSearch" type="text" autocomplete="off"
                       placeholder="Purchase no, supplier or invoice">
            </div>

            <div class="field col-4">
                <label for="supplierFilter">Supplier</label>
                <select class="select" id="supplierFilter" data-placeholder="All Suppliers">
                    <option value="">All Suppliers</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="statusFilter">Purchase Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="1">Draft</option>
                    <option value="2" selected>Posted</option>
                    <option value="3">Cancelled</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="paymentStatusFilter">Payment Status</label>
                <select class="select" id="paymentStatusFilter">
                    <option value="">All Payment</option>
                    <option value="1">Unpaid</option>
                    <option value="2">Partially Paid</option>
                    <option value="3">Paid</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="dateFrom">From Date</label>
                <input class="input" id="dateFrom" type="date">
            </div>

            <div class="field col-3">
                <label for="dateTo">To Date</label>
                <input class="input" id="dateTo" type="date">
            </div>

            <div class="field col-3">
                <label>&nbsp;</label>
                <button class="btn btn-primary" id="applyFilters" type="button">
                    <i data-lucide="search"></i>
                    Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div>
            <h2>Purchase Analysis</h2>
            <p>Compact report view. Open a Purchase to see complete item, tax and payment details.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table id="purchaseReportTable" class="display data-table">
            <thead>
            <tr>
                <th>Purchase</th>
                <th>Supplier</th>
                <th>Grand Total</th>
                <th>Actual Paid</th>
                <th>Settlement Discount</th>
                <th>Outstanding</th>
                <th>Payment</th>
                <th>Status</th>
                <th>View</th>

                <th>Purchase Date</th>
                <th>Supplier Invoice</th>
                <th>Subtotal</th>
                <th>Purchase Discount</th>
                <th>Tax</th>
                <th>Other Charges</th>
                <th>Round Off</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($,window,document){
    "use strict";

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var table = null;
    var searchTimer = null;
    var supplierSelect = null;
    var reportActions = [];

    function esc(value){
        return String(value == null ? "" : value)
            .replace(/&/g,"&amp;")
            .replace(/</g,"&lt;")
            .replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;")
            .replace(/'/g,"&#039;");
    }

    function number(value){
        var n = Number(value || 0);
        return Number.isFinite(n) ? n : 0;
    }

    function money(value){
        return number(value).toLocaleString("en-IN",{
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function formatDate(value){
        if(!value) return "-";
        var parts = String(value).split("-");
        return parts.length === 3
            ? parts[2] + "/" + parts[1] + "/" + parts[0]
            : String(value);
    }

    function paymentPill(value,type){
        var v = Number(value || 1);
        if(type !== "display") return v;
        if(v === 3) return '<span class="pill active">Paid</span>';
        if(v === 2) return '<span class="pill pending">Partially Paid</span>';
        return '<span class="pill inactive">Unpaid</span>';
    }

    function statusPill(value,type){
        var v = Number(value || 1);
        if(type !== "display") return v;
        if(v === 2) return '<span class="pill active">Posted</span>';
        if(v === 3) return '<span class="pill inactive">Cancelled</span>';
        return '<span class="pill pending">Draft</span>';
    }

    function renderSummary(summary){
        summary = summary || {};

        document.getElementById("kpiPurchaseCount").textContent =
            String(Number(summary.purchase_count || 0));

        document.getElementById("kpiGrandTotal").textContent =
            "₹" + money(summary.grand_total);

        document.getElementById("kpiActualPaid").textContent =
            "₹" + money(summary.actual_paid);

        document.getElementById("kpiSettlementDiscount").textContent =
            "₹" + money(summary.settlement_discount);

        document.getElementById("kpiOutstanding").textContent =
            "₹" + money(summary.outstanding);
    }

    function supplierItems(rows){
        return (rows || []).map(function(row){
            return {
                value:String(row.ref || ""),
                text:(row.supplier_code ? row.supplier_code + " - " : "") +
                    (row.supplier_name || "Supplier")
            };
        });
    }

    function fillSuppliers(rows,selectedValue){
        var select = document.getElementById("supplierFilter");
        var selected = selectedValue == null ? "" : String(selectedValue);

        if(window.GlobalSelect){
            supplierSelect = select._globalSelect ||
                GlobalSelect.init(select,{placeholder:"All Suppliers"});

            if(supplierSelect && typeof supplierSelect.setOptions === "function"){
                supplierSelect.setOptions(supplierItems(rows),selected);
                return;
            }
        }

        select.innerHTML = '<option value="">All Suppliers</option>';
        (rows || []).forEach(function(row){
            var option = document.createElement("option");
            option.value = row.ref || "";
            option.textContent =
                (row.supplier_code ? row.supplier_code + " - " : "") +
                (row.supplier_name || "Supplier");
            select.appendChild(option);
        });
        select.value = selected;
    }

    function currentSupplier(){
        return document.getElementById("supplierFilter").value || "";
    }

    function requestReload(resetPage){
        if(!table) return;
        table.ajax.reload(null, resetPage !== false);
    }

    function buildTable(){
        table = AppDataTable.init("#purchaseReportTable",{
            serverSide:true,
            searching:true,
            searchDelay:350,
            appSearch:false,
            pageLength:25,
            lengthMenu:[[10,25,50,100],[10,25,50,100]],
            order:[[9,"desc"]],
            autoWidth:false,
            buttons:[
                {
                    extend:"copyHtml5",
                    text:"Copy",
                    title:"Purchase Report",
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,9,1,10,11,12,13,14,15,2,3,4,5,6,7]}
                },
                {
                    extend:"csvHtml5",
                    text:"CSV",
                    title:"Purchase Report",
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,9,1,10,11,12,13,14,15,2,3,4,5,6,7]}
                },
                {
                    extend:"excelHtml5",
                    text:"Excel",
                    title:"Purchase Report",
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,9,1,10,11,12,13,14,15,2,3,4,5,6,7]}
                },
                {
                    extend:"pdfHtml5",
                    text:"PDF",
                    title:"Purchase Report",
                    orientation:"landscape",
                    pageSize:"A4",
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,9,1,10,11,12,13,14,15,2,3,4,5,6,7]}
                },
                {
                    extend:"print",
                    text:"Print",
                    title:"Purchase Report",
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,9,1,10,11,12,13,14,15,2,3,4,5,6,7]}
                }
            ],
            ajax:function(data,callback){
                var params = new URLSearchParams();

                params.set("datatable","1");
                params.set("draw",data.draw);
                params.set("start",data.start);
                params.set("length",data.length);
                params.set("search[value]",data.search.value || "");

                var supplier = currentSupplier();
                var status = document.getElementById("statusFilter").value;
                var paymentStatus = document.getElementById("paymentStatusFilter").value;
                var from = document.getElementById("dateFrom").value;
                var to = document.getElementById("dateTo").value;

                if(supplier) params.set("supplier_ref",supplier);
                if(status) params.set("status",status);
                if(paymentStatus) params.set("payment_status",paymentStatus);
                if(from) params.set("date_from",from);
                if(to) params.set("date_to",to);

                if(data.order && data.order[0]){
                    params.set("order[0][column]",data.order[0].column);
                    params.set("order[0][dir]",data.order[0].dir);
                }

                App.api("api/purchase-report.php?" + params.toString())
                    .then(function(result){
                        reportActions = (result.data.allowed_actions || []).map(Number);

                        AppDataTable.applyExportPermissions(
                            table,
                            reportActions
                        );

                        renderSummary(result.data.summary || {});
                        callback(result.data.datatable);
                    })
                    .catch(function(error){
                        renderSummary({});
                        App.showError(
                            error,
                            "Unable to load Purchase Report."
                        );

                        callback({
                            draw:data.draw,
                            recordsTotal:0,
                            recordsFiltered:0,
                            data:[]
                        });
                    });
            },
            columns:[
                {
                    data:null,
                    render:function(row,type){
                        if(type !== "display") return row.purchase_no || "";
                        return '<strong>' + esc(row.purchase_no || "-") + '</strong>' +
                            '<div class="muted">' + esc(formatDate(row.purchase_date)) + '</div>';
                    }
                },
                {
                    data:null,
                    render:function(row,type){
                        if(type !== "display") return row.supplier_label || "";
                        var html = '<strong>' + esc(row.supplier_label || "-") + '</strong>';
                        if(row.supplier_invoice_no){
                            html += '<div class="muted">Invoice: ' +
                                esc(row.supplier_invoice_no) + '</div>';
                        }
                        return html;
                    }
                },
                {
                    data:"grand_total",
                    className:"dt-body-right",
                    render:function(v,t){
                        return t === "display"
                            ? "<strong>₹" + money(v) + "</strong>"
                            : number(v);
                    }
                },
                {
                    data:"actual_paid",
                    className:"dt-body-right",
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                },
                {
                    data:"settlement_discount",
                    className:"dt-body-right",
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                },
                {
                    data:"outstanding",
                    className:"dt-body-right",
                    render:function(v,t){
                        return t === "display"
                            ? "<strong>₹" + money(v) + "</strong>"
                            : number(v);
                    }
                },
                {
                    data:"payment_status",
                    render:paymentPill
                },
                {
                    data:"status",
                    render:statusPill
                },
                {
                    data:null,
                    orderable:false,
                    searchable:false,
                    className:"table-action-icons",
                    render:function(data,type,row){
                        if(type !== "display") return "";
                        if(!row.view_url) return '<span class="muted">-</span>';

                        return App.iconActionHtml({
                            href:row.view_url,
                            icon:"eye",
                            label:"View Purchase"
                        });
                    }
                },

                {
                    data:"purchase_date",
                    visible:false,
                    render:function(v,t){
                        return t === "display" ? formatDate(v) : v;
                    }
                },
                {
                    data:"supplier_invoice_no",
                    visible:false,
                    defaultContent:"-"
                },
                {
                    data:"subtotal",
                    visible:false,
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                },
                {
                    data:"purchase_discount",
                    visible:false,
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                },
                {
                    data:"tax_amount",
                    visible:false,
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                },
                {
                    data:"other_charges",
                    visible:false,
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                },
                {
                    data:"round_off",
                    visible:false,
                    render:function(v,t){
                        return t === "display" ? "₹" + money(v) : number(v);
                    }
                }
            ],
            drawCallback:function(){
                if(window.lucide) window.lucide.createIcons();
            }
        });
    }

    document.getElementById("purchaseSearch").addEventListener("input",function(){
        var input = this;

        window.clearTimeout(searchTimer);

        searchTimer = window.setTimeout(function(){
            if(table){
                table.search(input.value.trim()).draw();
            }
        },350);
    });

    document.getElementById("applyFilters").addEventListener("click",function(){
        requestReload(true);
    });

    ["supplierFilter","statusFilter","paymentStatusFilter"].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            requestReload(true);
        });
    });

    document.getElementById("resetFilters").addEventListener("click",function(){
        document.getElementById("purchaseSearch").value = "";
        document.getElementById("statusFilter").value = "2";
        document.getElementById("paymentStatusFilter").value = "";
        document.getElementById("dateFrom").value = "";
        document.getElementById("dateTo").value = "";

        if(supplierSelect && typeof supplierSelect.setOptions === "function"){
            supplierSelect.setOptions(
                supplierItems(window.__purchaseReportSuppliers || []),
                ""
            );
        }else{
            document.getElementById("supplierFilter").value = "";
        }

        if(table){
            table.search("").draw();
        }
    });

    (async function(){
        try{
            var options = await App.api("api/purchase-report.php?options=1");
            window.__purchaseReportSuppliers = options.data.suppliers || [];
            fillSuppliers(window.__purchaseReportSuppliers,"");
            buildTable();
        }catch(error){
            App.showError(error,"Unable to load Purchase Report options.");
        }
    })();

})(jQuery,window,document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
if(window.lucide){window.lucide.createIcons();}
</script>
</body>
</html>