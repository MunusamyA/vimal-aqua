<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Purchase Form';
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

<section class="page-content">

<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-heading">
    <div>
        <h1 id="pageHeading">Create Purchase</h1>
        <p>Draft does not change stock. Post creates Purchase In stock.</p>
    </div>

    <div class="heading-actions">
        <a class="btn gray" href="purchase-list.php">
            <i data-lucide="list"></i>
            Purchase List
        </a>
    </div>
</div>

<form id="purchaseForm" novalidate>
    <input type="hidden" name="ref" id="purchaseRef">

    <div class="card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading">
                    <i data-lucide="receipt-text"></i>
                    Purchase Details
                </h2>
            </div>
        </div>

        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="purchaseNo">Purchase No</label>
                    <input class="input" id="purchaseNo" name="purchase_no"
                           type="text" readonly aria-readonly="true"
                           placeholder="Auto generated">
                </div>

                <div class="field col-3">
                    <label for="purchaseDate" class="required">Purchase Date</label>
                    <input class="input" id="purchaseDate" name="purchase_date"
                           type="date" required
                           value="<?php echo web_h(date('Y-m-d')); ?>"
                           data-required-message="Purchase Date is required.">
                </div>

                <div class="field col-3">
                    <label for="supplierId" class="required">Supplier</label>
                    <select class="select" id="supplierId" name="supplier_id"
                            required data-placeholder="Select Supplier"
                            data-required-message="Select Supplier.">
                        <option value="">Select Supplier</option>
                    </select>
                </div>

                <div class="field col-3">
                    <label for="supplierInvoiceNo">Supplier Invoice No</label>
                    <input class="input" id="supplierInvoiceNo" name="supplier_invoice_no"
                           type="text" maxlength="60"
                           placeholder="Enter supplier invoice no">
                </div>
            </div>
        </div>
    </div>

    <div class="app-section-title">Product Entry</div>

    <div class="card form-section">
        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="entryProduct">Product</label>
                    <select class="select" id="entryProduct" data-placeholder="Select Product">
                        <option value="">Select Product</option>
                    </select>
                </div>

                <div class="field col-1">
                    <label for="entryPrimaryQty" id="entryPrimaryQtyLabel">Primary Qty</label>
                    <input class="input" id="entryPrimaryQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-1">
                    <label for="entrySecondaryQty" id="entrySecondaryQtyLabel">Secondary Qty</label>
                    <input class="input" id="entrySecondaryQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000" disabled>
                </div>

                <div class="field col-1">
                    <label for="entryFreePrimaryQty" id="entryFreePrimaryQtyLabel">Free Primary</label>
                    <input class="input" id="entryFreePrimaryQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-1">
                    <label for="entryFreeSecondaryQty" id="entryFreeSecondaryQtyLabel">Free Secondary</label>
                    <input class="input" id="entryFreeSecondaryQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000" disabled>
                </div>

                <div class="field col-1">
                    <label for="entryRate" id="entryRateLabel">Primary Rate</label>
                    <input class="input" id="entryRate" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="2"
                           placeholder="0.00">
                </div>

                <div class="field col-2">
                    <label for="entryDiscountType">Discount Type</label>
                    <select class="select" id="entryDiscountType">
                        <option value="1">None</option>
                        <option value="2">Percentage</option>
                        <option value="3">Amount</option>
                    </select>
                </div>

                <div class="field col-1">
                    <label for="entryDiscountValue">Discount</label>
                    <input class="input" id="entryDiscountValue" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="2"
                           placeholder="0.00">
                </div>

                <div class="field col-1">
                    <label for="addItemButton">&nbsp;</label>
                    <button class="btn btn-primary" id="addItemButton" type="button"
                            title="Add Product" aria-label="Add Product">
                        <i data-lucide="plus"></i>
                    </button>
                </div>
            </div>

            <div id="entryProductInfo" hidden></div>
        </div>
    </div>

    <div class="app-section-title">Purchase Items</div>

    <div class="card table-card form-section">
        <div class="app-table-wrap">
            <table class="app-editable-table" id="itemsTable">
                <thead>
                <tr>
                    <th class="cell-index">#</th>
                    <th class="cell-main">Product</th>
                    <th>Primary Qty</th>
                    <th>Secondary Qty</th>
                    <th>Free Primary</th>
                    <th>Free Secondary</th>
                    <th class="cell-rate">Primary Rate</th>
                    <th>Total Stock Qty</th>
                    <th class="cell-discount">Discount</th>
                    <th class="cell-tax">Tax</th>
                    <th class="cell-amount">Net</th>
                    <th class="cell-action">Action</th>
                </tr>
                </thead>
                <tbody id="itemsBody">
                <tr>
                    <td class="empty" colspan="12">No Purchase Items added.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="app-section-title">Discount, Payment & Summary</div>

    <div class="app-split-grid form-section">
        <div class="app-side-card">
            <div class="app-side-card-head">Purchase Adjustments</div>

            <div class="app-side-card-body">
                <div class="app-inline-grid">
                    <div class="field">
                        <label for="overallDiscountType">Overall Discount Type</label>
                        <select class="select" id="overallDiscountType" name="overall_discount_type">
                            <option value="1">None</option>
                            <option value="2">Percentage</option>
                            <option value="3">Amount</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="overallDiscountValue">Overall Discount Value</label>
                        <input class="input" id="overallDiscountValue" name="overall_discount_value"
                               type="text" inputmode="decimal"
                               data-validation="decimal" data-decimal-places="2"
                               placeholder="0.00">
                    </div>

                    <div class="field">
                        <label for="otherCharges">Other Charges</label>
                        <input class="input" id="otherCharges" name="other_charges"
                               type="text" inputmode="decimal"
                               data-validation="decimal" data-decimal-places="2"
                               placeholder="0.00">
                    </div>

                    <div class="field">
                        <label for="roundOff">Round Off</label>
                        <div class="input-group">
                            <div class="input-group-control">
                                <input class="input" id="roundOff" name="round_off"
                                       type="text" inputmode="decimal"
                                       placeholder="0.00" readonly aria-readonly="true">
                            </div>
                            <button class="btn gray" id="roundOffButton" type="button">Round Off</button>
                        </div>
                    </div>
                </div>

                <div class="app-section-title">Pay Now - Used Only When Posting</div>

                <div class="app-table-wrap">
                    <table class="app-editable-table" id="paymentTable">
                        <thead>
                        <tr>
                            <th>Mode</th>
                            <th>Account</th>
                            <th>Amount</th>
                            <th>Reference No</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr data-mode="cash" data-account-type="1">
                            <td><strong>Cash</strong></td>
                            <td>
                                <select class="select js-payment-account" data-placeholder="Select Cash Account">
                                    <option value="">Select Cash Account</option>
                                </select>
                            </td>
                            <td>
                                <input class="input js-payment-amount" type="text" inputmode="decimal"
                                       data-validation="decimal" data-decimal-places="2"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <input class="input js-payment-reference" type="text" maxlength="100"
                                       placeholder="Optional">
                            </td>
                            <td>
                                <input class="input js-payment-date" type="date">
                            </td>
                        </tr>

                        <tr data-mode="upi" data-account-type="3">
                            <td><strong>UPI</strong></td>
                            <td>
                                <select class="select js-payment-account" data-placeholder="Select UPI Account">
                                    <option value="">Select UPI Account</option>
                                </select>
                            </td>
                            <td>
                                <input class="input js-payment-amount" type="text" inputmode="decimal"
                                       data-validation="decimal" data-decimal-places="2"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <input class="input js-payment-reference" type="text" maxlength="100"
                                       placeholder="UTR / Ref No">
                            </td>
                            <td>
                                <input class="input js-payment-date" type="date">
                            </td>
                        </tr>

                        <tr data-mode="bank" data-account-type="2">
                            <td><strong>Bank</strong></td>
                            <td>
                                <select class="select js-payment-account" data-placeholder="Select Bank Account">
                                    <option value="">Select Bank Account</option>
                                </select>
                            </td>
                            <td>
                                <input class="input js-payment-amount" type="text" inputmode="decimal"
                                       data-validation="decimal" data-decimal-places="2"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <input class="input js-payment-reference" type="text" maxlength="100"
                                       placeholder="Transaction / Ref No">
                            </td>
                            <td>
                                <input class="input js-payment-date" type="date">
                            </td>
                        </tr>

                        <tr data-mode="cheque" data-account-type="2">
                            <td><strong>Cheque</strong></td>
                            <td>
                                <select class="select js-payment-account" data-placeholder="Select Bank Account">
                                    <option value="">Select Bank Account</option>
                                </select>
                            </td>
                            <td>
                                <input class="input js-payment-amount" type="text" inputmode="decimal"
                                       data-validation="decimal" data-decimal-places="2"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <input class="input js-payment-reference" type="text" maxlength="100"
                                       placeholder="Cheque No">
                            </td>
                            <td>
                                <input class="input js-payment-date" type="date">
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="field">
                    <label for="paymentRemarks">Payment Remarks</label>
                    <input class="input" id="paymentRemarks" name="payment_remarks"
                           type="text" maxlength="255"
                           placeholder="Optional payment remarks">
                </div>

                <div class="app-total-group">
                    <div class="app-total-line">
                        <span>Split Paid Total</span>
                        <strong id="paidNowView">₹0.00</strong>
                    </div>

                    <div class="app-total-line">
                        <span>Balance After Payment</span>
                        <strong id="paymentBalanceView">₹0.00</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-side-card">
            <div class="app-side-card-head">Purchase Summary</div>

            <div class="app-side-card-body">
                <div class="app-summary-row">
                    <span>Subtotal</span>
                    <strong id="sumSubtotal">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Item Discount</span>
                    <strong id="sumItemDiscount">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Overall Discount</span>
                    <strong id="sumOverallDiscount">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Tax</span>
                    <strong id="sumTax">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Other Charges</span>
                    <strong id="sumOtherCharges">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Round Off</span>
                    <strong id="sumRoundOff">₹0.00</strong>
                </div>

                <div class="app-summary-row total">
                    <span>Grand Total</span>
                    <strong id="sumGrandTotal">₹0.00</strong>
                </div>

                <div class="app-summary-actions" id="formActions">
                    <button class="btn gray" id="saveDraftButton" type="button">
                        <i data-lucide="save"></i>
                        Save Draft
                    </button>

                    <button class="btn btn-primary" id="postButton" type="button">
                        <i data-lucide="check-circle-2"></i>
                        Post Purchase
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function (window, document) {
    "use strict";

    var form = document.getElementById("purchaseForm");
    var reference = new URLSearchParams(location.search).get("ref") || "";
    var viewOnly = new URLSearchParams(location.search).get("view") === "1";

    var supplierNode = document.getElementById("supplierId");
    var productNode = document.getElementById("entryProduct");

    var supplierSelect = GlobalSelect.init(supplierNode, {placeholder:"Select Supplier"});
    var productSelect = GlobalSelect.init(productNode, {placeholder:"Select Product"});

    var itemsBody = document.getElementById("itemsBody");
    var paymentRows = Array.prototype.slice.call(document.querySelectorAll("#paymentTable tbody tr"));
    var saveDraftButton = document.getElementById("saveDraftButton");
    var postButton = document.getElementById("postButton");

    var state = {
        suppliers:[],
        products:[],
        accounts:[],
        productMap:{},
        items:[],
        status:1,
        allowedActions:[],
        savedPaidAmount:0,
        paymentRowSelects:[],
        roundEnabled:false
    };

    function num(value) {
        var number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function round(value, places) {
        var power = Math.pow(10, places || 2);
        return Math.round((num(value) + Number.EPSILON) * power) / power;
    }

    function money(value) {
        return "₹" + num(value).toLocaleString("en-IN", {
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function qtyText(value) {
        return num(value).toLocaleString("en-IN", {
            minimumFractionDigits:0,
            maximumFractionDigits:3
        });
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g,"&amp;")
            .replace(/</g,"&lt;")
            .replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;")
            .replace(/'/g,"&#039;");
    }

    function hasAction(id) {
        return state.allowedActions.map(Number).indexOf(Number(id)) !== -1;
    }

    function optionItems(rows, valueKey, textBuilder) {
        return (rows || []).map(function (row) {
            return {
                value:String(row[valueKey]),
                text:typeof textBuilder === "function"
                    ? textBuilder(row)
                    : String(row[textBuilder] || "")
            };
        });
    }

    function buildProductMap() {
        state.productMap = {};
        state.products.forEach(function (product) {
            state.productMap[String(product.id)] = product;
        });
    }

    function productText(product) {
        var type = Number(product.product_type || 2);
        var typeText = type === 1
            ? "Raw Material"
            : (type === 3 ? "Consumable" : "Finished Product");

        return (product.product_code ? product.product_code + " - " : "") +
            product.product_name + " · " + typeText;
    }

    function unitText(unit) {
        if (!unit) return "-";
        return unit.short_name || unit.unit_name || "-";
    }

    function currentProduct() {
        return state.productMap[String(productNode.value || "")] || null;
    }

    function productUnit(product, unitType) {
        if (!product) return null;
        return (product.units || []).find(function (unit) {
            return Number(unit.unit_type) === Number(unitType);
        }) || null;
    }

    function selectedProductUnit() {
        return null;
    }

    function applySelectedUnitRate() {
        var product = currentProduct();
        document.getElementById("entryRate").value =
            product ? num(product.purchase_price).toFixed(2) : "";
    }

    function populateUnits() {
        return;
    }

    function applyProductInfo() {
        var product = currentProduct();
        var primary = productUnit(product, 1);
        var secondary = productUnit(product, 2);

        var primaryQty = document.getElementById("entryPrimaryQty");
        var secondaryQty = document.getElementById("entrySecondaryQty");
        var freePrimaryQty = document.getElementById("entryFreePrimaryQty");
        var freeSecondaryQty = document.getElementById("entryFreeSecondaryQty");

        if (!product || !primary) {
            document.getElementById("entryProductInfo").textContent =
                "Select a Product. Primary and Secondary units come automatically from Product Master.";
            document.getElementById("entryRate").value = "";
            document.getElementById("entryRate").placeholder = "0.00";
            document.getElementById("entryPrimaryQtyLabel").textContent = "Primary Qty";
            document.getElementById("entrySecondaryQtyLabel").textContent = "Secondary Qty";
            document.getElementById("entryFreePrimaryQtyLabel").textContent = "Free P";
            document.getElementById("entryFreeSecondaryQtyLabel").textContent = "Free S";
            document.getElementById("entryRateLabel").textContent = "Primary Rate";
            secondaryQty.disabled = true;
            freeSecondaryQty.disabled = true;
            return;
        }

        var primaryName = unitText(primary);
        var secondaryName = secondary ? unitText(secondary) : "No Secondary";

        document.getElementById("entryPrimaryQtyLabel").textContent = primaryName + " Qty";
        document.getElementById("entryFreePrimaryQtyLabel").textContent = "Free " + primaryName;
        document.getElementById("entrySecondaryQtyLabel").textContent = secondaryName + " Qty";
        document.getElementById("entryFreeSecondaryQtyLabel").textContent = secondary ? "Free " + secondaryName : "Free Secondary";
        document.getElementById("entryRateLabel").textContent = primaryName + " Rate";

        secondaryQty.disabled = !secondary;
        freeSecondaryQty.disabled = !secondary;
        if (!secondary) {
            secondaryQty.value = "";
            freeSecondaryQty.value = "";
        }

        primaryQty.disabled = false;
        freePrimaryQty.disabled = false;
        var rateInput = document.getElementById("entryRate");
        rateInput.placeholder = "0.00";
        rateInput.value = num(product.purchase_price) > 0
            ? num(product.purchase_price).toFixed(2)
            : "";

        var gstTypeText = Number(product.gst_type || 2) === 1 ? "Inclusive" : "Exclusive";
        var taxRate = num(product.tax_percentage);
        var primaryConversion = Math.max(1, num(primary.conversion_qty || 1));
        var secondaryConversion = secondary ? Math.max(1, num(secondary.conversion_qty || 1)) : 0;
        var conversionText = "";

        if (secondary) {
            /*
             * Both unit conversions point to the same stock base.
             * New standard example: Box=12, Piece=1 -> 1 Box = 12 Pieces.
             * Legacy example: Piece=1, Box=12 -> 1 Box = 12 Pieces.
             */
            if (primaryConversion >= secondaryConversion) {
                conversionText = " · 1 " + primaryName + " = " +
                    qtyText(primaryConversion / secondaryConversion) + " " + secondaryName;
            } else {
                conversionText = " · 1 " + secondaryName + " = " +
                    qtyText(secondaryConversion / primaryConversion) + " " + primaryName;
            }
        }

        document.getElementById("entryProductInfo").textContent =
            "Primary: " + primaryName +
            (secondary ? " · Secondary/Base: " + secondaryName : "") +
            conversionText +
            " · HSN: " + (product.hsn_code || "Not Set") +
            " · GST Type: " + gstTypeText +
            " · Tax: " + taxRate.toFixed(2) + "%";
    }

    function itemSnapshot(item) {
        var product = state.productMap[String(item.product_id)] || item.product_snapshot || {};
        return {
            product:product,
            primary:productUnit(product, 1) || item.primary_unit_snapshot || {},
            secondary:productUnit(product, 2) || item.secondary_unit_snapshot || null
        };
    }

    function itemBaseQty(item) {
        var snap = itemSnapshot(item);
        var primaryConversion = Math.max(1, num(snap.primary.conversion_qty || 1));
        var secondaryConversion = snap.secondary ? Math.max(1, num(snap.secondary.conversion_qty || 1)) : 0;
        var paid = num(item.primary_qty) * primaryConversion + num(item.secondary_qty) * secondaryConversion;
        var free = num(item.free_primary_qty) * primaryConversion + num(item.free_secondary_qty) * secondaryConversion;
        return {
            paid:round(paid,3),
            free:round(free,3),
            stock:round(paid + free,3)
        };
    }

    function calculateItems() {
        var rows = [];
        var subtotal = 0;
        var itemDiscountTotal = 0;
        var discountBase = 0;

        state.items.forEach(function (item) {
            var snap = itemSnapshot(item);
            var product = snap.product || {};
            var primary = snap.primary || {};
            var secondary = snap.secondary || null;
            var primaryQty = Math.max(0, num(item.primary_qty));
            var secondaryQty = secondary ? Math.max(0, num(item.secondary_qty)) : 0;
            var freePrimaryQty = Math.max(0, num(item.free_primary_qty));
            var freeSecondaryQty = secondary ? Math.max(0, num(item.free_secondary_qty)) : 0;
            var primaryRate = Math.max(0, num(item.primary_rate));
            var primaryConversion = Math.max(1, num(primary.conversion_qty || 1));
            var secondaryConversion = secondary ? Math.max(1, num(secondary.conversion_qty || 1)) : 0;

            /*
             * Rate conversion uses the same base-unit ratio as stock:
             * Secondary Rate = Primary Rate x Secondary Conv / Primary Conv.
             * Example: Box=12, Piece=1, Box Rate=120 -> Piece Rate=10.
             */
            var secondaryRate = secondary
                ? round(primaryRate * secondaryConversion / primaryConversion, 2)
                : 0;
            var gross = round(primaryQty * primaryRate + secondaryQty * secondaryRate, 2);
            var discountType = Number(item.discount_type || 1);
            var discountValue = Math.max(0, num(item.discount_value));
            var discountAmount = 0;

            if (discountType === 2) {
                discountAmount = round(gross * Math.min(discountValue,100) / 100,2);
            } else if (discountType === 3) {
                discountAmount = round(Math.min(discountValue,gross),2);
            }

            var afterItemDiscount = round(gross - discountAmount,2);
            var base = itemBaseQty(item);

            rows.push({
                source:item,
                product:product,
                primary:primary,
                secondary:secondary,
                primary_qty:primaryQty,
                secondary_qty:secondaryQty,
                free_primary_qty:freePrimaryQty,
                free_secondary_qty:freeSecondaryQty,
                primary_rate:primaryRate,
                secondary_rate:secondaryRate,
                gross:gross,
                discount_amount:discountAmount,
                after_item_discount:afterItemDiscount,
                base_qty:base.paid,
                free_base_qty:base.free,
                stock_base_qty:base.stock,
                overall_discount_amount:0,
                tax_amount:0,
                other_charge_amount:0,
                net_amount:0
            });

            subtotal += gross;
            itemDiscountTotal += discountAmount;
            discountBase += afterItemDiscount;
        });

        subtotal = round(subtotal,2);
        itemDiscountTotal = round(itemDiscountTotal,2);
        discountBase = round(discountBase,2);

        var overallType = Number(document.getElementById("overallDiscountType").value || 1);
        var overallValue = Math.max(0, num(document.getElementById("overallDiscountValue").value));
        var overallDiscount = 0;
        if (overallType === 2) overallDiscount = round(discountBase * Math.min(overallValue,100) / 100,2);
        else if (overallType === 3) overallDiscount = round(Math.min(overallValue,discountBase),2);

        var allocatedDiscount = 0;
        rows.forEach(function(row,index){
            var share = 0;
            if (overallDiscount > 0 && discountBase > 0) {
                if (index === rows.length - 1) share = round(overallDiscount - allocatedDiscount,2);
                else {
                    share = round(overallDiscount * row.after_item_discount / discountBase,2);
                    allocatedDiscount += share;
                }
            }
            row.overall_discount_amount = share;
            var lineAmount = round(row.after_item_discount - share,2);
            var taxRate = Math.max(0, num(row.product.tax_percentage || row.source.tax_percentage));
            var gstType = Number(row.product.gst_type || row.source.gst_type || 2);
            if (gstType === 1 && taxRate > 0) {
                var taxable = round(lineAmount / (1 + taxRate / 100),2);
                row.tax_amount = round(lineAmount - taxable,2);
                row.net_amount = lineAmount;
            } else {
                row.tax_amount = round(lineAmount * taxRate / 100,2);
                row.net_amount = round(lineAmount + row.tax_amount,2);
            }
        });

        var otherCharges = Math.max(0, num(document.getElementById("otherCharges").value));
        var allocationBase = rows.reduce(function(sum,row){
            return sum + Math.max(0,row.after_item_discount-row.overall_discount_amount);
        },0);
        var allocatedOther = 0;
        rows.forEach(function(row,index){
            var share = 0;
            if (otherCharges > 0 && allocationBase > 0) {
                var rowBase = Math.max(0,row.after_item_discount-row.overall_discount_amount);
                if (index === rows.length - 1) share = round(otherCharges - allocatedOther,2);
                else {
                    share = round(otherCharges * rowBase / allocationBase,2);
                    allocatedOther += share;
                }
            }
            row.other_charge_amount = share;
            row.net_amount = round(row.net_amount + share,2);
        });

        var taxTotal = round(rows.reduce(function(sum,row){ return sum + row.tax_amount; },0),2);
        var beforeRound = round(rows.reduce(function(sum,row){ return sum + row.net_amount; },0),2);
        var roundOff = state.roundEnabled ? round(Math.round(beforeRound) - beforeRound,2) : 0;
        var grandTotal = round(beforeRound + roundOff,2);
        document.getElementById("roundOff").value = roundOff.toFixed(2);

        return {
            rows:rows,
            subtotal:subtotal,
            item_discount_total:itemDiscountTotal,
            overall_discount_amount:overallDiscount,
            tax_amount:taxTotal,
            other_charges:round(otherCharges,2),
            before_round:beforeRound,
            round_off:roundOff,
            grand_total:grandTotal
        };
    }

    function renderItems() {
        var calc = calculateItems();

        if (!state.items.length) {
            itemsBody.innerHTML =
                '<tr><td class="empty" colspan="12">No Purchase Items added.</td></tr>';
            updateSummary(calc);
            return;
        }

        function inputValue(value, places) {
            var number = num(value);
            return Math.abs(number) < 0.0000001
                ? ""
                : number.toFixed(places);
        }

        var html = "";

        calc.rows.forEach(function(row,index){
            var item = row.source;
            var product = row.product || {};
            var primary = row.primary || {};
            var secondary = row.secondary || null;
            var primaryConversion = Math.max(1, num(primary.conversion_qty || 1));
            var secondaryConversion = secondary ? Math.max(1, num(secondary.conversion_qty || 1)) : 0;
            var stockUnit = secondary && secondaryConversion <= primaryConversion
                ? unitText(secondary)
                : unitText(primary);
            var taxRate = num(product.tax_percentage || item.tax_percentage);
            var gstType = Number(product.gst_type || item.gst_type || 2);
            var taxText =
                (gstType === 1 ? "Incl. " : "Excl. ") +
                taxRate.toFixed(2) + "% / " +
                money(row.tax_amount);

            html += '<tr data-index="'+index+'">' +

                '<td class="cell-index">'+(index+1)+'</td>' +

                '<td class="cell-main">' +
                    '<strong>'+escapeHtml(product.product_name || item.product_name || "-")+'</strong>' +
                '</td>' +

                '<td>' +
                    '<input type="text" inputmode="decimal" class="js-primary-qty" ' +
                           'data-validation="decimal" data-decimal-places="3" ' +
                           'placeholder="0.000" ' +
                           'value="'+escapeHtml(inputValue(item.primary_qty,3))+'">' +
                '</td>' +

                '<td>' +
                    (secondary
                        ? '<input type="text" inputmode="decimal" class="js-secondary-qty" ' +
                          'data-validation="decimal" data-decimal-places="3" ' +
                          'placeholder="0.000" ' +
                          'value="'+escapeHtml(inputValue(item.secondary_qty,3))+'">'
                        : '<span class="muted">—</span>') +
                '</td>' +

                '<td>' +
                    '<input type="text" inputmode="decimal" class="js-free-primary-qty" ' +
                           'data-validation="decimal" data-decimal-places="3" ' +
                           'placeholder="0.000" ' +
                           'value="'+escapeHtml(inputValue(item.free_primary_qty,3))+'">' +
                '</td>' +

                '<td>' +
                    (secondary
                        ? '<input type="text" inputmode="decimal" class="js-free-secondary-qty" ' +
                          'data-validation="decimal" data-decimal-places="3" ' +
                          'placeholder="0.000" ' +
                          'value="'+escapeHtml(inputValue(item.free_secondary_qty,3))+'">'
                        : '<span class="muted">—</span>') +
                '</td>' +

                '<td class="cell-rate">' +
                    '<input type="text" inputmode="decimal" class="js-item-rate" ' +
                           'data-validation="decimal" data-decimal-places="2" ' +
                           'placeholder="0.00" ' +
                           'value="'+escapeHtml(inputValue(item.primary_rate,2))+'">' +
                '</td>' +

                '<td>' +
                    '<strong>'+escapeHtml(qtyText(row.stock_base_qty) + " " + stockUnit)+'</strong>' +
                '</td>' +

                '<td class="cell-discount">' +
                    '<div class="input-group">' +
                        '<div class="input-group-control">' +
                            '<select class="js-item-discount-type">' +
                                '<option value="1" '+(Number(item.discount_type)===1?'selected':'')+'>None</option>' +
                                '<option value="2" '+(Number(item.discount_type)===2?'selected':'')+'>%</option>' +
                                '<option value="3" '+(Number(item.discount_type)===3?'selected':'')+'>Amount</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="input-group-control">' +
                            '<input type="text" inputmode="decimal" class="js-item-discount-value" ' +
                                   'data-validation="decimal" data-decimal-places="2" ' +
                                   'placeholder="0.00" ' +
                                   'value="'+escapeHtml(inputValue(item.discount_value,2))+'">' +
                        '</div>' +
                    '</div>' +
                '</td>' +

                '<td class="cell-tax">'+escapeHtml(taxText)+'</td>' +

                '<td class="cell-amount">' +
                    '<strong>'+escapeHtml(money(row.net_amount))+'</strong>' +
                '</td>' +

                '<td class="cell-action">' +
                    '<button class="action-button js-remove-item" type="button" ' +
                            'title="Remove Product" aria-label="Remove Product">' +
                        '<i data-lucide="trash-2"></i>' +
                    '</button>' +
                '</td>' +

            '</tr>';
        });

        itemsBody.innerHTML = html;

        if (window.Validation) Validation.init(itemsBody);
        if (window.lucide) window.lucide.createIcons();

        updateSummary(calc);
    }

    function collectPaymentDetails() {
        return paymentRows.map(function (row) {
            var mode = row.getAttribute("data-mode") || "";
            var accountType = Number(row.getAttribute("data-account-type") || 0);
            var account = row.querySelector(".js-payment-account");
            var amount = row.querySelector(".js-payment-amount");
            var reference = row.querySelector(".js-payment-reference");
            var date = row.querySelector(".js-payment-date");

            return {
                mode_key: mode,
                account_type: accountType,
                account_id: account && account.value ? Number(account.value) : null,
                amount: amount ? (amount.value.trim() || "0.00") : "0.00",
                reference_no: reference ? reference.value.trim() : "",
                detail_date: date ? date.value.trim() : ""
            };
        });
    }

    function paymentTotal() {
        return round(collectPaymentDetails().reduce(function(sum, row){
            return sum + Math.max(0, num(row.amount));
        }, 0), 2);
    }

    function updateSummary(calc) {
        calc=calc||calculateItems();
        document.getElementById("sumSubtotal").textContent=money(calc.subtotal);
        document.getElementById("sumItemDiscount").textContent=money(calc.item_discount_total);
        document.getElementById("sumOverallDiscount").textContent=money(calc.overall_discount_amount);
        document.getElementById("sumTax").textContent=money(calc.tax_amount);
        document.getElementById("sumOtherCharges").textContent=money(calc.other_charges);
        document.getElementById("sumRoundOff").textContent=money(calc.round_off);
        document.getElementById("sumGrandTotal").textContent=money(calc.grand_total);
        document.getElementById("roundOffButton").textContent=state.roundEnabled?"Unround":"Round Off";
        var paidNow=paymentTotal();
        document.getElementById("paidNowView").textContent=money(paidNow);
        var currentPaid=state.status===2?num(state.savedPaidAmount):0;
        var after=Math.max(0,calc.grand_total-currentPaid-paidNow);
        document.getElementById("paymentBalanceView").textContent=money(after);
    }

    function clearEntryAfterAdd() {
        /*
         * After Add, clear the complete Product Entry section.
         * Purchase Items remain below; entry fields return to blank placeholders.
         */
        if (productSelect && typeof productSelect.setValue === "function") {
            productSelect.setValue("");
        }

        productNode.value = "";

        document.getElementById("entryPrimaryQty").value = "";
        document.getElementById("entrySecondaryQty").value = "";
        document.getElementById("entryFreePrimaryQty").value = "";
        document.getElementById("entryFreeSecondaryQty").value = "";
        document.getElementById("entryRate").value = "";
        document.getElementById("entryRate").placeholder = "0.00";
        document.getElementById("entryDiscountType").value = "1";
        document.getElementById("entryDiscountValue").value = "";

        applyProductInfo();

        if (productSelect && typeof productSelect.focus === "function") {
            productSelect.focus();
        } else {
            productNode.focus();
        }
    }

    function addItem() {
        var product=currentProduct();
        if(!product){ if(window.showToast) showToast("Select Product.",{type:"warning",duration:2}); return; }
        var primary=productUnit(product,1);
        var secondary=productUnit(product,2);
        if(!primary){ if(window.showToast) showToast("Product has no Primary Unit.",{type:"warning",duration:3}); return; }

        var primaryQty=Math.max(0,num(document.getElementById("entryPrimaryQty").value));
        var secondaryQty=secondary?Math.max(0,num(document.getElementById("entrySecondaryQty").value)):0;
        var freePrimaryQty=Math.max(0,num(document.getElementById("entryFreePrimaryQty").value));
        var freeSecondaryQty=secondary?Math.max(0,num(document.getElementById("entryFreeSecondaryQty").value)):0;

        if(primaryQty<=0 && secondaryQty<=0 && freePrimaryQty<=0 && freeSecondaryQty<=0){
            if(window.showToast) showToast("Enter Primary Qty, Secondary Qty or Free Qty.",{type:"warning",duration:3});
            return;
        }

        var enteredRate = num(document.getElementById("entryRate").value);

        if ((primaryQty > 0 || secondaryQty > 0) && enteredRate <= 0) {
            if (window.showToast) {
                showToast("Enter Primary Rate for paid quantity.", {
                    type:"warning",
                    duration:3
                });
            }
            document.getElementById("entryRate").focus();
            return;
        }

        var duplicate=state.items.some(function(item){ return Number(item.product_id)===Number(product.id); });
        if(duplicate){
            if(window.showToast) showToast("This Product is already added. Edit its Primary / Secondary quantities in the table.",{type:"warning",duration:3});
            return;
        }

        state.items.push({
            product_id:Number(product.id),
            primary_qty:round(primaryQty,3),
            secondary_qty:round(secondaryQty,3),
            free_primary_qty:round(freePrimaryQty,3),
            free_secondary_qty:round(freeSecondaryQty,3),
            primary_rate:round(num(document.getElementById("entryRate").value),2),
            discount_type:Number(document.getElementById("entryDiscountType").value||1),
            discount_value:round(num(document.getElementById("entryDiscountValue").value),2)
        });
        renderItems();
        clearEntryAfterAdd();
    }

    function payload(intent) {
        return {
            ref:reference||"",
            intent:intent,
            purchase_date:document.getElementById("purchaseDate").value,
            supplier_id:Number(supplierNode.value||0),
            supplier_invoice_no:document.getElementById("supplierInvoiceNo").value.trim(),
            overall_discount_type:Number(document.getElementById("overallDiscountType").value||1),
            overall_discount_value:document.getElementById("overallDiscountValue").value.trim()||"0.00",
            other_charges:document.getElementById("otherCharges").value.trim()||"0.00",
            round_off_enabled:state.roundEnabled?1:0,
            payment_remarks:document.getElementById("paymentRemarks").value.trim(),
            payment_details_json:JSON.stringify(collectPaymentDetails()),
            items_json:JSON.stringify(state.items)
        };
    }

    function setReadOnly(readOnly) {
        if(!readOnly) return;
        form.querySelectorAll("input,select,textarea,button").forEach(function(element){
            element.disabled=true;
        });
        document.getElementById("formActions").hidden=true;
    }

    function applyAccountOptions(detailRows) {
        paymentRows.forEach(function(row, index){
            var accountType = Number(row.getAttribute("data-account-type") || 0);
            var accountNode = row.querySelector(".js-payment-account");

            if (!state.paymentRowSelects[index]) {
                state.paymentRowSelects[index] = GlobalSelect.init(accountNode, {
                    placeholder: accountNode.getAttribute("data-placeholder") || "Select Account"
                });
            }

            var choices = optionItems(
                state.accounts.filter(function (account) {
                    return Number(account.account_type) === accountType;
                }),
                "id",
                function (account) {
                    return account.account_name + " · " + account.account_type_label;
                }
            );

            var selectedId = "";
            if (detailRows && detailRows.length) {
                var mode = row.getAttribute("data-mode") || "";
                var detail = detailRows.find(function (r) {
                    return String(r.mode_key || "").toLowerCase() === mode;
                });
                if (detail && detail.account_id) selectedId = String(detail.account_id);
            }

            state.paymentRowSelects[index].setOptions(choices, selectedId);
        });
    }

    function fillPaymentDetails(detailRows) {
        detailRows = detailRows || [];

        paymentRows.forEach(function(row){
            var mode = row.getAttribute("data-mode") || "";
            var detail = detailRows.find(function(item){
                return String(item.mode_key || "").toLowerCase() === mode;
            }) || null;

            row.querySelector(".js-payment-amount").value =
                detail && num(detail.amount) > 0
                    ? num(detail.amount).toFixed(2)
                    : "";
            row.querySelector(".js-payment-reference").value = detail ? (detail.reference_no || "") : "";
            row.querySelector(".js-payment-date").value = detail ? (detail.detail_date || "") : "";
        });

        applyAccountOptions(detailRows);
    }

    async function submitPurchase(intent) {
        Validation.clearForm(form);

        if(!Validation.validateForm(form)) return;

        if(!supplierNode.value){
            Validation.applyErrors(form,{supplier_id:"Select Supplier."});
            return;
        }

        if(!state.items.length){
            if(window.showToast) showToast("Add at least one Purchase Item.",{type:"warning",duration:3});
            return;
        }

        var calc = calculateItems();
        var paidNow = paymentTotal();

        if(intent==="post" && paidNow > calc.grand_total + 0.009){
            App.showError({message:"Split Paid Total cannot exceed Grand Total."},"Split Paid Total cannot exceed Grand Total.");
            return;
        }

        saveDraftButton.disabled=true;
        postButton.disabled=true;

        try{
            var result=await App.api("api/purchases.php",{
                method:reference?"PUT":"POST",
                body:payload(intent)
            });

            if(window.showToast){
                showToast(result.message||"Purchase saved successfully.",{type:"success",duration:2});
            }

            window.setTimeout(function(){
                location.href="purchase-list.php";
            },700);

        }catch(error){
            if (window.Validation && error && error.errors) {
                Validation.applyErrors(form,error.errors || {});
            }
            App.showError(error,"Unable to save Purchase.");
            saveDraftButton.disabled=false;
            postButton.disabled=false;
        }
    }

    function fillPurchase(data) {
        var purchase=data.purchase||{};
        state.status=Number(purchase.status||1);
        state.savedPaidAmount=num(purchase.paid_amount);
        state.roundEnabled=Math.abs(num(purchase.round_off))>=0.005;
        document.getElementById("purchaseRef").value=purchase.ref||"";
        document.getElementById("purchaseNo").value=purchase.purchase_no||"";
        document.getElementById("purchaseDate").value=purchase.purchase_date||"";
        supplierSelect.setOptions(
            optionItems(state.suppliers,"id",function(row){
                return (row.supplier_code?row.supplier_code+" - ":"")+row.supplier_name;
            }),
            String(purchase.supplier_id||"")
        );
        document.getElementById("supplierInvoiceNo").value=purchase.supplier_invoice_no||"";
        document.getElementById("overallDiscountType").value=String(Number(purchase.overall_discount_type||1));
        document.getElementById("overallDiscountValue").value =
            num(purchase.overall_discount_value) > 0
                ? num(purchase.overall_discount_value).toFixed(2)
                : "";

        document.getElementById("otherCharges").value =
            num(purchase.other_charges) > 0
                ? num(purchase.other_charges).toFixed(2)
                : "";
        document.getElementById("roundOff").value=num(purchase.round_off).toFixed(2);
        document.getElementById("paymentRemarks").value=purchase.payment_remarks||"";

        state.items=(data.items||[]).map(function(row){
            return {
                product_id:Number(row.product_id),
                primary_qty:num(row.primary_qty),
                secondary_qty:num(row.secondary_qty),
                free_primary_qty:num(row.free_primary_qty),
                free_secondary_qty:num(row.free_secondary_qty),
                primary_rate:num(row.primary_rate),
                discount_type:Number(row.discount_type||1),
                discount_value:num(row.discount_value)
            };
        });

        fillPaymentDetails(data.payment_details||[]);
        if(state.status===2) document.getElementById("pageHeading").textContent="View Posted Purchase";
        else if(state.status===3) document.getElementById("pageHeading").textContent="View Cancelled Purchase";
        else document.getElementById("pageHeading").textContent="Edit Purchase Draft";
        renderItems();
        setReadOnly(viewOnly || state.status!==1);
    }

    async function load() {
        try{
            var options=await App.api("api/purchases.php?options=1");

            state.allowedActions=(options.data.allowed_actions||[]).map(Number);
            state.suppliers=options.data.suppliers||[];
            state.products=options.data.products||[];
            state.accounts=options.data.accounts||[];

            buildProductMap();

            supplierSelect.setOptions(
                optionItems(state.suppliers,"id",function(row){
                    return (row.supplier_code?row.supplier_code+" - ":"")+row.supplier_name;
                }),
                ""
            );

            productSelect.setOptions(
                optionItems(state.products,"id",productText),
                ""
            );

            document.getElementById("purchaseNo").value=options.data.next_purchase_no||"";
            saveDraftButton.hidden=!hasAction(10);
            postButton.hidden=!hasAction(11);

            if(reference){
                var record=await App.api("api/purchases.php?ref="+encodeURIComponent(reference));
                state.allowedActions=(record.data.allowed_actions||[]).map(Number);
                fillPurchase(record.data);
            }else{
                applyAccountOptions([]);
                if(!hasAction(2)){ setReadOnly(true); }
                renderItems();
            }

        }catch(error){
            setReadOnly(true);
            App.showError(error,"Unable to load Purchase form.");
        }

        if(window.lucide) window.lucide.createIcons();
    }

    productNode.addEventListener("change",applyProductInfo);
    document.getElementById("addItemButton").addEventListener("click",addItem);

    itemsBody.addEventListener("click",function(event){
        var button=event.target.closest(".js-remove-item");
        if(!button) return;

        var row=button.closest("tr[data-index]");
        var index=Number(row?row.getAttribute("data-index"):-1);
        if(index>=0){
            state.items.splice(index,1);
            renderItems();
        }
    });

    itemsBody.addEventListener("input",function(event){
        var row=event.target.closest("tr[data-index]");
        if(!row) return;
        var index=Number(row.getAttribute("data-index"));
        var item=state.items[index];
        if(!item) return;
        if(event.target.matches(".js-primary-qty")) item.primary_qty=event.target.value;
        if(event.target.matches(".js-secondary-qty")) item.secondary_qty=event.target.value;
        if(event.target.matches(".js-free-primary-qty")) item.free_primary_qty=event.target.value;
        if(event.target.matches(".js-free-secondary-qty")) item.free_secondary_qty=event.target.value;
        if(event.target.matches(".js-item-rate")) item.primary_rate=event.target.value;
        if(event.target.matches(".js-item-discount-value")) item.discount_value=event.target.value;
        updateSummary(calculateItems());
    });

    itemsBody.addEventListener("change",function(event){
        var row=event.target.closest("tr[data-index]");
        if(!row) return;

        var index=Number(row.getAttribute("data-index"));
        var item=state.items[index];
        if(!item) return;

        if(event.target.matches(".js-item-discount-type")) item.discount_type=Number(event.target.value||1);
        renderItems();
    });

    ["overallDiscountType","overallDiscountValue","otherCharges"].forEach(function(id){
        document.getElementById(id).addEventListener("input",function(){ updateSummary(calculateItems()); });
        document.getElementById(id).addEventListener("change",function(){ updateSummary(calculateItems()); });
    });

    document.getElementById("roundOffButton").addEventListener("click",function(){
        state.roundEnabled=!state.roundEnabled;
        updateSummary(calculateItems());
    });

    paymentRows.forEach(function(row){
        ["input","change"].forEach(function(evt){
            row.addEventListener(evt, function(event){
                if (event.target.matches(".js-payment-amount, .js-payment-reference, .js-payment-date") ||
                    event.target.matches(".js-payment-account")) {
                    updateSummary(calculateItems());
                }
            });
        });
    });

    saveDraftButton.addEventListener("click",function(){ submitPurchase("draft"); });

    postButton.addEventListener("click",function(){
        if(!window.confirm("Post this Purchase? Stock will increase and split payments will be recorded.")) return;
        submitPurchase("post");
    });

    load();

})(window,document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
            