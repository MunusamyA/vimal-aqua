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
        <p>One production document can contain multiple finished products.</p>
    </div>

    <div class="heading-actions">
        <a class="btn gray" href="production-list.php">
            <i data-lucide="list"></i>
            Production List
        </a>
    </div>
</div>

<form id="productionForm" novalidate>
    <input type="hidden" id="productionRef" name="ref">

    <div class="card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading">
                    <i data-lucide="factory"></i>
                    Production Details
                </h2>
            </div>
        </div>

        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="productionNo">Production No</label>
                    <input class="input" id="productionNo" type="text" readonly
                           aria-readonly="true" placeholder="Auto generated">
                </div>

                <div class="field col-3">
                    <label for="productionDate" class="required">Date</label>
                    <input class="input" id="productionDate" name="production_date"
                           type="date" required
                           value="<?php echo web_h(date('Y-m-d')); ?>">
                </div>

                <div class="field col-6">
                    <label for="remarks">Remarks</label>
                    <input class="input" id="remarks" name="remarks"
                           type="text" maxlength="255"
                           placeholder="Optional">
                </div>
            </div>
        </div>
    </div>

    <div class="app-section-title">Production Outputs</div>

    <div class="card form-section">
        <div class="card-body">
            <div class="form-row">
                <div class="field col-4">
                    <label for="finishedProduct">Finished Product</label>
                    <select class="select" id="finishedProduct" data-placeholder="Select Finished Product">
                        <option value="">Select Finished Product</option>
                    </select>
                </div>

                <div class="field col-2">
                    <label for="outputPrimaryQty" id="outputPrimaryQtyLabel">Primary Qty</label>
                    <input class="input" id="outputPrimaryQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="outputSecondaryQty" id="outputSecondaryQtyLabel">Secondary Qty</label>
                    <input class="input" id="outputSecondaryQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000" disabled>
                </div>

                <div class="field col-2">
                    <label for="outputBaseQty">Output Qty</label>
                    <input class="input" id="outputBaseQty" type="text"
                           readonly aria-readonly="true"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="addOutputButton">&nbsp;</label>
                    <button class="btn btn-primary" id="addOutputButton" type="button">
                        <i data-lucide="plus"></i>
                        Add
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card form-section">
        <div class="app-table-wrap">
            <table class="app-editable-table" id="outputsTable">
                <thead>
                <tr>
                    <th class="cell-index">#</th>
                    <th class="cell-main">Finished Product</th>
                    <th>Primary Qty</th>
                    <th>Secondary Qty</th>
                    <th>Output Qty</th>
                    <th class="cell-action">Action</th>
                </tr>
                </thead>
                <tbody id="outputsBody">
                <tr>
                    <td class="empty" colspan="6">No Finished Products added.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="app-section-title">Material Consumption</div>

    <div class="card form-section">
        <div class="card-body">
            <div class="form-row">
                <div class="field col-5">
                    <label for="materialProduct">Material Product</label>
                    <select class="select" id="materialProduct" data-placeholder="Select Material">
                        <option value="">Select Material</option>
                    </select>
                </div>

                <div class="field col-3">
                    <label for="materialQty" id="materialQtyLabel">Consume Qty</label>
                    <input class="input" id="materialQty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="materialAvailable">Available Stock</label>
                    <input class="input" id="materialAvailable" type="text"
                           readonly aria-readonly="true"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="addMaterialButton">&nbsp;</label>
                    <button class="btn btn-primary" id="addMaterialButton" type="button">
                        <i data-lucide="plus"></i>
                        Add
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card form-section">
        <div class="app-table-wrap">
            <table class="app-editable-table" id="materialsTable">
                <thead>
                <tr>
                    <th class="cell-index">#</th>
                    <th class="cell-main">Material</th>
                    <th>Unit</th>
                    <th>Consume Qty</th>
                    <th>Available</th>
                    <th>Balance After</th>
                    <th class="cell-action">Action</th>
                </tr>
                </thead>
                <tbody id="materialsBody">
                <tr>
                    <td class="empty" colspan="7">No Materials added.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="app-summary-actions" id="formActions">
        <button class="btn gray" id="saveDraftButton" type="button">
            <i data-lucide="save"></i>
            Save Draft
        </button>

        <button class="btn btn-primary" id="postButton" type="button">
            <i data-lucide="check-circle-2"></i>
            Post Production
        </button>
    </div>
</form>

<script>
(function(window, document){
    "use strict";

    var form = document.getElementById("productionForm");
    var params = new URLSearchParams(location.search);
    var reference = params.get("ref") || "";
    var viewOnly = params.get("view") === "1";

    var finishedNode = document.getElementById("finishedProduct");
    var materialNode = document.getElementById("materialProduct");

    var finishedSelect = GlobalSelect.init(finishedNode, {
        placeholder:"Select Finished Product"
    });

    var materialSelect = GlobalSelect.init(materialNode, {
        placeholder:"Select Material"
    });

    var state = {
        finishedProducts:[],
        materialProducts:[],
        finishedMap:{},
        materialMap:{},
        outputs:[],
        materials:[],
        allowedActions:[],
        status:1
    };

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_SAVE_DRAFT = 10;
    var ACTION_POST = 11;

    function num(value){
        var number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function round(value, places){
        var power = Math.pow(10, places || 2);
        return Math.round((num(value) + Number.EPSILON) * power) / power;
    }

    function qty(value){
        return num(value).toLocaleString("en-IN", {
            minimumFractionDigits:0,
            maximumFractionDigits:3
        });
    }

    function escapeHtml(value){
        return String(value == null ? "" : value)
            .replace(/&/g,"&amp;")
            .replace(/</g,"&lt;")
            .replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;")
            .replace(/'/g,"&#039;");
    }

    function hasAction(id){
        return state.allowedActions.map(Number).indexOf(Number(id)) !== -1;
    }

    function optionItems(rows, valueKey, textBuilder){
        return (rows || []).map(function(row){
            return {
                value:String(row[valueKey]),
                text:typeof textBuilder === "function"
                    ? textBuilder(row)
                    : String(row[textBuilder] || "")
            };
        });
    }

    function unitText(unit){
        if (!unit) return "-";
        return unit.short_name || unit.unit_name || "-";
    }

    function currentFinished(){
        return state.finishedMap[String(finishedNode.value || "")] || null;
    }

    function currentMaterial(){
        return state.materialMap[String(materialNode.value || "")] || null;
    }

    function entryOutputBaseQty(){
        var product = currentFinished();
        if (!product || !product.primary_unit) return 0;

        var primaryQty = Math.max(0, num(document.getElementById("outputPrimaryQty").value));
        var secondaryQty = Math.max(0, num(document.getElementById("outputSecondaryQty").value));

        var primaryConversion = Math.max(
            1,
            num(product.primary_unit.conversion_qty || 1)
        );

        var secondaryConversion = product.secondary_unit
            ? Math.max(1, num(product.secondary_unit.conversion_qty || 1))
            : 0;

        return round(
            primaryQty * primaryConversion +
            secondaryQty * secondaryConversion,
            3
        );
    }

    function refreshOutputEntry(){
        var product = currentFinished();
        var primaryInput = document.getElementById("outputPrimaryQty");
        var secondaryInput = document.getElementById("outputSecondaryQty");

        if (!product || !product.primary_unit) {
            document.getElementById("outputPrimaryQtyLabel").textContent = "Primary Qty";
            document.getElementById("outputSecondaryQtyLabel").textContent = "Secondary Qty";
            secondaryInput.disabled = true;
            document.getElementById("outputBaseQty").value = "";
            return;
        }

        document.getElementById("outputPrimaryQtyLabel").textContent =
            unitText(product.primary_unit) + " Qty";

        if (product.secondary_unit) {
            document.getElementById("outputSecondaryQtyLabel").textContent =
                unitText(product.secondary_unit) + " Qty";
            secondaryInput.disabled = false;
        } else {
            document.getElementById("outputSecondaryQtyLabel").textContent = "Secondary Qty";
            secondaryInput.disabled = true;
            secondaryInput.value = "";
        }

        var output = entryOutputBaseQty();
        document.getElementById("outputBaseQty").value =
            output > 0 ? output.toFixed(3) : "";
    }

    function clearOutputEntry(){
        if (finishedSelect && typeof finishedSelect.setValue === "function") {
            finishedSelect.setValue("");
        }

        finishedNode.value = "";
        document.getElementById("outputPrimaryQty").value = "";
        document.getElementById("outputSecondaryQty").value = "";
        document.getElementById("outputBaseQty").value = "";
        refreshOutputEntry();

        if (finishedSelect && typeof finishedSelect.focus === "function") {
            finishedSelect.focus();
        } else {
            finishedNode.focus();
        }
    }

    function addOutput(){
        var product = currentFinished();

        if (!product) {
            if (window.showToast) {
                showToast("Select Finished Product.", {
                    type:"warning",
                    duration:2
                });
            }
            return;
        }

        var primaryQty = Math.max(
            0,
            num(document.getElementById("outputPrimaryQty").value)
        );

        var secondaryQty = product.secondary_unit
            ? Math.max(
                0,
                num(document.getElementById("outputSecondaryQty").value)
            )
            : 0;

        if (primaryQty <= 0 && secondaryQty <= 0) {
            if (window.showToast) {
                showToast("Enter Primary Qty or Secondary Qty.", {
                    type:"warning",
                    duration:2
                });
            }
            return;
        }

        var duplicate = state.outputs.some(function(item){
            return Number(item.product_id) === Number(product.id);
        });

        if (duplicate) {
            if (window.showToast) {
                showToast("This Finished Product is already added.", {
                    type:"warning",
                    duration:3
                });
            }
            return;
        }

        state.outputs.push({
            product_id:Number(product.id),
            primary_qty:round(primaryQty,3),
            secondary_qty:round(secondaryQty,3)
        });

        renderOutputs();
        clearOutputEntry();
    }

    function outputBaseQty(item){
        var product = state.finishedMap[String(item.product_id)] || item.snapshot || {};
        if (!product.primary_unit) return 0;

        var primaryConversion = Math.max(
            1,
            num(product.primary_unit.conversion_qty || 1)
        );

        var secondaryConversion = product.secondary_unit
            ? Math.max(1, num(product.secondary_unit.conversion_qty || 1))
            : 0;

        return round(
            num(item.primary_qty) * primaryConversion +
            num(item.secondary_qty) * secondaryConversion,
            3
        );
    }

    function renderOutputs(){
        var body = document.getElementById("outputsBody");

        if (!state.outputs.length) {
            body.innerHTML =
                '<tr><td class="empty" colspan="6">No Finished Products added.</td></tr>';
            return;
        }

        var html = "";

        state.outputs.forEach(function(item,index){
            var product =
                state.finishedMap[String(item.product_id)] ||
                item.snapshot ||
                {};

            var primaryName = unitText(product.primary_unit);
            var secondaryName = product.secondary_unit
                ? unitText(product.secondary_unit)
                : "-";

            html += '<tr data-index="'+index+'">' +
                '<td class="cell-index">'+(index+1)+'</td>' +
                '<td class="cell-main"><strong>'+
                    escapeHtml(product.product_name || "-")+
                '</strong></td>' +

                '<td>' +
                    '<input type="text" inputmode="decimal" class="js-output-primary" ' +
                           'data-validation="decimal" data-decimal-places="3" ' +
                           'placeholder="0.000" value="'+
                           escapeHtml(num(item.primary_qty) > 0 ? num(item.primary_qty).toFixed(3) : "")+
                    '">' +
                    '<div class="muted">'+escapeHtml(primaryName)+'</div>' +
                '</td>' +

                '<td>' +
                    (product.secondary_unit
                        ? '<input type="text" inputmode="decimal" class="js-output-secondary" ' +
                          'data-validation="decimal" data-decimal-places="3" ' +
                          'placeholder="0.000" value="'+
                          escapeHtml(num(item.secondary_qty) > 0 ? num(item.secondary_qty).toFixed(3) : "")+
                          '">' +
                          '<div class="muted">'+escapeHtml(secondaryName)+'</div>'
                        : '<span class="muted">—</span>') +
                '</td>' +

                '<td><strong>'+escapeHtml(qty(outputBaseQty(item)))+'</strong></td>' +

                '<td class="cell-action">' +
                    '<button class="action-button js-remove-output" type="button" ' +
                            'title="Remove Finished Product" aria-label="Remove Finished Product">' +
                        '<i data-lucide="trash-2"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        });

        body.innerHTML = html;

        if (window.Validation) Validation.init(body);
        if (window.lucide) window.lucide.createIcons();
    }

    function refreshMaterialInfo(){
        var material = currentMaterial();

        if (!material) {
            document.getElementById("materialQtyLabel").textContent = "Consume Qty";
            document.getElementById("materialAvailable").value = "";
            return;
        }

        document.getElementById("materialQtyLabel").textContent =
            "Consume Qty / " + unitText(material.primary_unit);

        document.getElementById("materialAvailable").value =
            qty(material.available_stock);
    }

    function renderMaterials(){
        var body = document.getElementById("materialsBody");

        if (!state.materials.length) {
            body.innerHTML =
                '<tr><td class="empty" colspan="7">No Materials added.</td></tr>';
            return;
        }

        var html = "";

        state.materials.forEach(function(item,index){
            var material =
                state.materialMap[String(item.product_id)] ||
                item.snapshot ||
                {};

            var available = num(material.available_stock);
            var consume = Math.max(0, num(item.qty));
            var balance = round(available - consume, 3);

            html += '<tr data-index="'+index+'">' +
                '<td class="cell-index">'+(index+1)+'</td>' +
                '<td class="cell-main"><strong>'+
                    escapeHtml(material.product_name || "-")+
                '</strong></td>' +
                '<td>'+escapeHtml(unitText(material.primary_unit))+'</td>' +
                '<td><input type="text" inputmode="decimal" class="js-material-qty" ' +
                    'data-validation="decimal" data-decimal-places="3" ' +
                    'placeholder="0.000" value="'+
                    escapeHtml(consume > 0 ? consume.toFixed(3) : "")+
                '"></td>' +
                '<td>'+escapeHtml(qty(available))+'</td>' +
                '<td><strong>'+escapeHtml(qty(balance))+'</strong></td>' +
                '<td class="cell-action">' +
                    '<button class="action-button js-remove-material" type="button" ' +
                    'title="Remove Material" aria-label="Remove Material">' +
                    '<i data-lucide="trash-2"></i></button>' +
                '</td>' +
            '</tr>';
        });

        body.innerHTML = html;

        if (window.Validation) Validation.init(body);
        if (window.lucide) window.lucide.createIcons();
    }

    function clearMaterialEntry(){
        if (materialSelect && typeof materialSelect.setValue === "function") {
            materialSelect.setValue("");
        }

        materialNode.value = "";
        document.getElementById("materialQty").value = "";
        document.getElementById("materialAvailable").value = "";
        document.getElementById("materialQtyLabel").textContent = "Consume Qty";

        if (materialSelect && typeof materialSelect.focus === "function") {
            materialSelect.focus();
        } else {
            materialNode.focus();
        }
    }

    function addMaterial(){
        var material = currentMaterial();
        var quantity = Math.max(
            0,
            num(document.getElementById("materialQty").value)
        );

        if (!material) {
            if (window.showToast) {
                showToast("Select Material Product.", {
                    type:"warning",
                    duration:2
                });
            }
            return;
        }

        if (quantity <= 0) {
            if (window.showToast) {
                showToast("Enter Consume Quantity.", {
                    type:"warning",
                    duration:2
                });
            }
            return;
        }

        var duplicate = state.materials.some(function(item){
            return Number(item.product_id) === Number(material.id);
        });

        if (duplicate) {
            if (window.showToast) {
                showToast("This Material is already added.", {
                    type:"warning",
                    duration:3
                });
            }
            return;
        }

        state.materials.push({
            product_id:Number(material.id),
            qty:round(quantity,3)
        });

        renderMaterials();
        clearMaterialEntry();
    }

    function payload(intent){
        return {
            ref:reference || "",
            intent:intent,
            production_date:document.getElementById("productionDate").value,
            remarks:document.getElementById("remarks").value.trim(),
            outputs_json:JSON.stringify(state.outputs),
            materials_json:JSON.stringify(state.materials)
        };
    }

    function setReadOnly(readOnly){
        if (!readOnly) return;

        form.querySelectorAll("input,select,textarea,button").forEach(function(element){
            element.disabled = true;
        });

        document.getElementById("formActions").hidden = true;
    }

    async function submitProduction(intent){
        Validation.clearForm(form);

        if (!Validation.validateForm(form)) return;

        if (!state.outputs.length) {
            if (window.showToast) {
                showToast("Add at least one Finished Product.", {
                    type:"warning",
                    duration:3
                });
            }
            return;
        }

        var invalidOutput = state.outputs.some(function(item){
            return outputBaseQty(item) <= 0;
        });

        if (invalidOutput) {
            if (window.showToast) {
                showToast("Each Finished Product must have Production Quantity.", {
                    type:"warning",
                    duration:3
                });
            }
            return;
        }

        if (!state.materials.length) {
            if (window.showToast) {
                showToast("Add at least one Material.", {
                    type:"warning",
                    duration:3
                });
            }
            return;
        }

        var invalidMaterial = state.materials.some(function(item){
            return num(item.qty) <= 0;
        });

        if (invalidMaterial) {
            if (window.showToast) {
                showToast("Material Consume Quantity must be greater than zero.", {
                    type:"warning",
                    duration:3
                });
            }
            return;
        }

        var saveDraftButton = document.getElementById("saveDraftButton");
        var postButton = document.getElementById("postButton");

        saveDraftButton.disabled = true;
        postButton.disabled = true;

        try {
            var result = await App.api("api/production.php", {
                method:reference ? "PUT" : "POST",
                body:payload(intent)
            });

            if (window.showToast) {
                showToast(
                    result.message || "Production saved successfully.",
                    {type:"success",duration:2}
                );
            }

            window.setTimeout(function(){
                location.href = "production-list.php";
            },700);

        } catch(error) {
            if (error && error.errors) {
                Validation.applyErrors(form, error.errors || {});
            }

            App.showError(error, "Unable to save Production.");
            saveDraftButton.disabled = false;
            postButton.disabled = false;
        }
    }

    function fillProduction(data){
        var production = data.production || {};

        state.status = Number(production.status || 1);

        document.getElementById("productionRef").value =
            production.ref || "";

        document.getElementById("productionNo").value =
            production.production_no || "";

        document.getElementById("productionDate").value =
            production.production_date || "";

        document.getElementById("remarks").value =
            production.remarks || "";

        state.outputs = (data.outputs || []).map(function(row){
            return {
                product_id:Number(row.product_id),
                primary_qty:num(row.primary_qty),
                secondary_qty:num(row.secondary_qty),
                snapshot:{
                    id:Number(row.product_id),
                    product_name:row.product_name,
                    primary_unit:{
                        unit_name:row.primary_unit_name,
                        short_name:row.primary_short_name,
                        conversion_qty:num(row.primary_conversion_qty)
                    },
                    secondary_unit:row.secondary_product_unit_id
                        ? {
                            unit_name:row.secondary_unit_name,
                            short_name:row.secondary_short_name,
                            conversion_qty:num(row.secondary_conversion_qty)
                        }
                        : null
                }
            };
        });

        state.materials = (data.materials || []).map(function(row){
            return {
                product_id:Number(row.product_id),
                qty:num(row.qty),
                snapshot:{
                    id:Number(row.product_id),
                    product_name:row.product_name,
                    available_stock:num(row.available_stock),
                    primary_unit:{
                        unit_name:row.unit_name,
                        short_name:row.short_name,
                        conversion_qty:num(row.conversion_qty)
                    }
                }
            };
        });

        renderOutputs();
        renderMaterials();

        if (state.status === 2) {
            document.getElementById("pageHeading").textContent =
                "View Posted Production";
        } else {
            document.getElementById("pageHeading").textContent =
                "Edit Production Draft";
        }

        setReadOnly(viewOnly || state.status !== 1);
    }

    async function load(){
        try {
            var options = await App.api("api/production.php?options=1");

            state.allowedActions =
                (options.data.allowed_actions || []).map(Number);

            state.finishedProducts =
                options.data.finished_products || [];

            state.materialProducts =
                options.data.material_products || [];

            state.finishedMap = {};
            state.finishedProducts.forEach(function(product){
                state.finishedMap[String(product.id)] = product;
            });

            state.materialMap = {};
            state.materialProducts.forEach(function(product){
                state.materialMap[String(product.id)] = product;
            });

            finishedSelect.setOptions(
                optionItems(
                    state.finishedProducts,
                    "id",
                    function(row){
                        return (row.product_code ? row.product_code + " - " : "") +
                            row.product_name;
                    }
                ),
                ""
            );

            materialSelect.setOptions(
                optionItems(
                    state.materialProducts,
                    "id",
                    function(row){
                        return (row.product_code ? row.product_code + " - " : "") +
                            row.product_name;
                    }
                ),
                ""
            );

            document.getElementById("productionNo").value =
                options.data.next_production_no || "";

            document.getElementById("saveDraftButton").hidden =
                !hasAction(ACTION_SAVE_DRAFT);

            document.getElementById("postButton").hidden =
                !hasAction(ACTION_POST);

            if (reference) {
                var result = await App.api(
                    "api/production.php?ref=" +
                    encodeURIComponent(reference)
                );

                state.allowedActions =
                    (result.data.allowed_actions || []).map(Number);

                fillProduction(result.data);
            } else {
                if (!hasAction(ACTION_CREATE)) {
                    setReadOnly(true);
                }

                renderOutputs();
                renderMaterials();
            }

        } catch(error) {
            setReadOnly(true);
            App.showError(error, "Unable to load Production form.");
        }

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    finishedNode.addEventListener("change", function(){
        document.getElementById("outputPrimaryQty").value = "";
        document.getElementById("outputSecondaryQty").value = "";
        refreshOutputEntry();
    });

    document.getElementById("outputPrimaryQty").addEventListener("input", refreshOutputEntry);
    document.getElementById("outputSecondaryQty").addEventListener("input", refreshOutputEntry);
    document.getElementById("addOutputButton").addEventListener("click", addOutput);

    document.getElementById("outputsBody").addEventListener("click", function(event){
        var button = event.target.closest(".js-remove-output");
        if (!button) return;

        var row = button.closest("tr[data-index]");
        var index = Number(row ? row.getAttribute("data-index") : -1);

        if (index >= 0) {
            state.outputs.splice(index,1);
            renderOutputs();
        }
    });

    document.getElementById("outputsBody").addEventListener("input", function(event){
        var row = event.target.closest("tr[data-index]");
        if (!row) return;

        var index = Number(row.getAttribute("data-index"));
        var item = state.outputs[index];
        if (!item) return;

        if (event.target.matches(".js-output-primary")) {
            item.primary_qty = event.target.value;
        }

        if (event.target.matches(".js-output-secondary")) {
            item.secondary_qty = event.target.value;
        }

        var cells = row.querySelectorAll("td");
        if (cells[4]) {
            cells[4].innerHTML =
                "<strong>" + escapeHtml(qty(outputBaseQty(item))) + "</strong>";
        }
    });

    materialNode.addEventListener("change", refreshMaterialInfo);
    document.getElementById("addMaterialButton").addEventListener("click", addMaterial);

    document.getElementById("materialsBody").addEventListener("click", function(event){
        var button = event.target.closest(".js-remove-material");
        if (!button) return;

        var row = button.closest("tr[data-index]");
        var index = Number(row ? row.getAttribute("data-index") : -1);

        if (index >= 0) {
            state.materials.splice(index,1);
            renderMaterials();
        }
    });

    document.getElementById("materialsBody").addEventListener("input", function(event){
        if (!event.target.matches(".js-material-qty")) return;

        var row = event.target.closest("tr[data-index]");
        if (!row) return;

        var index = Number(row.getAttribute("data-index"));
        if (!state.materials[index]) return;

        state.materials[index].qty = event.target.value;

        var material =
            state.materialMap[String(state.materials[index].product_id)] ||
            state.materials[index].snapshot ||
            {};

        var available = num(material.available_stock);
        var balance = round(
            available - Math.max(0, num(state.materials[index].qty)),
            3
        );

        var cells = row.querySelectorAll("td");
        if (cells[5]) {
            cells[5].innerHTML =
                "<strong>" + escapeHtml(qty(balance)) + "</strong>";
        }
    });

    document.getElementById("saveDraftButton").addEventListener("click", function(){
        submitProduction("draft");
    });

    document.getElementById("postButton").addEventListener("click", function(){
        if (!window.confirm(
            "Post this Production? Material stock will decrease and all Finished Product stock will increase."
        )) {
            return;
        }

        submitProduction("post");
    });

    load();

})(window, document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
