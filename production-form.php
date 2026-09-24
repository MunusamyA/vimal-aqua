<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Production Form';
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
        <h1 id="pageHeading">Create Production</h1>
        <p>Draft does not change stock. Post consumes materials and adds finished production stock.</p>
    </div>

    <div class="heading-actions">
        <a class="btn gray" href="production-list.php">
            <i data-lucide="list"></i>
            Production List
        </a>
    </div>
</div>

<form id="productionForm" novalidate>
    <input type="hidden" id="productionRef">

    <div class="card form-section">
        <div class="card-header">
            <h2 class="section-heading">
                <i data-lucide="factory"></i>
                Production Details
            </h2>
        </div>

        <div class="card-body">
            <div class="form-row">
                <div class="field col-4">
                    <label for="productionNo">Production No</label>
                    <input class="input" id="productionNo" type="text"
                           readonly aria-readonly="true"
                           placeholder="Auto generated">
                </div>

                <div class="field col-4">
                    <label for="productionDate" class="required">Production Date</label>
                    <input class="input" id="productionDate"
                           type="date" required
                           value="<?php echo web_h(date('Y-m-d')); ?>">
                </div>

                <div class="field col-4">
                    <label for="remarks">Remarks</label>
                    <input class="input" id="remarks"
                           type="text" maxlength="255"
                           placeholder="Optional">
                </div>
            </div>
        </div>
    </div>

    <div class="app-section-title">Finished Product Output</div>

    <div class="card form-section">
        <div class="card-body">
            <div class="form-row">
                <div class="field col-4">
                    <label for="outputProduct">Finished Product</label>
                    <select class="select" id="outputProduct"
                            data-placeholder="Select Finished Product">
                        <option value="">Select Finished Product</option>
                    </select>
                </div>

                <div class="field col-2">
                    <label for="outputPrimaryQty" id="outputPrimaryQtyLabel">Primary Qty</label>
                    <input class="input" id="outputPrimaryQty"
                           type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="outputSecondaryQty" id="outputSecondaryQtyLabel">Secondary Qty</label>
                    <input class="input" id="outputSecondaryQty"
                           type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           placeholder="0.000" disabled>
                </div>

                <div class="field col-2">
                    <label for="outputBaseQtyView">Total Base Qty</label>
                    <input class="input" id="outputBaseQtyView"
                           type="text" value="0"
                           readonly aria-readonly="true">
                </div>

                <div class="field col-2">
                    <label for="addOutputButton">&nbsp;</label>
                    <button class="btn btn-primary" id="addOutputButton" type="button">
                        <i data-lucide="plus"></i>
                        Add
                    </button>
                </div>
            </div>

            <div id="outputConversionInfo">
                Select a Finished Product.
            </div>
        </div>
    </div>

    <div class="card table-card form-section">
        <div class="app-table-wrap">
            <table class="app-editable-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Finished Product</th>
                    <th>Primary Qty</th>
                    <th>Secondary Qty</th>
                    <th>Conversion</th>
                    <th>Base Qty</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="outputsBody">
                <tr>
                    <td class="empty" colspan="7">
                        No Finished Product added.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="app-section-title">Material Consumption</div>

    <div class="card form-section">
        <div class="card-body">
            <div class="form-row">
                <div class="field col-4">
                    <label for="materialProduct">Material Product</label>
                    <select class="select" id="materialProduct"
                            data-placeholder="Select Material Product">
                        <option value="">Select Material Product</option>
                    </select>
                </div>

                <div class="field col-2">
                    <label for="materialPrimaryQty" id="materialPrimaryQtyLabel">Primary Qty</label>
                    <input class="input" id="materialPrimaryQty"
                           type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="materialSecondaryQty" id="materialSecondaryQtyLabel">Secondary Qty</label>
                    <input class="input" id="materialSecondaryQty"
                           type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           placeholder="0.000" disabled>
                </div>

                <div class="field col-2">
                    <label for="materialBaseQtyView">Required Base Qty</label>
                    <input class="input" id="materialBaseQtyView"
                           type="text" value="0"
                           readonly aria-readonly="true">
                </div>

                <div class="field col-2">
                    <label for="addMaterialButton">&nbsp;</label>
                    <button class="btn btn-primary" id="addMaterialButton" type="button">
                        <i data-lucide="plus"></i>
                        Add
                    </button>
                </div>
            </div>

            <div id="materialConversionInfo">
                Select a Material Product.
            </div>
        </div>
    </div>

    <div class="card table-card form-section">
        <div class="app-table-wrap">
            <table class="app-editable-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Material</th>
                    <th>Available Base Stock</th>
                    <th>Primary Qty</th>
                    <th>Secondary Qty</th>
                    <th>Conversion</th>
                    <th>Base Qty</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="materialsBody">
                <tr>
                    <td class="empty" colspan="8">
                        No Material added.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card form-section">
        <div class="card-footer">
            <div class="buttons" id="formActions">
                <button class="btn gray" id="saveDraftButton" type="button">
                    <i data-lucide="save"></i>
                    Save Draft
                </button>

                <button class="btn btn-primary" id="postButton" type="button">
                    <i data-lucide="check-circle-2"></i>
                    Post Production
                </button>
            </div>
        </div>
    </div>
</form>

<script>
(function (window, document) {
    "use strict";

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_SAVE_DRAFT = 10;
    var ACTION_POST = 11;

    var form = document.getElementById("productionForm");
    var params = new URLSearchParams(location.search);
    var reference = params.get("ref") || "";
    var viewOnly = params.get("view") === "1";

    var outputNode = document.getElementById("outputProduct");
    var materialNode = document.getElementById("materialProduct");

    var outputSelect = GlobalSelect.init(outputNode, {
        placeholder: "Select Finished Product"
    });

    var materialSelect = GlobalSelect.init(materialNode, {
        placeholder: "Select Material Product"
    });

    var state = {
        finished: [],
        materials: [],
        finishedMap: {},
        materialMap: {},
        outputs: [],
        materialRows: [],
        allowedActions: [],
        status: 1
    };

    function responseData(response) {
        return response && response.data ? response.data : (response || {});
    }

    function num(value) {
        var number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function round(value, places) {
        var power = Math.pow(10, places || 3);
        return Math.round((num(value) + Number.EPSILON) * power) / power;
    }

    function qtyText(value) {
        return num(value).toLocaleString("en-IN", {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
        });
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function hasAction(id) {
        return state.allowedActions.map(Number).indexOf(Number(id)) !== -1;
    }

    function unitText(unit) {
        if (!unit) return "-";
        return unit.short_name || unit.unit_name || "-";
    }

    function productText(product) {
        return (product.product_code ? product.product_code + " - " : "") +
            product.product_name;
    }

    function optionItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value: String(row.id),
                text: productText(row)
            };
        });
    }

    function buildMaps() {
        state.finishedMap = {};
        state.materialMap = {};

        state.finished.forEach(function (product) {
            state.finishedMap[String(product.id)] = product;
        });

        state.materials.forEach(function (product) {
            state.materialMap[String(product.id)] = product;
        });
    }

    function currentOutputProduct() {
        return state.finishedMap[String(outputNode.value || "")] || null;
    }

    function currentMaterialProduct() {
        return state.materialMap[String(materialNode.value || "")] || null;
    }

    /*
     * Universal unit calculation:
     * Base Qty =
     *   (Primary Qty x Primary Conversion)
     * + (Secondary Qty x Secondary Conversion)
     *
     * New standard example:
     * Primary Box conversion 12
     * Secondary Piece conversion 1
     */
    function baseQty(product, primaryQty, secondaryQty) {
        if (!product || !product.primary_unit) return 0;

        var primaryConversion =
            Math.max(1, num(product.primary_unit.conversion_qty || 1));

        var secondaryConversion =
            product.secondary_unit
                ? Math.max(1, num(product.secondary_unit.conversion_qty || 1))
                : 0;

        return round(
            num(primaryQty) * primaryConversion +
            num(secondaryQty) * secondaryConversion,
            3
        );
    }

    function conversionText(product) {
        if (!product || !product.primary_unit) {
            return "";
        }

        var primaryName = unitText(product.primary_unit);

        if (!product.secondary_unit) {
            return "Primary Unit: " + primaryName;
        }

        var secondaryName = unitText(product.secondary_unit);
        var primaryConversion =
            Math.max(1, num(product.primary_unit.conversion_qty || 1));
        var secondaryConversion =
            Math.max(1, num(product.secondary_unit.conversion_qty || 1));

        if (secondaryConversion === 1) {
            return "1 " + primaryName +
                " = " + qtyText(primaryConversion) +
                " " + secondaryName;
        }

        return "Base Qty = " +
            primaryName + " x " + qtyText(primaryConversion) +
            " + " +
            secondaryName + " x " + qtyText(secondaryConversion);
    }

    function refreshOutputLiveCalculation() {
        var product = currentOutputProduct();

        var primaryInput =
            document.getElementById("outputPrimaryQty");

        var secondaryInput =
            document.getElementById("outputSecondaryQty");

        if (!product || !product.primary_unit) {
            document.getElementById("outputPrimaryQtyLabel")
                .textContent = "Primary Qty";

            document.getElementById("outputSecondaryQtyLabel")
                .textContent = "Secondary Qty";

            document.getElementById("outputConversionInfo")
                .textContent = "Select a Finished Product.";

            document.getElementById("outputBaseQtyView")
                .value = "0";

            secondaryInput.disabled = true;
            secondaryInput.value = "";
            return;
        }

        document.getElementById("outputPrimaryQtyLabel")
            .textContent =
                unitText(product.primary_unit) + " Qty";

        document.getElementById("outputSecondaryQtyLabel")
            .textContent =
                product.secondary_unit
                    ? unitText(product.secondary_unit) + " Qty"
                    : "Secondary Qty";

        secondaryInput.disabled =
            !product.secondary_unit || viewOnly;

        if (!product.secondary_unit) {
            secondaryInput.value = "";
        }

        var total = baseQty(
            product,
            primaryInput.value,
            secondaryInput.value
        );

        document.getElementById("outputBaseQtyView")
            .value = qtyText(total);

        document.getElementById("outputConversionInfo")
            .textContent =
                conversionText(product) +
                " · Live Total: " +
                qtyText(total) +
                " base units";
    }

    function refreshMaterialLiveCalculation() {
        var product = currentMaterialProduct();

        var primaryInput =
            document.getElementById("materialPrimaryQty");

        var secondaryInput =
            document.getElementById("materialSecondaryQty");

        if (!product || !product.primary_unit) {
            document.getElementById("materialPrimaryQtyLabel")
                .textContent = "Primary Qty";

            document.getElementById("materialSecondaryQtyLabel")
                .textContent = "Secondary Qty";

            document.getElementById("materialConversionInfo")
                .textContent = "Select a Material Product.";

            document.getElementById("materialBaseQtyView")
                .value = "0";

            secondaryInput.disabled = true;
            secondaryInput.value = "";
            return;
        }

        document.getElementById("materialPrimaryQtyLabel")
            .textContent =
                unitText(product.primary_unit) + " Qty";

        document.getElementById("materialSecondaryQtyLabel")
            .textContent =
                product.secondary_unit
                    ? unitText(product.secondary_unit) + " Qty"
                    : "Secondary Qty";

        secondaryInput.disabled =
            !product.secondary_unit || viewOnly;

        if (!product.secondary_unit) {
            secondaryInput.value = "";
        }

        var total = baseQty(
            product,
            primaryInput.value,
            secondaryInput.value
        );

        document.getElementById("materialBaseQtyView")
            .value = qtyText(total);

        document.getElementById("materialConversionInfo")
            .textContent =
                conversionText(product) +
                " · Available: " +
                qtyText(product.available_stock) +
                " base units · Required: " +
                qtyText(total);
    }

    function renderOutputs() {
        var body = document.getElementById("outputsBody");

        if (!state.outputs.length) {
            body.innerHTML =
                '<tr><td class="empty" colspan="7">' +
                'No Finished Product added.' +
                '</td></tr>';
            return;
        }

        var html = "";

        state.outputs.forEach(function (row, index) {
            var product =
                state.finishedMap[String(row.product_id)] ||
                row.product_snapshot ||
                {};

            var primary =
                product.primary_unit ||
                row.primary_unit_snapshot ||
                {};

            var secondary =
                product.secondary_unit ||
                row.secondary_unit_snapshot ||
                null;

            var primaryConversion =
                num(row.primary_conversion_qty ||
                    primary.conversion_qty ||
                    1);

            var secondaryConversion =
                secondary
                    ? num(row.secondary_conversion_qty ||
                        secondary.conversion_qty ||
                        1)
                    : 0;

            var total = round(
                num(row.primary_qty) * primaryConversion +
                num(row.secondary_qty) * secondaryConversion,
                3
            );

            html +=
                '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(
                    product.product_name ||
                    row.product_name ||
                    ""
                ) + '</td>' +
                '<td>' +
                    escapeHtml(qtyText(row.primary_qty)) +
                    ' ' +
                    escapeHtml(unitText(primary)) +
                '</td>' +
                '<td>' +
                    (
                        secondary
                            ? escapeHtml(qtyText(row.secondary_qty)) +
                              ' ' +
                              escapeHtml(unitText(secondary))
                            : '-'
                    ) +
                '</td>' +
                '<td>' +
                    escapeHtml(
                        conversionText({
                            primary_unit: primary,
                            secondary_unit: secondary
                        })
                    ) +
                '</td>' +
                '<td>' + escapeHtml(qtyText(total)) + '</td>' +
                '<td>' +
                    (
                        viewOnly
                            ? '-'
                            : '<button class="btn btn-danger js-remove-output" ' +
                              'type="button" data-index="' + index + '">' +
                              '<i data-lucide="trash-2"></i></button>'
                    ) +
                '</td>' +
                '</tr>';
        });

        body.innerHTML = html;

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function renderMaterials() {
        var body = document.getElementById("materialsBody");

        if (!state.materialRows.length) {
            body.innerHTML =
                '<tr><td class="empty" colspan="8">' +
                'No Material added.' +
                '</td></tr>';
            return;
        }

        var html = "";

        state.materialRows.forEach(function (row, index) {
            var product =
                state.materialMap[String(row.product_id)] ||
                row.product_snapshot ||
                {};

            var primary =
                product.primary_unit ||
                row.primary_unit_snapshot ||
                {};

            var secondary =
                product.secondary_unit ||
                row.secondary_unit_snapshot ||
                null;

            var primaryConversion =
                num(row.primary_conversion_qty ||
                    primary.conversion_qty ||
                    1);

            var secondaryConversion =
                secondary
                    ? num(row.secondary_conversion_qty ||
                        secondary.conversion_qty ||
                        1)
                    : 0;

            var total = round(
                num(row.primary_qty) * primaryConversion +
                num(row.secondary_qty) * secondaryConversion,
                3
            );

            html +=
                '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(
                    product.product_name ||
                    row.product_name ||
                    ""
                ) + '</td>' +
                '<td>' +
                    escapeHtml(
                        qtyText(
                            product.available_stock != null
                                ? product.available_stock
                                : row.available_stock
                        )
                    ) +
                '</td>' +
                '<td>' +
                    escapeHtml(qtyText(row.primary_qty)) +
                    ' ' +
                    escapeHtml(unitText(primary)) +
                '</td>' +
                '<td>' +
                    (
                        secondary
                            ? escapeHtml(qtyText(row.secondary_qty)) +
                              ' ' +
                              escapeHtml(unitText(secondary))
                            : '-'
                    ) +
                '</td>' +
                '<td>' +
                    escapeHtml(
                        conversionText({
                            primary_unit: primary,
                            secondary_unit: secondary
                        })
                    ) +
                '</td>' +
                '<td>' + escapeHtml(qtyText(total)) + '</td>' +
                '<td>' +
                    (
                        viewOnly
                            ? '-'
                            : '<button class="btn btn-danger js-remove-material" ' +
                              'type="button" data-index="' + index + '">' +
                              '<i data-lucide="trash-2"></i></button>'
                    ) +
                '</td>' +
                '</tr>';
        });

        body.innerHTML = html;

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function clearOutputEntry() {
        outputSelect.setOptions(
            optionItems(state.finished),
            ""
        );

        document.getElementById("outputPrimaryQty").value = "";
        document.getElementById("outputSecondaryQty").value = "";

        refreshOutputLiveCalculation();
    }

    function clearMaterialEntry() {
        materialSelect.setOptions(
            optionItems(state.materials),
            ""
        );

        document.getElementById("materialPrimaryQty").value = "";
        document.getElementById("materialSecondaryQty").value = "";

        refreshMaterialLiveCalculation();
    }

    function addOutput() {
        var product = currentOutputProduct();

        if (!product) {
            App.showError(null, "Select Finished Product.");
            return;
        }

        if (
            state.outputs.some(function (row) {
                return Number(row.product_id) === Number(product.id);
            })
        ) {
            App.showError(
                null,
                "Finished Product is already added."
            );
            return;
        }

        var primaryQty =
            Math.max(
                0,
                num(
                    document.getElementById(
                        "outputPrimaryQty"
                    ).value
                )
            );

        var secondaryQty =
            product.secondary_unit
                ? Math.max(
                    0,
                    num(
                        document.getElementById(
                            "outputSecondaryQty"
                        ).value
                    )
                )
                : 0;

        if (primaryQty <= 0 && secondaryQty <= 0) {
            App.showError(
                null,
                "Enter Finished Product quantity."
            );
            return;
        }

        state.outputs.push({
            product_id: Number(product.id),
            primary_qty: primaryQty,
            secondary_qty: secondaryQty,

            primary_conversion_qty:
                Math.max(
                    1,
                    num(
                        product.primary_unit
                            .conversion_qty || 1
                    )
                ),

            secondary_conversion_qty:
                product.secondary_unit
                    ? Math.max(
                        1,
                        num(
                            product.secondary_unit
                                .conversion_qty || 1
                        )
                    )
                    : 0,

            product_snapshot: product,
            primary_unit_snapshot: product.primary_unit,
            secondary_unit_snapshot:
                product.secondary_unit || null
        });

        renderOutputs();
        clearOutputEntry();
    }

    function addMaterial() {
        var product = currentMaterialProduct();

        if (!product) {
            App.showError(null, "Select Material Product.");
            return;
        }

        if (
            state.materialRows.some(function (row) {
                return Number(row.product_id) === Number(product.id);
            })
        ) {
            App.showError(
                null,
                "Material Product is already added."
            );
            return;
        }

        var primaryQty =
            Math.max(
                0,
                num(
                    document.getElementById(
                        "materialPrimaryQty"
                    ).value
                )
            );

        var secondaryQty =
            product.secondary_unit
                ? Math.max(
                    0,
                    num(
                        document.getElementById(
                            "materialSecondaryQty"
                        ).value
                    )
                )
                : 0;

        if (primaryQty <= 0 && secondaryQty <= 0) {
            App.showError(
                null,
                "Enter Material quantity."
            );
            return;
        }

        var total = baseQty(
            product,
            primaryQty,
            secondaryQty
        );

        if (
            total >
            num(product.available_stock) + 0.0005
        ) {
            App.showError(
                null,
                "Required stock " +
                    qtyText(total) +
                    " exceeds available stock " +
                    qtyText(product.available_stock) +
                    "."
            );
            return;
        }

        state.materialRows.push({
            product_id: Number(product.id),
            primary_qty: primaryQty,
            secondary_qty: secondaryQty,

            primary_conversion_qty:
                Math.max(
                    1,
                    num(
                        product.primary_unit
                            .conversion_qty || 1
                    )
                ),

            secondary_conversion_qty:
                product.secondary_unit
                    ? Math.max(
                        1,
                        num(
                            product.secondary_unit
                                .conversion_qty || 1
                        )
                    )
                    : 0,

            available_stock:
                num(product.available_stock),

            product_snapshot: product,
            primary_unit_snapshot:
                product.primary_unit,
            secondary_unit_snapshot:
                product.secondary_unit || null
        });

        renderMaterials();
        clearMaterialEntry();
    }

    function requestPayload(intent) {
        return {
            ref: reference || undefined,
            intent: intent,
            production_date:
                document.getElementById(
                    "productionDate"
                ).value,

            remarks:
                document.getElementById(
                    "remarks"
                ).value.trim(),

            outputs_json:
                state.outputs.map(function (row) {
                    return {
                        product_id: row.product_id,
                        primary_qty: row.primary_qty,
                        secondary_qty: row.secondary_qty
                    };
                }),

            materials_json:
                state.materialRows.map(function (row) {
                    return {
                        product_id: row.product_id,
                        primary_qty: row.primary_qty,
                        secondary_qty: row.secondary_qty
                    };
                })
        };
    }

    async function save(intent) {
        if (viewOnly) return;

        if (
            !document.getElementById(
                "productionDate"
            ).value
        ) {
            App.showError(
                null,
                "Production Date is required."
            );
            return;
        }

        if (!state.outputs.length) {
            App.showError(
                null,
                "Add at least one Finished Product."
            );
            return;
        }

        if (!state.materialRows.length) {
            App.showError(
                null,
                "Add at least one Material."
            );
            return;
        }

        if (
            intent === "draft" &&
            !hasAction(ACTION_SAVE_DRAFT)
        ) {
            App.showError(
                null,
                "You do not have permission to Save Draft."
            );
            return;
        }

        if (
            intent === "post" &&
            !hasAction(ACTION_POST)
        ) {
            App.showError(
                null,
                "You do not have permission to Post Production."
            );
            return;
        }

        if (
            !reference &&
            !hasAction(ACTION_CREATE)
        ) {
            App.showError(
                null,
                "You do not have permission to Create Production."
            );
            return;
        }

        if (
            reference &&
            !hasAction(ACTION_UPDATE)
        ) {
            App.showError(
                null,
                "You do not have permission to Update Production."
            );
            return;
        }

        var saveDraftButton =
            document.getElementById("saveDraftButton");

        var postButton =
            document.getElementById("postButton");

        saveDraftButton.disabled = true;
        postButton.disabled = true;

        try {
            var response = await App.api(
                "api/production.php",
                {
                    method:
                        reference
                            ? "PUT"
                            : "POST",

                    body:
                        requestPayload(intent)
                }
            );

            if (window.showToast) {
                showToast(
                    response.message ||
                    "Production saved successfully.",
                    {
                        type: "success",
                        duration: 2
                    }
                );
            }

            setTimeout(function () {
                location.href =
                    "production-list.php";
            }, 600);

        } catch (error) {
            App.showError(
                error,
                "Unable to save Production."
            );

            saveDraftButton.disabled = false;
            postButton.disabled = false;
        }
    }

    async function load() {
        try {
            var optionResponse =
                await App.api(
                    "api/production.php?options=1"
                );

            var options =
                responseData(optionResponse);

            state.allowedActions =
                (options.allowed_actions || [])
                    .map(Number);

            state.finished =
                options.finished_products || [];

            state.materials =
                Array.isArray(options.material_products)
                    ? options.material_products
                    : Object.values(options.material_products || {});

            buildMaps();

            outputSelect.setOptions(
                optionItems(state.finished),
                ""
            );

            materialSelect.setOptions(
                optionItems(state.materials),
                ""
            );

            document.getElementById(
                "productionNo"
            ).value =
                options.next_production_no || "";

            if (reference) {
                var response =
                    await App.api(
                        "api/production.php?ref=" +
                        encodeURIComponent(reference)
                    );

                var data =
                    responseData(response);

                state.allowedActions =
                    (
                        data.allowed_actions ||
                        state.allowedActions
                    ).map(Number);

                var production =
                    data.production || {};

                state.status =
                    Number(
                        production.status || 1
                    );

                document.getElementById(
                    "productionRef"
                ).value =
                    production.ref || reference;

                document.getElementById(
                    "productionNo"
                ).value =
                    production.production_no || "";

                document.getElementById(
                    "productionDate"
                ).value =
                    production.production_date || "";

                document.getElementById(
                    "remarks"
                ).value =
                    production.remarks || "";

                document.getElementById(
                    "pageHeading"
                ).textContent =
                    (viewOnly ? "View" : "Edit") +
                    " Production";

                state.outputs =
                    (data.outputs || [])
                        .map(function (row) {
                            var product =
                                state.finishedMap[
                                    String(row.product_id)
                                ] || {
                                    id: row.product_id,
                                    product_name:
                                        row.product_name,

                                    primary_unit: {
                                        product_unit_id:
                                            row.primary_product_unit_id,
                                        unit_name:
                                            row.primary_unit_name,
                                        short_name:
                                            row.primary_short_name,
                                        conversion_qty:
                                            row.primary_conversion_qty
                                    },

                                    secondary_unit:
                                        row.secondary_product_unit_id
                                            ? {
                                                product_unit_id:
                                                    row.secondary_product_unit_id,
                                                unit_name:
                                                    row.secondary_unit_name,
                                                short_name:
                                                    row.secondary_short_name,
                                                conversion_qty:
                                                    row.secondary_conversion_qty
                                            }
                                            : null
                                };

                            return {
                                product_id:
                                    Number(row.product_id),

                                primary_qty:
                                    num(row.primary_qty),

                                secondary_qty:
                                    num(row.secondary_qty),

                                primary_conversion_qty:
                                    num(
                                        row.primary_conversion_qty
                                    ),

                                secondary_conversion_qty:
                                    num(
                                        row.secondary_conversion_qty
                                    ),

                                product_snapshot:
                                    product,

                                primary_unit_snapshot:
                                    product.primary_unit,

                                secondary_unit_snapshot:
                                    product.secondary_unit
                            };
                        });

                state.materialRows =
                    (data.materials || [])
                        .map(function (row) {
                            var product =
                                state.materialMap[
                                    String(row.product_id)
                                ] || {
                                    id: row.product_id,
                                    product_name:
                                        row.product_name,
                                    available_stock:
                                        row.available_stock,

                                    primary_unit: {
                                        product_unit_id:
                                            row.primary_product_unit_id,
                                        unit_name:
                                            row.primary_unit_name,
                                        short_name:
                                            row.primary_short_name,
                                        conversion_qty:
                                            row.primary_conversion_qty
                                    },

                                    secondary_unit:
                                        row.secondary_product_unit_id
                                            ? {
                                                product_unit_id:
                                                    row.secondary_product_unit_id,
                                                unit_name:
                                                    row.secondary_unit_name,
                                                short_name:
                                                    row.secondary_short_name,
                                                conversion_qty:
                                                    row.secondary_conversion_qty
                                            }
                                            : null
                                };

                            return {
                                product_id:
                                    Number(row.product_id),

                                primary_qty:
                                    num(row.primary_qty),

                                secondary_qty:
                                    num(row.secondary_qty),

                                primary_conversion_qty:
                                    num(
                                        row.primary_conversion_qty
                                    ),

                                secondary_conversion_qty:
                                    num(
                                        row.secondary_conversion_qty
                                    ),

                                available_stock:
                                    num(row.available_stock),

                                product_snapshot:
                                    product,

                                primary_unit_snapshot:
                                    product.primary_unit,

                                secondary_unit_snapshot:
                                    product.secondary_unit
                            };
                        });

                renderOutputs();
                renderMaterials();

                if (state.status !== 1) {
                    viewOnly = true;
                }
            }

            if (viewOnly) {
                Array.prototype
                    .forEach.call(
                        form.querySelectorAll(
                            "input:not([readonly]), select, button"
                        ),
                        function (element) {
                            element.disabled = true;
                        }
                    );

                document.getElementById(
                    "formActions"
                ).hidden = true;

            } else {
                document.getElementById(
                    "saveDraftButton"
                ).disabled =
                    !hasAction(ACTION_SAVE_DRAFT);

                document.getElementById(
                    "postButton"
                ).disabled =
                    !hasAction(ACTION_POST);
            }

            refreshOutputLiveCalculation();
            refreshMaterialLiveCalculation();

        } catch (error) {
            App.showError(
                error,
                "Unable to load Production form."
            );

            document.getElementById(
                "formActions"
            ).hidden = true;
        }

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    outputNode.addEventListener(
        "change",
        refreshOutputLiveCalculation
    );

    materialNode.addEventListener(
        "change",
        refreshMaterialLiveCalculation
    );

    document.getElementById(
        "outputPrimaryQty"
    ).addEventListener(
        "input",
        refreshOutputLiveCalculation
    );

    document.getElementById(
        "outputSecondaryQty"
    ).addEventListener(
        "input",
        refreshOutputLiveCalculation
    );

    document.getElementById(
        "materialPrimaryQty"
    ).addEventListener(
        "input",
        refreshMaterialLiveCalculation
    );

    document.getElementById(
        "materialSecondaryQty"
    ).addEventListener(
        "input",
        refreshMaterialLiveCalculation
    );

    document.getElementById(
        "addOutputButton"
    ).addEventListener(
        "click",
        addOutput
    );

    document.getElementById(
        "addMaterialButton"
    ).addEventListener(
        "click",
        addMaterial
    );

    document.getElementById(
        "outputsBody"
    ).addEventListener(
        "click",
        function (event) {
            var button =
                event.target.closest(
                    ".js-remove-output"
                );

            if (!button) return;

            state.outputs.splice(
                Number(button.dataset.index),
                1
            );

            renderOutputs();
        }
    );

    document.getElementById(
        "materialsBody"
    ).addEventListener(
        "click",
        function (event) {
            var button =
                event.target.closest(
                    ".js-remove-material"
                );

            if (!button) return;

            state.materialRows.splice(
                Number(button.dataset.index),
                1
            );

            renderMaterials();
        }
    );

    document.getElementById(
        "saveDraftButton"
    ).addEventListener(
        "click",
        function () {
            save("draft");
        }
    );

    document.getElementById(
        "postButton"
    ).addEventListener(
        "click",
        function () {
            save("post");
        }
    );

    load();

})(window, document);
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
