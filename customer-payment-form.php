<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Payment';
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
<section class="page-content" id="customerPaymentApp">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-heading">
    <div>
        <h1 id="pageHeading">Customer Payment</h1>
        <p>Opening Balance and posted Sales Invoices are allocated automatically by FIFO.</p>
    </div>
    <div class="heading-actions">
        <a class="btn gray" href="customer-payment-list.php"><i data-lucide="list"></i>Payment List</a>
        <a class="btn gray" href="sales-list.php"><i data-lucide="receipt-text"></i>Sales</a>
    </div>
</div>

<form id="paymentForm" novalidate>
    <div class="card">
        <div class="card-header">
            <div>
                <h2>Payment Details</h2>
                <p id="formStatusText">New Customer Payment</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="paymentNo">Receipt No</label>
                    <input class="input" id="paymentNo" type="text" readonly placeholder="Auto generated">
                </div>
                <div class="field col-3">
                    <label for="paymentDate" class="required">Date</label>
                    <input class="input" id="paymentDate" type="date" required>
                </div>
                <div class="field col-6">
                    <label for="customerId" class="required">Customer</label>
                    <select class="select" id="customerId" required><option value="">Select Customer</option></select>
                </div>
            </div>
            <div class="form-row">
                <div class="field col-3">
                    <label for="discountType">Discount Type</label>
                    <select class="select" id="discountType">
                        <option value="1">None</option>
                        <option value="2">Percentage</option>
                        <option value="3">Amount</option>
                    </select>
                </div>
                <div class="field col-3">
                    <label for="discountValue">Discount Value</label>
                    <input class="input" id="discountValue" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00">
                </div>
                <div class="field col-6">
                    <label for="remarks">Remarks</label>
                    <input class="input" id="remarks" type="text" maxlength="255" placeholder="Optional">
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="sourceInvoiceCard" hidden>
        <div class="card-header">
            <div>
                <h2>Opened From Sales Invoice</h2>
                <p>The Customer is loaded from the encrypted Sales reference. Payment still follows overall FIFO.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-3"><label>Invoice No</label><input class="input" id="sourceSaleNo" type="text" readonly></div>
                <div class="field col-3"><label>Invoice Date</label><input class="input" id="sourceSaleDate" type="text" readonly></div>
                <div class="field col-3"><label>Invoice Total</label><input class="input" id="sourceSaleTotal" type="text" readonly></div>
                <div class="field col-3"><label>Invoice Balance</label><input class="input" id="sourceSaleBalance" type="text" readonly></div>
            </div>
        </div>
    </div>

    <div class="app-split-grid">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Payment</h2>
                    <p>Cash, UPI, Bank and Cheque are shown initially. Enter Amount only for the modes received.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="app-table-wrap">
                    <table class="app-editable-table" id="paymentRowsTable">
                        <thead>
                            <tr>
                                <th class="cell-main">Mode</th>
                                <th class="cell-main">Account</th>
                                <th class="cell-rate">Amount</th>
                                <th class="cell-main">Reference No</th>
                                <th class="cell-date">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-payment-mode="1">
                                <td><strong>Cash</strong></td>
                                <td><select class="select pay-account"><option value="">Select Cash Account</option></select></td>
                                <td><input class="input pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                                <td><input class="input pay-reference" type="text" maxlength="100" placeholder="Optional"></td>
                                <td><input class="input pay-date" type="date"></td>
                            </tr>
                            <tr data-payment-mode="2">
                                <td><strong>UPI</strong></td>
                                <td><select class="select pay-account"><option value="">Select UPI Account</option></select></td>
                                <td><input class="input pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                                <td><input class="input pay-reference" type="text" maxlength="100" placeholder="UTR / Ref No"></td>
                                <td><input class="input pay-date" type="date"></td>
                            </tr>
                            <tr data-payment-mode="3">
                                <td><strong>Bank</strong></td>
                                <td><select class="select pay-account"><option value="">Select Bank Account</option></select></td>
                                <td><input class="input pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                                <td><input class="input pay-reference" type="text" maxlength="100" placeholder="Transaction / Ref No"></td>
                                <td><input class="input pay-date" type="date"></td>
                            </tr>
                            <tr data-payment-mode="4">
                                <td><strong>Cheque</strong></td>
                                <td><select class="select pay-account"><option value="">Select Bank Account</option></select></td>
                                <td><input class="input pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                                <td><input class="input pay-reference" type="text" maxlength="100" placeholder="Cheque No"></td>
                                <td><input class="input pay-date" type="date"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="app-side-card">
            <div class="app-side-card-head">
                <div>
                    <h2>Customer Summary</h2>
                    <p id="summaryCustomerName">Select Customer</p>
                </div>
            </div>
            <div class="app-side-card-body">
                <div class="app-summary-row"><span>Opening Balance</span><strong id="openingOriginal">₹0.00</strong></div>
                <div class="app-summary-row"><span>Opening Due</span><strong id="openingDue">₹0.00</strong></div>
                <div class="app-summary-row"><span>Invoice Outstanding</span><strong id="invoiceOutstanding">₹0.00</strong></div>
                <div class="app-summary-row"><span>Existing Advance</span><strong id="existingAdvance">₹0.00</strong></div>
                <div class="app-summary-row" id="reservedOrderRow" hidden><span>Reserved Order Advance</span><strong id="reservedOrderAdvance">₹0.00</strong></div>
                <div class="app-summary-row total"><span>Current Outstanding</span><strong id="currentOutstanding">₹0.00</strong></div>
                <div class="app-summary-row"><span>This Payment</span><strong id="thisPayment">₹0.00</strong></div>
                <div class="app-summary-row"><span>Settlement Discount</span><strong id="thisDiscount">₹0.00</strong></div>
                <div class="app-summary-row total"><span>Total Settlement</span><strong id="totalSettlement">₹0.00</strong></div>
                <div class="app-summary-row total"><span>After Settlement Due</span><strong id="afterPaymentDue">₹0.00</strong></div>
                <div class="app-summary-row"><span>Advance After Payment</span><strong id="afterPaymentAdvance">₹0.00</strong></div>
            </div>
        </div>
    </div>

    <div class="card" id="fifoCard">
        <div class="card-header">
            <div>
                <h2>FIFO Allocation Preview</h2>
                <p>Opening Balance first, then oldest posted Invoice to newest.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="app-table-wrap">
                <table class="app-editable-table">
                    <thead>
                        <tr>
                            <th class="cell-main">Source</th>
                            <th class="cell-date">Date</th>
                            <th class="cell-amount">Original</th>
                            <th class="cell-amount">Already Paid</th>
                            <th class="cell-amount">This Settlement</th>
                            <th class="cell-amount">Balance</th>
                        </tr>
                    </thead>
                    <tbody id="fifoBody"><tr><td class="empty" colspan="6">Select Customer to view FIFO allocation.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="app-summary-actions">
        <button class="btn btn-danger" type="button" id="cancelPaymentButton" hidden><i data-lucide="trash-2"></i>Delete / Cancel Payment</button>
        <a class="btn gray" href="customer-payment-list.php"><i data-lucide="x"></i>Close</a>
        <button class="btn btn-primary" type="submit" id="savePaymentButton"><i data-lucide="save"></i><span id="savePaymentText">Save Payment</span></button>
    </div>
</form>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
(function (window, document) {
    "use strict";

    var params = new URLSearchParams(window.location.search);
    var paymentRef = params.get("ref") || "";
    var saleRef = params.get("sale") || "";

    var ACTION_UPDATE = 3;
    var ACTION_CANCEL = 14;
    var ACTION_RECEIVE_PAYMENT = 29;

    var state = {
        actions: [],
        customers: [],
        accounts: [],
        payment: null,
        selectedSale: null,
        summary: null,
        reservedCurrent: 0,
        locked: false,
        lastHeaderDate: ""
    };

    var customerSelect = GlobalSelect.init("#customerId", { placeholder: "Select Customer" });

    function byId(id) { return document.getElementById(id); }
    function hasAction(id) { return state.actions.indexOf(Number(id)) !== -1; }
    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
    function num(value) {
        var n = parseFloat(String(value == null ? "" : value).replace(/,/g, ""));
        return isFinite(n) ? n : 0;
    }
    function money(value) {
        return "₹" + num(value).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function reportError(error, fallback) {
        if (window.App && App.showError) App.showError(error, fallback);
        else window.alert((error && error.message) || fallback);
    }
    function success(message) {
        if (window.showToast) showToast(message, "success");
        else window.alert(message);
    }
    function refreshIcons() {
        if (window.lucide && lucide.createIcons) lucide.createIcons();
    }
    function customerText(row) {
        return (row.customer_code ? row.customer_code + " - " : "") + row.customer_name + (row.mobile ? " - " + row.mobile : "");
    }
    function customerOptions(rows) {
        return (rows || []).map(function (row) { return { value: String(row.id), text: customerText(row) }; });
    }
    function paymentRows() {
        return Array.prototype.slice.call(document.querySelectorAll("#paymentRowsTable tbody tr[data-payment-mode]"));
    }
    function accountModesFor(account) {
        return (account.payment_modes || []).map(Number);
    }
    function accountPlaceholder(mode) {
        return Number(mode) === 1 ? "Select Cash Account" : (Number(mode) === 2 ? "Select UPI Account" : "Select Bank Account");
    }
    function populateAccounts() {
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode"));
            var select = row.querySelector(".pay-account");
            var selected = select.value;
            var options = state.accounts.filter(function (account) { return accountModesFor(account).indexOf(mode) !== -1; });
            select.innerHTML = '<option value="">' + accountPlaceholder(mode) + '</option>' + options.map(function (account) {
                return '<option value="' + String(account.id) + '">' + esc(account.account_name) + '</option>';
            }).join("");
            if (selected && options.some(function (account) { return String(account.id) === String(selected); })) select.value = selected;
            else if (options.length === 1) select.value = String(options[0].id);
        });
    }
    function setPaymentRow(mode, data) {
        var row = document.querySelector('#paymentRowsTable tbody tr[data-payment-mode="' + String(mode) + '"]');
        if (!row) return;
        var account = row.querySelector(".pay-account");
        var amount = row.querySelector(".pay-amount");
        var reference = row.querySelector(".pay-reference");
        var date = row.querySelector(".pay-date");
        if (data && data.account_id) account.value = String(data.account_id);
        amount.value = data && Number(data.amount || 0) > 0 ? Number(data.amount).toFixed(2) : "";
        reference.value = data && data.reference_no ? data.reference_no : "";
        date.value = data && data.detail_date ? data.detail_date : (byId("paymentDate").value || "");
    }
    function fillPaymentRows(details) {
        var map = {};
        (details || []).forEach(function (row) { map[String(row.payment_mode)] = row; });
        paymentRows().forEach(function (row) {
            var mode = row.getAttribute("data-payment-mode");
            setPaymentRow(mode, map[mode] || null);
        });
    }
    function collectRows() {
        return paymentRows().map(function (row) {
            return {
                payment_mode: Number(row.getAttribute("data-payment-mode")),
                account_id: Number(row.querySelector(".pay-account").value || 0),
                amount: row.querySelector(".pay-amount").value.trim(),
                reference_no: row.querySelector(".pay-reference").value.trim(),
                detail_date: row.querySelector(".pay-date").value || byId("paymentDate").value
            };
        });
    }
    function paymentTotal() {
        return collectRows().reduce(function (sum, row) { return sum + num(row.amount); }, 0);
    }
    function discountType() {
        return Number(byId("discountType").value || 1);
    }
    function discountValue() {
        return Math.max(0, num(byId("discountValue").value));
    }
    function effectiveDiscount(currentOutstanding, usablePayment) {
        var type = discountType();
        var value = discountValue();
        currentOutstanding = Math.max(0, num(currentOutstanding));
        usablePayment = Math.max(0, num(usablePayment));
        var remaining = Math.max(0, currentOutstanding - Math.min(usablePayment, currentOutstanding));
        if (remaining <= 0.009 || type === 1 || value <= 0) return 0;
        var raw = type === 2 ? (currentOutstanding * value / 100) : value;
        return Math.max(0, Math.min(raw, remaining));
    }
    function syncDiscountControl() {
        var type = discountType();
        var input = byId("discountValue");
        if (type === 1) {
            input.value = "";
            input.disabled = true;
        } else {
            input.disabled = state.locked;
            input.placeholder = type === 2 ? "0.00 %" : "0.00";
        }
    }
    function currentCustomer() {
        var id = String(byId("customerId").value || "");
        return state.customers.find(function (row) { return String(row.id) === id; }) || null;
    }
    function summaryReceivable(type) {
        if (!state.summary) return null;
        return (state.summary.receivables || []).find(function (row) { return row.source_type === type; }) || null;
    }
    function invoiceOutstandingFromSummary() {
        if (!state.summary) return 0;
        return (state.summary.receivables || []).filter(function (row) { return row.source_type === "invoice"; })
            .reduce(function (sum, row) { return sum + num(row.balance); }, 0);
    }
    function renderSummaryAndPreview() {
        var customer = currentCustomer();
        var summary = state.summary;
        var total = paymentTotal();
        var reserved = num(state.reservedCurrent);
        var usablePayment = Math.max(0, total - reserved);

        byId("summaryCustomerName").textContent = customer ? customerText(customer) : "Select Customer";
        byId("thisPayment").textContent = money(total);
        byId("reservedOrderAdvance").textContent = money(reserved);
        byId("reservedOrderRow").hidden = reserved <= 0.009;

        if (!summary) {
            ["openingOriginal","openingDue","invoiceOutstanding","existingAdvance","currentOutstanding","thisDiscount","totalSettlement","afterPaymentDue","afterPaymentAdvance"].forEach(function (id) {
                byId(id).textContent = money(0);
            });
            byId("fifoBody").innerHTML = '<tr><td class="empty" colspan="6">Select Customer to view FIFO allocation.</td></tr>';
            return;
        }

        var opening = summaryReceivable("opening");
        var openingOriginal = num(summary.opening_original || (opening ? opening.original : 0));
        var openingDue = opening ? num(opening.balance) : 0;
        var invoiceDue = invoiceOutstandingFromSummary();
        var currentOutstanding = num(summary.current_outstanding);
        var existingAdvance = num(summary.existing_advance);
        var discount = effectiveDiscount(currentOutstanding, usablePayment);

        byId("openingOriginal").textContent = money(openingOriginal);
        byId("openingDue").textContent = money(openingDue);
        byId("invoiceOutstanding").textContent = money(invoiceDue);
        byId("existingAdvance").textContent = money(existingAdvance);
        byId("currentOutstanding").textContent = money(currentOutstanding);
        byId("thisDiscount").textContent = money(discount);
        byId("totalSettlement").textContent = money(total + discount);

        var cashAvailable = usablePayment;
        var discountAvailable = discount;
        var rows = [];
        (summary.receivables || []).forEach(function (source) {
            var due = num(source.balance);
            var cashApplied = Math.min(cashAvailable, due);
            cashAvailable = Math.max(0, cashAvailable - cashApplied);
            var remainingDue = Math.max(0, due - cashApplied);
            var discountApplied = Math.min(discountAvailable, remainingDue);
            discountAvailable = Math.max(0, discountAvailable - discountApplied);
            var applied = cashApplied + discountApplied;
            var finalBalance = Math.max(0, due - applied);
            var isSource = state.selectedSale && source.sale_ref && String(source.sale_ref) === String(state.selectedSale.ref);
            rows.push(
                '<tr>' +
                '<td>' + esc(source.sale_no || "") + (isSource ? ' <span class="pill pending">Opened Invoice</span>' : '') + '</td>' +
                '<td>' + esc(source.date || "—") + '</td>' +
                '<td class="cell-amount">' + money(source.original) + '</td>' +
                '<td class="cell-amount">' + money(source.already_paid) + '</td>' +
                '<td class="cell-amount">' + money(applied) + '</td>' +
                '<td class="cell-amount"><strong>' + money(finalBalance) + '</strong></td>' +
                '</tr>'
            );
        });
        if (!rows.length) rows.push('<tr><td class="empty" colspan="6">No Opening Balance or posted Invoice outstanding.</td></tr>');
        byId("fifoBody").innerHTML = rows.join("");

        var appliedCashAgainstDue = Math.min(usablePayment, currentOutstanding);
        var afterCashDue = Math.max(0, currentOutstanding - appliedCashAgainstDue);
        var afterDue = Math.max(0, afterCashDue - discount);
        var newAdvance = existingAdvance + Math.max(0, usablePayment - currentOutstanding);
        byId("afterPaymentDue").textContent = money(afterDue);
        byId("afterPaymentAdvance").textContent = money(newAdvance);
        refreshIcons();
    }

    async function loadSummary(customerId) {
        if (!customerId) {
            state.summary = null;
            renderSummaryAndPreview();
            return;
        }
        var query = new URLSearchParams();
        query.set("summary", "1");
        query.set("customer_id", String(customerId));
        if (paymentRef) query.set("exclude_ref", paymentRef);
        if (saleRef) query.set("sale", saleRef);
        try {
            var result = await App.api("api/customer-payments.php?" + query.toString());
            state.summary = result.data || null;
            if (result.data && result.data.selected_sale) state.selectedSale = result.data.selected_sale;
            renderSourceInvoice();
            renderSummaryAndPreview();
        } catch (error) {
            state.summary = null;
            reportError(error, "Unable to load Customer outstanding.");
            renderSummaryAndPreview();
        }
    }
    function renderSourceInvoice() {
        var sale = state.selectedSale;
        byId("sourceInvoiceCard").hidden = !sale;
        if (!sale) return;
        byId("sourceSaleNo").value = sale.sale_no || "";
        byId("sourceSaleDate").value = sale.sale_date || "";
        byId("sourceSaleTotal").value = money(sale.grand_total);
        byId("sourceSaleBalance").value = money(sale.balance_amount);
    }
    function setLockedState() {
        var editing = !!state.payment;
        state.locked = editing
            ? (!(hasAction(ACTION_UPDATE) && hasAction(ACTION_RECEIVE_PAYMENT)) || Number(state.payment.status) !== 1)
            : !hasAction(ACTION_RECEIVE_PAYMENT);

        byId("paymentDate").disabled = state.locked;
        byId("customerId").disabled = state.locked;
        byId("discountType").disabled = state.locked;
        byId("remarks").disabled = state.locked;
        paymentRows().forEach(function (row) {
            Array.prototype.forEach.call(row.querySelectorAll("input,select"), function (control) { control.disabled = state.locked; });
        });
        byId("savePaymentButton").hidden = state.locked;
        byId("cancelPaymentButton").hidden = !editing || Number(state.payment.status) !== 1 || !hasAction(ACTION_CANCEL) || !hasAction(ACTION_RECEIVE_PAYMENT);
        byId("formStatusText").textContent = editing
            ? (Number(state.payment.status) === 1 ? "Editing " + state.payment.payment_no : "Cancelled Payment - View Only")
            : "New Customer Payment";
        byId("savePaymentText").textContent = editing ? "Update Payment" : "Save Payment";
        syncDiscountControl();
    }
    async function bootstrap() {
        var query = new URLSearchParams();
        query.set("bootstrap", "1");
        if (paymentRef) query.set("ref", paymentRef);
        if (saleRef) query.set("sale", saleRef);
        try {
            var result = await App.api("api/customer-payments.php?" + query.toString());
            var data = result.data || {};
            state.actions = (data.allowed_actions || []).map(Number);
            state.customers = data.customers || [];
            state.accounts = data.accounts || [];
            state.payment = data.payment || null;
            state.selectedSale = data.selected_sale || null;
            state.summary = data.summary || null;
            state.reservedCurrent = state.payment ? num(state.payment.reserved_order_advance) : 0;

            var selectedCustomer = state.payment ? state.payment.customer_id : (state.selectedSale ? state.selectedSale.customer_id : "");
            customerSelect.setOptions(customerOptions(state.customers), selectedCustomer ? String(selectedCustomer) : "");
            byId("customerId").value = selectedCustomer ? String(selectedCustomer) : "";

            populateAccounts();
            var initialDate = state.payment ? state.payment.payment_date : (data.today || "");
            byId("paymentDate").value = initialDate;
            state.lastHeaderDate = initialDate;
            byId("paymentNo").value = state.payment ? state.payment.payment_no : "";
            byId("discountType").value = state.payment ? String(state.payment.discount_type || 1) : "1";
            byId("discountValue").value = state.payment && Number(state.payment.discount_value || 0) > 0 ? Number(state.payment.discount_value).toFixed(2) : "";
            byId("remarks").value = state.payment && state.payment.remarks ? state.payment.remarks : "";
            fillPaymentRows(state.payment ? state.payment.details : []);
            if (!state.payment) {
                paymentRows().forEach(function (row) {
                    row.querySelector(".pay-date").value = initialDate;
                });
            }

            renderSourceInvoice();
            setLockedState();
            renderSummaryAndPreview();
            refreshIcons();
        } catch (error) {
            reportError(error, "Unable to load Customer Payment page.");
            byId("savePaymentButton").disabled = true;
        }
    }
    function validateBeforeSave() {
        if (!byId("paymentDate").value) {
            window.alert("Select Payment Date.");
            byId("paymentDate").focus();
            return false;
        }
        if (!byId("customerId").value) {
            window.alert("Select Customer.");
            return false;
        }
        var rows = collectRows();
        var total = rows.reduce(function (sum, row) { return sum + num(row.amount); }, 0);
        for (var i = 0; i < rows.length; i++) {
            if (num(rows[i].amount) > 0.009 && !rows[i].account_id) {
                window.alert("Select Account for the entered Payment mode.");
                return false;
            }
        }
        if (discountType() === 2 && discountValue() > 100) {
            window.alert("Discount Percentage cannot be greater than 100.");
            byId("discountValue").focus();
            return false;
        }
        var currentOutstanding = state.summary ? num(state.summary.current_outstanding) : 0;
        var usablePayment = Math.max(0, total - num(state.reservedCurrent));
        var discount = effectiveDiscount(currentOutstanding, usablePayment);
        if (total <= 0.009 && discount <= 0.009) {
            window.alert("Enter Payment Amount or Settlement Discount.");
            return false;
        }
        if (total + 0.009 < state.reservedCurrent) {
            window.alert("Payment Amount cannot be less than reserved Customer Order advance.");
            return false;
        }
        return true;
    }

    async function savePayment(event) {
        event.preventDefault();
        if (state.locked || !validateBeforeSave()) return;
        var button = byId("savePaymentButton");
        button.disabled = true;
        try {
            var result = await App.api("api/customer-payments.php", {
                method: "POST",
                body: {
                    action: "save",
                    ref: paymentRef || null,
                    payment_date: byId("paymentDate").value,
                    customer_id: Number(byId("customerId").value || 0),
                    discount_type: discountType(),
                    discount_value: byId("discountValue").value.trim(),
                    remarks: byId("remarks").value.trim(),
                    payments: collectRows()
                }
            });
            success(result.message || "Customer Payment saved.");
            var saved = result.data && result.data.payment ? result.data.payment : null;
            if (saved && saved.ref) {
                setTimeout(function () {
                    window.location.href = "customer-payment-form.php?ref=" + encodeURIComponent(saved.ref);
                }, 350);
            }
        } catch (error) {
            reportError(error, "Unable to save Customer Payment.");
        } finally {
            button.disabled = false;
        }
    }
    async function cancelPayment() {
        if (!paymentRef || !state.payment || Number(state.payment.status) !== 1) return;
        if (!window.confirm("Delete / cancel this Customer Payment? Account ledger and FIFO Invoice allocations will be recalculated.")) return;
        var button = byId("cancelPaymentButton");
        button.disabled = true;
        try {
            var result = await App.api("api/customer-payments.php", {
                method: "POST",
                body: { action: "cancel", ref: paymentRef }
            });
            success(result.message || "Customer Payment cancelled.");
            setTimeout(function () { window.location.href = "customer-payment-list.php"; }, 350);
        } catch (error) {
            reportError(error, "Unable to cancel Customer Payment.");
            button.disabled = false;
        }
    }

    byId("paymentForm").addEventListener("submit", savePayment);
    byId("cancelPaymentButton").addEventListener("click", cancelPayment);
    byId("customerId").addEventListener("change", function () {
        loadSummary(Number(byId("customerId").value || 0));
    });
    byId("paymentDate").addEventListener("change", function () {
        var next = byId("paymentDate").value;
        paymentRows().forEach(function (row) {
            var date = row.querySelector(".pay-date");
            if (!date.value || date.value === state.lastHeaderDate) date.value = next;
        });
        state.lastHeaderDate = next;
    });
    paymentRows().forEach(function (row) {
        row.querySelector(".pay-amount").addEventListener("input", renderSummaryAndPreview);
        row.querySelector(".pay-account").addEventListener("change", renderSummaryAndPreview);
        row.querySelector(".pay-reference").addEventListener("input", renderSummaryAndPreview);
        row.querySelector(".pay-date").addEventListener("change", renderSummaryAndPreview);
    });
    byId("discountType").addEventListener("change", function () {
        syncDiscountControl();
        renderSummaryAndPreview();
    });
    byId("discountValue").addEventListener("input", renderSummaryAndPreview);

    bootstrap();
})(window, document);
</script>
</body>
</html>
