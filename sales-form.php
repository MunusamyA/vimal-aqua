<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Sales';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
<title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
<?php render_frontend_config_script(); ?>
<script src="assets/js/runtime.js"></script>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<link rel="stylesheet" href="assets/css/sales-form.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body class="sales-pos-page">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="pos-shell" id="salesPosApp">
<header class="pos-topbar">
    <div class="pos-brand-block">
        <div class="pos-title-row">
            <h1>Sales</h1>
            <span class="pos-tax-badge" id="nonGstBadge" hidden>NON GST</span>
            <span class="pos-document-badge" id="documentBadge">New Document</span>
        </div>
        <div class="pos-current-document" id="currentDocumentLabel">Quotation / Order / Direct Sale / Line Supply Sale</div>
    </div>
    <div class="pos-top-actions">
        <a class="btn gray" href="sales-list.php"><i data-lucide="list"></i><span>Sales List</span></a>
        <a class="btn gray" href="line-supply-list.php"><i data-lucide="truck"></i><span>Line Supply</span></a>
        <button class="btn btn-danger" id="exitButton" type="button"><i data-lucide="log-out"></i><span>Exit</span></button>
    </div>
</header>

<main class="pos-content pos-fast-v2">
<form id="salesForm" novalidate>
<input id="saleRef" type="hidden">
<section class="pos-card pos-header-card" id="fastBillDetails">
    <div class="fast-section-title"><span class="fast-step">01</span><div><strong>Billing Details</strong><small>Choose sale type and customer</small></div><button class="fast-more-toggle" type="button" id="fastMoreToggle" aria-expanded="false" aria-controls="aquaHeaderGrid"><i data-lucide="sliders-horizontal"></i> More details</button></div>
    <div class="pos-form-grid aqua-header-grid" id="aquaHeaderGrid">
        <div class="field" id="saleModeField">
            <label for="saleMode" class="required">Sale Type</label>
            <select id="saleMode" required></select>
        </div>
        <div class="field mobile-hide-document-meta fast-extra-field">
            <label for="saleNo">Document No</label>
            <input id="saleNo" type="text" readonly placeholder="Auto generated">
        </div>
        <div class="field mobile-hide-document-meta fast-extra-field">
            <label for="saleDate" class="required">Date</label>
            <input id="saleDate" type="date" required value="<?php echo web_h(date('Y-m-d')); ?>">
        </div>
        <div class="field customer-field" id="customerField">
            <label for="customerId" class="required">Customer</label>
            <select id="customerId" required><option value="">Select Customer</option></select>
        </div>
        <div class="field remarks-field fast-extra-field" id="remarksField">
            <label for="remarks">Remarks</label>
            <input id="remarks" type="text" maxlength="255" placeholder="Optional">
        </div>
    </div>

    <div class="pos-form-grid aqua-line-grid" id="lineModeFields" hidden>
        <div class="field" id="lineField">
            <label for="lineId">Line <span class="muted">(Optional)</span></label>
            <select id="lineId"><option value="">Select Line</option></select>
        </div>
        <div class="field" id="vehicleField">
            <label for="vehicleId" class="required">Truck</label>
            <select id="vehicleId"><option value="">Select Truck</option></select>
        </div>
        <div class="line-trip-hint" id="lineTripHint" hidden></div>
        <input id="activeSupplyNo" type="hidden">
        <input id="activeSupplyStatus" type="hidden">
    </div>

    <div class="pos-form-grid aqua-order-grid" id="orderSourceFields" hidden>
        <div class="field aqua-order-select-field">
            <label for="sourceOrder">Pending Customer Order</label>
            <select id="sourceOrder"><option value="">Spot Sale / No Order</option></select>
        </div>
        <div class="field outstanding-field compact-info-field">
            <label>Outstanding</label>
            <input id="customerOutstanding" type="text" readonly placeholder="₹0.00">
        </div>
        <div class="field returnable-summary-field compact-info-field">
            <label>Returnable Cans</label>
            <input id="customerReturnableTotal" type="text" readonly value="0.000">
        </div>
    </div>


    <div class="pos-supply-line compact-status-line" id="customerReturnableSummary" hidden>
        <strong>Returnable Cans:</strong>
        <span id="customerReturnableCount">0</span>
        <span id="customerReturnableBreakdown" class="muted"></span>
    </div>

    <div class="pos-supply-line compact-status-line fast-source-indicator">
        <strong id="stockSourceLabel">Plant Stock</strong>
        <span id="modeHelp" hidden></span>
        <span class="pos-shortcut-help" id="taxShortcutHelp" hidden>Ctrl + Shift + U · GST / Non-GST</span>
    </div>
</section>


<div class="fast-billing-layout">
  <div class="fast-billing-cart">
<section class="pos-card" id="productEntryCard">
    <div class="pos-section-head fast-product-head"><div class="fast-heading"><span class="fast-step">02</span><div><h2>Add Products</h2><p id="productEntryHelp" hidden></p></div></div><span class="fast-key-hint">Search → Qty → Add</span></div>
    <div class="product-entry-scroll">
        <div class="aqua-product-entry-grid">
            <div class="field product-col"><label for="entryProduct">Product <span class="muted">(Select Customer first)</span></label><select id="entryProduct"><option value="">Select Product</option></select></div>
            <div class="field stock-col compact-stock"><label id="entryStockLabel">Available</label><div class="compact-stock-value" id="entryStockText">0.000</div><input id="entryStock" type="hidden" value="0"></div>
            <div class="field order-pending-col" id="entryOrderPendingField" hidden><label>Order Pending</label><input id="entryOrderPending" type="text" readonly placeholder="0.000"></div>
            <div class="field qty-col"><label id="primaryQtyLabel" for="primaryQty">Primary Qty</label><input id="primaryQty" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" placeholder="0.000"></div>
            <div class="field qty-col" id="secondaryQtyField"><label id="secondaryQtyLabel" for="secondaryQty">Secondary Qty</label><input id="secondaryQty" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" placeholder="0.000" disabled></div>
            <div class="field rate-col"><label id="primaryRateLabel" for="primaryRate">Main Rate</label><input id="primaryRate" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></div>
            <div class="field discount-col discount-field"><label for="discountType">Disc Type</label><select id="discountType"><option value="1">None</option><option value="2">%</option><option value="3">Amount</option></select></div>
            <div class="field discount-col discount-field"><label for="discountValue">Discount</label><input id="discountValue" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></div>
            <div class="field can-col reusable-field" hidden><label>Previous Can Bal.</label><input id="previousCanBalance" type="text" readonly placeholder="0.000"></div>
            <div class="field can-col reusable-field" hidden><label for="emptyReturn">Empty Return</label><input id="emptyReturn" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" placeholder="0.000"></div>
            <div class="field can-col reusable-field" hidden><label for="damagedReturn">Damaged</label><input id="damagedReturn" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" placeholder="0.000"></div>
            <div class="field add-col"><label>&nbsp;</label><button class="btn btn-primary pos-add-product" id="addProductButton" type="button" title="Add Product"><i data-lucide="plus"></i></button></div>
        </div>
    </div>
</section>

<section class="pos-card items-card">
    <div class="pos-section-head"><div class="fast-heading"><span class="fast-step">03</span><div><h2 id="itemsHeading">Bill Items</h2><p id="itemsHelp">No Products added.</p></div></div><span class="fast-items-hint">Edit quantities directly</span></div>
    <div class="pos-items-table-wrap">
        <table class="pos-items-table" id="salesItemsTable">
            <thead><tr>
                <th>#</th><th>Product</th><th id="stockColumnHead">Stock</th><th class="order-column">Order Pending</th>
                <th id="primaryQtyColumnHead">Main Qty</th><th id="secondaryQtyColumnHead">Base Qty</th><th id="primaryRateColumnHead">Main Rate</th><th class="discount-column">Discount Type</th><th class="discount-column">Discount Amount</th>
                <th class="reusable-column">Empty</th><th class="reusable-column">Damaged</th><th class="tax-column">Tax</th><th>Amount</th><th></th>
            </tr></thead>
            <tbody id="salesItemsBody"><tr><td class="empty" colspan="14">No Products added.</td></tr></tbody>
        </table>
    </div>
    <div class="pos-mobile-items" id="mobileItems"></div>
</section>

<section class="pos-card mobile-adjustments-card" id="mobileAdjustmentsCard">
    <div class="pos-section-head"><div><h2>Discount</h2></div></div>
    <div class="mobile-adjustments-grid">
        <div class="field discount-field">
            <label for="mobileOverallDiscountType">Overall Discount</label>
            <select id="mobileOverallDiscountType">
                <option value="1">None</option>
                <option value="2">Percentage</option>
                <option value="3">Amount</option>
            </select>
        </div>
        <div class="field discount-field">
            <label for="mobileOverallDiscountValue">Discount Value</label>
            <input id="mobileOverallDiscountValue" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00">
        </div>
    </div>
</section>

  </div>
  <aside class="fast-billing-checkout" aria-label="Bill summary and payment">
    <div class="pos-card summary-card" id="summaryCard">
        <div class="pos-section-head"><div class="fast-heading"><span class="fast-step">04</span><div><h2>Bill Summary</h2></div></div></div>
        <div class="summary-lines">
            <div><span>Gross</span><strong id="sumGross">₹0.00</strong></div>
            <div><span>Item Discount</span><strong id="sumItemDiscount">₹0.00</strong></div>
            <div class="summary-control-row discount-field"><label for="overallDiscountType">Overall Discount</label><select id="overallDiscountType"><option value="1">None</option><option value="2">Percentage</option><option value="3">Amount</option></select></div>
            <div class="summary-control-row discount-field"><label for="overallDiscountValue">Discount Value</label><input id="overallDiscountValue" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></div>
            <div><span>Overall Discount</span><strong id="sumOverallDiscount">₹0.00</strong></div>
            <div class="tax-summary-line"><span>Tax</span><strong id="sumTax">₹0.00</strong></div>
            <div class="summary-control-row"><label for="otherCharges">Other Charges</label><input id="otherCharges" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></div>
            <div class="round-row"><span>Round Off</span><span class="round-actions"><strong id="sumRoundOff">₹0.00</strong><button class="btn gray small" type="button" id="roundOffButton">Round Off</button></span></div>
            <div id="summaryReceivedRow"><span>Received</span><strong id="sumPaid">₹0.00</strong></div>
            <div id="summaryOutstandingRow"><span>Outstanding</span><strong id="sumBalance">₹0.00</strong></div>
            <div class="grand-total-line"><span>GRAND TOTAL</span><strong id="sumGrandTotal">₹0.00</strong></div>
        </div>
    </div>
    <div class="pos-card payment-card" id="paymentCard">
        <div class="pos-section-head"><div class="fast-heading"><span class="fast-step">05</span><div><h2>Payment</h2></div></div></div>
        <div class="mobile-payment-wrap" id="mobilePaymentWrap">
            <div class="mobile-payment-entry">
                <div class="field">
                    <label for="mobilePaymentMode">Mode</label>
                    <select id="mobilePaymentMode">
                        <option value="1">Cash</option>
                        <option value="2">UPI</option>
                        <option value="3">Bank</option>
                        <option value="4">Cheque</option>
                    </select>
                </div>
                <div class="field">
                    <label for="mobilePaymentAccount">Account</label>
                    <select id="mobilePaymentAccount"><option value="">Select Account</option></select>
                </div>
                <div class="field">
                    <label for="mobilePaymentAmount">Amount</label>
                    <input id="mobilePaymentAmount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00">
                </div>
                <div class="field mobile-payment-reference-field" id="mobilePaymentReferenceField" hidden>
                    <label for="mobilePaymentReference">Reference No</label>
                    <input id="mobilePaymentReference" type="text" maxlength="100" placeholder="Reference No">
                </div>
                <div class="field mobile-payment-date-field" id="mobilePaymentDateField" hidden>
                    <label for="mobilePaymentDate">Date</label>
                    <input id="mobilePaymentDate" type="date">
                </div>
                <div class="field mobile-payment-add-field">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary" type="button" id="mobilePaymentAddButton" title="Add Payment"><i data-lucide="plus"></i></button>
                </div>
            </div>
            <div class="mobile-payment-list" id="mobilePaymentList"></div>
        </div>
        <div class="payment-table-wrap aqua-payment-table-wrap desktop-payment-table-wrap">
            <table class="payment-table aqua-payment-table">
                <colgroup>
                    <col class="pay-col-mode">
                    <col class="pay-col-account">
                    <col class="pay-col-amount">
                    <col class="pay-col-reference">
                    <col class="pay-col-date">
                </colgroup>
                <thead><tr><th>Mode</th><th>Account</th><th>Amount</th><th>Reference No</th><th>Date</th></tr></thead>
                <tbody>
                    <tr data-payment-mode="1">
                        <td><strong>Cash</strong></td>
                        <td><select class="pay-account"><option value="">Select Cash Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="Optional"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    <tr data-payment-mode="2">
                        <td><strong>UPI</strong></td>
                        <td><select class="pay-account"><option value="">Select UPI Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="UTR / Ref No"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    <tr data-payment-mode="3">
                        <td><strong>Bank</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="Transaction / Ref No"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    <tr data-payment-mode="4">
                        <td><strong>Cheque</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="Cheque No"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="payment-totals">
                <div id="alreadyPaidRow" hidden><span>Already Paid</span><strong id="alreadyPaid">₹0.00</strong></div>
                <div><span>Received Now</span><strong id="receivedNow">₹0.00</strong></div>
                <div class="payment-balance"><span>Balance</span><strong id="paymentBalance">₹0.00</strong></div>
        </div>
    </div>

  </aside>
</div>
<section class="pos-card remarks-card" id="remarksCardCompact" hidden></section>
</form>
</main>
<footer class="pos-actionbar">
    <div class="mobile-fast-total" id="mobileFastTotal">
        <span class="mobile-fast-total-main"><small>Total</small><strong id="mobileGrandTotal">₹0.00</strong></span>
        <span class="mobile-fast-total-meta" id="mobilePaidWrap"><small>Paid</small><strong id="mobilePaidTotal">₹0.00</strong></span>
        <span class="mobile-fast-total-meta" id="mobileBalanceWrap"><small>Due</small><strong id="mobileBalanceTotal">₹0.00</strong></span>
    </div>
    <div class="pos-draft-actions"><span class="local-save-status" id="documentStatusText"></span></div>
    <div class="pos-document-actions">
        <button class="btn gray" type="button" id="clearButton"><i data-lucide="eraser"></i>Clear</button>
        <button class="btn btn-primary final-invoice-action" type="button" id="saveDocumentButton"><i data-lucide="check-circle-2"></i><span id="saveDocumentText">Save</span></button>
    </div>
</footer>
</div>
<script>
(function () {
    function ready() {
        var button = document.getElementById('fastMoreToggle');
        var card = document.getElementById('fastBillDetails');
        if (!button || !card) return;
        card.classList.add('fast-details-ready');
        button.addEventListener('click', function () {
            var expanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            card.classList.toggle('fast-details-open', expanded);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();
})();
</script>

<script>
(function (window, document) {
    "use strict";

    /* GST / Non-GST mode is kept inside this Sales page.
       No separate aqua-tax-mode.js file is required. */
    var TAX_MODE_STORAGE_KEY = "aqua:tax-mode";
    function readTaxModePreference() {
        try { return Number(localStorage.getItem(TAX_MODE_STORAGE_KEY)) === 0 ? 0 : 1; }
        catch (ignore) { return 1; }
    }
    function saveTaxModePreference(mode) {
        mode = Number(mode) === 0 ? 0 : 1;
        try { localStorage.setItem(TAX_MODE_STORAGE_KEY, String(mode)); } catch (ignore) {}
        return mode;
    }
    function toggleTaxModePreference() {
        return saveTaxModePreference(readTaxModePreference() === 0 ? 1 : 0);
    }

    var params = new URLSearchParams(window.location.search);
    var reference = params.get("ref") || "";

    var MODE_QUOTATION = 1;
    var MODE_ORDER = 2;
    var MODE_DIRECT = 3;
    var MODE_LINE = 4;

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_SAVE_DRAFT = 10;
    var ACTION_POST = 11;
    var ACTION_RECEIVE_PAYMENT = 29;
    var ACTION_APPLY_DISCOUNT = 35;
    var ACTION_MANAGE_TAX = 49;

    var MODE_LABELS = {
        1: "Quotation",
        2: "Customer Order",
        3: "Direct Sales Invoice",
        4: "Line Supply Sale"
    };

    var customerSelect = GlobalSelect.init("#customerId", { placeholder: "Select Customer" });
    var productSelect = GlobalSelect.init("#entryProduct", { placeholder: "Select Product" });
    var vehicleSelect = GlobalSelect.init("#vehicleId", { placeholder: "Select Truck" });
    var lineSelect = GlobalSelect.init("#lineId", { placeholder: "Select Line" });

    var state = {
        actions: [],
        allowedModes: [],
        mode: MODE_QUOTATION,
        status: 1,
        documentType: 0,
        deliveryStatus: 0,
        locked: false,
        modeLocked: false,
        loading: true,
        saving: false,
        taxMode: readTaxModePreference(),
        roundOffEnabled: 0,
        customers: [],
        masterCustomers: [],
        products: [],
        masterProducts: [],
        accounts: [],
        vehicles: [],
        lines: [],
        customerMap: {},
        productMap: {},
        vehicleMap: {},
        lineMap: {},
        items: [],
        sourceOrders: [],
        sourceOrderMap: {},
        reusableBalances: {},
        reusableTotal: 0,
        legacyUnallocatedOpeningCan: 0,
        activeTrip: null,
        currentSaleNo: "",
        payments: [],
        preferredPaymentAccounts: {},
        existingPaid: 0,
        sourceOrderAdvance: 0
    };

    function byId(id) { return document.getElementById(id); }
    function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
    function numberValue(value) {
        var n = Number(value || 0);
        return Number.isFinite(n) ? n : 0;
    }
    function round(value, places) {
        var m = Math.pow(10, places || 2);
        return Math.round((numberValue(value) + Number.EPSILON) * m) / m;
    }
    function money(value) {
        return "₹" + numberValue(value).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function decimal3(value) { return numberValue(value).toFixed(3); }

    function qtyText(value) {
        var n = round(numberValue(value), 3);

        /*
         * Friendly quantity display:
         * 12      -> 12
         * 12.5    -> 12.5
         * 12.125  -> 12.125
         *
         * Keep calculation precision at 3 decimals but avoid unnecessary
         * trailing zeros in conversion / stock descriptions.
         */
        if (Math.abs(n - Math.round(n)) < 0.0005) {
            return String(Math.round(n));
        }

        return n.toFixed(3)
            .replace(/0+$/, "")
            .replace(/\.$/, "");
    }
    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    function hasAction(id) {
        return state.actions.map(Number).indexOf(Number(id)) !== -1;
    }
    function isInvoiceMode() {
        return state.mode === MODE_DIRECT || state.mode === MODE_LINE;
    }
    function isLineMode() { return state.mode === MODE_LINE; }
    function unitName(unit) {
        return unit ? (unit.short_name || unit.unit_name || "Unit") : "Unit";
    }
    function currentProduct() {
        return state.productMap[String(byId("entryProduct").value || "")] || null;
    }
    function currentCustomerId() { return Number(byId("customerId").value || 0); }
    function currentVehicleId() { return Number(byId("vehicleId").value || 0); }
    function currentLineId() { return Number(byId("lineId").value || 0); }

    function showWarning(message) {
        if (window.showToast) showToast(message, { type: "warning", duration: 3 });
        else window.alert(message);
    }
    function showSuccess(message) {
        if (window.showToast) showToast(message, { type: "success", duration: 2 });
    }
    function reportError(error, fallback) {
        if (window.App && App.showError) App.showError(error, fallback);
        else window.alert((error && error.message) || fallback);
    }
    function refreshIcons() {
        if (window.lucide && lucide.createIcons) lucide.createIcons();
    }

    function optionRows(rows, textBuilder) {
        return (rows || []).map(function (row) {
            return { value: String(row.id), text: textBuilder(row) };
        });
    }

    function rebuildMaps() {
        state.customerMap = {};
        state.productMap = {};
        state.vehicleMap = {};
        state.lineMap = {};
        state.customers.forEach(function (row) { state.customerMap[String(row.id)] = row; });
        state.products.forEach(function (row) { state.productMap[String(row.id)] = row; });
        state.vehicles.forEach(function (row) { state.vehicleMap[String(row.id)] = row; });
        state.lines.forEach(function (row) { state.lineMap[String(row.id)] = row; });
    }

    function customerText(row) {
        return (row.customer_code ? row.customer_code + " - " : "") + row.customer_name + (row.mobile ? " - " + row.mobile : "");
    }
    function productText(row) {
        return (row.product_code ? row.product_code + " - " : "") + row.product_name;
    }
    function vehicleText(row) {
        return row.vehicle_no + (row.vehicle_name ? " - " + row.vehicle_name : "");
    }
    function lineText(row) {
        return (row.line_code ? row.line_code + " - " : "") + row.line_name;
    }

    function positionCustomerField(lineMode) {
        var customerField = byId("customerField");
        var headerGrid = byId("aquaHeaderGrid");
        var remarksField = byId("remarksField");
        var lineFields = byId("lineModeFields");
        var vehicleField = byId("vehicleField");
        if (!customerField || !headerGrid || !remarksField || !lineFields || !vehicleField) return;
        if (lineMode) {
            /* Line is an optional Customer filter. Customer can also be selected directly. */
            lineFields.insertBefore(customerField, vehicleField);
        } else {
            headerGrid.insertBefore(customerField, remarksField);
        }
    }

    function filterCustomersForSelectedLine(preserveCustomerId) {
        var lineId = currentLineId();
        var selected = preserveCustomerId ? String(preserveCustomerId) : "";
        if (!lineId) {
            /* Rare direct-Customer flow: no Line means show every permitted Customer. */
            state.customers = (state.masterCustomers || []).slice();
        } else {
            state.customers = (state.masterCustomers || []).filter(function (row) {
                return Number(row.line_id || 0) === Number(lineId);
            });
        }
        rebuildMaps();
        if (selected && !state.customerMap[selected]) selected = "";
        customerSelect.setOptions(optionRows(state.customers, customerText), selected);
        byId("customerId").value = selected;
        byId("customerId").disabled = state.locked;
        return selected;
    }

    function clearActiveLineTrip() {
        state.activeTrip = null;
        state.products = [];
        state.productMap = {};
        productSelect.setOptions([], "");
        byId("entryProduct").value = "";
        byId("activeSupplyNo").value = "";
        byId("activeSupplyStatus").value = "";
        byId("lineTripHint").hidden = true;
        byId("lineTripHint").textContent = "";
        refreshEntryProduct();
    }

    function setMasterOptions(data, preserve) {
        preserve = preserve || {};
        if (Array.isArray(data.customers)) state.masterCustomers = data.customers.slice();
        state.customers = isLineMode() ? state.customers : state.masterCustomers.slice();
        state.masterProducts = data.products || state.masterProducts || [];
        if (!isLineMode()) state.products = state.masterProducts.slice();
        state.accounts = data.accounts || state.accounts || [];
        state.vehicles = data.vehicles || state.vehicles || [];
        state.lines = data.lines || state.lines || [];
        rebuildMaps();

        if (isLineMode()) {
            lineSelect.setOptions(optionRows(state.lines, lineText), preserve.line || byId("lineId").value || "");
            byId("lineId").value = preserve.line || byId("lineId").value || "";
            filterCustomersForSelectedLine(preserve.customer || byId("customerId").value || "");
        } else {
            customerSelect.setOptions(optionRows(state.customers, customerText), preserve.customer || byId("customerId").value || "");
        }
        var selectedCustomerForProducts = Number(
            preserve.customer || byId("customerId").value || 0
        );

        if (!isLineMode() && !selectedCustomerForProducts) {
            productSelect.setOptions([], "");
            byId("entryProduct").value = "";
        } else {
            productSelect.setOptions(
                optionRows(state.products, productText),
                preserve.product || ""
            );
        }
        vehicleSelect.setOptions(optionRows(state.vehicles, vehicleText), preserve.vehicle || byId("vehicleId").value || "");
        lineSelect.setOptions(optionRows(state.lines, lineText), preserve.line || byId("lineId").value || "");
        renderPaymentAccounts();
    }

    function setLineContext(data) {
        var customerId = currentCustomerId();
        var productId = byId("entryProduct").value || "";
        state.activeTrip = data.trip || null;
        state.customers = data.customers || [];
        state.products = data.products || [];
        rebuildMaps();
        customerSelect.setOptions(optionRows(state.customers, customerText), customerId ? String(customerId) : "");
        productSelect.setOptions(optionRows(state.products, productText), productId);
        if (customerId && !state.customerMap[String(customerId)]) {
            customerSelect.setOptions(optionRows(state.customers, customerText), "");
            byId("customerId").value = "";
            state.sourceOrders = [];
            renderSourceOrders();
        }
        byId("activeSupplyNo").value = state.activeTrip ? state.activeTrip.supply_no : "";
        byId("activeSupplyStatus").value = state.activeTrip ? (Number(state.activeTrip.status) === 3 ? "In Route" : "Loaded") : "";
        byId("lineTripHint").hidden = !state.activeTrip;
        byId("lineTripHint").textContent = state.activeTrip ? ("Active load: " + state.activeTrip.supply_no) : "";
    }

    function renderModeOptions() {
        var select = byId("saleMode");
        var modes = state.allowedModes.slice();
        if (reference && modes.indexOf(state.mode) === -1) modes.unshift(state.mode);
        if (!modes.length) modes = [state.mode || MODE_QUOTATION];
        select.innerHTML = modes.map(function (mode) {
            return '<option value="' + mode + '">' + escapeHtml(MODE_LABELS[mode] || "Sales") + '</option>';
        }).join("");
        select.value = String(state.mode);
        select.disabled = state.locked;
        byId("saleModeField").hidden = false;
    }

    async function loadPreviewNumber() {
        if (reference || !state.mode) return;
        try {
            var result = await App.api("api/sales.php?next_no=1&mode=" + encodeURIComponent(state.mode) + "&tax_mode=" + encodeURIComponent(state.taxMode));
            byId("saleNo").value = (result.data && result.data.sale_no) || "";
        } catch (error) { byId("saleNo").value = ""; }
    }

    function applyTaxMode(mode, persist) {
        state.taxMode = Number(mode) === 0 ? 0 : 1;
        if (persist) saveTaxModePreference(state.taxMode);
        byId("nonGstBadge").hidden = state.taxMode !== 0;
        all(".tax-column,.tax-summary-line").forEach(function (row) {
            row.hidden = state.taxMode === 0 || !hasAction(ACTION_MANAGE_TAX);
        });
        recalculateAll();
        refreshEntryUnitSummary();
        if (!reference) loadPreviewNumber();
    }

    function modeHelpText() {
        if (state.mode === MODE_QUOTATION) return "Quotation only. No stock, payment, outstanding or can movement.";
        if (state.mode === MODE_ORDER) return "Customer Order only. Ordered / Delivered / Pending quantities are maintained; no stock effect now.";
        if (state.mode === MODE_LINE) return "Posted delivery reduces selected Truck Stock and Overall Stock.";
        return "Posted invoice reduces Plant Stock and Overall Stock.";
    }

    function updateDocumentLabels() {
        var label = MODE_LABELS[state.mode] || "Sales";
        byId("documentBadge").textContent = label;
        byId("currentDocumentLabel").textContent = state.currentSaleNo ? label + " · " + state.currentSaleNo : label;
        byId("modeHelp").textContent = modeHelpText();
        byId("stockSourceLabel").textContent = isLineMode() ? "Truck Stock" : "Plant Stock";
        byId("entryStockLabel").textContent = "Available";
        byId("stockColumnHead").textContent = isLineMode() ? "Truck Stock" : "Plant Stock";
        byId("itemsHeading").textContent = state.mode === MODE_ORDER ? "Order Items" : "Sales Items";
        var saveLabel = state.mode === MODE_QUOTATION
            ? "Save Quotation"
            : (state.mode === MODE_ORDER ? "Save Customer Order" : (state.mode === MODE_LINE ? "Post Line Sale" : "Post Sales Invoice"));
        if (reference && !state.locked) {
            saveLabel = state.mode === MODE_QUOTATION
                ? "Update Quotation"
                : (state.mode === MODE_ORDER ? "Update Customer Order" : (state.mode === MODE_LINE ? "Update Line Sale" : "Update Sales Invoice"));
        }
        byId("saveDocumentText").textContent = saveLabel;
        if (state.locked) {
            byId("documentStatusText").textContent = state.status === 3 ? "Cancelled / read only" : "Read only";
        } else if (reference) {
            byId("documentStatusText").textContent = state.status === 2
                ? "Posted · whole-page edit enabled"
                : "Editing existing document";
        } else {
            byId("documentStatusText").textContent = state.mode === MODE_QUOTATION ? "Draft quotation" : "Ready to save";
        }
    }

    function applyModeUI() {
        var lineMode = isLineMode();
        var invoiceMode = isInvoiceMode();
        var orderMode = state.mode === MODE_ORDER;
        var orderSourceVisible = invoiceMode;

        positionCustomerField(lineMode);
        byId("lineModeFields").hidden = !lineMode;
        byId("orderSourceFields").hidden = !orderSourceVisible;
        var paymentMode = (invoiceMode || orderMode) && hasAction(ACTION_RECEIVE_PAYMENT);
        byId("paymentCard").hidden = !paymentMode;
        byId("summaryReceivedRow").hidden = !paymentMode;
        byId("summaryOutstandingRow").hidden = !paymentMode;
        byId("mobilePaidWrap").hidden = !paymentMode;
        byId("mobileBalanceWrap").hidden = !paymentMode;
        byId("taxShortcutHelp").hidden = true;
        byId("entryOrderPendingField").hidden = !invoiceMode || !byId("sourceOrder").value;
        all(".order-column").forEach(function (node) { node.hidden = !(orderMode || (invoiceMode && !!byId("sourceOrder").value)); });

        var showReusable = invoiceMode;
        all(".reusable-field").forEach(function (node) { node.hidden = true; });
        all(".reusable-column").forEach(function (node) { node.hidden = !showReusable; });

        all(".discount-field,.discount-column").forEach(function (node) {
            node.hidden = !hasAction(ACTION_APPLY_DISCOUNT);
        });
        byId("mobileAdjustmentsCard").hidden = !hasAction(ACTION_APPLY_DISCOUNT);
        all(".tax-column,.tax-summary-line").forEach(function (node) {
            node.hidden = state.taxMode === 0 || !hasAction(ACTION_MANAGE_TAX);
        });
        all(".outstanding-field").forEach(function (node) {
            node.hidden = !hasAction(ACTION_RECEIVE_PAYMENT);
        });

        var commercialEdit = !state.locked && (state.ref ? hasAction(ACTION_UPDATE) : hasAction(ACTION_CREATE));
        byId("primaryRate").readOnly = !commercialEdit;
        byId("otherCharges").readOnly = !commercialEdit;
        byId("overallDiscountType").disabled = state.locked || !hasAction(ACTION_APPLY_DISCOUNT);
        byId("overallDiscountValue").readOnly = state.locked || !hasAction(ACTION_APPLY_DISCOUNT);
        byId("remarks").readOnly = state.locked;
        byId("roundOffButton").hidden = !commercialEdit;

        byId("saveDocumentButton").hidden = state.locked || !canSaveCurrentMode();
        byId("clearButton").hidden = state.locked;
        byId("saleDate").disabled = state.locked;
        byId("customerId").disabled = state.locked;
        if (lineMode) {
            byId("lineId").disabled = state.locked;
            /* Truck is independent. Line is optional and Customer is mandatory only when saving. */
            byId("vehicleId").disabled = state.locked;
        }
        byId("sourceOrder").disabled = state.locked || (lineMode && !currentCustomerId());

        /*
         * Customer determines the selling price level.
         * Do not allow Product selection before Customer is selected.
         */
        byId("entryProduct").disabled =
            state.locked ||
            !currentCustomerId() ||
            (lineMode && !state.activeTrip);

        paymentRows().forEach(function (row) {
            var account = row.querySelector(".pay-account");
            var amount = row.querySelector(".pay-amount");
            var referenceInput = row.querySelector(".pay-reference");
            var dateInput = row.querySelector(".pay-date");
            if (account) account.disabled = state.locked;
            if (amount) amount.readOnly = state.locked;
            if (referenceInput) referenceInput.readOnly = state.locked;
            if (dateInput) dateInput.readOnly = state.locked;
        });
        ["mobilePaymentMode","mobilePaymentAccount","mobilePaymentAmount","mobilePaymentReference","mobilePaymentDate","mobilePaymentAddButton","mobileOverallDiscountType","mobileOverallDiscountValue"].forEach(function (id) {
            var control = byId(id);
            if (!control) return;
            if (control.tagName === "INPUT") control.readOnly = state.locked;
            else control.disabled = state.locked;
        });

        updateDocumentLabels();
        refreshEntryProduct();
        renderItems();
        recalculateAll();
    }

    function canSaveCurrentMode() {
        if (state.mode === MODE_QUOTATION) return hasAction(ACTION_SAVE_DRAFT);
        return hasAction(ACTION_POST);
    }

    function replaceProductInState(product) {
        if (!product || !product.id) return;

        var id = Number(product.id);

        function replaceIn(list) {
            var found = false;
            list = (list || []).map(function (row) {
                if (Number(row.id) === id) {
                    found = true;
                    return product;
                }
                return row;
            });

            if (!found) list.push(product);
            return list;
        }

        state.products = replaceIn(state.products);
        state.masterProducts = replaceIn(state.masterProducts);
        rebuildMaps();
    }

    function clearEntryForCustomerChange() {
        byId("entryProduct").value = "";
        productSelect.setOptions([], "");
        ["primaryQty","secondaryQty","primaryRate","discountValue","emptyReturn","damagedReturn","previousCanBalance","entryOrderPending"].forEach(function (id) {
            byId(id).value = "";
        });
        byId("discountType").value = "1";
        refreshEntryProduct();
    }

    async function handleProductChange() {
        var productId = Number(byId("entryProduct").value || 0);

        if (!productId) {
            refreshEntryProduct();
            updateItemColumnHeaders();
            return;
        }

        var customerId = currentCustomerId();
        if (!customerId) {
            showWarning("Select Customer before Product.");
            clearEntryForCustomerChange();
            applyModeUI();
            return;
        }

        /*
         * Always refresh the selected Product from the server.
         * This guarantees current:
         * - Plant / Truck Stock
         * - Customer Price Level price
         * - normalized Main/Base Unit orientation
         * - Customer returnable balance for this Product
         */
        try {
            var url =
                "api/sales.php?product_context=1" +
                "&product_id=" + encodeURIComponent(productId) +
                "&customer_id=" + encodeURIComponent(customerId) +
                "&mode=" + encodeURIComponent(state.mode);

            if (isLineMode()) {
                url += "&vehicle_id=" + encodeURIComponent(currentVehicleId());
                if (currentLineId()) {
                    url += "&line_id=" + encodeURIComponent(currentLineId());
                }
            }

            var result = await App.api(url);
            var fresh = result.data.product || null;

            if (fresh) {
                replaceProductInState(fresh);

                if (
                    Number(fresh.container_type) === 1 &&
                    result.data.product.customer_can_balance !== undefined
                ) {
                    state.reusableBalances[String(fresh.id)] =
                        numberValue(fresh.customer_can_balance);
                }

                productSelect.setOptions(
                    optionRows(state.products, productText),
                    String(productId)
                );
                byId("entryProduct").value = String(productId);
            }

            if (result.data.trip && isLineMode()) {
                state.activeTrip = result.data.trip;
            }

            refreshEntryProduct();
            renderCustomerReturnableSummary();
            updateItemColumnHeaders(fresh || currentProduct());
        } catch (error) {
            byId("entryProduct").value = "";
            refreshEntryProduct();
            reportError(error, "Unable to load Product stock / price.");
        }
    }

    async function reloadStandardOptions(customerId) {
        var vehicle = byId("vehicleId").value || "";
        var line = byId("lineId").value || "";
        var url = "api/sales.php?options=1";
        if (customerId) url += "&customer_id=" + encodeURIComponent(customerId);
        var result = await App.api(url);
        state.actions = (result.data.allowed_actions || state.actions || []).map(Number);
        state.allowedModes = (result.data.allowed_modes || state.allowedModes || []).map(Number);
        setMasterOptions(result.data, { customer: customerId ? String(customerId) : "", vehicle: vehicle, line: line });
        renderModeOptions();
        applyModeUI();
    }

    async function loadLineContext() {
        if (!isLineMode()) return;
        var lineId = currentLineId();
        var customerId = currentCustomerId();
        var vehicleId = currentVehicleId();
        if (!vehicleId) {
            clearActiveLineTrip();
            return;
        }
        var url = "api/sales.php?line_context=1&vehicle_id=" + encodeURIComponent(vehicleId);
        if (customerId) url += "&customer_id=" + encodeURIComponent(customerId);
        if (lineId) url += "&line_id=" + encodeURIComponent(lineId);
        try {
            var result = await App.api(url);
            setLineContext(result.data);
            refreshEntryProduct();
            if (currentCustomerId()) await loadCustomerSummaryAndOrders();
        } catch (error) {
            clearActiveLineTrip();
            reportError(error, "Unable to load active Truck Stock.");
        }
    }

    function paymentRows() {
        return Array.prototype.slice.call(document.querySelectorAll("tr[data-payment-mode]"));
    }

    function paymentAccountPlaceholder(mode) {
        if (mode === 1) return "Select Cash Account";
        if (mode === 2) return "Select UPI Account";
        return "Select Bank Account";
    }

    function renderPaymentAccounts() {
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode") || 0);
            var requiredType = mode === 1 ? 1 : (mode === 2 ? 3 : 2);
            var select = row.querySelector(".pay-account");
            var current = select.value;
            var matchingAccounts = state.accounts.filter(function (account) {
                return Number(account.account_type) === requiredType;
            });
            select.innerHTML = '<option value="">' + escapeHtml(paymentAccountPlaceholder(mode)) + '</option>' + matchingAccounts
                .map(function (account) { return '<option value="' + Number(account.id) + '">' + escapeHtml(account.account_name) + '</option>'; })
                .join("");

            var preferred = String((state.preferredPaymentAccounts && state.preferredPaymentAccounts[mode]) || "");
            if (current && Array.prototype.some.call(select.options, function (o) { return o.value === current; })) {
                select.value = current;
            } else if (preferred && Array.prototype.some.call(select.options, function (o) { return o.value === preferred; })) {
                select.value = preferred;
            } else if (matchingAccounts.length === 1) {
                select.value = String(matchingAccounts[0].id);
            }
        });
        renderMobilePaymentAccount();
    }

    function mobilePaymentMode() {
        return Number(byId("mobilePaymentMode").value || 1);
    }

    function paymentModeLabel(mode) {
        return ({1:"Cash",2:"UPI",3:"Bank",4:"Cheque"})[Number(mode)] || "Payment";
    }

    function renderMobilePaymentAccount() {
        var select = byId("mobilePaymentAccount");
        if (!select) return;
        var mode = mobilePaymentMode();
        var requiredType = mode === 1 ? 1 : (mode === 2 ? 3 : 2);
        var current = select.value;
        var matchingAccounts = state.accounts.filter(function (account) {
            return Number(account.account_type) === requiredType;
        });
        select.innerHTML = '<option value="">' + escapeHtml(paymentAccountPlaceholder(mode)) + '</option>' + matchingAccounts
            .map(function (account) { return '<option value="' + Number(account.id) + '">' + escapeHtml(account.account_name) + '</option>'; })
            .join("");
        var preferred = String((state.preferredPaymentAccounts && state.preferredPaymentAccounts[mode]) || "");
        if (current && Array.prototype.some.call(select.options, function (o) { return o.value === current; })) {
            select.value = current;
        } else if (preferred && Array.prototype.some.call(select.options, function (o) { return o.value === preferred; })) {
            select.value = preferred;
        } else if (matchingAccounts.length === 1) {
            select.value = String(matchingAccounts[0].id);
        }
        /* Mobile keeps every payment field visible/editable. Reference is optional only for Cash. */
        byId("mobilePaymentReferenceField").hidden = false;
        byId("mobilePaymentDateField").hidden = false;
        var ref = byId("mobilePaymentReference");
        if (mode === 1) ref.placeholder = "Optional";
        else if (mode === 2) ref.placeholder = "UTR / Ref No";
        else if (mode === 3) ref.placeholder = "Transaction / Ref No";
        else if (mode === 4) ref.placeholder = "Cheque No";
        else ref.placeholder = "Reference No";
    }

    function clearMobilePaymentEntry() {
        byId("mobilePaymentAccount").value = "";
        byId("mobilePaymentAmount").value = "";
        byId("mobilePaymentReference").value = "";
        byId("mobilePaymentDate").value = "";
    }

    function renderMobilePaymentList() {
        var list = byId("mobilePaymentList");
        if (!list) return;
        var html = [];
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode") || 0);
            var amount = round(numberValue(row.querySelector(".pay-amount").value), 2);
            if (amount <= 0) return;
            var account = row.querySelector(".pay-account");
            var accountText = account && account.selectedIndex >= 0 ? account.options[account.selectedIndex].text : "";
            var ref = row.querySelector(".pay-reference").value.trim();
            var date = row.querySelector(".pay-date").value || "";
            html.push('<div class="mobile-payment-chip" data-mobile-payment-mode="' + mode + '">' +
                '<div><strong>' + escapeHtml(paymentModeLabel(mode)) + ' · ' + money(amount) + '</strong>' +
                '<small>' + escapeHtml([accountText, ref, date].filter(Boolean).join(' · ')) + '</small></div>' +
                '<div class="mobile-payment-actions">' +
                    '<button class="icon-button mobile-payment-edit" type="button" title="Edit"><i data-lucide="pencil"></i></button>' +
                    '<button class="icon-button mobile-payment-remove" type="button" title="Remove"><i data-lucide="x"></i></button>' +
                '</div>' +
                '</div>');
        });
        list.innerHTML = html.length ? html.join("") : '<div class="mobile-payment-empty">No payment added.</div>';
        refreshIcons();
    }

    function addMobilePayment() {
        var mode = mobilePaymentMode();
        var amount = round(numberValue(byId("mobilePaymentAmount").value), 2);
        var accountId = Number(byId("mobilePaymentAccount").value || 0);
        var referenceNo = byId("mobilePaymentReference").value.trim();
        var date = byId("mobilePaymentDate").value || "";
        if (amount <= 0) { showWarning("Enter payment amount."); return; }
        if (!accountId) { showWarning("Select account for " + paymentModeLabel(mode) + "."); return; }
        if ((mode === 2 || mode === 3 || mode === 4) && !referenceNo) { showWarning("Enter reference number for " + paymentModeLabel(mode) + "."); return; }
        if (mode === 4 && !date) { showWarning("Select cheque date."); return; }
        var row = document.querySelector('tr[data-payment-mode="' + mode + '"]');
        if (!row) return;
        row.querySelector(".pay-account").value = String(accountId);
        row.querySelector(".pay-amount").value = amount.toFixed(2);
        row.querySelector(".pay-reference").value = referenceNo;
        row.querySelector(".pay-date").value = date;
        clearMobilePaymentEntry();
        renderMobilePaymentList();
        recalculateAll();
    }

    function renderPayments() {
        /* existingPaid now means only a non-editable/shared allocation, if one exists. */
        byId("alreadyPaidRow").hidden = state.existingPaid <= 0.009 && state.sourceOrderAdvance <= 0.009;
        byId("alreadyPaid").textContent = money(state.existingPaid + state.sourceOrderAdvance);
        renderMobilePaymentList();
    }

    function readPayments(validateRows) {
        var rows = [];
        var valid = true;
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode") || 0);
            var amount = round(numberValue(row.querySelector(".pay-amount").value), 2);
            if (amount <= 0) return;
            var accountId = Number(row.querySelector(".pay-account").value || 0);
            var referenceNo = row.querySelector(".pay-reference").value.trim();
            var date = row.querySelector(".pay-date").value || null;
            if (validateRows) {
                if (!accountId) { showWarning("Select account for " + ({1:"Cash",2:"UPI",3:"Bank",4:"Cheque"}[mode] || "Payment") + "."); valid = false; return; }
                if ((mode === 2 || mode === 3 || mode === 4) && !referenceNo) { showWarning("Enter reference number for " + ({2:"UPI",3:"Bank",4:"Cheque"}[mode] || "Payment") + "."); valid = false; return; }
                if (mode === 4 && !date) { showWarning("Select cheque date."); valid = false; return; }
            }
            rows.push({payment_mode:mode,account_id:accountId,amount:amount,reference_no:referenceNo,detail_date:date});
        });
        return valid ? rows : null;
    }

    function renderSourceOrders() {
        var select = byId("sourceOrder");
        var current = select.value;
        select.innerHTML = '<option value="">Spot Sale / No Order</option>' + state.sourceOrders.map(function (order) {
            return '<option value="' + escapeHtml(order.ref) + '">' + escapeHtml(order.sale_no + (numberValue(order.advance_paid)>0 ? ' · Advance ' + money(order.advance_paid) : '')) + '</option>';
        }).join("");
        if (current && state.sourceOrderMap[current]) select.value = current;
    }

    function renderCustomerReturnableSummary() {
        var wrap = byId("customerReturnableSummary");
        var totalNode = byId("customerReturnableCount");
        var breakdownNode = byId("customerReturnableBreakdown");

        var totalInput = byId("customerReturnableTotal");

        if (!currentCustomerId()) {
            wrap.hidden = true;
            totalNode.textContent = "0";
            if (totalInput) totalInput.value = "0.000";
            breakdownNode.textContent = "";
            return;
        }

        totalNode.textContent = decimal3(state.reusableTotal);
        if (totalInput) totalInput.value = decimal3(state.reusableTotal);

        var parts = [];

        Object.keys(state.reusableBalances).forEach(function (productId) {
            var balance = numberValue(state.reusableBalances[productId]);
            if (Math.abs(balance) <= 0.0005) return;

            var product = state.productMap[String(productId)] || null;
            var productName = product
                ? product.product_name
                : "Product #" + productId;

            parts.push(productName + ": " + decimal3(balance));
        });

        if (state.legacyUnallocatedOpeningCan > 0.0005) {
            parts.push(
                "Legacy opening (unallocated): " +
                decimal3(state.legacyUnallocatedOpeningCan)
            );
        }

        breakdownNode.textContent = parts.length
            ? "· " + parts.join(" · ")
            : "· No pending returnable cans";

        wrap.hidden = false;
    }

    async function loadCustomerSummaryAndOrders() {
        var customerId = currentCustomerId();
        state.reusableBalances = {};
        state.reusableTotal = 0;
        state.legacyUnallocatedOpeningCan = 0;

        if (!customerId) {
            byId("customerOutstanding").value = "";
            renderCustomerReturnableSummary();
            state.sourceOrders = [];
            state.sourceOrderMap = {};
            state.sourceOrderAdvance = 0;
            renderSourceOrders();
            renderPayments();
            return;
        }
        try {
            var summary = await App.api("api/sales.php?customer_summary=1&customer_id=" + customerId);
            byId("customerOutstanding").value = money(summary.data.outstanding || 0);

            state.reusableTotal = numberValue(
                summary.data.returnable_can_total || 0
            );

            state.legacyUnallocatedOpeningCan = numberValue(
                summary.data.legacy_unallocated_opening_can || 0
            );

            (summary.data.reusable_balances || []).forEach(function (row) {
                state.reusableBalances[String(row.product_id)] =
                    numberValue(row.can_balance);
            });

            renderCustomerReturnableSummary();

            if (isInvoiceMode()) {
                var orders = await App.api("api/sales.php?pending_orders=1&customer_id=" + customerId);
                state.sourceOrders = orders.data.orders || [];
                state.sourceOrderMap = {};
                state.sourceOrders.forEach(function (order) { state.sourceOrderMap[order.ref] = order; });
                renderSourceOrders();
                if (!byId("sourceOrder").value) state.sourceOrderAdvance = 0;
                renderPayments();
            }
            refreshEntryProduct();
        } catch (error) {
            reportError(error, "Unable to load Customer balance / pending orders.");
        }
    }

    async function handleLineChange() {
        if (!isLineMode()) return;

        /* Line is optional and only filters Customers. Never clear the selected Truck. */
        var previousCustomer = currentCustomerId();
        var keptCustomer = filterCustomersForSelectedLine(previousCustomer ? String(previousCustomer) : "");

        if (!keptCustomer) {
            state.sourceOrders = [];
            state.sourceOrderMap = {};
            state.sourceOrderAdvance = 0;
            state.reusableBalances = {};
            state.reusableTotal = 0;
            state.legacyUnallocatedOpeningCan = 0;
            renderSourceOrders();
            renderPayments();
            byId("customerOutstanding").value = "";
            renderCustomerReturnableSummary();
        } else {
            await loadCustomerSummaryAndOrders();
        }

        if (currentVehicleId()) await loadLineContext();
        else clearActiveLineTrip();
        applyModeUI();
    }

    async function handleCustomerChange() {
        var customerId = currentCustomerId();
        if (!customerId) {
            state.sourceOrders = [];
            state.sourceOrderMap = {};
            state.sourceOrderAdvance = 0;
            state.reusableBalances = {};
            state.reusableTotal = 0;
            state.legacyUnallocatedOpeningCan = 0;
            renderSourceOrders();
            renderPayments();
            byId("customerOutstanding").value = "";
            renderCustomerReturnableSummary();
            clearEntryForCustomerChange();

            if (!isLineMode()) {
                state.products = [];
                state.productMap = {};
            }

            if (isLineMode()) {
                if (currentVehicleId()) await loadLineContext();
                else clearActiveLineTrip();
                applyModeUI();
            }
            return;
        }
        if (isLineMode()) {
            /* Customer can come from the selected Line or be selected directly when Line is blank. */
            await loadCustomerSummaryAndOrders();
            applyModeUI();
            if (currentVehicleId()) await loadLineContext();
        } else {
            try {
                await reloadStandardOptions(customerId);
                customerSelect.setOptions(
                    optionRows(state.customers, customerText),
                    String(customerId)
                );
                byId("customerId").value = String(customerId);

                productSelect.setOptions(
                    optionRows(state.products, productText),
                    ""
                );
                byId("entryProduct").value = "";
                refreshEntryProduct();
            } catch (error) {
                reportError(error, "Unable to load Customer pricing.");
            }
            await loadCustomerSummaryAndOrders();
        }
    }

    function primaryConversion(product) {
        return product && product.primary_unit
            ? Math.max(1, numberValue(product.primary_unit.conversion_qty || 1))
            : 1;
    }

    function secondaryConversion(product) {
        return product && product.secondary_unit
            ? Math.max(1, numberValue(product.secondary_unit.conversion_qty || 1))
            : 0;
    }

    function secondaryRateFor(product, primaryRate) {
        if (!product || !product.secondary_unit) return 0;

        return round(
            numberValue(primaryRate) *
            secondaryConversion(product) /
            primaryConversion(product),
            2
        );
    }

    function unitConversionText(product) {
        if (!product || !product.primary_unit) return "";

        var primaryName = unitName(product.primary_unit);
        var primaryConv = primaryConversion(product);

        if (!product.secondary_unit) {
            return "Primary: " + primaryName +
                " · Conversion " + qtyText(primaryConv);
        }

        var secondaryName = unitName(product.secondary_unit);
        var secondaryConv = secondaryConversion(product);

        if (primaryConv >= secondaryConv) {
            return "1 " + primaryName +
                " = " + qtyText(primaryConv / secondaryConv) +
                " " + secondaryName;
        }

        return "1 " + secondaryName +
            " = " + qtyText(secondaryConv / primaryConv) +
            " " + primaryName;
    }

    function itemBaseQty(item) {
        var product = state.productMap[String(item.product_id)] || item.snapshot || {};
        var primaryConv = primaryConversion(product);
        var secondaryConv = secondaryConversion(product);

        return round(
            numberValue(item.primary_qty) * primaryConv +
            numberValue(item.secondary_qty) * secondaryConv,
            3
        );
    }

    function refreshEntryUnitSummary() {
        var product = currentProduct();
        var help = byId("productEntryHelp");
        var addButton = byId("addProductButton");

        if (!product) {
            help.hidden = true;
            help.textContent = "";
            byId("entryStock").value = "0";
            byId("entryStockText").textContent = "0.000";
            addButton.disabled = false;
            return;
        }

        var draft = entryDraftItem(product);
        var primaryQty = Math.max(0, numberValue(draft.primary_qty));
        var secondaryQty = product.secondary_unit
            ? Math.max(0, numberValue(draft.secondary_qty))
            : 0;

        var baseQty = round(
            primaryQty * primaryConversion(product) +
            secondaryQty * secondaryConversion(product),
            3
        );

        var primaryRate = Math.max(0, numberValue(draft.primary_rate));
        var secondaryRate = secondaryRateFor(product, primaryRate);
        var calc = itemCalculation(draft);

        var taxPreview = itemTax(draft, calc.after);
        var lineTotal = round(taxPreview.net, 2);

        var available = productStock(product);
        byId("entryStock").value = decimal3(available);
        byId("entryStockText").textContent = formatStockQuantity(product, available);

        var stockCheck = entryStockValidation(product, baseQty);

        var parts = [
            unitConversionText(product),
            "Base Qty: " + decimal3(baseQty) + " " + unitName(baseUnit(product))
        ];

        if (product.secondary_unit && primaryRate > 0) {
            parts.push(
                unitName(product.secondary_unit) +
                " Rate: ₹" +
                secondaryRate.toFixed(2)
            );
        }

        parts.push("Gross: " + money(calc.gross));

        if (calc.discount > 0) {
            parts.push("Item Discount: " + money(calc.discount));
        }

        if (state.taxMode === 1 && taxPreview.tax > 0) {
            parts.push("Tax: " + money(taxPreview.tax));
        }

        parts.push("Line Total: " + money(lineTotal));

        if (isInvoiceMode()) {
            if (stockCheck.ok) {
                parts.push(
                    "After Sale: " +
                    formatStockQuantity(product, stockCheck.remaining)
                );
            } else {
                parts.push("⚠ " + stockCheck.message);
            }
        } else {
            parts.push("No stock deduction in " + (state.mode === MODE_ORDER ? "Customer Order" : "Quotation"));
        }

        var orderItem = linkedOrderItemForProduct(product.id);
        if (orderItem && baseQty > numberValue(orderItem.pending_base_qty) + 0.0005) {
            parts.push(
                "⚠ Exceeds Order Pending by " +
                decimal3(baseQty - numberValue(orderItem.pending_base_qty))
            );
        }

        help.textContent = parts.join(" · ");
        help.hidden = false;

        var hasQty = baseQty > 0.0005;
        var orderOk = !orderItem ||
            baseQty <= numberValue(orderItem.pending_base_qty) + 0.0005;

        addButton.disabled = state.locked ||
            !hasQty ||
            !stockCheck.ok ||
            !orderOk;
    }

    function productStock(product) {
        if (!product) return 0;
        return isLineMode() ? numberValue(product.truck_stock) : numberValue(product.plant_stock);
    }

    function isPostedInvoiceBeingEdited() {
        return !!reference &&
            Number(state.documentType) === 2 &&
            Number(state.status) === 2 &&
            isInvoiceMode();
    }

    function availableStockForItem(product, item) {
        var available = productStock(product);

        /*
         * Existing posted Invoice stock is already deducted from current stock.
         * Backend reverses that old Invoice before validating the edited Invoice.
         * Add the original item Base Qty back in the browser too so live validation
         * matches the backend edit transaction.
         */
        if (
            isPostedInvoiceBeingEdited() &&
            item &&
            Number(item.item_id || 0) > 0
        ) {
            available += numberValue(item.original_base_qty || 0);
        }

        return round(available, 3);
    }

    function baseUnit(product) {
        if (!product || !product.primary_unit) return null;
        if (!product.secondary_unit) return product.primary_unit;

        return secondaryConversion(product) <= primaryConversion(product)
            ? product.secondary_unit
            : product.primary_unit;
    }

    function formatStockQuantity(product, baseQty) {
        baseQty = Math.max(0, numberValue(baseQty));

        if (!product || !product.primary_unit) {
            return decimal3(baseQty);
        }

        var primary = product.primary_unit;
        var secondary = product.secondary_unit;
        var pc = primaryConversion(product);

        if (!secondary) {
            var onlyQty = pc > 0 ? baseQty / pc : baseQty;
            return qtyText(onlyQty) + " " + unitName(primary) +
                " (" + decimal3(baseQty) + " base)";
        }

        var sc = secondaryConversion(product);

        if (pc > sc) {
            var pQty = Math.floor((baseQty / pc) + 0.0000001);
            var remBase = Math.max(0, round(baseQty - pQty * pc, 3));
            var sQty = sc > 0 ? round(remBase / sc, 3) : 0;

            return qtyText(pQty) + " " + unitName(primary) +
                " + " + qtyText(sQty) + " " + unitName(secondary) +
                " (" + decimal3(baseQty) + " base)";
        }

        if (sc > pc) {
            var sLargeQty = Math.floor((baseQty / sc) + 0.0000001);
            var rem = Math.max(0, round(baseQty - sLargeQty * sc, 3));
            var pSmallQty = pc > 0 ? round(rem / pc, 3) : 0;

            return qtyText(pSmallQty) + " " + unitName(primary) +
                " + " + qtyText(sLargeQty) + " " + unitName(secondary) +
                " (" + decimal3(baseQty) + " base)";
        }

        return decimal3(baseQty) + " " + unitName(baseUnit(product));
    }

    function entryDraftItem(product) {
        return {
            product_id: Number(product ? product.id : 0),
            primary_qty: numberValue(byId("primaryQty").value),
            secondary_qty: product && product.secondary_unit
                ? numberValue(byId("secondaryQty").value)
                : 0,
            primary_rate: numberValue(byId("primaryRate").value),
            discount_type: Number(byId("discountType").value || 1),
            discount_value: numberValue(byId("discountValue").value),
            snapshot: product || {}
        };
    }

    function entryStockValidation(product, baseQty) {
        if (!product) {
            return { ok: false, message: "Select Product." };
        }

        if (!isInvoiceMode()) {
            return { ok: true, available: productStock(product), remaining: productStock(product) };
        }

        var available = availableStockForItem(product, null);
        var remaining = round(available - baseQty, 3);

        if (baseQty > available + 0.0005) {
            return {
                ok: false,
                available: available,
                remaining: remaining,
                shortage: round(baseQty - available, 3),
                message: (isLineMode() ? "Truck" : "Plant") +
                    " Stock insufficient by " +
                    decimal3(baseQty - available) + " base units."
            };
        }

        return {
            ok: true,
            available: available,
            remaining: Math.max(0, remaining)
        };
    }

    function refreshEntryProduct() {
        var product = currentProduct();
        var secondary = byId("secondaryQty");
        var invoiceMode = isInvoiceMode();
        all(".reusable-field").forEach(function (node) { node.hidden = true; });
        byId("previousCanBalance").value = "";
        byId("entryOrderPending").value = "";

        if (!product) {
            byId("primaryQtyLabel").textContent = "Main Qty";
            byId("secondaryQtyLabel").textContent = "Base Qty";
            byId("primaryRateLabel").textContent = "Main Rate";
            updateItemColumnHeaders();
            byId("entryStock").value = "0"; byId("entryStockText").textContent = "0.000";
            byId("primaryRate").value = "";
            secondary.disabled = true;
            secondary.value = "";
            refreshEntryUnitSummary();
            return;
        }

        byId("primaryQtyLabel").textContent =
            unitName(product.primary_unit) + " Qty";

        byId("secondaryQtyLabel").textContent =
            product.secondary_unit
                ? unitName(product.secondary_unit) + " Qty"
                : "Base Qty";

        byId("primaryRateLabel").textContent =
            unitName(product.primary_unit) + " Rate";

        updateItemColumnHeaders(product);
        secondary.disabled = !product.secondary_unit || state.locked;
        if (!product.secondary_unit) secondary.value = "";
        byId("entryStock").value = decimal3(productStock(product));
        byId("entryStockText").textContent = formatStockQuantity(product, productStock(product));
        byId("primaryRate").value = numberValue(product.primary_price) > 0 ? numberValue(product.primary_price).toFixed(2) : "";
        byId("primaryRate").readOnly = state.locked || (state.ref ? !hasAction(ACTION_UPDATE) : !hasAction(ACTION_CREATE));

        refreshEntryUnitSummary();

        var selectedOrder = state.sourceOrderMap[byId("sourceOrder").value] || null;
        if (selectedOrder) {
            var pending = 0;
            selectedOrder.items.forEach(function (item) {
                if (Number(item.product_id) === Number(product.id)) pending = numberValue(item.pending_base_qty);
            });
            byId("entryOrderPending").value = pending > 0 ? decimal3(pending) : "";
        }

        if (invoiceMode && Number(product.container_type) === 1) {
            all(".reusable-field").forEach(function (node) { node.hidden = false; });
            byId("previousCanBalance").value = decimal3(state.reusableBalances[String(product.id)] || 0);
        }
    }

    function clearEntry() {
        productSelect.setOptions(optionRows(state.products, productText), "");
        byId("entryProduct").value = "";
        ["primaryQty","secondaryQty","primaryRate","discountValue","emptyReturn","damagedReturn","previousCanBalance","entryOrderPending"].forEach(function (id) {
            byId(id).value = "";
        });
        byId("discountType").value = "1";
        refreshEntryProduct();
    }

    function linkedOrderItemForProduct(productId) {
        var order = state.sourceOrderMap[byId("sourceOrder").value] || null;
        if (!order) return null;
        for (var i = 0; i < order.items.length; i += 1) {
            if (Number(order.items[i].product_id) === Number(productId) && numberValue(order.items[i].pending_base_qty) > 0.0005) {
                return order.items[i];
            }
        }
        return null;
    }

    function addProductFromEntry() {
        var product = currentProduct();
        if (!product) { showWarning("Select Product."); return; }
        if (state.items.some(function (item) { return Number(item.product_id) === Number(product.id); })) {
            showWarning("Product already added. Edit the existing row.");
            return;
        }
        var primaryQty = numberValue(byId("primaryQty").value);
        var secondaryQty = product.secondary_unit ? numberValue(byId("secondaryQty").value) : 0;
        if (primaryQty <= 0 && secondaryQty <= 0) { showWarning("Enter quantity."); return; }

        var draftBaseQty = round(
            primaryQty * primaryConversion(product) +
            secondaryQty * secondaryConversion(product),
            3
        );

        var stockCheck = entryStockValidation(product, draftBaseQty);
        if (!stockCheck.ok) {
            showWarning(stockCheck.message);
            return;
        }

        var orderItem = linkedOrderItemForProduct(product.id);
        if (
            orderItem &&
            draftBaseQty > numberValue(orderItem.pending_base_qty) + 0.0005
        ) {
            showWarning(
                "Quantity exceeds pending Customer Order quantity. Pending: " +
                decimal3(orderItem.pending_base_qty)
            );
            return;
        }
        state.items.push({
            item_id: 0,
            product_id: Number(product.id),
            primary_qty: round(primaryQty, 3),
            secondary_qty: round(secondaryQty, 3),
            primary_rate: round(numberValue(byId("primaryRate").value), 2),
            discount_type: Number(byId("discountType").value || 1),
            discount_value: round(numberValue(byId("discountValue").value), 2),
            empty_return_qty: isInvoiceMode() && Number(product.container_type) === 1 ? round(numberValue(byId("emptyReturn").value), 3) : 0,
            damaged_return_qty: isInvoiceMode() && Number(product.container_type) === 1 ? round(numberValue(byId("damagedReturn").value), 3) : 0,
            lost_settled_qty: 0,
            source_order_item_id: orderItem ? Number(orderItem.id) : null,
            order_pending_base_qty: orderItem ? numberValue(orderItem.pending_base_qty) : 0,
            ordered_base_qty: state.mode === MODE_ORDER ? 0 : 0,
            delivered_base_qty: 0,
            original_base_qty: 0,
            snapshot: product
        });
        renderItems();
        clearEntry();
    }

    function loadSelectedOrderItems() {
        var ref = byId("sourceOrder").value;
        var order = state.sourceOrderMap[ref] || null;
        state.sourceOrderAdvance = order ? numberValue(order.advance_paid) : 0;
        state.items = state.items.filter(function (item) { return !item.source_order_item_id; });
        if (!order) {
            renderPayments();
            applyModeUI();
            return;
        }

        var skipped = [];
        order.items.forEach(function (orderItem) {
            var product = state.productMap[String(orderItem.product_id)] || null;
            if (!product) {
                skipped.push(orderItem.product_name || "Product");
                return;
            }
            if (state.items.some(function (item) { return Number(item.product_id) === Number(orderItem.product_id); })) return;
            var normalizedOrderItem =
                remapStoredItemToCurrentUnits(orderItem, product);

            state.items.push({
                item_id: 0,
                product_id: Number(orderItem.product_id),
                primary_qty: 0,
                secondary_qty: 0,
                primary_rate: normalizedOrderItem.primary_rate,
                discount_type: Number(orderItem.discount_type || 1),
                discount_value: numberValue(orderItem.discount_value),
                empty_return_qty: 0,
                damaged_return_qty: 0,
                lost_settled_qty: 0,
                source_order_item_id: Number(orderItem.id),
                order_pending_base_qty: numberValue(orderItem.pending_base_qty),
                ordered_base_qty: numberValue(orderItem.ordered_base_qty),
                delivered_base_qty: numberValue(orderItem.delivered_base_qty),
                original_base_qty: 0,
                snapshot: product
            });
        });
        if (skipped.length) showWarning("Some Order Products are not available in the current Truck: " + skipped.join(", "));
        renderPayments();
        applyModeUI();
    }

    function itemCalculation(item) {
        var product = state.productMap[String(item.product_id)] || item.snapshot || {};
        var primaryRate = numberValue(item.primary_rate);
        var secondaryRate = secondaryRateFor(product, primaryRate);

        var gross = round(
            numberValue(item.primary_qty) * primaryRate +
            numberValue(item.secondary_qty) * secondaryRate,
            2
        );

        var discount = 0;
        if (Number(item.discount_type) === 2) discount = round(gross * Math.min(100, numberValue(item.discount_value)) / 100, 2);
        else if (Number(item.discount_type) === 3) discount = Math.min(gross, round(item.discount_value, 2));
        return { gross: gross, discount: discount, after: Math.max(0, round(gross - discount, 2)) };
    }

    function itemTax(item, afterOverall) {
        var product = state.productMap[String(item.product_id)] || item.snapshot || {};
        if (state.taxMode === 0) return { tax: 0, net: afterOverall };
        var percentage = numberValue(product.tax_percentage);
        if (percentage <= 0) return { tax: 0, net: afterOverall };
        if (Number(product.gst_type) === 1) {
            var taxable = afterOverall * 100 / (100 + percentage);
            return { tax: round(afterOverall - taxable, 2), net: round(afterOverall, 2) };
        }
        var tax = round(afterOverall * percentage / 100, 2);
        return { tax: tax, net: round(afterOverall + tax, 2) };
    }

    function readItemRows() {
        all("#salesItemsBody tr[data-index]").forEach(function (row) {
            var index = Number(row.getAttribute("data-index"));
            var item = state.items[index];
            if (!item) return;
            item.primary_qty = round(numberValue(row.querySelector(".js-primary-qty").value), 3);
            var secondary = row.querySelector(".js-secondary-qty");
            item.secondary_qty = secondary ? round(numberValue(secondary.value), 3) : 0;
            var rate = row.querySelector(".js-rate");
            if (rate) item.primary_rate = round(numberValue(rate.value), 2);
            var dtype = row.querySelector(".js-discount-type");
            if (dtype) item.discount_type = Number(dtype.value || 1);
            var discount = row.querySelector(".js-discount-value");
            if (discount) item.discount_value = round(numberValue(discount.value), 2);
            var empty = row.querySelector(".js-empty-return");
            if (empty) item.empty_return_qty = round(numberValue(empty.value), 3);
            var damaged = row.querySelector(".js-damaged-return");
            if (damaged) item.damaged_return_qty = round(numberValue(damaged.value), 3);
        });
    }

    function updateItemColumnHeaders(productHint) {
        var primaryNames = {};
        var secondaryNames = {};

        state.items.forEach(function (item) {
            var product =
                state.productMap[String(item.product_id)] ||
                item.snapshot ||
                {};

            if (product.primary_unit) {
                primaryNames[unitName(product.primary_unit)] = true;
            }

            if (product.secondary_unit) {
                secondaryNames[unitName(product.secondary_unit)] = true;
            }
        });

        if (!state.items.length) {
            var product = productHint || currentProduct();
            if (product) {
                if (product.primary_unit) {
                    primaryNames[unitName(product.primary_unit)] = true;
                }
                if (product.secondary_unit) {
                    secondaryNames[unitName(product.secondary_unit)] = true;
                }
            }
        }

        var pNames = Object.keys(primaryNames);
        var sNames = Object.keys(secondaryNames);

        byId("primaryQtyColumnHead").textContent =
            pNames.length === 1 ? pNames[0] + " Qty" : "Main Qty";

        byId("secondaryQtyColumnHead").textContent =
            sNames.length === 1 ? sNames[0] + " Qty" : "Base Qty";

        byId("primaryRateColumnHead").textContent =
            pNames.length === 1 ? pNames[0] + " Rate" : "Main Rate";
    }

    function renderItems() {
        updateItemColumnHeaders();

        var body = byId("salesItemsBody");
        var mobile = byId("mobileItems");
        var showOrder = state.mode === MODE_ORDER || (isInvoiceMode() && !!byId("sourceOrder").value);
        all(".order-column").forEach(function (node) { node.hidden = !showOrder; });
        all(".reusable-column").forEach(function (node) { node.hidden = !isInvoiceMode(); });

        if (!state.items.length) {
            body.innerHTML = '<tr><td class="empty" colspan="14">No Products added.</td></tr>';
            mobile.innerHTML = "";
            byId("itemsHelp").textContent = "No Products added.";
            recalculateAll();
            return;
        }

        body.innerHTML = state.items.map(function (item, index) {
            var product = state.productMap[String(item.product_id)] || item.snapshot || {};
            var calc = itemCalculation(item);
            var reusable = isInvoiceMode() && Number(product.container_type) === 1;
            var stock = productStock(product);
            var orderPending = state.mode === MODE_ORDER
                ? Math.max(0, numberValue(item.ordered_base_qty || itemBaseQty(item)) - numberValue(item.delivered_base_qty))
                : numberValue(item.order_pending_base_qty);
            var deliveredInfo = state.mode === MODE_ORDER
                ? '<div class="muted">Delivered ' + decimal3(item.delivered_base_qty || 0) + '</div>'
                : '';
            var taxPct = state.taxMode === 1 ? numberValue(product.tax_percentage) : 0;
            var rateReadonly = (!state.locked && (state.ref ? hasAction(ACTION_UPDATE) : hasAction(ACTION_CREATE))) ? '' : ' readonly';
            var disabled = state.locked ? ' disabled' : '';
            return '<tr data-index="' + index + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td><strong>' + escapeHtml(product.product_name || "-") + '</strong><div class="muted">' + escapeHtml(product.hsn_code ? "HSN " + product.hsn_code : "") + '</div>' + deliveredInfo + '</td>' +
                '<td class="stock-cell js-live-stock">' +
                    '<strong class="js-live-stock-available">' + escapeHtml(formatStockQuantity(product, stock)) + '</strong>' +
                    '<div class="muted js-live-stock-status"></div>' +
                '</td>' +
                '<td class="order-column"' + (showOrder ? '' : ' hidden') + '>' + (orderPending > 0 ? '<strong>' + decimal3(orderPending) + '</strong>' : '—') + '</td>' +
                '<td><input class="js-primary-qty" type="text" inputmode="decimal" value="' + (numberValue(item.primary_qty) || "") + '"' + disabled + '><div class="muted">' + escapeHtml(unitName(product.primary_unit)) + '</div><div class="muted js-live-base"></div></td>' +
                '<td>' + (product.secondary_unit ? '<input class="js-secondary-qty" type="text" inputmode="decimal" value="' + (numberValue(item.secondary_qty) || "") + '"' + disabled + '><div class="muted">' + escapeHtml(unitName(product.secondary_unit)) + '</div>' : '—') + '</td>' +
                '<td><input class="js-rate" type="text" inputmode="decimal" value="' + numberValue(item.primary_rate).toFixed(2) + '"' + rateReadonly + disabled + '></td>' +
                '<td class="discount-column"' + (hasAction(ACTION_APPLY_DISCOUNT) ? '' : ' hidden') + '>' +
                    '<select class="js-discount-type"' + disabled + '><option value="1"' + (Number(item.discount_type) === 1 ? ' selected' : '') + '>None</option><option value="2"' + (Number(item.discount_type) === 2 ? ' selected' : '') + '>%</option><option value="3"' + (Number(item.discount_type) === 3 ? ' selected' : '') + '>Amount</option></select></td>' +
                '<td class="discount-column"' + (hasAction(ACTION_APPLY_DISCOUNT) ? '' : ' hidden') + '>' +
                    '<input class="js-discount-value" type="text" inputmode="decimal" value="' + (numberValue(item.discount_value) || "") + '"' + disabled + '></td>' +
                '<td class="reusable-column"' + (isInvoiceMode() ? '' : ' hidden') + '>' + (reusable ? '<input class="js-empty-return" type="text" inputmode="decimal" value="' + (numberValue(item.empty_return_qty) || "") + '"' + disabled + '>' : '—') + '</td>' +
                '<td class="reusable-column"' + (isInvoiceMode() ? '' : ' hidden') + '>' + (reusable ? '<input class="js-damaged-return" type="text" inputmode="decimal" value="' + (numberValue(item.damaged_return_qty) || "") + '"' + disabled + '>' : '—') + '</td>' +
                '<td class="tax-column"' + ((state.taxMode === 0 || !hasAction(ACTION_MANAGE_TAX)) ? ' hidden' : '') + '>' + (taxPct ? taxPct.toFixed(2) + '%' : '0%') + '</td>' +
                '<td class="amount-cell js-live-amount">' + money(calc.after) + '</td>' +
                '<td class="remove-cell">' + (state.locked ? '' : '<button class="row-remove-button js-remove" type="button" title="Remove"><i data-lucide="trash-2"></i></button>') + '</td>' +
                '</tr>';
        }).join("");

        mobile.innerHTML = state.items.map(function (item, index) {
            var product = state.productMap[String(item.product_id)] || item.snapshot || {};
            var calc = itemCalculation(item);
            var reusable = isInvoiceMode() && Number(product.container_type) === 1;
            var orderPending = state.mode === MODE_ORDER
                ? Math.max(0, numberValue(item.ordered_base_qty || itemBaseQty(item)) - numberValue(item.delivered_base_qty))
                : numberValue(item.order_pending_base_qty);
            return '<div class="mobile-item-card" data-mobile-index="' + index + '">' +
                '<div class="mobile-item-top"><span class="mobile-item-number">' + escapeHtml(product.product_name || "-") + '</span>' +
                (state.locked ? '' : '<button class="row-remove-button js-mobile-remove" type="button"><i data-lucide="trash-2"></i></button>') + '</div>' +
                '<div class="mobile-item-meta"><span class="js-mobile-stock-meta">Available ' + decimal3(productStock(product)) + '</span>' + (orderPending > 0 ? '<span>Pending ' + decimal3(orderPending) + '</span>' : '') + '</div>' +
                '<div class="mobile-item-grid">' +
                '<div class="field"><label>' + escapeHtml(unitName(product.primary_unit)) + ' Qty</label><input class="js-mobile-primary" type="text" inputmode="decimal" value="' + (numberValue(item.primary_qty) || "") + '"' + (state.locked ? ' disabled' : '') + '></div>' +
                (product.secondary_unit ? '<div class="field"><label>' + escapeHtml(unitName(product.secondary_unit)) + ' Qty</label><input class="js-mobile-secondary" type="text" inputmode="decimal" value="' + (numberValue(item.secondary_qty) || "") + '"' + (state.locked ? ' disabled' : '') + '></div>' : '') +
                '<div class="field"><label>Rate</label><input class="js-mobile-rate" type="text" inputmode="decimal" value="' + numberValue(item.primary_rate).toFixed(2) + '"' + ((!state.locked && (state.ref ? hasAction(ACTION_UPDATE) : hasAction(ACTION_CREATE))) ? '' : ' readonly') + '></div>' +
                (hasAction(ACTION_APPLY_DISCOUNT) ? '<div class="field"><label>Discount Type</label><select class="js-mobile-discount-type"' + (state.locked ? ' disabled' : '') + '><option value="1"' + (Number(item.discount_type) === 1 ? ' selected' : '') + '>None</option><option value="2"' + (Number(item.discount_type) === 2 ? ' selected' : '') + '>%</option><option value="3"' + (Number(item.discount_type) === 3 ? ' selected' : '') + '>Amount</option></select></div>' : '') +
                (hasAction(ACTION_APPLY_DISCOUNT) ? '<div class="field"><label>Discount Value</label><input class="js-mobile-discount-value" type="text" inputmode="decimal" value="' + (numberValue(item.discount_value) || "") + '"' + (state.locked ? ' readonly' : '') + '></div>' : '') +
                (reusable ? '<div class="field"><label>Empty Return</label><input class="js-mobile-empty" type="text" inputmode="decimal" value="' + (numberValue(item.empty_return_qty) || "") + '"' + (state.locked ? ' readonly' : '') + '></div>' : '') +
                (reusable ? '<div class="field"><label>Damaged Return</label><input class="js-mobile-damaged" type="text" inputmode="decimal" value="' + (numberValue(item.damaged_return_qty) || "") + '"' + (state.locked ? ' readonly' : '') + '></div>' : '') +
                '</div><div class="mobile-item-total"><span>Amount</span><strong class="js-mobile-live-amount">' + money(calc.after) + '</strong></div></div>';
        }).join("");

        byId("itemsHelp").textContent = state.items.length + (state.items.length === 1 ? " Product" : " Products");
        refreshIcons();
        recalculateAll();
    }

    function syncMobileRows() {
        all("#mobileItems .mobile-item-card[data-mobile-index]").forEach(function (card) {
            var item = state.items[Number(card.getAttribute("data-mobile-index"))];
            if (!item) return;
            var primary = card.querySelector(".js-mobile-primary");
            var secondary = card.querySelector(".js-mobile-secondary");
            var rate = card.querySelector(".js-mobile-rate");
            var discountType = card.querySelector(".js-mobile-discount-type");
            var discountValue = card.querySelector(".js-mobile-discount-value");
            var empty = card.querySelector(".js-mobile-empty");
            var damaged = card.querySelector(".js-mobile-damaged");
            if (primary) item.primary_qty = round(numberValue(primary.value), 3);
            if (secondary) item.secondary_qty = round(numberValue(secondary.value), 3);
            if (rate) item.primary_rate = round(numberValue(rate.value), 2);
            if (discountType) item.discount_type = Number(discountType.value || 1);
            if (discountValue) item.discount_value = round(numberValue(discountValue.value), 2);
            if (empty) item.empty_return_qty = round(numberValue(empty.value), 3);
            if (damaged) item.damaged_return_qty = round(numberValue(damaged.value), 3);
        });
    }

    function syncMobileDiscountControls() {
        var type = byId("mobileOverallDiscountType");
        var value = byId("mobileOverallDiscountValue");
        if (!type || !value) return;
        if (document.activeElement !== type) type.value = byId("overallDiscountType").value;
        if (document.activeElement !== value) value.value = byId("overallDiscountValue").value;
    }

    function syncStateToMobileControls() {
        all("#mobileItems .mobile-item-card[data-mobile-index]").forEach(function (card) {
            var item = state.items[Number(card.getAttribute("data-mobile-index"))];
            if (!item) return;

            var map = [
                [".js-mobile-primary", item.primary_qty],
                [".js-mobile-secondary", item.secondary_qty],
                [".js-mobile-rate", numberValue(item.primary_rate).toFixed(2)],
                [".js-mobile-discount-type", String(Number(item.discount_type || 1))],
                [".js-mobile-discount-value", item.discount_value],
                [".js-mobile-empty", item.empty_return_qty],
                [".js-mobile-damaged", item.damaged_return_qty]
            ];

            map.forEach(function (pair) {
                var node = card.querySelector(pair[0]);
                if (!node || document.activeElement === node) return;
                node.value = pair[1] === 0 ? "" : String(pair[1] == null ? "" : pair[1]);
            });
        });
    }

    function syncStateToDesktopControls() {
        all("#salesItemsBody tr[data-index]").forEach(function (row) {
            var item = state.items[Number(row.getAttribute("data-index"))];
            if (!item) return;

            var map = [
                [".js-primary-qty", item.primary_qty],
                [".js-secondary-qty", item.secondary_qty],
                [".js-rate", numberValue(item.primary_rate).toFixed(2)],
                [".js-discount-type", String(Number(item.discount_type || 1))],
                [".js-discount-value", item.discount_value],
                [".js-empty-return", item.empty_return_qty],
                [".js-damaged-return", item.damaged_return_qty]
            ];

            map.forEach(function (pair) {
                var node = row.querySelector(pair[0]);
                if (!node || document.activeElement === node) return;
                node.value = pair[1] === 0 ? "" : String(pair[1] == null ? "" : pair[1]);
            });
        });
    }

    function refreshRenderedItemCalculations() {
        var itemCalcs = state.items.map(function (item) {
            return itemCalculation(item);
        });

        var discountBase = itemCalcs.reduce(function (sum, calc) {
            return sum + numberValue(calc.after);
        }, 0);

        var overallType = Number(byId("overallDiscountType").value || 1);
        var overallValue = numberValue(byId("overallDiscountValue").value);
        var overall = 0;

        if (hasAction(ACTION_APPLY_DISCOUNT)) {
            if (overallType === 2) {
                overall = round(
                    discountBase * Math.min(100, overallValue) / 100,
                    2
                );
            } else if (overallType === 3) {
                overall = Math.min(discountBase, round(overallValue, 2));
            }
        }

        state.items.forEach(function (item, index) {
            var product =
                state.productMap[String(item.product_id)] ||
                item.snapshot ||
                {};

            var calc = itemCalcs[index];
            var overallShare =
                discountBase > 0
                    ? overall * (calc.after / discountBase)
                    : 0;

            var taxableAfterDiscount = Math.max(
                0,
                round(calc.after - overallShare, 2)
            );

            var taxCalc = itemTax(item, taxableAfterDiscount);
            var finalLineAmount = round(taxCalc.net, 2);

            var baseQty = itemBaseQty(item);
            var available = availableStockForItem(product, item);
            var remaining = round(available - baseQty, 3);
            var isShort = isInvoiceMode() && remaining < -0.0005;

            var row = document.querySelector(
                '#salesItemsBody tr[data-index="' + index + '"]'
            );

            if (row) {
                var amount = row.querySelector(".js-live-amount");
                if (amount) amount.textContent = money(finalLineAmount);

                var availableNode =
                    row.querySelector(".js-live-stock-available");

                if (availableNode) {
                    availableNode.textContent =
                        formatStockQuantity(product, available);
                }

                var baseNode = row.querySelector(".js-live-base");
                if (baseNode) {
                    baseNode.textContent =
                        "Base " +
                        decimal3(baseQty) +
                        " " +
                        unitName(baseUnit(product));
                }

                var stockStatus =
                    row.querySelector(".js-live-stock-status");

                if (stockStatus) {
                    if (!isInvoiceMode()) {
                        stockStatus.textContent = "No stock effect";
                    } else if (isShort) {
                        stockStatus.textContent =
                            "Short " +
                            formatStockQuantity(
                                product,
                                Math.abs(remaining)
                            );
                    } else {
                        stockStatus.textContent =
                            "Remaining " +
                            formatStockQuantity(
                                product,
                                Math.max(0, remaining)
                            );
                    }
                }
            }

            var card = document.querySelector(
                '#mobileItems .mobile-item-card[data-mobile-index="' +
                index +
                '"]'
            );

            if (card) {
                var mobileAmount =
                    card.querySelector(".js-mobile-live-amount");

                if (mobileAmount) {
                    mobileAmount.textContent = money(finalLineAmount);
                }

                var meta =
                    card.querySelector(".js-mobile-stock-meta");

                if (meta) {
                    if (!isInvoiceMode()) {
                        meta.textContent =
                            "Base " +
                            decimal3(baseQty) +
                            " · No stock effect";
                    } else if (isShort) {
                        meta.textContent =
                            "Available " +
                            formatStockQuantity(product, available) +
                            " · Short " +
                            formatStockQuantity(
                                product,
                                Math.abs(remaining)
                            );
                    } else {
                        meta.textContent =
                            "Available " +
                            formatStockQuantity(product, available) +
                            " · Remaining " +
                            formatStockQuantity(
                                product,
                                Math.max(0, remaining)
                            );
                    }
                }
            }
        });
    }

    function validateLiveItemStocks(showMessage) {
        if (!isInvoiceMode()) return true;

        for (var i = 0; i < state.items.length; i += 1) {
            var item = state.items[i];
            var product = state.productMap[String(item.product_id)] || item.snapshot || {};
            var required = itemBaseQty(item);
            var available = availableStockForItem(product, item);

            if (required > available + 0.0005) {
                if (showMessage) {
                    showWarning(
                        (product.product_name || "Product") +
                        " stock insufficient. Available " +
                        decimal3(available) +
                        ", required " +
                        decimal3(required) +
                        "."
                    );
                }
                return false;
            }

            if (
                item.source_order_item_id &&
                numberValue(item.order_pending_base_qty) > 0 &&
                required > numberValue(item.order_pending_base_qty) + 0.0005
            ) {
                if (showMessage) {
                    showWarning(
                        (product.product_name || "Product") +
                        " exceeds Customer Order pending quantity."
                    );
                }
                return false;
            }
        }

        return true;
    }

    function recalculateAll() {
        syncMobileDiscountControls();
        var gross = 0;
        var itemDiscount = 0;
        var discountBase = 0;
        state.items.forEach(function (item) {
            var calc = itemCalculation(item);
            gross += calc.gross;
            itemDiscount += calc.discount;
            discountBase += calc.after;
            if (state.mode === MODE_ORDER) item.ordered_base_qty = itemBaseQty(item);
        });
        gross = round(gross, 2);
        itemDiscount = round(itemDiscount, 2);
        discountBase = round(discountBase, 2);

        var overallType = Number(byId("overallDiscountType").value || 1);
        var overallValue = numberValue(byId("overallDiscountValue").value);
        var overall = 0;
        if (hasAction(ACTION_APPLY_DISCOUNT)) {
            if (overallType === 2) overall = round(discountBase * Math.min(100, overallValue) / 100, 2);
            else if (overallType === 3) overall = Math.min(discountBase, round(overallValue, 2));
        }

        var taxTotal = 0;
        var netItems = 0;
        state.items.forEach(function (item) {
            var calc = itemCalculation(item);
            var share = discountBase > 0 ? overall * (calc.after / discountBase) : 0;
            var amount = Math.max(0, calc.after - share);
            var tax = itemTax(item, amount);
            taxTotal += tax.tax;
            netItems += tax.net;
        });
        taxTotal = round(taxTotal, 2);
        netItems = round(netItems, 2);

        var otherCharges = hasAction(ACTION_UPDATE) ? numberValue(byId("otherCharges").value) : 0;
        var beforeRound = round(netItems + otherCharges, 2);
        var roundOff = state.roundOffEnabled && hasAction(ACTION_UPDATE) ? round(Math.round(beforeRound) - beforeRound, 2) : 0;
        var grand = round(beforeRound + roundOff, 2);
        var currentPayments = readPayments(false) || [];
        var newReceived = currentPayments.reduce(function (sum, p) { return sum + numberValue(p.amount); }, 0);
        var acceptsPayment = (isInvoiceMode() || state.mode === MODE_ORDER) && hasAction(ACTION_RECEIVE_PAYMENT);
        var priorPaid = acceptsPayment ? round(state.existingPaid + (isInvoiceMode() ? state.sourceOrderAdvance : 0), 2) : 0;
        var received = acceptsPayment ? round(priorPaid + newReceived, 2) : 0;
        var balance = Math.max(0, round(grand - received, 2));

        byId("sumGross").textContent = money(gross);
        byId("sumItemDiscount").textContent = money(itemDiscount);
        byId("sumOverallDiscount").textContent = money(overall);
        byId("sumTax").textContent = money(state.taxMode === 0 ? 0 : taxTotal);
        byId("sumRoundOff").textContent = money(roundOff);
        byId("sumGrandTotal").textContent = money(grand);
        byId("sumPaid").textContent = money(received);
        byId("sumBalance").textContent = money(balance);
        byId("mobileGrandTotal").textContent = money(grand);
        byId("mobilePaidTotal").textContent = money(received);
        byId("mobileBalanceTotal").textContent = money(balance);
        byId("receivedNow").textContent = money(received);
        byId("paymentBalance").textContent = money(balance);
        byId("roundOffButton").textContent = state.roundOffEnabled ? "Unround" : "Round Off";

        refreshRenderedItemCalculations();
    }

    function payload() {
        return {
            ref: reference || undefined,
            mode: state.mode,
            sale_date: byId("saleDate").value || null,
            customer_id: currentCustomerId(),
            vehicle_id: isLineMode() ? currentVehicleId() : null,
            line_id: isLineMode() ? currentLineId() : null,
            source_order_ref: isInvoiceMode() ? (byId("sourceOrder").value || null) : null,
            tax_mode: state.taxMode,
            items_json: JSON.stringify(state.items),
            overall_discount_type: Number(byId("overallDiscountType").value || 1),
            overall_discount_value: numberValue(byId("overallDiscountValue").value),
            other_charges: hasAction(ACTION_UPDATE) ? numberValue(byId("otherCharges").value) : 0,
            round_off_enabled: hasAction(ACTION_UPDATE) ? state.roundOffEnabled : 0,
            remarks: byId("remarks").value.trim(),
            payments_json: JSON.stringify((isInvoiceMode() || state.mode === MODE_ORDER) && hasAction(ACTION_RECEIVE_PAYMENT) ? readPayments() : [])
        };
    }

    function validateBeforeSave() {
        if (!state.items.length) { showWarning("Add at least one Product."); return false; }
        if (!currentCustomerId()) { showWarning("Select Customer."); return false; }
        if (isLineMode()) {
            if (!currentVehicleId()) { showWarning("Select Truck."); return false; }
            if (!state.activeTrip) { showWarning("No active Truck Loading found for the selected Truck" + (currentLineId() ? " and Line." : ".")); return false; }
        }
        for (var i = 0; i < state.items.length; i += 1) {
            if (itemBaseQty(state.items[i]) <= 0.0005) {
                showWarning("Enter quantity for " + ((state.productMap[String(state.items[i].product_id)] || state.items[i].snapshot || {}).product_name || "Product") + ".");
                return false;
            }
        }

        if (!validateLiveItemStocks(true)) return false;

        if ((isInvoiceMode() || state.mode === MODE_ORDER) && hasAction(ACTION_RECEIVE_PAYMENT) && readPayments(true) === null) return false;
        return true;
    }

    async function saveDocument() {
        if (state.saving || state.locked || !validateBeforeSave()) return;
        state.saving = true;
        byId("saveDocumentButton").disabled = true;
        try {
            var result = await App.api("api/sales.php", { method: "POST", body: payload() });
            showSuccess(result.message || "Saved.");
            setTimeout(function () { window.location.href = "sales-list.php"; }, 600);
        } catch (error) {
            if (error && error.errors && window.Validation) Validation.applyErrors(byId("salesForm"), error.errors);
            reportError(error, "Unable to save Sales document.");
        } finally {
            state.saving = false;
            byId("saveDocumentButton").disabled = false;
        }
    }

    function fillPayments(rows, paidAmount, fixedPaidAmount) {
        /* Saved payment rows are the editable source of truth. Account, Amount,
           Reference No and Date can all be changed and saved again. */
        state.payments = Array.isArray(rows) ? rows.slice() : [];
        state.preferredPaymentAccounts = {};
        state.existingPaymentByMode = {};

        state.payments.forEach(function (payment) {
            var mode = Number(payment.payment_mode || 0);
            if (mode < 1 || mode > 4) return;

            var accountId = Number(payment.account_id || 0);
            var bucket = state.existingPaymentByMode[mode];
            if (!bucket) {
                bucket = state.existingPaymentByMode[mode] = {
                    account_id: 0,
                    amount: 0,
                    reference_no: "",
                    detail_date: ""
                };
            }
            bucket.amount = round(bucket.amount + numberValue(payment.amount), 2);
            if (accountId > 0) {
                bucket.account_id = accountId;
                state.preferredPaymentAccounts[mode] = accountId;
            }
            if (String(payment.reference_no || "").trim() !== "") bucket.reference_no = String(payment.reference_no).trim();
            if (String(payment.detail_date || "").trim() !== "") bucket.detail_date = String(payment.detail_date).trim();
        });

        /* Only shared/fixed allocation stays outside the editable rows. */
        state.existingPaid = numberValue(fixedPaidAmount || 0);

        paymentRows().forEach(function (row) {
            row.removeAttribute("data-existing-payment");
            var account = row.querySelector(".pay-account");
            var amount = row.querySelector(".pay-amount");
            var referenceInput = row.querySelector(".pay-reference");
            var dateInput = row.querySelector(".pay-date");
            account.disabled = false;
            amount.readOnly = false;
            referenceInput.readOnly = false;
            dateInput.readOnly = false;
            account.value = "";
            amount.value = "";
            referenceInput.value = "";
            dateInput.value = "";
        });

        renderPaymentAccounts();

        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode") || 0);
            var payment = state.existingPaymentByMode[mode];
            if (!payment || numberValue(payment.amount) <= 0) return;
            var account = row.querySelector(".pay-account");
            if (payment.account_id > 0) account.value = String(payment.account_id);
            row.querySelector(".pay-amount").value = numberValue(payment.amount).toFixed(2);
            row.querySelector(".pay-reference").value = payment.reference_no || "";
            row.querySelector(".pay-date").value = payment.detail_date || "";
        });

        renderPayments();
    }

    function remapStoredItemToCurrentUnits(row, product) {
        var primaryQty = numberValue(row.primary_qty);
        var secondaryQty = numberValue(row.secondary_qty);
        var primaryRate = numberValue(row.rate);

        if (
            product &&
            product.primary_unit &&
            product.secondary_unit
        ) {
            var currentPrimaryId =
                Number(product.primary_unit.product_unit_id || 0);

            var currentSecondaryId =
                Number(product.secondary_unit.product_unit_id || 0);

            var storedPrimaryId =
                Number(row.product_unit_id || 0);

            var storedSecondaryId =
                Number(row.secondary_product_unit_id || 0);

            /*
             * Existing legacy Sales row:
             * stored Primary PCS + Secondary Box
             *
             * Current normalized Sales UI:
             * Primary Box + Secondary PCS
             */
            if (
                storedPrimaryId === currentSecondaryId &&
                storedSecondaryId === currentPrimaryId
            ) {
                var oldPrimaryQty = primaryQty;
                primaryQty = secondaryQty;
                secondaryQty = oldPrimaryQty;

                var storedPrimaryConversion =
                    Math.max(1, numberValue(row.conversion_qty || 1));

                primaryRate = round(
                    primaryRate *
                    primaryConversion(product) /
                    storedPrimaryConversion,
                    2
                );
            }
        }

        return {
            primary_qty: primaryQty,
            secondary_qty: secondaryQty,
            primary_rate: primaryRate
        };
    }

    function normalizeLoadedItem(row) {
        var product = state.productMap[String(row.product_id)] || null;
        if (!product) {
            product = {
                id: Number(row.product_id),
                product_name: row.product_name,
                product_code: row.product_code,
                container_type: Number(row.container_type),
                gst_type: Number(row.gst_type),
                tax_percentage: numberValue(row.tax_percentage),
                primary_unit: {
                    product_unit_id: Number(row.product_unit_id),
                    unit_name: row.primary_unit_name,
                    short_name: row.primary_short_name,
                    conversion_qty: numberValue(row.conversion_qty) || 1
                },
                secondary_unit: row.secondary_product_unit_id ? {
                    product_unit_id: Number(row.secondary_product_unit_id),
                    unit_name: row.secondary_unit_name,
                    short_name: row.secondary_short_name,
                    conversion_qty: numberValue(row.secondary_conversion_qty)
                } : null,
                plant_stock: 0,
                truck_stock: 0
            };

            if (
                product.secondary_unit &&
                numberValue(product.secondary_unit.conversion_qty) >
                numberValue(product.primary_unit.conversion_qty)
            ) {
                var tmpUnit = product.primary_unit;
                product.primary_unit = product.secondary_unit;
                product.secondary_unit = tmpUnit;
            }
        }

        var normalizedStored = remapStoredItemToCurrentUnits(row, product);

        return {
            item_id: Number(row.id),
            product_id: Number(row.product_id),
            primary_qty: normalizedStored.primary_qty,
            secondary_qty: normalizedStored.secondary_qty,
            primary_rate: normalizedStored.primary_rate,
            discount_type: Number(row.discount_type || 1),
            discount_value: numberValue(row.discount_value),
            empty_return_qty: numberValue(row.empty_return_qty),
            damaged_return_qty: numberValue(row.damaged_return_qty),
            lost_settled_qty: numberValue(row.lost_settled_qty),
            source_order_item_id: row.source_order_item_id ? Number(row.source_order_item_id) : null,
            order_pending_base_qty: numberValue(row.pending_base_qty),
            ordered_base_qty: numberValue(row.ordered_base_qty),
            delivered_base_qty: numberValue(row.delivered_base_qty),
            original_base_qty: numberValue(row.base_qty),
            snapshot: product
        };
    }

    async function fillExisting(data) {
        var sale = data.sale || {};
        state.mode = Number(sale.mode || MODE_QUOTATION);
        state.status = Number(sale.status || 1);
        state.documentType = Number(sale.document_type || 0);
        state.deliveryStatus = Number(sale.delivery_status || 0);
        state.taxMode = Number(sale.tax_mode) === 0 ? 0 : 1;
        state.roundOffEnabled = Number(sale.round_off_enabled || 0) === 1 ? 1 : 0;
        state.currentSaleNo = sale.sale_no || "";
        /* Whole-page edit: every user-entered field remains editable after posting
           when Update permission is available. Cancelled documents stay read-only.
           Delivered Customer Orders keep only the conversion guard in the backend so
           linked delivery history cannot be corrupted. */
        state.locked = state.status === 3 || !hasAction(ACTION_UPDATE);
        state.modeLocked = false;

        renderModeOptions();
        byId("saleMode").value = String(state.mode);
        byId("saleNo").value = sale.sale_no || "";
        byId("saleDate").value = sale.sale_date || "";
        byId("remarks").value = sale.remarks || "";
        byId("overallDiscountType").value = String(Number(sale.overall_discount_type || 1));
        byId("overallDiscountValue").value = numberValue(sale.overall_discount_value) > 0 ? numberValue(sale.overall_discount_value).toFixed(2) : "";
        byId("otherCharges").value = numberValue(sale.other_charges) > 0 ? numberValue(sale.other_charges).toFixed(2) : "";

        if (state.mode === MODE_LINE) {
            lineSelect.setOptions(optionRows(state.lines, lineText), String(sale.line_id || ""));
            byId("lineId").value = String(sale.line_id || "");
            filterCustomersForSelectedLine(String(sale.customer_id || ""));
            vehicleSelect.setOptions(optionRows(state.vehicles, vehicleText), String(sale.vehicle_id || ""));
            byId("vehicleId").value = String(sale.vehicle_id || "");
        } else {
            state.customers = state.masterCustomers.slice();
            rebuildMaps();
            customerSelect.setOptions(optionRows(state.customers, customerText), String(sale.customer_id || ""));
            byId("customerId").value = String(sale.customer_id || "");
        }

        state.items = (data.items || []).map(normalizeLoadedItem);
        fillPayments(data.payments || [], sale.paid_amount || 0, data.fixed_paid_amount || 0);
        await loadCustomerSummaryAndOrders();
        if (sale.source_order_ref) {
            if (!state.sourceOrderMap[sale.source_order_ref]) {
                byId("sourceOrder").insertAdjacentHTML("beforeend", '<option value="' + escapeHtml(sale.source_order_ref) + '">Linked Customer Order</option>');
            }
            byId("sourceOrder").value = sale.source_order_ref;
            state.sourceOrderAdvance = state.sourceOrderMap[sale.source_order_ref] ? numberValue(state.sourceOrderMap[sale.source_order_ref].advance_paid) : 0;
            renderPayments();
        }
        if (state.mode === MODE_LINE && !state.locked) await loadLineContext();
        applyTaxMode(state.taxMode, false);
        applyModeUI();
    }

    function resetForm() {
        if (reference) { window.location.reload(); return; }
        state.items = [];
        state.sourceOrders = [];
        state.sourceOrderMap = {};
        state.reusableBalances = {};
        state.roundOffEnabled = 0;
        state.activeTrip = null;
        byId("saleNo").value = "";
        byId("customerId").value = "";
        state.customers = isLineMode() ? [] : state.masterCustomers.slice();
        rebuildMaps();
        customerSelect.setOptions(optionRows(state.customers, customerText), "");
        byId("vehicleId").value = "";
        vehicleSelect.setOptions(optionRows(state.vehicles, vehicleText), "");
        byId("lineId").value = "";
        lineSelect.setOptions(optionRows(state.lines, lineText), "");
        byId("sourceOrder").innerHTML = '<option value="">Spot Sale / No Order</option>';
        byId("customerOutstanding").value = "";
        byId("remarks").value = "";
        byId("overallDiscountType").value = "1";
        byId("overallDiscountValue").value = "";
        byId("otherCharges").value = "";
        fillPayments([], 0);
        clearEntry();
        renderItems();
        applyModeUI();
    }

    async function handleModeChange() {
        var newMode = Number(byId("saleMode").value || state.mode);
        if (state.items.length && newMode !== state.mode) {
            if (!window.confirm("Changing Sale Type will keep the current Product rows but will change stock/payment behavior. Continue?")) {
                byId("saleMode").value = String(state.mode);
                return;
            }
        }
        state.mode = newMode;
        state.activeTrip = null;
        if (newMode === MODE_QUOTATION || newMode === MODE_ORDER) {
            state.items.forEach(function (item) {
                item.source_order_item_id = null;
                item.order_pending_base_qty = 0;
                if (newMode === MODE_ORDER) item.ordered_base_qty = itemBaseQty(item);
            });
        }
        byId("sourceOrder").value = "";
        state.sourceOrders = [];
        state.sourceOrderMap = {};
        state.sourceOrderAdvance = 0;
        renderSourceOrders();
        if (isLineMode()) {
            state.products = [];
            state.productMap = {};
            productSelect.setOptions([], "");
            byId("lineId").value = "";
            lineSelect.setOptions(optionRows(state.lines, lineText), "");
            byId("vehicleId").value = "";
            vehicleSelect.setOptions(optionRows(state.vehicles, vehicleText), "");
            byId("customerId").value = "";
            filterCustomersForSelectedLine("");
            clearActiveLineTrip();
        } else {
            state.customers = state.masterCustomers.slice();
            rebuildMaps();
            customerSelect.setOptions(optionRows(state.customers, customerText), byId("customerId").value || "");
            try { await reloadStandardOptions(currentCustomerId()); } catch (error) { reportError(error, "Unable to change Sale Type."); }
            if (currentCustomerId()) await loadCustomerSummaryAndOrders();
        }
        applyModeUI();
        if (!reference) await loadPreviewNumber();
    }

    async function load() {
        state.loading = true;
        try {
            var options = await App.api("api/sales.php?options=1");
            state.actions = (options.data.allowed_actions || []).map(Number);
            state.allowedModes = (options.data.allowed_modes || []).map(Number);
            state.mode = state.allowedModes.length ? Number(state.allowedModes[0]) : MODE_QUOTATION;
            setMasterOptions(options.data, {});
            renderModeOptions();

            if (!hasAction(ACTION_MANAGE_TAX) && !reference) state.taxMode = 1;
            applyTaxMode(state.taxMode, false);

            if (reference) {
                var result = await App.api("api/sales.php?ref=" + encodeURIComponent(reference));
                state.actions = (result.data.allowed_actions || []).map(Number);
                state.allowedModes = (result.data.allowed_modes || []).map(Number);
                var customerId = Number(result.data.sale.customer_id || 0);
                var priced = await App.api("api/sales.php?options=1&customer_id=" + customerId);
                state.actions = (priced.data.allowed_actions || state.actions).map(Number);
                state.allowedModes = (priced.data.allowed_modes || state.allowedModes).map(Number);
                setMasterOptions(priced.data, { customer: String(customerId), vehicle: String(result.data.sale.vehicle_id || ""), line: String(result.data.sale.line_id || "") });
                await fillExisting(result.data);
            } else {
                state.currentSaleNo = "";
                state.modeLocked = false;
                applyModeUI();
                if (!hasAction(ACTION_CREATE) || !state.allowedModes.length) {
                    state.locked = true;
                    applyModeUI();
                    showWarning("You do not have permission to create a Sales document.");
                }
            }
        } catch (error) {
            state.locked = true;
            applyModeUI();
            reportError(error, "Unable to load Sales form.");
        } finally {
            state.loading = false;
            renderItems();
            refreshIcons();
        }
    }

    document.addEventListener("keydown", function (event) {
        if (event.ctrlKey && event.shiftKey && String(event.key).toLowerCase() === "u") {
            if (!hasAction(ACTION_MANAGE_TAX) || state.locked) return;
            event.preventDefault();
            if (state.items.length && !window.confirm("Changing GST / Non-GST will recalculate every item and total. Continue?")) return;
            applyTaxMode(toggleTaxModePreference(), false);
        }
    });

    byId("saleMode").addEventListener("change", handleModeChange);
    byId("customerId").addEventListener("change", handleCustomerChange);
    byId("lineId").addEventListener("change", handleLineChange);
    byId("vehicleId").addEventListener("change", loadLineContext);
    byId("sourceOrder").addEventListener("change", function () {
        loadSelectedOrderItems();
        byId("entryOrderPendingField").hidden = !byId("sourceOrder").value;
    });
    byId("entryProduct").addEventListener("change", handleProductChange);

    ["primaryQty","secondaryQty","primaryRate","discountValue"].forEach(function (id) {
        byId(id).addEventListener("input", refreshEntryUnitSummary);
    });

    byId("discountType").addEventListener("change", refreshEntryUnitSummary);
    byId("addProductButton").addEventListener("click", addProductFromEntry);

    function recalculateFromDesktop() {
        readItemRows();
        syncStateToMobileControls();
        recalculateAll();
    }

    function recalculateFromMobile() {
        syncMobileRows();
        syncStateToDesktopControls();
        recalculateAll();
    }

    byId("salesItemsBody").addEventListener("input", recalculateFromDesktop);
    byId("salesItemsBody").addEventListener("change", recalculateFromDesktop);
    byId("salesItemsBody").addEventListener("click", function (event) {
        var button = event.target.closest(".js-remove");
        if (!button) return;
        var index = Number(button.closest("tr[data-index]").getAttribute("data-index"));
        if (state.mode === MODE_ORDER && numberValue(state.items[index].delivered_base_qty) > 0.0005) {
            showWarning("Delivered Order Item cannot be removed.");
            return;
        }
        state.items.splice(index, 1);
        renderItems();
    });

    byId("mobileItems").addEventListener("input", recalculateFromMobile);
    byId("mobileItems").addEventListener("change", recalculateFromMobile);
    byId("mobileItems").addEventListener("click", function (event) {
        var button = event.target.closest(".js-mobile-remove");
        if (!button) return;
        var index = Number(button.closest("[data-mobile-index]").getAttribute("data-mobile-index"));
        state.items.splice(index, 1);
        renderItems();
    });

    byId("mobileOverallDiscountType").addEventListener("change", function () {
        byId("overallDiscountType").value = this.value;
        recalculateAll();
    });
    byId("mobileOverallDiscountValue").addEventListener("input", function () {
        byId("overallDiscountValue").value = this.value;
        recalculateAll();
    });
    byId("mobilePaymentMode").addEventListener("change", function () {
        clearMobilePaymentEntry();
        renderMobilePaymentAccount();
    });
    byId("mobilePaymentAddButton").addEventListener("click", addMobilePayment);
    byId("mobilePaymentList").addEventListener("click", function (event) {
        var editButton = event.target.closest(".mobile-payment-edit");
        var removeButton = event.target.closest(".mobile-payment-remove");
        if (!editButton && !removeButton) return;
        var item = event.target.closest("[data-mobile-payment-mode]");
        if (!item) return;
        var mode = Number(item.getAttribute("data-mobile-payment-mode") || 0);
        var row = document.querySelector('tr[data-payment-mode="' + mode + '"]');
        if (!row) return;

        if (editButton) {
            byId("mobilePaymentMode").value = String(mode);
            renderMobilePaymentAccount();
            byId("mobilePaymentAccount").value = row.querySelector(".pay-account").value || "";
            byId("mobilePaymentAmount").value = row.querySelector(".pay-amount").value || "";
            byId("mobilePaymentReference").value = row.querySelector(".pay-reference").value || "";
            byId("mobilePaymentDate").value = row.querySelector(".pay-date").value || "";
            byId("mobilePaymentAmount").focus();
            return;
        }

        row.querySelector(".pay-account").value = "";
        row.querySelector(".pay-amount").value = "";
        row.querySelector(".pay-reference").value = "";
        row.querySelector(".pay-date").value = "";
        renderMobilePaymentList();
        recalculateAll();
    });

    ["overallDiscountType","overallDiscountValue","otherCharges"].forEach(function (id) {
        byId(id).addEventListener("input", recalculateAll);
        byId(id).addEventListener("change", recalculateAll);
    });
    paymentRows().forEach(function (row) {
        Array.prototype.slice.call(row.querySelectorAll("input,select")).forEach(function (control) {
            control.addEventListener(control.tagName === "SELECT" ? "change" : "input", function () { renderMobilePaymentList(); recalculateAll(); });
        });
    });
    byId("roundOffButton").addEventListener("click", function () {
        state.roundOffEnabled = state.roundOffEnabled ? 0 : 1;
        recalculateAll();
    });
    byId("saveDocumentButton").addEventListener("click", saveDocument);
    byId("clearButton").addEventListener("click", resetForm);
    byId("exitButton").addEventListener("click", function () {
        if (window.history.length > 1) window.history.back();
        else window.location.href = "sales-list.php";
    });

    load();
})(window, document);
</script>
</body>
</html>
