<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Customer Payments';

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
<title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

<?php render_frontend_config_script(); ?>

<script src="assets/js/runtime.js"></script>

<?php foreach($headStyles as $url): ?>
<link rel="stylesheet" href="<?php echo web_h($url); ?>">
<?php endforeach; ?>

<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

<?php foreach($headScripts as $url): ?>
<script src="<?php echo web_h($url); ?>"></script>
<?php endforeach; ?>
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
<script src="assets/js/global-select.js"></script>


<div class="page-heading">
    <div>
        <h1>Customer Payments</h1>
        <p>Customer receipts with automatic Opening Balance and Invoice FIFO allocation.</p>
    </div>

    <div class="heading-actions">
        <a class="btn gray" href="sales-list.php">
            <i data-lucide="receipt-text"></i>
            Sales
        </a>

        <a class="btn btn-primary" id="addButton" href="customer-payment-form.php" hidden>
            <i data-lucide="plus"></i>
            New Payment
        </a>
    </div>
</div>


<!-- =========================================================
     PAYMENT STATISTICS
========================================================= -->
<div class="kpi-grid">

    <div class="card kpi-card">
        <div class="kpi-icon blue">
            <i data-lucide="receipt-text"></i>
        </div>
        <div>
            <div class="kpi-label">Receipts</div>
            <div class="kpi-value" id="kpiReceipts">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green">
            <i data-lucide="indian-rupee"></i>
        </div>
        <div>
            <div class="kpi-label">Received</div>
            <div class="kpi-value" id="kpiReceived">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="badge-percent"></i>
        </div>
        <div>
            <div class="kpi-label">Discount</div>
            <div class="kpi-value" id="kpiDiscount">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal">
            <i data-lucide="badge-check"></i>
        </div>
        <div>
            <div class="kpi-label">Settlement</div>
            <div class="kpi-value" id="kpiSettlement">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="ban"></i>
        </div>
        <div>
            <div class="kpi-label">Cancelled</div>
            <div class="kpi-value" id="kpiCancelled">0</div>
        </div>
    </div>

</div>


<!-- =========================================================
     FILTERS IN TABLE CARD HEADER
========================================================= -->
<div class="card table-card">

    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">

            <div class="field col-3">
                <label for="masterSearch">Search</label>
                <input
                    class="input"
                    id="masterSearch"
                    type="text"
                    placeholder="Receipt, customer, code, mobile..."
                >
            </div>

            <div class="field col-3">
                <label for="customerFilter">Customer</label>
                <select
                    class="select"
                    id="customerFilter"
                    data-placeholder="All Customers"
                >
                    <option value="">All Customers</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="paymentModeFilter">Payment Mode</label>
                <select class="select" id="paymentModeFilter">
                    <option value="">All</option>
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

</div>


<script>
(function ($, window, document) {
    "use strict";

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) {
        return;
    }

    var actions = [];
    var timer = null;
    var customerSelect = null;

    var has = AppDataTable.has;

    var ACTION_UPDATE = 3;
    var ACTION_CANCEL = 14;
    var ACTION_RECEIVE_PAYMENT = 29;


    function byId(id) {
        return document.getElementById(id);
    }


    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }


    function money(value) {
        return "₹" + Number(value || 0).toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }


    function refreshIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }


    function setStats(summary) {
        summary = summary || {};

        byId("kpiReceipts").textContent =
            Number(summary.receipt_count || 0).toLocaleString("en-IN");

        byId("kpiReceived").textContent =
            money(summary.received_amount);

        byId("kpiDiscount").textContent =
            money(summary.discount_amount);

        byId("kpiSettlement").textContent =
            money(summary.settlement_amount);

        byId("kpiCancelled").textContent =
            Number(summary.cancelled_count || 0).toLocaleString("en-IN");
    }


    function addFilter(query, key, id) {
        var element = byId(id);

        if (!element) {
            return;
        }

        var value = String(element.value || "").trim();

        if (value !== "") {
            query.set(key, value);
        }
    }


    var table = AppDataTable.init("#paymentTable", {

        serverSide: true,
        searching: true,
        appSearch: false,
        scrollX: true,
        autoWidth: false,

        order: [[1, "desc"]],

        pageLength: 25,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        buttons: [

            {
                extend: "copyHtml5",
                text: "Copy",
                action: AppDataTable.serverSideExportAction,
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },

            {
                extend: "csvHtml5",
                text: "CSV",
                action: AppDataTable.serverSideExportAction,
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },

            {
                extend: "excelHtml5",
                text: "Excel",
                action: AppDataTable.serverSideExportAction,
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },

            {
                extend: "pdfHtml5",
                text: "PDF",
                orientation: "landscape",
                pageSize: "A4",
                action: AppDataTable.serverSideExportAction,
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },

            {
                extend: "print",
                text: "Print",
                action: AppDataTable.serverSideExportAction,
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            }

        ],


        ajax: function (data, callback) {

            var query = new URLSearchParams();

            query.set("datatable", "1");
            query.set("draw", data.draw);
            query.set("start", data.start);
            query.set("length", data.length);
            query.set("search[value]", data.search.value || "");

            addFilter(query, "customer_id", "customerFilter");
            addFilter(query, "payment_mode", "paymentModeFilter");
            addFilter(query, "status", "statusFilter");
            addFilter(query, "date_from", "dateFrom");
            addFilter(query, "date_to", "dateTo");

            App.api(
                "api/customer-payments.php?" + query.toString()
            )
            .then(function (result) {

                actions = (
                    result.data.allowed_actions || []
                ).map(Number);

                byId("addButton").hidden =
                    !(has(actions, ACTION_RECEIVE_PAYMENT));

                setStats(result.data.summary);

                AppDataTable.applyExportPermissions(
                    table,
                    actions
                );

                callback(result.data.datatable);
            })
            .catch(function (error) {

                App.showError(
                    error,
                    "Unable to load Customer Payments."
                );

                callback({
                    draw: data.draw,
                    recordsTotal: 0,
                    recordsFiltered: 0,
                    data: []
                });
            });
        },


        columns: [

            {
                data: "payment_no",
                render: function (value, type) {
                    return type === "display"
                        ? esc(value)
                        : value;
                }
            },

            {
                data: "payment_date"
            },

            {
                data: null,
                render: function (value, type, row) {

                    var text =
                        (row.customer_code
                            ? row.customer_code + " - "
                            : "") +
                        row.customer_name;

                    return type === "display"
                        ? esc(text)
                        : text;
                }
            },

            {
                data: "cash_amount",
                className: "dt-body-right",
                render: function (value, type) {
                    return type === "display"
                        ? money(value)
                        : Number(value || 0);
                }
            },

            {
                data: "upi_amount",
                className: "dt-body-right",
                render: function (value, type) {
                    return type === "display"
                        ? money(value)
                        : Number(value || 0);
                }
            },

            {
                data: "bank_amount",
                className: "dt-body-right",
                render: function (value, type) {
                    return type === "display"
                        ? money(value)
                        : Number(value || 0);
                }
            },

            {
                data: "cheque_amount",
                className: "dt-body-right",
                render: function (value, type) {
                    return type === "display"
                        ? money(value)
                        : Number(value || 0);
                }
            },

            {
                data: "amount",
                className: "dt-body-right",
                render: function (value, type) {
                    return type === "display"
                        ? "<strong>" + money(value) + "</strong>"
                        : Number(value || 0);
                }
            },

            {
                data: "discount_amount",
                className: "dt-body-right",

                render: function (value, type, row) {

                    if (type !== "display") {
                        return Number(value || 0);
                    }

                    var amount = money(value);

                    if (
                        Number(row.discount_type) === 2 &&
                        Number(row.discount_value || 0) > 0
                    ) {
                        amount +=
                            " (" +
                            Number(row.discount_value).toFixed(2) +
                            "%)";
                    }

                    return amount;
                }
            },

            {
                data: "settlement_amount",
                className: "dt-body-right",
                render: function (value, type) {
                    return type === "display"
                        ? "<strong>" + money(value) + "</strong>"
                        : Number(value || 0);
                }
            },

            {
                data: "remarks",
                render: function (value, type) {

                    var text = value || "—";

                    return type === "display"
                        ? esc(text)
                        : text;
                }
            },

            {
                data: "status",

                render: function (value, type) {

                    var active = Number(value) === 1;
                    var text = active ? "Active" : "Cancelled";

                    return type === "display"
                        ? '<span class="pill ' +
                            (active ? 'active' : 'danger') +
                            '">' +
                            text +
                            '</span>'
                        : text;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: "table-action-icons",

                render: function (data, type, row) {

                    if (type !== "display") {
                        return "";
                    }

                    var active =
                        Number(row.status) === 1;

                    var canEdit =
                        active &&
                        has(actions, ACTION_UPDATE) &&
                        has(actions, ACTION_RECEIVE_PAYMENT);

                    var html = App.iconActionHtml({
                        href: row.edit_url,
                        icon: canEdit ? "pencil" : "eye",
                        label: canEdit
                            ? "Edit Payment"
                            : "View Payment"
                    });

                    if (
                        active &&
                        has(actions, ACTION_CANCEL) &&
                        has(actions, ACTION_RECEIVE_PAYMENT)
                    ) {
                        html +=
                            '<button type="button" ' +
                            'class="table-icon-action danger js-cancel-payment" ' +
                            'data-ref="' + esc(row.ref) + '" ' +
                            'title="Delete / Cancel Payment" ' +
                            'aria-label="Delete / Cancel Payment">' +
                            '<i data-lucide="trash-2"></i>' +
                            '</button>';
                    }

                    return html;
                }
            }

        ],

        drawCallback: refreshIcons
    });


    /*
     * Remove DataTable's duplicate built-in search row because
     * the page uses the Search field in the card header.
     */
    var card =
        byId("paymentTable").closest(".table-card");

    var defaultSearch =
        card
            ? card.querySelector(".app-table-search-row")
            : null;

    if (defaultSearch) {
        defaultSearch.remove();
    }


    /* Live search */
    byId("masterSearch").addEventListener(
        "input",
        function () {

            clearTimeout(timer);

            timer = setTimeout(
                function () {

                    table
                        .search(
                            byId("masterSearch")
                                .value
                                .trim()
                        )
                        .draw();

                },
                350
            );
        }
    );


    /* Automatic filters */
    [
        "customerFilter",
        "paymentModeFilter",
        "statusFilter",
        "dateFrom",
        "dateTo"
    ].forEach(function (id) {

        var element = byId(id);

        if (!element) {
            return;
        }

        element.addEventListener(
            "change",
            function () {
                table.ajax.reload(null, true);
            }
        );
    });


    /* Load customer filter options */
    async function loadFilters() {

        try {

            var result =
                await App.api(
                    "api/customer-payments.php?bootstrap=1"
                );

            var data = result.data || {};

            customerSelect =
                GlobalSelect.init(
                    byId("customerFilter"),
                    {
                        placeholder: "All Customers"
                    }
                );

            customerSelect.setOptions(
                (data.customers || []).map(
                    function (customer) {
                        return {
                            value: customer.id,
                            text:
                                customer.customer_code +
                                " - " +
                                customer.customer_name
                        };
                    }
                ),
                ""
            );

            GlobalSelect.init(
                byId("paymentModeFilter"),
                {
                    placeholder: "All"
                }
            );

            GlobalSelect.init(
                byId("statusFilter"),
                {
                    placeholder: "All"
                }
            );

        }
        catch (error) {

            App.showError(
                error,
                "Unable to load Customer Payment filters."
            );
        }
    }


    document.addEventListener(
        "click",
        async function (event) {

            var button =
                event.target.closest(
                    ".js-cancel-payment"
                );

            if (!button) {
                return;
            }

            var ref =
                button.getAttribute("data-ref") || "";

            if (
                !ref ||
                !window.confirm(
                    "Delete / cancel this Customer Payment? " +
                    "Account ledger and FIFO allocations will be recalculated."
                )
            ) {
                return;
            }

            button.disabled = true;

            try {

                var result =
                    await App.api(
                        "api/customer-payments.php",
                        {
                            method: "POST",
                            body: {
                                action: "cancel",
                                ref: ref
                            }
                        }
                    );

                if (window.showToast) {
                    showToast(
                        result.message ||
                        "Customer Payment cancelled.",
                        "success"
                    );
                }

                table.ajax.reload(null, false);
            }
            catch (error) {

                App.showError(
                    error,
                    "Unable to cancel Customer Payment."
                );

                button.disabled = false;
            }
        }
    );


    loadFilters();
    refreshIcons();

})(jQuery, window, document);
</script>

</section>

<?php require __DIR__.'/include/footer.php'; ?>

</main>
</div>
</body>
</html>
