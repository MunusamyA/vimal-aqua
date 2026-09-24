<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Supplier Ledger';

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
        <h1>Supplier Ledger</h1>
        <p>Supplier outstanding, purchases, payments and settlement discount statement.</p>
    </div>
    <a class="btn gray" href="supplier-payment-list.php">
        <i data-lucide="hand-coins"></i>Supplier Payments
    </a>
</div>

<!-- =========================================================
     CURRENT OUTSTANDING - TOP STATS
========================================================= -->
<div class="form-row">
    <div class="field col-4">
        <article class="card kpi-card">
            <span class="kpi-icon orange">
                <i data-lucide="wallet"></i>
            </span>
            <div>
                <div class="kpi-label">Opening Balance Pending</div>
                <div class="kpi-value" id="kpiOpeningPending">₹0.00</div>
                <div class="kpi-meta"><span>Current</span></div>
            </div>
        </article>
    </div>

    <div class="field col-4">
        <article class="card kpi-card">
            <span class="kpi-icon blue">
                <i data-lucide="shopping-cart"></i>
            </span>
            <div>
                <div class="kpi-label">Purchase Outstanding</div>
                <div class="kpi-value" id="kpiPurchaseOutstanding">₹0.00</div>
                <div class="kpi-meta"><span>Current</span></div>
            </div>
        </article>
    </div>

    <div class="field col-4">
        <article class="card kpi-card">
            <span class="kpi-icon teal">
                <i data-lucide="circle-dollar-sign"></i>
            </span>
            <div>
                <div class="kpi-label">Overall Outstanding</div>
                <div class="kpi-value" id="kpiOverallOutstanding">₹0.00</div>
                <div class="kpi-meta"><span>Current payable</span></div>
            </div>
        </article>
    </div>
</div>

<!-- =========================================================
     FILTERS
========================================================= -->
<article class="card form-card form-section">
    <div class="card-header">
        <div>
            <h2 class="section-heading">
                <i data-lucide="filter"></i>Ledger Filters
            </h2>
            <p>Select supplier and period to view the complete statement.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label for="supplierRef" class="required">Supplier</label>
                <select id="supplierRef" data-placeholder="Select Supplier">
                    <option value="">Select Supplier</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="dateFrom">From Date</label>
                <input id="dateFrom" type="date">
            </div>

            <div class="field col-2">
                <label for="dateTo">To Date</label>
                <input id="dateTo" type="date">
            </div>

            <div class="field col-2">
                <label for="transactionType">Transaction Type</label>
                <select id="transactionType">
                    <option value="">All Transactions</option>
                    <option value="purchase">Purchase</option>
                    <option value="payment">Supplier Payment</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="ledgerSearch">Search</label>
                <input id="ledgerSearch" type="text" autocomplete="off" placeholder="No / Against...">
            </div>
        </div>
    </div>
</article>

<!-- =========================================================
     SUPPLIER INFORMATION - SINGLE ROW
========================================================= -->
<article class="card form-section" id="supplierIdentity" hidden>
    <div class="card-header">
        <div>
            <h2 class="card-title">Supplier Information</h2>
            <p class="card-description">Current supplier details for the selected ledger.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-2">
                <span class="metric-label">Supplier</span>
                <strong id="supplierName">-</strong>
                <span class="muted" id="supplierCode">-</span>
            </div>

            <div class="field col-2">
                <span class="metric-label">Mobile</span>
                <strong id="supplierMobile">-</strong>
            </div>

            <div class="field col-2">
                <span class="metric-label">Email</span>
                <strong id="supplierEmail">-</strong>
            </div>

            <div class="field col-2">
                <span class="metric-label">GSTIN</span>
                <strong id="supplierGstin">-</strong>
            </div>

            <div class="field col-2">
                <span class="metric-label">PAN</span>
                <strong id="supplierPan">-</strong>
            </div>

            <div class="field col-2">
                <span class="metric-label">Current Payable</span>
                <strong id="supplierCurrentPayable">₹0.00</strong>
            </div>
        </div>
    </div>
</article>

<!-- =========================================================
     PENDING PURCHASE / INVOICE OUTSTANDING
========================================================= -->
<article class="card table-card form-section">
    <div class="card-header">
        <div>
            <h2 class="card-title">Pending Purchase / Invoice Outstanding</h2>
            <p class="card-description">Pending posted purchases available for supplier payment.</p>
        </div>
    </div>

    <div class="table-scroll">
        <table class="data-table">
            <thead>
            <tr>
                <th>Purchase No</th>
                <th>Date</th>
                <th>Supplier Invoice No</th>
                <th>Grand Total</th>
                <th>Settled</th>
                <th>Pending</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody id="pendingPurchaseBody">
            <tr>
                <td colspan="8" class="empty">Select a Supplier.</td>
            </tr>
            </tbody>
        </table>
    </div>
</article>

<!-- =========================================================
     STATEMENT SUMMARY
========================================================= -->
<article class="card form-section">
    <div class="card-header">
        <div>
            <h2 class="card-title">Statement Summary</h2>
            <p class="card-description">Summary for the selected date range.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-3">
                <article class="card kpi-card">
                    <span class="kpi-icon blue">
                        <i data-lucide="history"></i>
                    </span>
                    <div>
                        <div class="kpi-label">Opening / Brought Forward</div>
                        <div class="kpi-value" id="kpiBroughtForward">₹0.00</div>
                        <div class="kpi-meta"><span id="kpiBroughtForwardMeta">Start balance</span></div>
                    </div>
                </article>
            </div>

            <div class="field col-3">
                <article class="card kpi-card">
                    <span class="kpi-icon orange">
                        <i data-lucide="receipt-text"></i>
                    </span>
                    <div>
                        <div class="kpi-label">Period Purchases</div>
                        <div class="kpi-value" id="kpiPeriodPurchases">₹0.00</div>
                        <div class="kpi-meta"><span>Liability added</span></div>
                    </div>
                </article>
            </div>

            <div class="field col-2">
                <article class="card kpi-card">
                    <span class="kpi-icon green">
                        <i data-lucide="banknote"></i>
                    </span>
                    <div>
                        <div class="kpi-label">Actual Payments</div>
                        <div class="kpi-value" id="kpiActualPayments">₹0.00</div>
                        <div class="kpi-meta"><span>Cash / Bank</span></div>
                    </div>
                </article>
            </div>

            <div class="field col-2">
                <article class="card kpi-card">
                    <span class="kpi-icon teal">
                        <i data-lucide="badge-percent"></i>
                    </span>
                    <div>
                        <div class="kpi-label">Settlement Discount</div>
                        <div class="kpi-value" id="kpiDiscounts">₹0.00</div>
                        <div class="kpi-meta"><span>Waived</span></div>
                    </div>
                </article>
            </div>

            <div class="field col-2">
                <article class="card kpi-card">
                    <span class="kpi-icon green">
                        <i data-lucide="scale"></i>
                    </span>
                    <div>
                        <div class="kpi-label">Closing Balance</div>
                        <div class="kpi-value" id="kpiClosing">₹0.00</div>
                        <div class="kpi-meta"><span>Closing</span></div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</article>

<!-- =========================================================
     LEDGER STATEMENT
========================================================= -->
<article class="card table-card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Ledger Statement</h2>
            <p class="card-description">Chronological purchases, actual payments and settlement discounts.</p>
        </div>
    </div>

    <div class="table-scroll">
        <table id="supplierLedgerTable" class="display data-table">
            <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Transaction</th>
                <th>Against / Details</th>
                <th>Mode</th>
                <th>Debit</th>
                <th>Actual Payment</th>
                <th>Discount</th>
                <th>Total Credit</th>
                <th>Running Balance</th>
            </tr>
            </thead>
        </table>
    </div>
</article>

<script>
(function($,window,document){
    'use strict';

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()){
        return;
    }

    var supplierSelect = null;
    var searchTimer = null;
    var initialSupplierRef = new URLSearchParams(location.search).get('supplier_ref') || '';
    var paymentActions = [];

    function el(id){
        return document.getElementById(id);
    }

    function esc(value){
        return App.escapeHtml(String(value == null ? '' : value));
    }

    function n(value){
        var number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function money(value){
        return '₹' + n(value).toLocaleString('en-IN',{
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function has(actions,id){
        return (actions || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function formatDate(value){
        if(!value) return '-';
        var parts = String(value).split('-');
        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : String(value);
    }

    function refresh(globalSelect){
        if(globalSelect && typeof globalSelect.refresh === 'function'){
            globalSelect.refresh();
        }
    }

    function fillSuppliers(rows){
        rows = rows || [];

        var selectedValue = '';
        var items = rows.map(function(row){
            if(row.selected){
                selectedValue = row.ref;
            }

            return {
                value:row.ref,
                text:row.supplier_code + ' - ' + row.supplier_name +
                    (row.mobile ? ' | ' + row.mobile : '')
            };
        });

        /*
         * Supplier refs are encrypted and may be different each time the API
         * response is generated. Do not try to restore selection by comparing
         * the previous encrypted ref with the new option refs.
         *
         * The API marks the current supplier with selected=true, then we select
         * that newly generated ref and refresh the searchable GlobalSelect UI.
         */
        if(supplierSelect && typeof supplierSelect.setOptions === 'function'){
            supplierSelect.setOptions(items,selectedValue);
        }else{
            var select = el('supplierRef');
            select.innerHTML = '<option value="">Select Supplier</option>';

            rows.forEach(function(row){
                var option = document.createElement('option');
                option.value = row.ref;
                option.textContent = row.supplier_code + ' - ' + row.supplier_name +
                    (row.mobile ? ' | ' + row.mobile : '');
                if(row.selected){
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        if(selectedValue){
            initialSupplierRef = '';
        }
    }

    function renderSupplier(supplier){
        if(!supplier){
            el('supplierIdentity').hidden = true;
            return;
        }

        el('supplierIdentity').hidden = false;
        el('supplierName').textContent = supplier.supplier_name || '-';
        el('supplierCode').textContent = supplier.supplier_code || '-';
        el('supplierMobile').textContent = supplier.mobile || '-';
        el('supplierEmail').textContent = supplier.email || '-';
        el('supplierGstin').textContent = supplier.gstin || '-';
        el('supplierPan').textContent = supplier.pan || '-';
    }

    function renderSummary(data){
        data = data || {};

        var current = data.current || {};
        var period = data.period || {};

        el('kpiOpeningPending').textContent = money(current.opening_pending);
        el('kpiPurchaseOutstanding').textContent = money(current.purchase_outstanding);
        el('kpiOverallOutstanding').textContent = money(current.overall_outstanding);
        el('supplierCurrentPayable').textContent = money(current.overall_outstanding);

        el('kpiBroughtForward').textContent = money(period.opening_brought_forward);
        el('kpiPeriodPurchases').textContent = money(period.purchases);
        el('kpiActualPayments').textContent = money(period.actual_payments);
        el('kpiDiscounts').textContent = money(period.discount_settlement);
        el('kpiClosing').textContent = money(period.closing_balance);

        el('kpiBroughtForwardMeta').textContent = el('dateFrom').value
            ? 'Brought forward'
            : 'Supplier opening balance';
    }

    function pendingStatus(pending,total){
        pending = n(pending);
        total = n(total);

        if(pending <= 0.009){
            return '<span class="pill active">Paid</span>';
        }

        if(pending + 0.009 < total){
            return '<span class="pill pending">Partial</span>';
        }

        return '<span class="pill inactive">Unpaid</span>';
    }

    function renderPending(rows){
        var body = el('pendingPurchaseBody');
        rows = rows || [];

        if(!el('supplierRef').value){
            body.innerHTML = '<tr><td colspan="8" class="empty">Select a Supplier.</td></tr>';
            return;
        }

        if(!rows.length){
            body.innerHTML = '<tr><td colspan="8" class="empty">No pending Purchase / Invoice found.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function(row){
            var action = has(paymentActions,2)
                ? '<a class="table-icon-action" href="' + esc(row.pay_url) + '" title="Pay Invoice" aria-label="Pay Invoice"><i data-lucide="hand-coins"></i></a>'
                : '<span class="muted">-</span>';

            return '<tr>' +
                '<td><strong>' + esc(row.purchase_no || '-') + '</strong></td>' +
                '<td>' + esc(formatDate(row.purchase_date)) + '</td>' +
                '<td>' + esc(row.supplier_invoice_no || '-') + '</td>' +
                '<td class="dt-body-right">' + money(row.grand_total) + '</td>' +
                '<td class="dt-body-right">' + money(row.settled_amount) + '</td>' +
                '<td class="dt-body-right"><strong>' + money(row.pending_amount) + '</strong></td>' +
                '<td>' + pendingStatus(row.pending_amount,row.grand_total) + '</td>' +
                '<td class="table-action-icons">' + action + '</td>' +
            '</tr>';
        }).join('');

        if(window.lucide){
            window.lucide.createIcons();
        }
    }

    if(window.GlobalSelect){
        supplierSelect = GlobalSelect.init(el('supplierRef'),{
            placeholder:'Select Supplier'
        });
    }

    var table = AppDataTable.init('#supplierLedgerTable',{
        serverSide:true,
        searching:true,
        appSearch:false,
        pageLength:25,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        ordering:false,
        scrollX:true,
        autoWidth:false,
        buttons:[
            {
                extend:'copyHtml5',
                text:'Copy',
                title:'Supplier Ledger',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
            },
            {
                extend:'csvHtml5',
                text:'CSV',
                title:'Supplier Ledger',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
            },
            {
                extend:'excelHtml5',
                text:'Excel',
                title:'Supplier Ledger',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
            },
            {
                extend:'pdfHtml5',
                text:'PDF',
                title:'Supplier Ledger',
                orientation:'landscape',
                pageSize:'A4',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
            },
            {
                extend:'print',
                text:'Print',
                title:'Supplier Ledger',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
            }
        ],

        ajax:function(data,callback){
            var params = new URLSearchParams();

            params.set('datatable','1');
            params.set('draw',data.draw);
            params.set('start',data.start);
            params.set('length',data.length);
            params.set('search[value]',data.search.value || '');

            var supplier = el('supplierRef').value || initialSupplierRef;
            var from = el('dateFrom').value;
            var to = el('dateTo').value;
            var type = el('transactionType').value;

            if(supplier) params.set('supplier_ref',supplier);
            if(from) params.set('date_from',from);
            if(to) params.set('date_to',to);
            if(type) params.set('transaction_type',type);

            App.api('api/supplier-ledger.php?' + params.toString())
                .then(function(result){
                    fillSuppliers(result.data.suppliers || []);
                    paymentActions = (result.data.payment_actions || []).map(Number);

                    renderSupplier(result.data.supplier || null);
                    renderSummary(result.data.summary || {});
                    renderPending(result.data.pending_purchases || []);

                    AppDataTable.applyExportPermissions(
                        table,
                        result.data.allowed_actions || []
                    );

                    callback(
                        result.data.datatable || {
                            draw:data.draw,
                            recordsTotal:0,
                            recordsFiltered:0,
                            data:[]
                        }
                    );
                })
                .catch(function(error){
                    renderSupplier(null);
                    renderSummary({});
                    renderPending([]);

                    App.showError(error,'Unable to load Supplier Ledger.');

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
                data:'date',
                defaultContent:'-',
                render:function(value,type){
                    return type === 'display' ? formatDate(value) : value;
                }
            },
            {
                data:'reference',
                defaultContent:'-',
                render:function(value,type,row){
                    if(type !== 'display') return value;
                    if(row.row_type === 'opening_balance' || row.row_type === 'closing_balance'){
                        return '<strong>' + esc(value || '-') + '</strong>';
                    }
                    return '<strong>' + esc(value || '-') + '</strong>';
                }
            },
            {
                data:'transaction_label',
                defaultContent:'-',
                render:function(value,type,row){
                    if(type !== 'display') return value;
                    return (row.row_type === 'opening_balance' || row.row_type === 'closing_balance')
                        ? '<strong>' + esc(value || '-') + '</strong>'
                        : esc(value || '-');
                }
            },
            {
                data:'against',
                defaultContent:'-',
                render:function(value,type,row){
                    if(type !== 'display') return value;

                    var html = esc(value || '-');

                    if(row.row_type === 'opening_balance' || row.row_type === 'closing_balance'){
                        html = '<strong>' + html + '</strong>';
                    }

                    if(row.description){
                        html += '<br><span class="muted">' + esc(row.description) + '</span>';
                    }

                    return html;
                }
            },
            {
                data:'mode_label',
                defaultContent:'-'
            },
            {
                data:'debit',
                className:'dt-body-right',
                render:function(value,type){
                    return type === 'display'
                        ? (n(value) > 0 ? money(value) : '-')
                        : n(value);
                }
            },
            {
                data:'actual_payment',
                className:'dt-body-right',
                render:function(value,type){
                    return type === 'display'
                        ? (n(value) > 0 ? money(value) : '-')
                        : n(value);
                }
            },
            {
                data:'discount',
                className:'dt-body-right',
                render:function(value,type){
                    return type === 'display'
                        ? (n(value) > 0 ? money(value) : '-')
                        : n(value);
                }
            },
            {
                data:'credit',
                className:'dt-body-right',
                render:function(value,type){
                    return type === 'display'
                        ? (n(value) > 0 ? money(value) : '-')
                        : n(value);
                }
            },
            {
                data:'running_balance',
                className:'dt-body-right',
                render:function(value,type,row){
                    if(type !== 'display') return n(value);
                    var html = '<strong>' + money(value) + '</strong>';
                    if(row.row_type === 'opening_balance'){
                        html += '<br><span class="muted">Opening</span>';
                    }else if(row.row_type === 'closing_balance'){
                        html += '<br><span class="muted">Closing</span>';
                    }
                    return html;
                }
            }
        ],

        language:{
            emptyTable:'Select a Supplier to view the ledger.',
            zeroRecords:'No matching Supplier Ledger transactions found.'
        },

        drawCallback:function(){
            if(window.lucide){
                window.lucide.createIcons();
            }
        }
    });

    (function removeDefaultSearchRow(){
        var tableElement = el('supplierLedgerTable');
        var card = tableElement ? tableElement.closest('.table-card') : null;
        var row = card ? card.querySelector('.app-table-search-row') : null;

        if(row){
            row.remove();
        }
    })();

    function reload(){
        table.ajax.reload(null,true);
    }

    el('supplierRef').addEventListener('change',reload);

    ['dateFrom','dateTo','transactionType'].forEach(function(id){
        el(id).addEventListener('change',reload);
    });

    el('ledgerSearch').addEventListener('input',function(){
        var input = this;

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(input.value.trim()).draw();
        },350);
    });

    if(window.lucide){
        window.lucide.createIcons();
    }
})(jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>
</body>
</html>
