<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Ledger';
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
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
<main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
<section class="page-content" id="customerLedgerApp">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-heading">
    <div>
        <h1>Customer Ledger</h1>
        <p>Opening balance, posted Sales Invoices, Customer Payments and settlement discounts.</p>
    </div>
    <div class="heading-actions">
        <button class="btn gray" id="printButton" type="button"><i data-lucide="printer"></i>Print</button>
        <button class="btn btn-primary" id="refreshButton" type="button"><i data-lucide="refresh-cw"></i>Refresh</button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="form-row">
            <div class="field col-6">
                <label for="customerRef" class="required">Customer</label>
                <select class="select" id="customerRef"><option value="">Select Customer</option></select>
            </div>
            <div class="field col-2">
                <label for="fromDate">From Date</label>
                <input class="input" id="fromDate" type="date">
            </div>
            <div class="field col-2">
                <label for="toDate">To Date</label>
                <input class="input" id="toDate" type="date">
            </div>
            <div class="field col-2">
                <label>&nbsp;</label>
                <button class="btn btn-primary" id="applyButton" type="button"><i data-lucide="filter"></i>Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="card" id="customerCard" hidden>
    <div class="card-body">
        <div class="form-row">
            <div class="field col-3"><label>Customer Code</label><input class="input" id="customerCode" type="text" readonly></div>
            <div class="field col-3"><label>Customer Name</label><input class="input" id="customerName" type="text" readonly></div>
            <div class="field col-3"><label>Mobile</label><input class="input" id="customerMobile" type="text" readonly></div>
            <div class="field col-3"><label>Line</label><input class="input" id="customerLine" type="text" readonly></div>
        </div>
    </div>
</div>

<div class="kpi-grid" id="summaryGrid" hidden>
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="history"></i></div>
        <div><div class="kpi-label">Opening B/F</div><div class="kpi-value" id="openingBf">₹0.00</div><div class="kpi-meta">Before selected From Date</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="arrow-up-right"></i></div>
        <div><div class="kpi-label">Period Debit</div><div class="kpi-value" id="periodDebit">₹0.00</div><div class="kpi-meta">Posted invoices</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="arrow-down-left"></i></div>
        <div><div class="kpi-label">Period Credit</div><div class="kpi-value" id="periodCredit">₹0.00</div><div class="kpi-meta">Payments + settlement discount</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="scale"></i></div>
        <div><div class="kpi-label">Closing Balance</div><div class="kpi-value" id="closingBalance">₹0.00</div><div class="kpi-meta" id="closingType">Settled</div></div>
    </div>
</div>

<div class="card" id="canSummaryCard" hidden>
    <div class="card-header">
        <div>
            <h2>Can Balance Summary</h2>
            <p class="muted">Reusable can movement details.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="field col-3">
                <label>Opening Can</label>
                <input class="input" id="openingCan" type="text" readonly>
            </div>
            <div class="field col-3">
                <label>Delivered Can</label>
                <input class="input" id="deliveredCan" type="text" readonly>
            </div>
            <div class="field col-3">
                <label>Returned Can</label>
                <input class="input" id="returnedCan" type="text" readonly>
            </div>
            <div class="field col-3">
                <label>Pending Can</label>
                <input class="input" id="pendingCan" type="text" readonly>
            </div>
        </div>
    </div>
</div>

<div class="app-split-grid" id="positionGrid" hidden>
    <div class="card">
        <div class="card-header"><div><h2>Current Position</h2><p class="muted">Overall customer financial position as of now.</p></div></div>
        <div class="card-body">
            <div class="app-summary-row"><span>Opening Balance</span><strong id="currentOpening">₹0.00</strong></div>
            <div class="app-summary-row"><span>Posted Invoice Total</span><strong id="currentInvoices">₹0.00</strong></div>
            <div class="app-summary-row"><span>Customer Payments</span><strong id="currentPayments">₹0.00</strong></div>
            <div class="app-summary-row"><span>Settlement Discount</span><strong id="currentDiscount">₹0.00</strong></div>
            <div class="app-summary-row total"><span>Current Net Balance</span><strong id="currentNet">₹0.00</strong></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><div><h2>Receivable Summary</h2><p class="muted">Positive balance is Due; negative balance is Customer Advance.</p></div></div>
        <div class="card-body">
            <div class="app-summary-row"><span>Invoice Outstanding</span><strong id="invoiceOutstanding">₹0.00</strong></div>
            <div class="app-summary-row"><span>Total Due</span><strong id="totalDue">₹0.00</strong></div>
            <div class="app-summary-row"><span>Customer Advance</span><strong id="customerAdvance">₹0.00</strong></div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div><h2>Ledger Transactions</h2><p class="muted" id="periodLabel">Select Customer to load ledger.</p></div>
    </div>
    <div class="app-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Particulars</th>
                    <th>Reference</th>
                    <th class="dt-body-right">Debit</th>
                    <th class="dt-body-right">Credit</th>
                    <th class="dt-body-right">Balance</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody id="ledgerBody"><tr><td colspan="8" class="empty">Select Customer to load ledger.</td></tr></tbody>
        </table>
    </div>
</div>

<script>
(function (window, document) {
    "use strict";

    var state = { customers: [], selectedRef: "" };
    var customerSelect = GlobalSelect.init("#customerRef", { placeholder: "Select Customer" });

    function byId(id) { return document.getElementById(id); }
    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;");
    }
    function num(value) { var n = Number(value || 0); return isFinite(n) ? n : 0; }
    function money(value) { return "₹" + Math.abs(num(value)).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function balanceText(value) {
        value = num(value);
        if (value > 0.009) return money(value) + " Due";
        if (value < -0.009) return money(value) + " Advance";
        return "₹0.00 Settled";
    }
    function isoDate(date) {
        return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0") + "-" + String(date.getDate()).padStart(2, "0");
    }
    function reportError(error, fallback) {
        if (window.App && App.showError) App.showError(error, fallback);
        else window.alert((error && error.message) || fallback);
    }
    function refreshIcons() { if (window.lucide && lucide.createIcons) lucide.createIcons(); }
    function setDefaultDates(todayText) {
        var today = todayText ? new Date(todayText + "T00:00:00") : new Date();
        var first = new Date(today.getFullYear(), today.getMonth(), 1);
        if (!byId("fromDate").value) byId("fromDate").value = isoDate(first);
        if (!byId("toDate").value) byId("toDate").value = isoDate(today);
    }
    function customerOption(row) {
        var text = (row.customer_code ? row.customer_code + " - " : "") + row.customer_name;
        if (row.mobile) text += " - " + row.mobile;
        return { value: row.ref, text: text };
    }
    function renderRows(data) {
        var body = byId("ledgerBody");
        var rows = data.rows || [];
        var opening = num(data.opening_bf);
        var html = '<tr>' +
            '<td>1</td><td>' + esc(data.period.from_date) + '</td><td><strong>Balance B/F</strong></td><td>-</td>' +
            '<td class="dt-body-right">' + (opening > 0 ? money(opening) : "-") + '</td>' +
            '<td class="dt-body-right">' + (opening < 0 ? money(opening) : "-") + '</td>' +
            '<td class="dt-body-right"><strong>' + esc(balanceText(opening)) + '</strong></td><td>Balance brought forward</td></tr>';
        rows.forEach(function (row, index) {
            var ref = row.reference_url
                ? '<a href="' + esc(row.reference_url) + '">' + esc(row.reference) + '</a>'
                : esc(row.reference || "-");
            html += '<tr>' +
                '<td>' + (index + 2) + '</td>' +
                '<td>' + esc(row.date) + '</td>' +
                '<td>' + esc(row.type) + '</td>' +
                '<td>' + ref + '</td>' +
                '<td class="dt-body-right">' + (num(row.debit) > 0 ? money(row.debit) : "-") + '</td>' +
                '<td class="dt-body-right">' + (num(row.credit) > 0 ? money(row.credit) : "-") + '</td>' +
                '<td class="dt-body-right"><strong>' + esc(balanceText(row.balance)) + '</strong></td>' +
                '<td>' + esc(row.remarks || "") + '</td>' +
                '</tr>';
        });
        body.innerHTML = html;
    }
    function render(data) {
        var customer = data.customer || {};
        byId("customerCard").hidden = false;
        byId("summaryGrid").hidden = false;
        byId("positionGrid").hidden = false;
        byId("customerCode").value = customer.customer_code || "";
        byId("customerName").value = customer.customer_name || "";
        byId("customerMobile").value = customer.mobile || "";
        byId("customerLine").value = customer.line_name || "";
        byId("openingBf").textContent = balanceText(data.opening_bf);
        byId("periodDebit").textContent = money(data.period_debit);
        byId("periodCredit").textContent = money(data.period_credit);
        byId("closingBalance").textContent = money(data.closing_balance);
        byId("closingType").textContent = data.closing_type || "Settled";
        byId("periodLabel").textContent = data.period.from_date + " to " + data.period.to_date;

        var c = data.current || {};
        byId("currentOpening").textContent = money(c.opening_balance);
        byId("currentInvoices").textContent = money(c.invoice_total);
        byId("currentPayments").textContent = money(c.payments);
        byId("currentDiscount").textContent = money(c.settlement_discount);
        byId("currentNet").textContent = balanceText(c.ledger_balance);
        byId("invoiceOutstanding").textContent = money(c.invoice_outstanding);
        byId("totalDue").textContent = money(c.due);
        byId("customerAdvance").textContent = money(c.advance);

        var can = data.can_summary || {};
        if (byId("canSummaryCard")) {
            byId("canSummaryCard").hidden = false;
            byId("openingCan").value = Number(can.opening_can || 0).toFixed(3);
            byId("deliveredCan").value = Number(can.delivered_can || 0).toFixed(3);
            byId("returnedCan").value = Number(can.returned_can || 0).toFixed(3);
            byId("pendingCan").value = Number(can.pending_can || 0).toFixed(3);
        }

        renderRows(data);
        refreshIcons();
    }
    async function loadLedger() {
        var ref = byId("customerRef").value || "";
        if (!ref) {
            byId("ledgerBody").innerHTML = '<tr><td colspan="8" class="empty">Select Customer to load ledger.</td></tr>';
            return;
        }
        var from = byId("fromDate").value || "";
        var to = byId("toDate").value || "";
        try {
            var result = await App.api("api/customer-ledger.php?customer_ref=" + encodeURIComponent(ref) + "&from_date=" + encodeURIComponent(from) + "&to_date=" + encodeURIComponent(to));
            render(result.data || {});
        } catch (error) {
            reportError(error, "Unable to load Customer Ledger.");
        }
    }
    async function init() {
        try {
            var result = await App.api("api/customer-ledger.php?options=1");
            var data = result.data || {};
            state.customers = data.customers || [];
            setDefaultDates(data.today || "");
            var options = state.customers.map(customerOption);
            var urlRef = new URLSearchParams(window.location.search).get("ref") || "";
            var selected = urlRef && options.some(function (x) { return x.value === urlRef; }) ? urlRef : "";
            customerSelect.setOptions(options, selected);
            byId("customerRef").value = selected;
            if (selected) await loadLedger();
            refreshIcons();
        } catch (error) {
            reportError(error, "Unable to load Customer Ledger options.");
        }
    }

    byId("customerRef").addEventListener("change", loadLedger);
    byId("applyButton").addEventListener("click", loadLedger);
    byId("refreshButton").addEventListener("click", loadLedger);
    byId("printButton").addEventListener("click", function () { window.print(); });
    init();
})(window, document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
</body>
</html>