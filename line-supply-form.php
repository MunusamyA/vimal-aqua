<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Line Supply';
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
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
<main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
<section class="page-content" id="lineSupplyApp">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>
    <div class="page-heading">
        <div>
            <div class="heading-title-row">
                <h1>Line Supply</h1>
                <span class="pill pending" id="tripStatusBadge">New Truck Loading</span>
            </div>
            <p id="tripCurrentLabel">Truck Loading → Sales → Truck Return → Close</p>
        </div>
        <div class="heading-actions">
            <a class="btn gray" href="line-supply-list.php"><i data-lucide="list"></i><span>Line Supply List</span></a>
            <a class="btn gray" href="sales-form.php"><i data-lucide="receipt-text"></i><span>Sales</span></a>
            <button class="btn btn-danger" id="exitButton" type="button"><i data-lucide="log-out"></i><span>Exit</span></button>
        </div>
    </div>

    <form id="lineSupplyForm" novalidate>
        <input id="supplyRef" type="hidden">

        <div class="card form-section">
            <div class="card-header"><h2 class="section-heading"><i data-lucide="truck"></i>Trip Details</h2></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="field col-3">
                        <label for="supplyNo">Supply No</label>
                        <input class="input" id="supplyNo" type="text" readonly placeholder="Auto generated">
                    </div>
                    <div class="field col-3">
                        <label for="supplyDate" class="required">Date</label>
                        <input class="input" id="supplyDate" type="date" required value="<?php echo web_h(date('Y-m-d')); ?>">
                    </div>
                    <div class="field col-3">
                        <label for="vehicleId" class="required">Truck</label>
                        <select class="select" id="vehicleId" required><option value="">Select Truck</option></select>
                    </div>
                    <div class="field col-3">
                        <label for="lineId" class="required">Line</label>
                        <select class="select" id="lineId" required><option value="">Select Line</option></select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field col-12">
                        <label for="remarks">Loading / Trip Remarks</label>
                        <input class="input" id="remarks" type="text" maxlength="255" placeholder="Optional">
                    </div>
                </div>
                <div class="app-entry-note" id="tripHelp">Prepare Truck Loading. Plant stock moves to Truck only when Loading is posted.</div>
            </div>
        </div>

        <div class="card form-section" id="loadingEntryCard">
            <div class="card-header"><h2 class="section-heading"><i data-lucide="package-plus"></i>Truck Loading</h2></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="field col-3">
                        <label for="entryProduct">Product</label>
                        <select class="select" id="entryProduct"><option value="">Select Product</option></select>
                    </div>
                    <div class="field col-2">
                        <label>Plant Stock</label>
                        <input class="input" id="entryPlantStock" type="text" readonly placeholder="0.000">
                    </div>
                    <div class="field col-2">
                        <label id="entryPrimaryQtyLabel" for="entryPrimaryQty">Primary Qty</label>
                        <input class="input" id="entryPrimaryQty" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" placeholder="0.000">
                    </div>
                    <div class="field col-2">
                        <label id="entrySecondaryQtyLabel" for="entrySecondaryQty">Secondary Qty</label>
                        <input class="input" id="entrySecondaryQty" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" placeholder="0.000" disabled>
                    </div>
                    <div class="field col-2">
                        <label id="entryBaseQtyLabel">Loaded Base Qty</label>
                        <input class="input" id="entryBaseQty" type="text" readonly placeholder="0.000">
                    </div>
                    <div class="field col-1">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" id="addLoadingItem" type="button" title="Add Product"><i data-lucide="plus"></i></button>
                    </div>
                </div>
                <div class="app-entry-note" id="entryUnitHelp">
                    Select a Product to see conversion, live Base Qty and remaining Plant Stock.
                </div>
            </div>
        </div>

        <div class="card table-card form-section">
            <div class="card-header">
                <div><h2 class="section-heading"><i data-lucide="packages"></i>Loaded Products</h2><p class="muted" id="loadingItemsHelp">No Products added.</p></div>
            </div>
            <div class="app-table-wrap">
                <table class="app-editable-table">
                    <thead><tr><th>#</th><th>Product</th><th>Plant Stock</th><th id="loadPrimaryHead">Main Qty</th><th id="loadSecondaryHead">Base Qty</th><th id="loadedBaseHead">Loaded Base Qty</th><th>Current Truck Stock</th><th></th></tr></thead>
                    <tbody id="loadingItemsBody"><tr><td class="empty" colspan="8">No Products added.</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="card form-section" id="salesSection" hidden>
            <div class="card-header">
                <div><h2 class="section-heading"><i data-lucide="receipt-text"></i>Customer Sales</h2><p class="muted">Use the common Sales page and select Line Supply Sale.</p></div>
                <div class="heading-actions"><a class="btn btn-primary" id="openSalesButton" href="sales-form.php"><i data-lucide="receipt-text"></i>Open Sales</a></div>
            </div>
            <div class="card-body">
                <div class="app-total-group">
                    <div class="app-total-line"><span>Invoices</span><strong id="invoiceCount">0</strong></div>
                    <div class="app-total-line"><span>Total Sales</span><strong id="totalSales">₹0.00</strong></div>
                    <div class="app-total-line"><span>Total Received</span><strong id="totalReceived">₹0.00</strong></div>
                    <div class="app-total-line"><span>Outstanding</span><strong id="totalOutstanding">₹0.00</strong></div>
                </div>
            </div>
        </div>

        <div class="app-summary-actions">
            <span class="muted" id="tripActionNote"></span>
            <div class="heading-actions">
                <button class="btn gray" id="saveDraftButton" type="button" hidden><i data-lucide="save"></i>Save Draft</button>
                <button class="btn btn-primary" id="postLoadingButton" type="button" hidden><i data-lucide="truck"></i>Post Truck Loading</button>
                <a class="btn btn-primary" id="salesFooterButton" href="sales-form.php" hidden><i data-lucide="receipt-text"></i>Customer Sales</a>
                <a class="btn btn-primary" id="returnFooterButton" href="line-return-form.php" hidden><i data-lucide="undo-2"></i>Truck Return</a>
            </div>
        </div>
    </form>
</main>
<script>
(function (window, document) {
    "use strict";

    var params = new URLSearchParams(window.location.search);
    var reference = params.get("ref") || "";

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_SAVE_DRAFT = 10;
    var ACTION_POST = 11;
    var ACTION_RETURN = 32;

    var vehicleSelect = GlobalSelect.init("#vehicleId", { placeholder: "Select Truck" });
    var lineSelect = GlobalSelect.init("#lineId", { placeholder: "Select Line" });
    var productSelect = GlobalSelect.init("#entryProduct", { placeholder: "Select Product" });

    var state = {
        actions: [],
        status: 1,
        loading: true,
        saving: false,
        vehicles: [],
        lines: [],
        products: [],
        productMap: {},
        items: [],
        financial: {},
        trip: null,
        canLineSale: false
    };

    function byId(id) { return document.getElementById(id); }
    function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
    function numberValue(value) {
        var n = Number(value || 0);
        return Number.isFinite(n) ? n : 0;
    }
    function round3(value) { return Math.round((numberValue(value) + Number.EPSILON) * 1000) / 1000; }
    function decimal3(value) { return numberValue(value).toFixed(3); }

    function qtyText(value) {
        var n = round3(value);
        if (Math.abs(n - Math.round(n)) < 0.0005) return String(Math.round(n));
        return n.toFixed(3).replace(/0+$/, "").replace(/\.$/, "");
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

    function baseUnit(product) {
        if (!product || !product.primary_unit) return null;
        if (!product.secondary_unit) return product.primary_unit;
        return secondaryConversion(product) <= primaryConversion(product)
            ? product.secondary_unit
            : product.primary_unit;
    }

    function conversionText(product) {
        if (!product || !product.primary_unit) return "";

        var pName = unitName(product.primary_unit);
        var pConv = primaryConversion(product);

        if (!product.secondary_unit) {
            return "Primary: " + pName + " · Conversion " + qtyText(pConv);
        }

        var sName = unitName(product.secondary_unit);
        var sConv = secondaryConversion(product);

        if (pConv >= sConv) {
            return "1 " + pName + " = " + qtyText(pConv / sConv) + " " + sName;
        }

        return "1 " + sName + " = " + qtyText(sConv / pConv) + " " + pName;
    }

    function formatStockQuantity(product, baseQty) {
        baseQty = Math.max(0, numberValue(baseQty));

        if (!product || !product.primary_unit) return decimal3(baseQty);

        var primary = product.primary_unit;
        var secondary = product.secondary_unit;
        var pc = primaryConversion(product);

        if (!secondary) {
            return qtyText(baseQty / pc) + " " + unitName(primary) +
                " (" + decimal3(baseQty) + " base)";
        }

        var sc = secondaryConversion(product);

        if (pc >= sc) {
            var pQty = Math.floor((baseQty / pc) + 0.0000001);
            var remainder = Math.max(0, round3(baseQty - pQty * pc));
            var sQty = sc > 0 ? round3(remainder / sc) : 0;

            return qtyText(pQty) + " " + unitName(primary) +
                " + " + qtyText(sQty) + " " + unitName(secondary) +
                " (" + decimal3(baseQty) + " base)";
        }

        var sQtyLarge = Math.floor((baseQty / sc) + 0.0000001);
        var rem = Math.max(0, round3(baseQty - sQtyLarge * sc));
        var pQtySmall = pc > 0 ? round3(rem / pc) : 0;

        return qtyText(pQtySmall) + " " + unitName(primary) +
            " + " + qtyText(sQtyLarge) + " " + unitName(secondary) +
            " (" + decimal3(baseQty) + " base)";
    }
    function money(value) {
        return "₹" + numberValue(value).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    function hasAction(id) { return state.actions.map(Number).indexOf(Number(id)) !== -1; }
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
    function refreshIcons() { if (window.lucide && lucide.createIcons) lucide.createIcons(); }
    function unitName(unit) { return unit ? (unit.short_name || unit.unit_name || "Unit") : "Unit"; }
    function statusLabel(status) {
        status = Number(status);
        if (status === 2) return "Loaded";
        if (status === 3) return "In Route";
        if (status === 4) return "Returned";
        if (status === 5) return "Closed";
        if (status === 6) return "Cancelled";
        return "Draft";
    }
    function currentProduct() { return state.productMap[String(byId("entryProduct").value || "")] || null; }
    function productBaseQty(product, primaryQty, secondaryQty) {
        return round3(
            numberValue(primaryQty) * primaryConversion(product) +
            numberValue(secondaryQty) * secondaryConversion(product)
        );
    }

    function replaceProductInState(product) {
        if (!product || !product.id) return;

        var found = false;
        state.products = state.products.map(function (row) {
            if (Number(row.id) === Number(product.id)) {
                found = true;
                return product;
            }
            return row;
        });

        if (!found) state.products.push(product);
        state.productMap[String(product.id)] = product;
    }

    async function handleProductChange() {
        var productId = Number(byId("entryProduct").value || 0);

        if (!productId) {
            refreshEntryProduct();
            updateLoadingHeaders();
            return;
        }

        try {
            var result = await App.api(
                "api/line-supply.php?product_context=1&product_id=" +
                encodeURIComponent(productId)
            );

            var fresh = result.data.product || null;
            if (fresh) {
                replaceProductInState(fresh);

                productSelect.setOptions(
                    state.products.map(function (row) {
                        return {
                            value: String(row.id),
                            text: (row.product_code ? row.product_code + " - " : "") +
                                row.product_name
                        };
                    }),
                    String(productId)
                );

                byId("entryProduct").value = String(productId);
            }

            refreshEntryProduct();
            updateLoadingHeaders(fresh || currentProduct());
        } catch (error) {
            byId("entryProduct").value = "";
            refreshEntryProduct();
            reportError(error, "Unable to load current Product stock.");
        }
    }

    function setOptions(data) {
        state.vehicles = data.vehicles || [];
        state.lines = data.lines || [];
        state.products = data.products || [];
        state.actions = (data.allowed_actions || []).map(Number);
        state.canLineSale = !!data.can_line_sale;
        state.productMap = {};
        state.products.forEach(function (product) { state.productMap[String(product.id)] = product; });

        vehicleSelect.setOptions(state.vehicles.map(function (row) {
            return { value: String(row.id), text: row.vehicle_no + (row.vehicle_name ? " - " + row.vehicle_name : "") };
        }), "");
        lineSelect.setOptions(state.lines.map(function (row) {
            return { value: String(row.id), text: (row.line_code ? row.line_code + " - " : "") + row.line_name };
        }), "");
        productSelect.setOptions(state.products.map(function (row) {
            return { value: String(row.id), text: (row.product_code ? row.product_code + " - " : "") + row.product_name };
        }), "");
    }

    function updateLoadingHeaders(productHint) {
        var primaryNames = {};
        var secondaryNames = {};

        state.items.forEach(function (item) {
            var product = productForItem(item);
            if (product && product.primary_unit) {
                primaryNames[unitName(product.primary_unit)] = true;
            }
            if (product && product.secondary_unit) {
                secondaryNames[unitName(product.secondary_unit)] = true;
            }
        });

        if (!state.items.length) {
            var product = productHint || currentProduct();
            if (product && product.primary_unit) {
                primaryNames[unitName(product.primary_unit)] = true;
            }
            if (product && product.secondary_unit) {
                secondaryNames[unitName(product.secondary_unit)] = true;
            }
        }

        var pNames = Object.keys(primaryNames);
        var sNames = Object.keys(secondaryNames);

        byId("loadPrimaryHead").textContent =
            pNames.length === 1 ? pNames[0] + " Qty" : "Main Qty";

        byId("loadSecondaryHead").textContent =
            sNames.length === 1 ? sNames[0] + " Qty" : "Base Qty";

        byId("loadedBaseHead").textContent =
            sNames.length === 1
                ? "Loaded " + sNames[0]
                : "Loaded Base Qty";
    }

    function refreshEntryProduct() {
        var product = currentProduct();

        if (!product) {
            byId("entryPlantStock").value = "";
            byId("entryPrimaryQtyLabel").textContent = "Main Qty";
            byId("entrySecondaryQtyLabel").textContent = "Base Qty";
            byId("entryBaseQtyLabel").textContent = "Loaded Base Qty";
            byId("entrySecondaryQty").disabled = true;
            byId("entrySecondaryQty").value = "";
            byId("entryBaseQty").value = "";
            byId("entryUnitHelp").textContent =
                "Select a Product to see conversion, live Base Qty and remaining Plant Stock.";
            byId("addLoadingItem").disabled = state.status !== 1;
            updateLoadingHeaders();
            return;
        }

        byId("entryPlantStock").value =
            formatStockQuantity(product, product.plant_stock || 0);

        byId("entryPrimaryQtyLabel").textContent =
            unitName(product.primary_unit) + " Qty";

        byId("entrySecondaryQtyLabel").textContent =
            product.secondary_unit
                ? unitName(product.secondary_unit) + " Qty"
                : "Base Qty";

        byId("entryBaseQtyLabel").textContent =
            "Loaded " + unitName(baseUnit(product));

        byId("entrySecondaryQty").disabled =
            !product.secondary_unit || state.status !== 1;

        if (!product.secondary_unit) {
            byId("entrySecondaryQty").value = "";
        }

        updateEntryBaseQty();
        updateLoadingHeaders(product);
    }

    function updateEntryBaseQty() {
        var product = currentProduct();

        if (!product) {
            byId("entryBaseQty").value = "";
            return;
        }

        var primaryQty = numberValue(byId("entryPrimaryQty").value);
        var secondaryQty = product.secondary_unit
            ? numberValue(byId("entrySecondaryQty").value)
            : 0;

        var loaded = productBaseQty(product, primaryQty, secondaryQty);
        var available = numberValue(product.plant_stock);
        var remaining = round3(available - loaded);
        var baseName = unitName(baseUnit(product));

        byId("entryBaseQty").value =
            decimal3(loaded) + " " + baseName;

        var parts = [
            conversionText(product),
            "Available: " + formatStockQuantity(product, available),
            "Load Base: " + decimal3(loaded) + " " + baseName
        ];

        if (loaded > 0.0005) {
            if (remaining < -0.0005) {
                parts.push(
                    "⚠ Short by " +
                    formatStockQuantity(product, Math.abs(remaining))
                );
            } else {
                parts.push(
                    "After Load: " +
                    formatStockQuantity(product, Math.max(0, remaining))
                );
            }
        }

        byId("entryUnitHelp").textContent = parts.join(" · ");

        byId("addLoadingItem").disabled =
            state.status !== 1 ||
            loaded <= 0.0005 ||
            loaded > available + 0.0005;
    }

    function clearEntry() {
        /* Same behaviour as Sales Product Entry: once added, clear the
           complete entry row so the next Product can be entered immediately. */
        productSelect.setOptions(state.products.map(function (row) {
            return { value: String(row.id), text: (row.product_code ? row.product_code + " - " : "") + row.product_name };
        }), "");
        byId("entryProduct").value = "";
        byId("entryPlantStock").value = "";
        byId("entryPrimaryQty").value = "";
        byId("entrySecondaryQty").value = "";
        byId("entryBaseQty").value = "";
        byId("entryPrimaryQtyLabel").textContent = "Main Qty";
        byId("entrySecondaryQtyLabel").textContent = "Base Qty";
        byId("entryBaseQtyLabel").textContent = "Loaded Base Qty";
        byId("entrySecondaryQty").disabled = true;
        byId("entryUnitHelp").textContent =
            "Select a Product to see conversion, live Base Qty and remaining Plant Stock.";
        updateLoadingHeaders();
        if (productSelect && typeof productSelect.focus === "function") productSelect.focus();
    }

    function addLoadingItem() {
        var product = currentProduct();
        if (!product) { showWarning("Select Product."); return; }
        if (state.status !== 1) return;
        if (state.items.some(function (item) { return Number(item.product_id) === Number(product.id); })) {
            showWarning("Product already added. Edit the existing row.");
            return;
        }
        var primaryQty = round3(byId("entryPrimaryQty").value);
        var secondaryQty = product.secondary_unit ? round3(byId("entrySecondaryQty").value) : 0;
        if (primaryQty <= 0 && secondaryQty <= 0) { showWarning("Enter loading quantity."); return; }
        var loaded = productBaseQty(product, primaryQty, secondaryQty);
        if (loaded > numberValue(product.plant_stock) + 0.0005) {
            showWarning(
                "Loading quantity exceeds Plant Stock. Available: " +
                formatStockQuantity(product, product.plant_stock) +
                ", Required: " +
                formatStockQuantity(product, loaded)
            );
            return;
        }
        state.items.push({
            product_id: Number(product.id),
            primary_qty: primaryQty,
            secondary_qty: secondaryQty,
            loaded_base_qty: loaded,
            plant_stock: numberValue(product.plant_stock),
            product: product
        });
        renderLoadingItems();
        clearEntry();
    }

    function productForItem(item) {
        return state.productMap[String(item.product_id)] || item.product || {
            product_name: item.product_name || "-",
            product_code: item.product_code || "",
            primary_unit: { unit_name: item.primary_unit_name, short_name: item.primary_short_name, conversion_qty: item.primary_conversion_qty || 1 },
            secondary_unit: item.secondary_product_unit_id ? { unit_name: item.secondary_unit_name, short_name: item.secondary_short_name, conversion_qty: item.secondary_conversion_qty || 1 } : null,
            container_type: item.container_type
        };
    }

    function readLoadingRows() {
        if (state.status !== 1) return;
        all("#loadingItemsBody tr[data-index]").forEach(function (row) {
            var index = Number(row.getAttribute("data-index"));
            var item = state.items[index];
            if (!item) return;
            var product = productForItem(item);
            var primaryInput = row.querySelector(".js-load-primary");
            var secondaryInput = row.querySelector(".js-load-secondary");
            item.primary_qty = round3(primaryInput ? primaryInput.value : item.primary_qty);
            item.secondary_qty = product.secondary_unit ? round3(secondaryInput ? secondaryInput.value : item.secondary_qty) : 0;
            item.loaded_base_qty = productBaseQty(product, item.primary_qty, item.secondary_qty);
        });
    }

    function renderLoadingItems() {
        updateLoadingHeaders();

        var body = byId("loadingItemsBody");
        if (!state.items.length) {
            body.innerHTML = '<tr><td class="empty" colspan="8">No Products added.</td></tr>';
            byId("loadingItemsHelp").textContent = "No Products added.";
            return;
        }
        body.innerHTML = state.items.map(function (item, index) {
            var product = productForItem(item);
            var loaded = numberValue(item.loaded_base_qty || productBaseQty(product, item.primary_qty, item.secondary_qty));
            var plant = item.plant_stock !== undefined ? numberValue(item.plant_stock) : numberValue(product.plant_stock);
            var truck = item.truck_stock !== undefined ? numberValue(item.truck_stock) : (state.status === 1 ? 0 : loaded);
            var disabled = state.status === 1 ? "" : " disabled";
            return '<tr data-index="' + index + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td><strong>' + escapeHtml(product.product_name || item.product_name || "-") + '</strong><div class="muted">' + escapeHtml(product.product_code || item.product_code || "") + '</div></td>' +
                '<td><strong>' + escapeHtml(formatStockQuantity(product, plant)) + '</strong><div class="muted js-load-stock-status">' +
                    (state.status === 1
                        ? 'Remaining ' + escapeHtml(formatStockQuantity(product, Math.max(0, plant - loaded)))
                        : '') +
                '</div></td>' +
                '<td><input class="input js-load-primary" type="text" inputmode="decimal" value="' + (numberValue(item.primary_qty) || "") + '"' + disabled + '><div class="muted">' + escapeHtml(unitName(product.primary_unit)) + '</div></td>' +
                '<td>' + (product.secondary_unit ? '<input class="input js-load-secondary" type="text" inputmode="decimal" value="' + (numberValue(item.secondary_qty) || "") + '"' + disabled + '><div class="muted">' + escapeHtml(unitName(product.secondary_unit)) + '</div>' : '—') + '</td>' +
                '<td data-loaded-index="' + index + '"><strong>' + decimal3(loaded) + '</strong><div class="muted">' + escapeHtml(unitName(baseUnit(product))) + '</div></td>' +
                '<td>' + escapeHtml(formatStockQuantity(product, truck)) + '</td>' +
                '<td >' + (state.status === 1 ? '<button class="action-button js-remove-loading" type="button" data-index="' + index + '" title="Remove"><i data-lucide="trash-2"></i></button>' : '') + '</td>' +
                '</tr>';
        }).join("");
        byId("loadingItemsHelp").textContent = state.items.length + " Product" + (state.items.length === 1 ? "" : "s") + " in this Truck Loading.";
        refreshIcons();
    }

    function normalizeTripItems(rows) {
        return (rows || []).map(function (row) {
            var product = state.productMap[String(row.product_id)] || null;
            return Object.assign({}, row, {
                product_id: Number(row.product_id),
                primary_qty: numberValue(row.primary_qty),
                secondary_qty: numberValue(row.secondary_qty),
                loaded_base_qty: numberValue(row.loaded_base_qty),
                returned_base_qty: numberValue(row.returned_base_qty),
                shortage_base_qty: numberValue(row.shortage_base_qty),
                empty_returned_to_plant_qty: numberValue(row.empty_returned_to_plant_qty),
                empty_shortage_qty: numberValue(row.empty_shortage_qty),
                damaged_returned_to_plant_qty: numberValue(row.damaged_returned_to_plant_qty),
                damaged_shortage_qty: numberValue(row.damaged_shortage_qty),
                expected_return_qty: numberValue(row.expected_return_qty),
                empty_collected_qty: numberValue(row.empty_collected_qty),
                damaged_collected_qty: numberValue(row.damaged_collected_qty),
                truck_stock: numberValue(row.truck_stock),
                plant_stock: numberValue(
                    row.plant_stock !== undefined
                        ? row.plant_stock
                        : (product ? product.plant_stock : 0)
                ),
                product: product || {
                    id: Number(row.product_id),
                    product_name: row.product_name || "",
                    product_code: row.product_code || "",
                    container_type: Number(row.container_type || 0),
                    primary_unit: {
                        product_unit_id: Number(row.primary_product_unit_id || 0),
                        conversion_qty: numberValue(row.primary_conversion_qty || 1),
                        unit_name: row.primary_unit_name || "",
                        short_name: row.primary_short_name || ""
                    },
                    secondary_unit: row.secondary_product_unit_id ? {
                        product_unit_id: Number(row.secondary_product_unit_id || 0),
                        conversion_qty: numberValue(row.secondary_conversion_qty || 1),
                        unit_name: row.secondary_unit_name || "",
                        short_name: row.secondary_short_name || ""
                    } : null,
                    plant_stock: numberValue(row.plant_stock || 0)
                }
            });
        });
    }

    function renderFinancial() {
        var f = state.financial || {};
        byId("invoiceCount").textContent = String(Number(f.invoice_count || 0));
        byId("totalSales").textContent = money(f.total_sales || 0);
        byId("totalReceived").textContent = money(f.total_received || 0);
        byId("totalOutstanding").textContent = money(f.outstanding || 0);
    }

    function applyTripUI() {
        var status = Number(state.status || 1);
        var draft = status === 1;
        var active = status === 2 || status === 3;
        var returned = status === 4;
        var closed = status === 5;
        var canEditDraft = draft && (!reference ? hasAction(ACTION_CREATE) : hasAction(ACTION_UPDATE));

        byId("tripStatusBadge").textContent = reference ? statusLabel(status) : "New Truck Loading";
        byId("tripCurrentLabel").textContent = reference && state.trip ? (state.trip.supply_no + " · " + statusLabel(status)) : "Truck Loading → Sales → Truck Return → Close";
        byId("tripHelp").textContent = draft
            ? "Prepare Truck Loading. Plant stock moves to Truck only when Loading is posted."
            : (active ? "Truck is active. Customer sales use the common Sales page; Truck Return is handled in the Line Return page." : (returned ? "Truck Return submitted. Verify the trip and close when authorized." : (closed ? "Truck Trip is closed and read-only." : "Trip is read-only.")));

        byId("loadingEntryCard").hidden = !draft;
        byId("salesSection").hidden = !(active || returned || closed);

        byId("saveDraftButton").hidden = !canEditDraft || !hasAction(ACTION_SAVE_DRAFT);
        byId("postLoadingButton").hidden = !canEditDraft || !hasAction(ACTION_POST);
        byId("salesFooterButton").hidden = !active || !state.canLineSale;
        byId("openSalesButton").hidden = !active || !state.canLineSale;

        /* Truck Return is handled only in line-return-form.php. */
        byId("returnFooterButton").hidden = !(active || returned || closed)
            || (active && !hasAction(ACTION_RETURN));

        byId("supplyDate").disabled = !canEditDraft;
        if (vehicleSelect && vehicleSelect.setDisabled) vehicleSelect.setDisabled(!canEditDraft);
        else byId("vehicleId").disabled = !canEditDraft;
        if (lineSelect && lineSelect.setDisabled) lineSelect.setDisabled(!canEditDraft);
        else byId("lineId").disabled = !canEditDraft;
        byId("remarks").readOnly = !canEditDraft;
        byId("entryProduct").disabled = !canEditDraft;
        byId("entryPrimaryQty").disabled = !canEditDraft;
        byId("entrySecondaryQty").disabled = !canEditDraft || !(currentProduct() && currentProduct().secondary_unit);
        byId("addLoadingItem").disabled = !canEditDraft;

        renderLoadingItems();
        if (canEditDraft) updateEntryBaseQty();
        
        
        refreshIcons();
    }

    function setTripData(data) {
        state.trip = data.trip || null;
        state.status = state.trip ? Number(state.trip.status || 1) : 1;
        state.items = normalizeTripItems(data.items || []);
        state.financial = data.financial || {};
        if (data.allowed_actions) state.actions = data.allowed_actions.map(Number);
        if (data.can_line_sale !== undefined) state.canLineSale = !!data.can_line_sale;

        if (state.trip) {
            reference = state.trip.ref || reference;
            byId("supplyRef").value = reference;
            byId("supplyNo").value = state.trip.supply_no || "";
            byId("supplyDate").value = state.trip.supply_date || "";
            var selectedVehicleId = String(state.trip.vehicle_id || "");
            var selectedLineId = String(state.trip.line_id || "");

            vehicleSelect.setOptions(state.vehicles.map(function (row) {
                return { value: String(row.id), text: row.vehicle_no + (row.vehicle_name ? " - " + row.vehicle_name : "") };
            }), selectedVehicleId);
            byId("vehicleId").value = selectedVehicleId;

            lineSelect.setOptions(state.lines.map(function (row) {
                return { value: String(row.id), text: (row.line_code ? row.line_code + " - " : "") + row.line_name };
            }), selectedLineId);
            byId("lineId").value = selectedLineId;
            byId("remarks").value = state.trip.remarks || "";
        }
        applyTripUI();
    }

    function validateLoadingItems() {
        readLoadingRows();

        for (var i = 0; i < state.items.length; i += 1) {
            var item = state.items[i];
            var product = productForItem(item);
            var required = productBaseQty(
                product,
                item.primary_qty,
                item.secondary_qty
            );
            var available = numberValue(
                item.plant_stock !== undefined
                    ? item.plant_stock
                    : product.plant_stock
            );

            if (required <= 0.0005) {
                showWarning(
                    "Enter loading quantity for " +
                    (product.product_name || "Product") + "."
                );
                return false;
            }

            if (required > available + 0.0005) {
                showWarning(
                    (product.product_name || "Product") +
                    " Plant Stock insufficient. Available: " +
                    formatStockQuantity(product, available) +
                    ", Required: " +
                    formatStockQuantity(product, required)
                );
                return false;
            }
        }

        return true;
    }

    function loadingPayload(intent) {
        readLoadingRows();
        return {
            ref: reference || undefined,
            intent: intent,
            supply_date: byId("supplyDate").value,
            vehicle_id: Number(byId("vehicleId").value || 0),
            line_id: Number(byId("lineId").value || 0),
            remarks: byId("remarks").value.trim(),
            items_json: JSON.stringify(state.items.map(function (item) {
                return { product_id: Number(item.product_id), primary_qty: round3(item.primary_qty), secondary_qty: round3(item.secondary_qty) };
            }))
        };
    }

    async function saveLoading(intent) {
        if (state.saving) return;
        if (!byId("supplyDate").value || !byId("vehicleId").value || !byId("lineId").value) {
            showWarning("Date, Truck and Line are required.");
            return;
        }
        if (!state.items.length) {
            showWarning("Add at least one Product to Truck Loading.");
            return;
        }

        if (!validateLoadingItems()) return;

        state.saving = true;
        try {
            var result = await App.api("api/line-supply.php", { method: "POST", body: loadingPayload(intent) });
            showSuccess(result.message || (intent === "load" ? "Truck Loading posted." : "Draft saved."));
            setTripData(result.data || {});
            if (!window.location.search && reference) history.replaceState(null, "", "line-supply-form.php?ref=" + encodeURIComponent(reference));
        } catch (error) {
            reportError(error, "Unable to save Truck Loading.");
        } finally { state.saving = false; }
    }

    async function loadInitial() {
        try {
            var options = await App.api("api/line-supply.php?options=1");
            setOptions(options.data || {});
            if (reference) {
                var result = await App.api("api/line-supply.php?ref=" + encodeURIComponent(reference));
                setTripData(result.data || {});
            } else {
                state.status = 1;
                state.trip = null;
                state.items = [];
                state.financial = {};
                applyTripUI();
            }
        } catch (error) {
            reportError(error, "Unable to load Line Supply.");
        } finally {
            state.loading = false;
            refreshIcons();
        }
    }

    byId("entryProduct").addEventListener("change", handleProductChange);
    byId("entryPrimaryQty").addEventListener("input", updateEntryBaseQty);
    byId("entrySecondaryQty").addEventListener("input", updateEntryBaseQty);
    byId("addLoadingItem").addEventListener("click", addLoadingItem);

    byId("loadingItemsBody").addEventListener("input", function (event) {
        var row = event.target.closest("tr[data-index]");
        if (!row) return;

        readLoadingRows();

        var index = Number(row.getAttribute("data-index"));
        var item = state.items[index];
        if (!item) return;

        var product = productForItem(item);
        var loaded = numberValue(item.loaded_base_qty);
        var plant = numberValue(
            item.plant_stock !== undefined
                ? item.plant_stock
                : product.plant_stock
        );
        var remaining = round3(plant - loaded);

        var cell = row.querySelector(
            '[data-loaded-index="' + index + '"]'
        );
        if (cell) {
            cell.innerHTML =
                '<strong>' + decimal3(loaded) + '</strong>' +
                '<div class="muted">' +
                escapeHtml(unitName(baseUnit(product))) +
                '</div>';
        }

        var stockStatus = row.querySelector(".js-load-stock-status");
        if (stockStatus) {
            stockStatus.textContent =
                remaining < -0.0005
                    ? "Short " +
                        formatStockQuantity(product, Math.abs(remaining))
                    : "Remaining " +
                        formatStockQuantity(product, Math.max(0, remaining));
        }
    });
    byId("loadingItemsBody").addEventListener("click", function (event) {
        var button = event.target.closest(".js-remove-loading");
        if (!button || state.status !== 1) return;
        state.items.splice(Number(button.getAttribute("data-index")), 1);
        renderLoadingItems();
        clearEntry();
    });

    byId("saveDraftButton").addEventListener("click", function () { saveLoading("draft"); });
    byId("postLoadingButton").addEventListener("click", function () { saveLoading("load"); });
    byId("exitButton").addEventListener("click", function () { window.location.href = "line-supply-list.php"; });

    loadInitial();
})(window, document);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
</body>
</html>
