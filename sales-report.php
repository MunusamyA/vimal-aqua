<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Sales Report';

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

<title>
    <?php echo web_h((string)$pageTitle); ?> · <?php echo web_h(app_name()); ?>
</title>

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


<!-- =========================================================
     PAGE HEADER
========================================================= -->
<div class="page-head">
    <div>
        <h1>Sales Report</h1>
        <p>Sales invoice totals, collections, outstanding and GST.</p>
    </div>
</div>


<!-- =========================================================
     KPI CARDS
========================================================= -->
<div class="kpi-grid">

    <div class="card kpi-card">
        <div class="kpi-icon blue">
            <i data-lucide="receipt-text"></i>
        </div>
        <div>
            <div class="kpi-label">Sales</div>
            <div class="kpi-value" id="kpiSales">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal">
            <i data-lucide="indian-rupee"></i>
        </div>
        <div>
            <div class="kpi-label">Grand Total</div>
            <div class="kpi-value" id="kpiGrand">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green">
            <i data-lucide="badge-check"></i>
        </div>
        <div>
            <div class="kpi-label">Paid</div>
            <div class="kpi-value" id="kpiPaid">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="clock-3"></i>
        </div>
        <div>
            <div class="kpi-label">Outstanding</div>
            <div class="kpi-value" id="kpiBalance">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon blue">
            <i data-lucide="percent"></i>
        </div>
        <div>
            <div class="kpi-label">GST</div>
            <div class="kpi-value" id="kpiTax">₹0.00</div>
        </div>
    </div>

</div>


<!-- =========================================================
     FILTERS + SALES TABLE
     No separate Report Filters card
     No Sales Details header
     No Apply / Reset buttons
========================================================= -->
<div class="card table-card">

    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">

            <div class="field col-3">
                <label for="salesSearch">Search</label>
                <input
                    class="input"
                    id="salesSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Sale no, customer, remarks..."
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
                <label for="documentFilter">Document</label>
                <select class="select" id="documentFilter">
                    <option value="">All Documents</option>
                    <option value="1">Quotation</option>
                    <option value="2" selected>Sales Invoice</option>
                    <option value="3">Customer Order</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="taxFilter">Tax Mode</label>
                <select class="select" id="taxFilter">
                    <option value="">All</option>
                    <option value="1">GST</option>
                    <option value="0">Non-GST</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="paymentFilter">Payment</label>
                <select class="select" id="paymentFilter">
                    <option value="">All</option>
                    <option value="1">Unpaid</option>
                    <option value="2">Partially Paid</option>
                    <option value="3">Paid</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Draft</option>
                    <option value="2" selected>Posted</option>
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

    <div class="app-table-wrap">
        <table id="salesReportTable" class="display data-table">
            <thead>
                <tr>
                    <th>Sale</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Document</th>
                    <th>Tax</th>
                    <th>Grand Total</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>View</th>
                    <th>Sale Type</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>GST</th>
                    <th>Other Charges</th>
                    <th>Round Off</th>
                </tr>
            </thead>
        </table>
    </div>

</div>


<script>
(function ($, window, document) {
    'use strict';

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) {
        return;
    }

    var table = null;
    var actions = [];
    var searchTimer = null;
    var customerSelect = null;

    var has = AppDataTable.has;


    /* ---------------------------------------------------------
       HELPERS
    --------------------------------------------------------- */

    function money(value) {
        return '₹' + Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }


    function dateText(value) {

        if (!value) {
            return '-';
        }

        var parts = String(value).split('-');

        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : value;
    }


    function statusPill(value) {

        value = Number(value || 0);

        if (value === 2) {
            return '<span class="pill active">Posted</span>';
        }

        if (value === 3) {
            return '<span class="pill inactive">Cancelled</span>';
        }

        return '<span class="pill pending">Draft</span>';
    }


    function paymentPill(value) {

        value = Number(value || 0);

        if (value === 3) {
            return '<span class="pill active">Paid</span>';
        }

        if (value === 2) {
            return '<span class="pill pending">Partially Paid</span>';
        }

        return '<span class="pill inactive">Unpaid</span>';
    }


    function setSummary(summary) {

        summary = summary || {};

        document.getElementById('kpiSales').textContent =
            Number(summary.sale_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiGrand').textContent =
            money(summary.grand_total);

        document.getElementById('kpiPaid').textContent =
            money(summary.paid_amount);

        document.getElementById('kpiBalance').textContent =
            money(summary.balance_amount);

        document.getElementById('kpiTax').textContent =
            money(summary.tax_amount);
    }


    /* ---------------------------------------------------------
       DATATABLE REQUEST PARAMS
    --------------------------------------------------------- */

    function params(data) {

        var p = new URLSearchParams();

        p.set('report', 'sales');
        p.set('datatable', '1');
        p.set('draw', data.draw);
        p.set('start', data.start);
        p.set('length', data.length);
        p.set('search[value]', data.search.value || '');

        [
            ['customer_ref', 'customerFilter'],
            ['document_type', 'documentFilter'],
            ['tax_mode', 'taxFilter'],
            ['payment_status', 'paymentFilter'],
            ['status', 'statusFilter'],
            ['date_from', 'dateFrom'],
            ['date_to', 'dateTo']
        ].forEach(function (item) {

            var element = document.getElementById(item[1]);
            var value = element ? element.value : '';

            if (value !== '') {
                p.set(item[0], value);
            }
        });

        if (data.order && data.order[0]) {

            p.set('order[0][column]', data.order[0].column);
            p.set('order[0][dir]', data.order[0].dir);
        }

        return p;
    }


    /* ---------------------------------------------------------
       INIT DATATABLE
    --------------------------------------------------------- */

    function initTable() {

        table = AppDataTable.init('#salesReportTable', {

            serverSide: true,
            searching: true,
            searchDelay: 350,
            appSearch: false,

            pageLength: 25,

            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],

            order: [
                [1, 'desc']
            ],

            scrollX: true,
            autoWidth: false,

            columnDefs: [
                {
                    targets: [11, 12, 13, 14, 15, 16],
                    visible: false
                }
            ],

            buttons: [

                {
                    extend: 'copyHtml5',
                    text: 'Copy',
                    title: 'Sales Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]
                    }
                },

                {
                    extend: 'csvHtml5',
                    text: 'CSV',
                    title: 'Sales Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]
                    }
                },

                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: 'Sales Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]
                    }
                },

                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: 'Sales Report',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]
                    }
                },

                {
                    extend: 'print',
                    text: 'Print',
                    title: 'Sales Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,11,12,13,14,15,16]
                    }
                }

            ],


            ajax: function (data, callback) {

                App.api(
                    'api/reports.php?' + params(data).toString()
                )
                .then(function (response) {

                    actions = (
                        response.data.allowed_actions || []
                    ).map(Number);

                    setSummary(response.data.summary);

                    AppDataTable.applyExportPermissions(
                        table,
                        actions
                    );

                    callback(response.data.datatable);
                })
                .catch(function (error) {

                    App.showError(
                        error,
                        'Unable to load Sales Report.'
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
                    data: 'sale_no',
                    defaultContent: '-'
                },

                {
                    data: 'sale_date',
                    render: function (value, type) {
                        return type === 'display'
                            ? dateText(value)
                            : value;
                    }
                },

                {
                    data: 'customer_label',
                    defaultContent: '-'
                },

                {
                    data: 'document_type_label',
                    defaultContent: '-'
                },

                {
                    data: 'tax_mode',
                    render: function (value, type) {

                        if (type !== 'display') {
                            return Number(value);
                        }

                        return Number(value) === 1
                            ? '<span class="pill active">GST</span>'
                            : '<span class="pill pending">Non-GST</span>';
                    }
                },

                {
                    data: 'grand_total',
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'paid_amount',
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'balance_amount',
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'payment_status',
                    render: function (value, type) {
                        return type === 'display'
                            ? paymentPill(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'status',
                    render: function (value, type) {
                        return type === 'display'
                            ? statusPill(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'table-action-icons',

                    render: function (data, type, row) {

                        if (type !== 'display') {
                            return '';
                        }

                        if (has(actions, 1) && row.view_url) {

                            return App.iconActionHtml({
                                href: row.view_url,
                                icon: 'eye',
                                label: 'View Sale'
                            });
                        }

                        return '<span class="muted">-</span>';
                    }
                },

                {
                    data: 'sale_type_label',
                    defaultContent: '-'
                },

                {
                    data: 'subtotal',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'discount_amount',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'tax_amount',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'other_charges',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                },

                {
                    data: 'round_off',
                    render: function (value, type) {
                        return type === 'display'
                            ? money(value)
                            : Number(value || 0);
                    }
                }

            ],


            language: {
                emptyTable: 'No Sales found.',
                zeroRecords: 'No matching Sales found.'
            },


            drawCallback: function () {

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

        });


        /*
         * Remove duplicate DataTable search row because we use
         * the custom Search field placed above the table.
         */
        var card = document
            .getElementById('salesReportTable')
            .closest('.table-card');

        var duplicateSearch = card
            ? card.querySelector('.app-table-search-row')
            : null;

        if (duplicateSearch) {
            duplicateSearch.remove();
        }
    }


    /* ---------------------------------------------------------
       RELOAD REPORT
    --------------------------------------------------------- */

    function reloadReport() {

        if (table) {
            table.ajax.reload(null, true);
        }
    }


    /* ---------------------------------------------------------
       LOAD OPTIONS + DEFAULT FILTERS
    --------------------------------------------------------- */

    async function boot() {

        try {

            var response = await App.api(
                'api/reports.php?report=sales&options=1'
            );

            var data = response.data || {};

            var items = (data.customers || []).map(function (item) {

                return {
                    value: item.ref,
                    text: item.customer_code + ' - ' + item.customer_name
                };
            });


            customerSelect = GlobalSelect.init(
                document.getElementById('customerFilter'),
                {
                    placeholder: 'All Customers'
                }
            );

            customerSelect.setOptions(items, '');


            [
                'documentFilter',
                'taxFilter',
                'paymentFilter',
                'statusFilter'
            ].forEach(function (id) {

                GlobalSelect.init(
                    document.getElementById(id),
                    {
                        placeholder: 'All'
                    }
                );
            });


            var today = data.today ||
                new Date().toISOString().slice(0, 10);

            var firstDay =
                today.slice(0, 8) + '01';


            /*
             * Existing defaults retained:
             *
             * Document = Sales Invoice
             * Status   = Posted
             * Date     = First day of month to today
             */
            document.getElementById('documentFilter').value = '2';
            document.getElementById('statusFilter').value = '2';
            document.getElementById('dateFrom').value = firstDay;
            document.getElementById('dateTo').value = today;


            /*
             * Refresh GlobalSelect controls after default values.
             */
            [
                'documentFilter',
                'taxFilter',
                'paymentFilter',
                'statusFilter'
            ].forEach(function (id) {

                var element = document.getElementById(id);

                if (
                    element &&
                    element._globalSelect &&
                    typeof element._globalSelect.refresh === 'function'
                ) {
                    element._globalSelect.refresh();
                }
            });


            initTable();

        }
        catch (error) {

            App.showError(
                error,
                'Unable to load Sales Report options.'
            );
        }
    }


    /* ---------------------------------------------------------
       LIVE SEARCH
    --------------------------------------------------------- */

    document
        .getElementById('salesSearch')
        .addEventListener('input', function () {

            var field = this;

            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {

                if (table) {

                    table
                        .search(field.value.trim())
                        .draw();
                }

            }, 350);
        });


    /* ---------------------------------------------------------
       AUTO FILTER
       No Apply Filters button required.
    --------------------------------------------------------- */

    [
        'customerFilter',
        'documentFilter',
        'taxFilter',
        'paymentFilter',
        'statusFilter',
        'dateFrom',
        'dateTo'
    ].forEach(function (id) {

        var element = document.getElementById(id);

        if (!element) {
            return;
        }

        element.addEventListener('change', function () {
            reloadReport();
        });
    });


    boot();

})(jQuery, window, document);
</script>


</section>

<?php require __DIR__ . '/include/footer.php'; ?>

</main>
</div>

<script>
if (window.lucide) {
    window.lucide.createIcons();
}
</script>

</body>
</html>