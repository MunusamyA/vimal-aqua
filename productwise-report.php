<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Product-wise Report';

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
        <h1>Product-wise Report</h1>
        <p>Product-level sales quantity, taxable value, GST and net sales.</p>
    </div>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="receipt-text"></i></div>
        <div>
            <div class="kpi-label">Documents</div>
            <div class="kpi-value" id="kpiInvoices">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="package"></i></div>
        <div>
            <div class="kpi-label">Products</div>
            <div class="kpi-value" id="kpiProducts">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="boxes"></i></div>
        <div>
            <div class="kpi-label">Quantity</div>
            <div class="kpi-value" id="kpiQty">0.000</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="indian-rupee"></i></div>
        <div>
            <div class="kpi-label">Taxable Sales</div>
            <div class="kpi-value" id="kpiTaxable">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="percent"></i></div>
        <div>
            <div class="kpi-label">GST</div>
            <div class="kpi-value" id="kpiTax">₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="chart-no-axes-combined"></i></div>
        <div>
            <div class="kpi-label">Net Sales</div>
            <div class="kpi-value" id="kpiNet">₹0.00</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">

            <div class="field col-3">
                <label for="reportSearch">Search</label>
                <input
                    class="input"
                    id="reportSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Product, category, HSN..."
                >
            </div>

            <div class="field col-3">
                <label for="productFilter">Product</label>
                <select class="select" id="productFilter" data-placeholder="All Products">
                    <option value="">All Products</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="categoryFilter">Category</label>
                <select class="select" id="categoryFilter" data-placeholder="All Categories">
                    <option value="">All Categories</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="documentFilter">Document</label>
                <select class="select" id="documentFilter">
                    <option value="">All Sales Documents</option>
                    <option value="2">Sales Invoice</option>
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
        <table id="productReportTable" class="display data-table">
            <thead>
                <tr>
                    <th>Product Code</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>HSN</th>
                    <th>Documents</th>
                    <th>Qty</th>
                    <th>Gross</th>
                    <th>Discount</th>
                    <th>Taxable</th>
                    <th>GST</th>
                    <th>Net Sales</th>
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
    var productSelect = null;
    var categorySelect = null;

    function money(value) {
        return '₹' + Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function qty(value) {
        return Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        });
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiInvoices').textContent =
            Number(summary.invoice_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiProducts').textContent =
            Number(summary.product_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiQty').textContent =
            qty(summary.total_qty);

        document.getElementById('kpiTaxable').textContent =
            money(summary.taxable_value);

        document.getElementById('kpiTax').textContent =
            money(summary.tax_amount);

        document.getElementById('kpiNet').textContent =
            money(summary.net_sales);
    }

    function params(data) {
        var p = new URLSearchParams();

        p.set('report', 'productwise');
        p.set('datatable', '1');
        p.set('draw', data.draw || 1);
        p.set('start', data.start || 0);
        p.set('length', data.length || 25);
        p.set('search[value]', (data.search && data.search.value) ? data.search.value : '');

        [
            ['product_ref', 'productFilter'],
            ['category_ref', 'categoryFilter'],
            ['document_type', 'documentFilter'],
            ['tax_mode', 'taxFilter'],
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

    function initTable() {
        table = AppDataTable.init('#productReportTable', {
            serverSide: true,
            processing: true,
            searching: true,
            searchDelay: 350,
            appSearch: false,
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],
            order: [[1, 'asc']],
            scrollX: true,
            autoWidth: false,

            /*
             * Same explicit export setup used by the working Sales Report.
             * Action IDs in this project:
             * 5=Copy, 6=CSV, 7=Excel, 8=PDF, 9=Print.
             */
            buttons: [
                {
                    extend: 'copyHtml5',
                    text: 'Copy',
                    title: 'Product-wise Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,10]
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: 'CSV',
                    title: 'Product-wise Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,10]
                    }
                },
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: 'Product-wise Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,10]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: 'Product-wise Report',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,10]
                    }
                },
                {
                    extend: 'print',
                    text: 'Print',
                    title: 'Product-wise Report',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: {
                        columns: [0,1,2,3,4,5,6,7,8,9,10]
                    }
                }
            ],

            ajax: function (data, callback) {
                App.api('api/reports.php?' + params(data).toString())
                    .then(function (response) {
                        var payload = response && response.data ? response.data : {};

                        actions = (payload.allowed_actions || []).map(Number);

                        setSummary(payload.summary || {});

                        if (table) {
                            AppDataTable.applyExportPermissions(table, actions);
                        }

                        var dt = payload.datatable || {};

                        callback({
                            draw: Number(dt.draw || data.draw || 1),
                            recordsTotal: Number(dt.recordsTotal || 0),
                            recordsFiltered: Number(dt.recordsFiltered || 0),
                            data: Array.isArray(dt.data) ? dt.data : []
                        });
                    })
                    .catch(function (error) {
                        App.showError(error, 'Unable to load Product-wise Report.');

                        setSummary({});

                        callback({
                            draw: data.draw || 1,
                            recordsTotal: 0,
                            recordsFiltered: 0,
                            data: []
                        });
                    });
            },

            columns: [
                {
                    data: 'product_code',
                    defaultContent: '-'
                },
                {
                    data: 'product_name',
                    defaultContent: '-'
                },
                {
                    data: 'category_name',
                    defaultContent: '-'
                },
                {
                    data: 'hsn_code',
                    defaultContent: '-'
                },
                {
                    data: 'invoice_count',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display'
                            ? Number(value || 0).toLocaleString('en-IN')
                            : Number(value || 0);
                    }
                },
                {
                    data: 'total_qty',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type, row) {
                        if (type !== 'display') {
                            return Number(value || 0);
                        }

                        return qty(value) + (row.primary_unit ? ' ' + row.primary_unit : '');
                    }
                },
                {
                    data: 'gross_amount',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display' ? money(value) : Number(value || 0);
                    }
                },
                {
                    data: 'discount_amount',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display' ? money(value) : Number(value || 0);
                    }
                },
                {
                    data: 'taxable_value',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display' ? money(value) : Number(value || 0);
                    }
                },
                {
                    data: 'tax_amount',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display' ? money(value) : Number(value || 0);
                    }
                },
                {
                    data: 'net_sales',
                    defaultContent: 0,
                    className: 'dt-body-right',
                    render: function (value, type) {
                        return type === 'display' ? money(value) : Number(value || 0);
                    }
                }
            ],

            language: {
                emptyTable: 'No Product Sales found.',
                zeroRecords: 'No matching Product Sales found.'
            },

            drawCallback: function () {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }
        });

        // Same behavior as the working Sales Report:
        // remove only the automatic/default DataTable search row.
        var card = document
            .getElementById('productReportTable')
            .closest('.table-card');

        var duplicateSearch = card
            ? card.querySelector('.app-table-search-row')
            : null;

        if (duplicateSearch) {
            duplicateSearch.remove();
        }
    }

    function reloadReport() {
        if (table) {
            table.ajax.reload(null, true);
        }
    }

    function refreshSelect(id) {
        var element = document.getElementById(id);

        if (
            element &&
            element._globalSelect &&
            typeof element._globalSelect.refresh === 'function'
        ) {
            element._globalSelect.refresh();
        }
    }

    async function boot() {
        try {
            var response = await App.api('api/reports.php?report=productwise&options=1');
            var data = response && response.data ? response.data : {};

            productSelect = GlobalSelect.init(
                document.getElementById('productFilter'),
                { placeholder: 'All Products' }
            );

            productSelect.setOptions(
                (data.products || []).map(function (item) {
                    return {
                        value: item.ref,
                        text: item.product_code + ' - ' + item.product_name
                    };
                }),
                ''
            );

            categorySelect = GlobalSelect.init(
                document.getElementById('categoryFilter'),
                { placeholder: 'All Categories' }
            );

            categorySelect.setOptions(
                (data.categories || []).map(function (item) {
                    return {
                        value: item.ref,
                        text: item.category_code + ' - ' + item.category_name
                    };
                }),
                ''
            );

            GlobalSelect.init(
                document.getElementById('documentFilter'),
                { placeholder: 'All Sales Documents' }
            );

            GlobalSelect.init(
                document.getElementById('taxFilter'),
                { placeholder: 'All' }
            );

            var today = data.today || new Date().toISOString().slice(0, 10);
            var firstDay = today.slice(0, 8) + '01';

            document.getElementById('dateFrom').value = firstDay;
            document.getElementById('dateTo').value = today;

            refreshSelect('productFilter');
            refreshSelect('categoryFilter');
            refreshSelect('documentFilter');
            refreshSelect('taxFilter');

            initTable();
        }
        catch (error) {
            App.showError(error, 'Unable to load Product-wise Report options.');
        }
    }

    document.getElementById('reportSearch').addEventListener('input', function () {
        var field = this;

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            if (table) {
                table.search(field.value.trim()).draw();
            }
        }, 350);
    });

    [
        'productFilter',
        'categoryFilter',
        'documentFilter',
        'taxFilter',
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
