<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Product Form';
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

<div class="page-head">
    <div>
        <h1 id="pageHeading">Add Product</h1>
        <p>Maintain product, unit, HSN/GST and customer price-level pricing.</p>
    </div>
    <a class="btn gray" href="product-list.php"><i data-lucide="list"></i>Product List</a>
</div>

<form id="productForm" novalidate>
    <input type="hidden" name="ref" id="productRef">

    <div class="card form-card form-section">
        <div class="card-header">
            <div><h2 class="section-heading"><i data-lucide="package"></i>Basic Details</h2></div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="productCode">Product Code</label>
                    <input id="productCode" name="product_code" type="text" maxlength="30" readonly aria-readonly="true" placeholder="Auto generated">
                </div>
                <div class="field col-5">
                    <label for="productName" class="required">Product Name</label>
                    <input id="productName" name="product_name" type="text" maxlength="150" required autocomplete="off"
                           placeholder="Enter Product Name" data-required-message="Product Name is required.">
                </div>
                <div class="field col-4">
                    <label for="productType" class="required">Product Type</label>
                    <select id="productType" name="product_type" required data-required-message="Product Type is required.">
                        <option value="2">Finished Product</option>
                        <option value="1">Raw Material</option>
                        <option value="3">Consumable</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="field col-6">
                    <label for="categoryId" class="required">Category</label>
                    <div class="input-group">
                        <div class="input-group-control">
                            <select id="categoryId" name="category_id" required data-placeholder="Select or type Category"
                                    data-error-id="categoryError" data-required-message="Category is required.">
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <button class="btn btn-primary input-group-button" id="addCategoryButton" type="button" title="Add Category" aria-label="Add Category">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <small id="categoryError" class="validation-error"></small>
                </div>

                <div class="field col-6">
                    <label for="subcategoryId">Subcategory</label>
                    <div class="input-group">
                        <div class="input-group-control">
                            <select id="subcategoryId" name="subcategory_id" data-placeholder="Select or type Subcategory" data-error-id="subcategoryError">
                                <option value="">Select Subcategory</option>
                            </select>
                        </div>
                        <button class="btn btn-primary input-group-button" id="addSubcategoryButton" type="button" title="Add Subcategory" aria-label="Add Subcategory">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <small id="subcategoryError" class="validation-error"></small>
                </div>
            </div>

            <div class="form-row">
                <div class="field col-4">
                    <label for="containerType" class="required">Container Type</label>
                    <select id="containerType" name="container_type" required data-required-message="Container Type is required.">
                        <option value="1">Reusable / Returnable</option>
                        <option value="2">Use & Throw</option>
                        <option value="3" selected>Normal Product</option>
                    </select>
                </div>
                <div class="field col-4">
                    <label for="saleAllowed" class="required">Sales Allowed</label>
                    <select id="saleAllowed" name="sale_allowed" required data-required-message="Sales Allowed is required.">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                    <div class="muted">Raw Material can also be saleable when required.</div>
                </div>
                <div class="field col-4">
                    <label for="productStatus" class="required">Status</label>
                    <select id="productStatus" name="status" required>
                        <option value="1">Active</option>
                        <option value="2">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card form-card form-section">
        <div class="card-header">
            <div><h2 class="section-heading"><i data-lucide="ruler"></i>Unit Details</h2></div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-4">
                    <label for="primaryUnitId" class="required">Primary Unit</label>
                    <div class="input-group">
                        <div class="input-group-control">
                            <select id="primaryUnitId" name="primary_unit_id" required data-placeholder="Select or type Primary Unit"
                                    data-error-id="primaryUnitError" data-required-message="Primary Unit is required.">
                                <option value="">Select Primary Unit</option>
                            </select>
                        </div>
                        <button class="btn btn-primary input-group-button js-add-unit" data-target="primary" type="button" title="Add Unit" aria-label="Add Primary Unit">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <small id="primaryUnitError" class="validation-error"></small>
                </div>

                <div class="field col-4">
                    <label for="secondaryUnitId">Secondary Unit</label>
                    <div class="input-group">
                        <div class="input-group-control">
                            <select id="secondaryUnitId" name="secondary_unit_id" data-placeholder="Select or type Secondary Unit" data-error-id="secondaryUnitError">
                                <option value="">Select Secondary Unit</option>
                            </select>
                        </div>
                        <button class="btn btn-primary input-group-button js-add-unit" data-target="secondary" type="button" title="Add Unit" aria-label="Add Secondary Unit">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <small id="secondaryUnitError" class="validation-error"></small>
                </div>

                <div class="field col-4">
                    <label for="conversionQty">Conversion Qty</label>
                    <input id="conversionQty" name="conversion_qty" type="text" inputmode="decimal"
                           data-validation="decimal" data-decimal-places="4"
                           data-regex="^(?:[1-9][0-9]*(?:\.[0-9]{1,4})?|0*\.[0-9]{1,4})$"
                           data-regex-message="Enter a Conversion Qty greater than zero." placeholder="Example: 12">
                    <div class="muted" id="conversionHelp">Select a Secondary Unit to set conversion.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card form-card form-section">
        <div class="card-header">
            <div><h2 class="section-heading"><i data-lucide="receipt-text"></i>HSN & GST</h2></div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-6">
                    <label for="hsnId">HSN Code</label>
                    <div class="input-group">
                        <div class="input-group-control">
                            <select id="hsnId" name="hsn_id" data-placeholder="Select or type HSN" data-error-id="hsnError">
                                <option value="">No HSN / Non-GST</option>
                            </select>
                        </div>
                        <button class="btn btn-primary input-group-button" id="addHsnButton" type="button" title="Add HSN" aria-label="Add HSN">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <small id="hsnError" class="validation-error"></small>
                </div>
                <div class="field col-3">
                    <label for="gstType" class="required">GST Type</label>
                    <select id="gstType" name="gst_type" required>
                        <option value="1">Inclusive</option>
                        <option value="2" selected>Exclusive</option>
                    </select>
                </div>
                <div class="field col-3">
                    <label for="gstRate">GST %</label>
                    <input id="gstRate" type="text" value="0.00" readonly aria-readonly="true">
                </div>
            </div>

            <div class="form-row">
                <div class="field col-3"><label for="cgstRate">CGST %</label><input id="cgstRate" type="text" value="0.00" readonly aria-readonly="true"></div>
                <div class="field col-3"><label for="sgstRate">SGST %</label><input id="sgstRate" type="text" value="0.00" readonly aria-readonly="true"></div>
                <div class="field col-3"><label for="igstRate">IGST %</label><input id="igstRate" type="text" value="0.00" readonly aria-readonly="true"></div>
                <div class="field col-3"><label for="cessRate">Cess %</label><input id="cessRate" type="text" value="0.00" readonly aria-readonly="true"></div>
            </div>
        </div>
    </div>

    <div class="card form-card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="indian-rupee"></i>Base Price</h2>
                <p>Base / Purchase Price is for one Primary Unit.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-4">
                    <label for="purchasePrice" class="required">Base / Purchase Price</label>
                    <input id="purchasePrice" name="purchase_price" type="text" inputmode="decimal" required value="0.00"
                           data-validation="decimal" data-decimal-places="2"
                           data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                           data-required-message="Base / Purchase Price is required."
                           data-regex-message="Enter a valid Base / Purchase Price.">
                </div>
                <div class="field col-4">
                    <label>Primary Unit Base</label>
                    <input id="primaryBaseDisplay" type="text" value="₹0.00" readonly aria-readonly="true">
                </div>
                <div class="field col-4">
                    <label>Secondary Unit Base</label>
                    <input id="secondaryBaseDisplay" type="text" value="-" readonly aria-readonly="true">
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card form-section pricing-card" id="pricingCard">
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="badge-indian-rupee"></i>Customer Price-Level Pricing</h2>
                <p>Enter pricing once for each Price Level. Secondary Unit price is calculated automatically from Unit Conversion.</p>
            </div>
        </div>
        <div class="app-table-wrap">
            <table id="pricingTable" class="data-table app-editable-table">
                <thead>
                <tr>
                    <th>Customer Type / Price Level</th>
                    <th id="pricingBaseHeader">Primary Unit Base</th>
                    <th>Markup Type</th>
                    <th>Markup Value</th>
                    <th id="primaryPriceHeader">Primary Unit Selling Price</th>
                    <th id="secondaryPriceHeader" hidden>Secondary Unit Selling Price</th>
                </tr>
                </thead>
                <tbody id="pricingBody">
                <tr><td colspan="6" class="empty">Select Primary Unit to configure pricing.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card form-card form-section">
        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit">
                    <i data-lucide="save"></i><span id="saveButtonText">Save Product</span>
                </button>
                <a class="btn gray" href="product-list.php">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php
$smallMasterForms = [
    __DIR__ . '/model/category-form.php',
    __DIR__ . '/model/subcategory-form.php',
    __DIR__ . '/model/unit-form.php',
    __DIR__ . '/model/hsn-form.php',
];
foreach ($smallMasterForms as $smallMasterForm) {
    if (is_file($smallMasterForm)) require $smallMasterForm;
}
?>

<script>
(function(window,document){
    "use strict";

    var form=document.getElementById("productForm");
    var reference=new URLSearchParams(window.location.search).get("ref")||"";
    var saveButton=document.getElementById("saveButton");
    var saveButtonText=document.getElementById("saveButtonText");
    var pricingCard=document.getElementById("pricingCard");
    var pricingBody=document.getElementById("pricingBody");

    var categorySelect=GlobalSelect.init("#categoryId",{placeholder:"Select or type Category"});
    var subcategorySelect=GlobalSelect.init("#subcategoryId",{placeholder:"Select or type Subcategory"});
    var primaryUnitSelect=GlobalSelect.init("#primaryUnitId",{placeholder:"Select or type Primary Unit"});
    var secondaryUnitSelect=GlobalSelect.init("#secondaryUnitId",{placeholder:"Select or type Secondary Unit"});
    var hsnSelect=GlobalSelect.init("#hsnId",{placeholder:"Select or type HSN"});

    var categoryRows=[],subcategoryRows=[],unitRows=[],hsnRows=[],priceLevelRows=[];
    var unitMap={},hsnMap={},allowedActions=[],loading=false;
    var pricingState={};

    function dataOf(response){return response&&response.data&&typeof response.data==="object"?response.data:(response||{});}
    function hasAction(id){return allowedActions.map(Number).indexOf(Number(id))!==-1;}
    function escapeHtml(value){
        if(window.App&&typeof App.escapeHtml==="function")return App.escapeHtml(String(value==null?"":value));
        return String(value==null?"":value).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
    }
    function num(value){var n=Number(value||0);return Number.isFinite(n)?n:0;}
    function money(value){return "₹"+num(value).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});}
    function rate(value){return num(value).toFixed(2);}
    function optionItems(rows,valueKey,textBuilder){return (rows||[]).map(function(row){return{value:String(row[valueKey]),text:String(textBuilder(row))};});}

    function setCategoryOptions(selected){
        categorySelect.setOptions(optionItems(categoryRows,"id",function(row){
            var text=row.category_code?row.category_code+" - "+row.category_name:row.category_name;
            if(Number(row.status)!==1)text+=" (Inactive)";
            return text;
        }),selected?String(selected):"");
    }
    function setSubcategoryOptions(selected){
        subcategorySelect.setOptions(optionItems(subcategoryRows,"id",function(row){
            var text=row.subcategory_code?row.subcategory_code+" - "+row.subcategory_name:row.subcategory_name;
            if(Number(row.status)!==1)text+=" (Inactive)";
            return text;
        }),selected?String(selected):"");
    }
    function rebuildUnitMap(){unitMap={};unitRows.forEach(function(row){unitMap[String(row.id)]=row;});}
    function unitText(row){
        var text=row.short_name?row.unit_name+" ("+row.short_name+")":row.unit_name;
        if(Number(row.status)!==1)text+=" (Inactive)";
        return text;
    }
    function setUnitOptions(primary,secondary){
        rebuildUnitMap();
        var items=optionItems(unitRows,"id",unitText);
        primaryUnitSelect.setOptions(items,primary?String(primary):"");
        secondaryUnitSelect.setOptions(items,secondary?String(secondary):"");
    }
    function unitLabel(id){var row=unitMap[String(id||"")]||null;return row?(row.short_name||row.unit_name||"-"):"-";}

    function rebuildHsnMap(){hsnMap={};hsnRows.forEach(function(row){hsnMap[String(row.id)]=row;});}
    function hsnText(row){
        var text=row.hsn_code||"";
        if(row.description)text+=" - "+row.description;
        text+=" - GST "+rate(row.gst_rate)+"%";
        if(Number(row.status)!==1)text+=" (Inactive)";
        return text;
    }
    function setHsnOptions(selected){
        rebuildHsnMap();
        hsnSelect.setOptions(optionItems(hsnRows,"id",hsnText),selected?String(selected):"");
        applyHsnTaxes();
    }
    function currentHsn(){return hsnMap[String(form.hsn_id.value||"")]||null;}
    function applyHsnTaxes(){
        var row=currentHsn();
        document.getElementById("gstRate").value=rate(row&&row.gst_rate);
        document.getElementById("cgstRate").value=rate(row&&row.cgst_rate);
        document.getElementById("sgstRate").value=rate(row&&row.sgst_rate);
        document.getElementById("igstRate").value=rate(row&&row.igst_rate);
        document.getElementById("cessRate").value=rate(row&&row.cess_rate);
    }

    function primaryBase(){return Math.max(0,num(form.purchase_price.value));}
    function secondaryBase(){
        if(!Number(form.secondary_unit_id.value||0))return null;
        var conversion=num(form.conversion_qty.value);
        if(conversion<=0)return 0;
        return Math.round((primaryBase()*conversion+Number.EPSILON)*100)/100;
    }
    function updateBaseDisplays(){
        document.getElementById("primaryBaseDisplay").value=money(primaryBase());
        var secondary=secondaryBase();
        document.getElementById("secondaryBaseDisplay").value=secondary===null?"-":money(secondary);
    }

    function updateConversionState(){
        var primaryId=Number(form.primary_unit_id.value||0);
        var secondaryId=Number(form.secondary_unit_id.value||0);
        if(primaryId&&secondaryId&&primaryId===secondaryId){
            secondaryUnitSelect.setValue("");
            secondaryId=0;
            if(window.showToast)showToast("Primary Unit and Secondary Unit must be different.",{type:"warning",duration:3});
        }
        if(!secondaryId){
            form.conversion_qty.disabled=true;
            form.conversion_qty.required=false;
            form.conversion_qty.value="";
            document.getElementById("conversionHelp").textContent="Select a Secondary Unit to set conversion.";
        }else{
            form.conversion_qty.disabled=false;
            form.conversion_qty.required=true;
            form.conversion_qty.setAttribute("data-required-message","Conversion Qty is required when Secondary Unit is selected.");
            document.getElementById("conversionHelp").textContent="1 "+unitLabel(secondaryId)+" = Conversion Qty × "+unitLabel(primaryId)+".";
        }
        updateBaseDisplays();
        renderPricing();
    }

    function configFor(levelId){
        var config=pricingState[String(levelId)]||{};
        return{
            markup_type:Number(config.markup_type||1),
            markup_value:config.markup_value===undefined||config.markup_value===null||config.markup_value===""?"0.00":String(config.markup_value)
        };
    }
    function sellingPrice(base,markupType,markupValue){
        base=Math.max(0,num(base));
        markupValue=Math.max(0,num(markupValue));
        return Number(markupType)===2
            ?Math.round((base+markupValue+Number.EPSILON)*100)/100
            :Math.round((base+(base*markupValue/100)+Number.EPSILON)*100)/100;
    }
    function convertedSellingPrice(primarySelling){
        var secondaryId=Number(form.secondary_unit_id.value||0);
        if(!secondaryId)return null;
        var conversion=num(form.conversion_qty.value);
        if(conversion<=0)return 0;
        return Math.round((num(primarySelling)*conversion+Number.EPSILON)*100)/100;
    }
    function capturePricing(){
        pricingBody.querySelectorAll("tr[data-price-level-id]").forEach(function(row){
            var levelId=row.getAttribute("data-price-level-id");
            pricingState[String(levelId)]={
                markup_type:Number(row.querySelector(".js-markup-type").value||1),
                markup_value:row.querySelector(".js-markup-value").value.trim()
            };
        });
    }
    function updatePricingHeaders(){
        var primaryId=Number(form.primary_unit_id.value||0);
        var secondaryId=Number(form.secondary_unit_id.value||0);
        var primaryLabel=primaryId?unitLabel(primaryId):"Primary Unit";
        var secondaryLabel=secondaryId?unitLabel(secondaryId):"Secondary Unit";
        document.getElementById("pricingBaseHeader").textContent=primaryLabel+" Base Price";
        document.getElementById("primaryPriceHeader").textContent=primaryLabel+" Selling Price";
        var secondaryHeader=document.getElementById("secondaryPriceHeader");
        secondaryHeader.textContent=secondaryLabel+" Selling Price";
        secondaryHeader.hidden=!secondaryId;
    }
    function renderPricing(){
        capturePricing();
        updateBaseDisplays();
        updatePricingHeaders();

        var saleAllowed=Number(form.sale_allowed.value||0)===1;
        pricingCard.hidden=!saleAllowed;
        if(!saleAllowed)return;

        var primaryId=Number(form.primary_unit_id.value||0);
        if(!primaryId){
            pricingBody.innerHTML='<tr><td colspan="6" class="empty">Select Primary Unit to configure pricing.</td></tr>';
            return;
        }
        if(!priceLevelRows.length){
            pricingBody.innerHTML='<tr><td colspan="6" class="empty">No active Price Level found. Create Price Level first.</td></tr>';
            return;
        }

        var secondaryId=Number(form.secondary_unit_id.value||0);
        var html="";

        priceLevelRows.forEach(function(level){
            var config=configFor(level.id);
            var primarySale=sellingPrice(primaryBase(),config.markup_type,config.markup_value);
            var secondarySale=convertedSellingPrice(primarySale);

            html+='<tr data-price-level-id="'+String(level.id)+'">'+
                '<td>'+escapeHtml(level.price_level_name||"")+'</td>'+
                '<td class="js-base-price">'+escapeHtml(money(primaryBase()))+'</td>'+
                '<td><select class="js-markup-type">'+
                    '<option value="1" '+(config.markup_type===1?'selected':'')+'>Percentage</option>'+
                    '<option value="2" '+(config.markup_type===2?'selected':'')+'>Fixed Amount</option>'+
                '</select></td>'+
                '<td><input class="js-markup-value" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="2" '+
                    'data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$" data-regex-message="Enter a valid Markup Value." '+
                    'value="'+escapeHtml(config.markup_value)+'" placeholder="0.00"></td>'+
                '<td class="js-primary-selling-price">'+escapeHtml(money(primarySale))+'</td>'+
                '<td class="js-secondary-selling-price" '+(secondaryId?'':'hidden')+'>'+
                    escapeHtml(secondarySale===null?"-":money(secondarySale))+
                '</td>'+
            '</tr>';
        });

        pricingBody.innerHTML=html;
        if(window.Validation)Validation.init(pricingBody);
    }
    function recalcPricing(){
        updateBaseDisplays();
        updatePricingHeaders();
        var secondaryId=Number(form.secondary_unit_id.value||0);

        pricingBody.querySelectorAll("tr[data-price-level-id]").forEach(function(row){
            var base=primaryBase();
            var markupType=row.querySelector(".js-markup-type").value;
            var markupValue=row.querySelector(".js-markup-value").value;
            var primarySale=sellingPrice(base,markupType,markupValue);
            var secondarySale=convertedSellingPrice(primarySale);
            var baseCell=row.querySelector(".js-base-price");
            var primaryCell=row.querySelector(".js-primary-selling-price");
            var secondaryCell=row.querySelector(".js-secondary-selling-price");

            if(baseCell)baseCell.textContent=money(base);
            if(primaryCell)primaryCell.textContent=money(primarySale);
            if(secondaryCell){
                secondaryCell.hidden=!secondaryId;
                secondaryCell.textContent=secondarySale===null?"-":money(secondarySale);
            }
        });
    }
    function pricePayload(){
        capturePricing();
        var payload={};
        Object.keys(pricingState||{}).forEach(function(levelId){
            var config=pricingState[levelId]||{};
            payload[levelId]={
                markup_type:Number(config.markup_type||1),
                markup_value:String(config.markup_value||"0.00")
            };
        });
        return payload;
    }

    function upsert(rows,row){
        var found=false;
        var result=(rows||[]).map(function(item){if(Number(item.id)===Number(row.id)){found=true;return row;}return item;});
        if(!found)result.push(row);
        return result;
    }
    async function loadSubcategories(categoryId,selectedId){
        if(!categoryId){subcategoryRows=[];setSubcategoryOptions("");return;}
        var url="api/products.php?subcategories=1&category_id="+encodeURIComponent(categoryId);
        if(selectedId)url+="&include_subcategory_id="+encodeURIComponent(selectedId);
        var response=await App.api(url);
        var data=dataOf(response);
        subcategoryRows=Array.isArray(data.subcategories)?data.subcategories:[];
        setSubcategoryOptions(selectedId||"");
    }
    function applyOptions(options,selected){
        options=options||{};selected=selected||{};
        categoryRows=options.categories||[];
        subcategoryRows=options.subcategories||[];
        unitRows=options.units||[];
        hsnRows=options.hsn_codes||[];
        priceLevelRows=options.price_levels||[];
        setCategoryOptions(selected.category_id||"");
        setSubcategoryOptions(selected.subcategory_id||"");
        setUnitOptions(selected.primary_unit_id||"",selected.secondary_unit_id||"");
        setHsnOptions(selected.hsn_id||"");
    }
    function fillProduct(data){
        var product=data.product||{};
        var units=data.units||{};
        pricingState={};
        var savedPrimaryPrices=data.prices&&data.prices.primary?data.prices.primary:{};
        var savedSecondaryPrices=data.prices&&data.prices.secondary?data.prices.secondary:{};
        var savedPrices=Object.keys(savedPrimaryPrices).length?savedPrimaryPrices:savedSecondaryPrices;
        Object.keys(savedPrices||{}).forEach(function(levelId){
            var row=savedPrices[levelId]||{};
            pricingState[String(levelId)]={
                markup_type:Number(row.markup_type||1),
                markup_value:String(row.markup_value==null?"0.00":row.markup_value)
            };
        });
        document.getElementById("productRef").value=product.ref||"";
        form.product_code.value=product.product_code||"";
        form.product_name.value=product.product_name||"";
        form.product_type.value=String(Number(product.product_type||2));
        form.container_type.value=String(Number(product.container_type||3));
        form.sale_allowed.value=String(Number(product.sale_allowed)===1?1:0);
        form.status.value=String(Number(product.status)===1?1:2);
        form.gst_type.value=String(Number(product.gst_type||2));
        form.purchase_price.value=Number(product.purchase_price||0).toFixed(2);

        applyOptions(data.options||{}, {
            category_id:product.category_id,
            subcategory_id:product.subcategory_id,
            primary_unit_id:units.primary?units.primary.unit_id:"",
            secondary_unit_id:units.secondary?units.secondary.unit_id:"",
            hsn_id:product.hsn_id
        });
        form.conversion_qty.value=units.secondary?String(units.secondary.conversion_qty||""):"";
        applyHsnTaxes();
        updateConversionState();
        renderPricing();
    }

    async function load(){
        if(loading)return;
        loading=true;
        try{
            if(reference){
                document.getElementById("pageHeading").textContent="Edit Product";
                saveButtonText.textContent="Update Product";
                var response=await App.api("api/products.php?ref="+encodeURIComponent(reference));
                var data=dataOf(response);
                allowedActions=(data.allowed_actions||[]).map(Number);
                fillProduct(data);
                if(!hasAction(3))saveButton.disabled=true;
            }else{
                var responseCreate=await App.api("api/products.php?options=1");
                var dataCreate=dataOf(responseCreate);
                allowedActions=(dataCreate.allowed_actions||[]).map(Number);
                form.product_code.value=dataCreate.next_product_code||"";
                applyOptions(dataCreate.options||{},{});
                form.product_type.value="2";
                form.container_type.value="3";
                form.sale_allowed.value="1";
                form.status.value="1";
                form.gst_type.value="2";
                form.purchase_price.value="0.00";
                updateConversionState();
                renderPricing();
                if(!hasAction(2))saveButton.disabled=true;
            }
        }catch(error){
            saveButton.disabled=true;
            App.showError(error,"Unable to load Product form.");
        }finally{loading=false;}
        if(window.lucide)window.lucide.createIcons();
    }

    form.category_id.addEventListener("change",function(){
        loadSubcategories(form.category_id.value,"").catch(function(error){App.showError(error,"Unable to load Subcategories.");});
    });
    form.hsn_id.addEventListener("change",applyHsnTaxes);
    form.primary_unit_id.addEventListener("change",updateConversionState);
    form.secondary_unit_id.addEventListener("change",updateConversionState);
    form.sale_allowed.addEventListener("change",renderPricing);
    form.purchase_price.addEventListener("input",recalcPricing);
    form.conversion_qty.addEventListener("input",recalcPricing);
    pricingBody.addEventListener("input",function(event){if(event.target.matches(".js-markup-value"))recalcPricing();});
    pricingBody.addEventListener("change",function(event){if(event.target.matches(".js-markup-type"))recalcPricing();});

    document.getElementById("addCategoryButton").addEventListener("click",function(){
        if(!window.AppCategoryForm){App.showError(null,"Reusable Category modal is unavailable.");return;}
        AppCategoryForm.openCreate({
            apiUrl:"api/category.php",
            onSaved:function(category){
                if(!category)return;
                categoryRows=upsert(categoryRows,category);
                setCategoryOptions(category.id);
                loadSubcategories(category.id,"").catch(function(error){App.showError(error,"Unable to load Subcategories.");});
            }
        });
    });

    document.getElementById("addSubcategoryButton").addEventListener("click",function(){
        var categoryId=Number(form.category_id.value||0);
        if(!categoryId){
            if(window.showToast)showToast("Select Category first.",{type:"warning",duration:3});
            if(categorySelect&&categorySelect.focus)categorySelect.focus();
            return;
        }
        if(!window.AppSubcategoryForm){App.showError(null,"Reusable Subcategory modal is unavailable.");return;}
        AppSubcategoryForm.openCreate({
            apiUrl:"api/subcategory.php",
            categoryId:categoryId,
            onSaved:function(subcategory){
                if(!subcategory)return;
                subcategoryRows=upsert(subcategoryRows,subcategory);
                setSubcategoryOptions(subcategory.id);
            }
        });
    });

    document.addEventListener("click",function(event){
        var button=event.target.closest(".js-add-unit");
        if(!button)return;
        if(!window.AppUnitForm){App.showError(null,"Reusable Unit modal is unavailable.");return;}
        var target=button.getAttribute("data-target")||"primary";
        AppUnitForm.openCreate({
            apiUrl:"api/unit.php",
            onSaved:function(unit){
                if(!unit)return;
                var primaryId=form.primary_unit_id.value||"";
                var secondaryId=form.secondary_unit_id.value||"";
                unitRows=upsert(unitRows,unit);
                if(target==="primary")primaryId=unit.id;else secondaryId=unit.id;
                setUnitOptions(primaryId,secondaryId);
                updateConversionState();
            }
        });
    });

    document.getElementById("addHsnButton").addEventListener("click",function(){
        if(!window.AppHSNForm){App.showError(null,"Reusable HSN modal is unavailable.");return;}
        AppHSNForm.openCreate({
            apiUrl:"api/hsn.php",
            onSaved:function(hsn){
                if(!hsn)return;
                hsnRows=upsert(hsnRows,hsn);
                setHsnOptions(hsn.id);
            }
        });
    });

    form.addEventListener("submit",async function(event){
        event.preventDefault();
        Validation.clearForm(form);
        updateConversionState();
        if(!Validation.validateForm(form))return;

        var primaryId=Number(form.primary_unit_id.value||0);
        var secondaryId=Number(form.secondary_unit_id.value||0);
        if(secondaryId&&primaryId===secondaryId){
            Validation.applyErrors(form,{secondary_unit_id:"Primary Unit and Secondary Unit must be different."});
            return;
        }
        if(Number(form.sale_allowed.value||0)===1&&!priceLevelRows.length){
            App.showError(null,"Create at least one active Price Level before enabling Sales Allowed.");
            return;
        }

        capturePricing();
        var payload={
            ref:reference||undefined,
            product_name:form.product_name.value.trim(),
            product_type:Number(form.product_type.value||2),
            category_id:Number(form.category_id.value||0),
            subcategory_id:form.subcategory_id.value?Number(form.subcategory_id.value):null,
            hsn_id:form.hsn_id.value?Number(form.hsn_id.value):null,
            gst_type:Number(form.gst_type.value||2),
            purchase_price:form.purchase_price.value.trim(),
            container_type:Number(form.container_type.value||3),
            sale_allowed:Number(form.sale_allowed.value||0),
            status:Number(form.status.value||1),
            primary_unit_id:primaryId,
            secondary_unit_id:secondaryId||null,
            conversion_qty:secondaryId?form.conversion_qty.value.trim():"1.0000",
            prices:pricePayload()
        };

        saveButton.disabled=true;
        try{
            var response=await App.api("api/products.php",{method:reference?"PUT":"POST",body:payload});
            if(window.showToast)showToast(response.message||"Product saved successfully.",{type:"success",duration:2});
            window.setTimeout(function(){window.location.href="product-list.php";},700);
        }catch(error){
            var applied=false;
            if(error&&error.errors&&Object.keys(error.errors).length){
                applied=Validation.applyErrors(form,error.errors);
            }
            if(!applied)App.showError(error,"Unable to save Product.");
            saveButton.disabled=false;
        }
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
