<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Form';
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
        <h1 id="pageHeading">Add Customer</h1>
        <p>Create or update a branch customer.</p>
    </div>
    <a class="btn gray" href="customer-list.php"><i data-lucide="list"></i>Customer List</a>
</div>

<form id="customerForm" novalidate>
    <input type="hidden" name="ref" id="customerRef">

    <div class="card form-card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="user-round"></i>Customer Information</h2>
            </div>
        </div>

        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="customerCode">Customer Code</label>
                    <input id="customerCode" name="customer_code" type="text" maxlength="30" readonly aria-readonly="true" placeholder="Auto generated">
                </div>

                <div class="field col-5">
                    <label for="customerName" class="required">Customer Name</label>
                    <input id="customerName" name="customer_name" type="text" maxlength="150" required
                           data-required-message="Customer name is required."
                           placeholder="Enter customer name">
                </div>

                <div class="field col-4">
                    <label for="customerMobile">Mobile</label>
                    <input id="customerMobile" name="mobile" type="text" inputmode="numeric" maxlength="10"
                           data-validation="mobile"
                           data-mobile-message="Enter a valid 10-digit mobile number."
                           placeholder="Enter mobile number">
                </div>

                <div class="field col-4">
                    <label for="lineSelect" class="required">Line</label>
                    <select id="lineSelect" name="line_id" required
                            data-required-message="Please select a line."
                            data-global-select data-placeholder="Select Line">
                        <option value="">Select Line</option>
                    </select>
                </div>

                <div class="field col-4">
                    <label for="lineSequence">Line Sequence</label>
                    <input id="lineSequence" name="line_sequence" type="text" inputmode="numeric"
                           data-validation="integer"
                           data-regex="^[1-9][0-9]*$"
                           data-regex-message="Enter a positive whole number."
                           placeholder="Visit order">
                </div>

                <div class="field col-4">
                    <label for="priceLevelSelect" class="required">Price Level</label>
                    <select id="priceLevelSelect" name="price_level_id" required
                            data-required-message="Please select a price level."
                            data-global-select data-placeholder="Select Price Level">
                        <option value="">Select Price Level</option>
                    </select>
                </div>

                <div class="field col-4">
                    <label for="creditLimit">Credit Limit</label>
                    <input id="creditLimit" name="credit_limit" type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="2"
                           data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                           data-regex-message="Enter zero or a positive amount."
                           placeholder="0.00">
                </div>

                <div class="field col-4">
                    <label for="openingBalance">Opening Balance</label>
                    <input id="openingBalance" name="opening_balance" type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="2"
                           data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                           data-regex-message="Enter zero or a positive amount."
                           placeholder="0.00">
                </div>

                <input id="openingCanBalance" name="opening_can_balance" type="hidden" value="0.000">

                <div class="field col-4">
                    <label for="customerStatus">Status</label>
                    <select id="customerStatus" name="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="field col-12">
                    <label for="customerAddress">Address</label>
                    <textarea id="customerAddress" name="address" rows="3" placeholder="Enter customer address"></textarea>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit">
                    <i data-lucide="save"></i><span id="saveButtonText">Save Customer</span>
                </button>
                <a class="btn gray" href="customer-list.php">Cancel</a>
            </div>
        </div>
    </div>

    <div class="card form-card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading">
                    <i data-lucide="package-open"></i>
                    Customer Opening Stock
                </h2>
                <p style="margin:6px 0 0;">
                    Reusable / returnable products already with this customer before ERP start.
                    This does not reduce warehouse stock.
                </p>
            </div>
        </div>

        <div class="card-body">
            <div id="openingStockLockNote" class="muted" style="display:none;margin-bottom:12px;"></div>

            <div class="form-row" id="openingStockEntryRow">
                <div class="field col-4">
                    <label for="openingStockProduct">Product</label>
                    <select id="openingStockProduct"
                            data-placeholder="Select reusable product">
                        <option value="">Select reusable product</option>
                    </select>
                </div>

                <div class="field col-2">
                    <label for="openingPrimaryQty" id="openingPrimaryQtyLabel">Primary Qty</label>
                    <input id="openingPrimaryQty"
                           type="text"
                           inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           placeholder="0.000">
                </div>

                <div class="field col-2">
                    <label for="openingSecondaryQty" id="openingSecondaryQtyLabel">Secondary Qty</label>
                    <input id="openingSecondaryQty"
                           type="text"
                           inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           placeholder="0.000"
                           disabled>
                </div>

                <div class="field col-2">
                    <label for="openingBaseQty">Base Qty</label>
                    <input id="openingBaseQty"
                           type="text"
                           value="0"
                           readonly
                           aria-readonly="true">
                </div>

                <div class="field col-2">
                    <label for="addOpeningStockButton">&nbsp;</label>
                    <button class="btn btn-primary"
                            id="addOpeningStockButton"
                            type="button">
                        <i data-lucide="plus"></i>
                        Add
                    </button>
                </div>
            </div>

            <div id="openingConversionInfo" class="muted" style="margin-top:8px;">
                Select a reusable / returnable product.
            </div>

            <div class="app-table-wrap" style="margin-top:16px;">
                <table class="app-editable-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Primary Qty</th>
                        <th>Secondary Qty</th>
                        <th>Conversion</th>
                        <th>Base Qty</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="openingStockBody">
                    <tr>
                        <td class="empty" colspan="7">No customer opening stock added.</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px;font-weight:600;">
                Total Opening Can / Container Balance:
                <span id="openingStockTotal">0</span>
            </div>
        </div>
    </div>
</form>

<script>
(function(window,document){
    "use strict";

    var form=document.getElementById("customerForm");
    var saveButton=document.getElementById("saveButton");
    var saveButtonText=document.getElementById("saveButtonText");
    var reference=new URLSearchParams(location.search).get("ref")||"";

    var lineSelect=GlobalSelect.init("#lineSelect",{placeholder:"Select or type line"});
    var priceLevelSelect=GlobalSelect.init("#priceLevelSelect",{placeholder:"Select or type price level"});
    var openingProductSelect=GlobalSelect.init("#openingStockProduct",{placeholder:"Select reusable product"});

    var isLoading=false;

    var openingProducts=[];
    var openingProductMap={};
    var openingRows=[];
    var openingLocked=false;
    var legacyOpeningBalance=0;

    function hasAction(actions,id){
        return (actions||[]).map(Number).indexOf(Number(id))!==-1;
    }

    function optionItems(rows,valueKey,textKey){
        return (rows||[]).map(function(row){
            return {value:String(row[valueKey]),text:String(row[textKey]||"")};
        });
    }

    function productOptions(rows){
        return (rows||[]).map(function(row){
            return {
                value:String(row.id),
                text:(row.product_code?row.product_code+" - ":"")+row.product_name
            };
        });
    }

    function numberValue(value){
        var n=Number(value||0);
        return Number.isFinite(n)?n:0;
    }

    function qty(value){
        return numberValue(value).toLocaleString("en-IN",{
            minimumFractionDigits:0,
            maximumFractionDigits:3
        });
    }

    function escapeHtml(value){
        return String(value==null?"":value)
            .replace(/&/g,"&amp;")
            .replace(/</g,"&lt;")
            .replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;")
            .replace(/'/g,"&#039;");
    }

    function unitName(unit){
        return unit?(unit.short_name||unit.unit_name||"-"):"-";
    }

    function buildOpeningProductMap(){
        openingProductMap={};
        openingProducts.forEach(function(product){
            openingProductMap[String(product.id)]=product;
        });
    }

    function selectedOpeningProduct(){
        return openingProductMap[
            String(document.getElementById("openingStockProduct").value||"")
        ]||null;
    }

    function calculateBaseQty(product,primaryQty,secondaryQty){
        if(!product||!product.primary_unit)return 0;

        var primaryConversion=Math.max(
            1,
            numberValue(product.primary_unit.conversion_qty||1)
        );

        var secondaryConversion=product.secondary_unit
            ?Math.max(1,numberValue(product.secondary_unit.conversion_qty||1))
            :0;

        return Math.round(
            (
                numberValue(primaryQty)*primaryConversion+
                numberValue(secondaryQty)*secondaryConversion
            )*1000
        )/1000;
    }

    function conversionText(product){
        if(!product||!product.primary_unit)return "";

        var primary=unitName(product.primary_unit);

        if(!product.secondary_unit){
            return "Primary Unit: "+primary;
        }

        var secondary=unitName(product.secondary_unit);
        var pc=Math.max(1,numberValue(product.primary_unit.conversion_qty||1));
        var sc=Math.max(1,numberValue(product.secondary_unit.conversion_qty||1));

        if(sc===1){
            return "1 "+primary+" = "+qty(pc)+" "+secondary;
        }

        return "Base Qty = "+primary+" × "+qty(pc)+" + "+secondary+" × "+qty(sc);
    }

    function refreshOpeningLiveCalculation(){
        var product=selectedOpeningProduct();
        var primaryInput=document.getElementById("openingPrimaryQty");
        var secondaryInput=document.getElementById("openingSecondaryQty");

        if(!product){
            document.getElementById("openingPrimaryQtyLabel").textContent="Primary Qty";
            document.getElementById("openingSecondaryQtyLabel").textContent="Secondary Qty";
            document.getElementById("openingBaseQty").value="0";
            document.getElementById("openingConversionInfo").textContent=
                "Select a reusable / returnable product.";
            secondaryInput.disabled=true;
            secondaryInput.value="";
            return;
        }

        document.getElementById("openingPrimaryQtyLabel").textContent=
            unitName(product.primary_unit)+" Qty";

        document.getElementById("openingSecondaryQtyLabel").textContent=
            product.secondary_unit
                ?unitName(product.secondary_unit)+" Qty"
                :"Secondary Qty";

        secondaryInput.disabled=!product.secondary_unit||openingLocked;

        if(!product.secondary_unit){
            secondaryInput.value="";
        }

        var baseQty=calculateBaseQty(
            product,
            primaryInput.value,
            secondaryInput.value
        );

        document.getElementById("openingBaseQty").value=qty(baseQty);
        document.getElementById("openingConversionInfo").textContent=
            conversionText(product)+" · Live Total: "+qty(baseQty)+" base units";
    }

    function openingTotal(){
        return openingRows.reduce(function(total,row){
            var product=openingProductMap[String(row.product_id)]||row.product_snapshot||{};
            return total+calculateBaseQty(
                product,
                row.primary_qty,
                row.secondary_qty
            );
        },0);
    }

    function renderOpeningRows(){
        var body=document.getElementById("openingStockBody");

        if(!openingRows.length){
            body.innerHTML=
                '<tr><td class="empty" colspan="7">No customer opening stock added.</td></tr>';
        }else{
            body.innerHTML=openingRows.map(function(row,index){
                var product=openingProductMap[String(row.product_id)]||row.product_snapshot||{};
                var baseQty=calculateBaseQty(
                    product,
                    row.primary_qty,
                    row.secondary_qty
                );

                return '<tr>'+
                    '<td>'+(index+1)+'</td>'+
                    '<td>'+escapeHtml(product.product_name||row.product_name||"")+'</td>'+
                    '<td>'+escapeHtml(qty(row.primary_qty))+' '+escapeHtml(unitName(product.primary_unit))+'</td>'+
                    '<td>'+(product.secondary_unit
                        ?escapeHtml(qty(row.secondary_qty))+' '+escapeHtml(unitName(product.secondary_unit))
                        :'-')+'</td>'+
                    '<td>'+escapeHtml(conversionText(product))+'</td>'+
                    '<td>'+escapeHtml(qty(baseQty))+'</td>'+
                    '<td>'+(openingLocked
                        ?'-'
                        :'<button class="btn btn-danger js-remove-opening" type="button" data-index="'+index+'">'+
                          '<i data-lucide="trash-2"></i></button>')+'</td>'+
                '</tr>';
            }).join("");
        }

        var calculatedTotal=Math.round(openingTotal()*1000)/1000;
        var total=(
            openingRows.length===0 &&
            openingLocked &&
            legacyOpeningBalance>0
        )
            ?legacyOpeningBalance
            :calculatedTotal;

        document.getElementById("openingStockTotal").textContent=qty(total);
        document.getElementById("openingCanBalance").value=Number(total).toFixed(3);

        if(window.lucide)lucide.createIcons();
    }

    function addOpeningRow(){
        if(openingLocked)return;

        var product=selectedOpeningProduct();

        if(!product){
            App.showError(null,"Select reusable / returnable product.");
            return;
        }

        if(openingRows.some(function(row){
            return Number(row.product_id)===Number(product.id);
        })){
            App.showError(null,"This opening stock product is already added.");
            return;
        }

        var primaryQty=Math.max(
            0,
            numberValue(document.getElementById("openingPrimaryQty").value)
        );

        var secondaryQty=product.secondary_unit
            ?Math.max(
                0,
                numberValue(document.getElementById("openingSecondaryQty").value)
            )
            :0;

        if(primaryQty<=0&&secondaryQty<=0){
            App.showError(null,"Enter opening stock quantity.");
            return;
        }

        openingRows.push({
            product_id:Number(product.id),
            product_name:product.product_name,
            primary_qty:primaryQty,
            secondary_qty:secondaryQty,
            product_snapshot:product
        });

        openingProductSelect.setOptions(productOptions(openingProducts),"");
        document.getElementById("openingPrimaryQty").value="";
        document.getElementById("openingSecondaryQty").value="";
        refreshOpeningLiveCalculation();
        renderOpeningRows();
    }

    function applyOpeningLock(note){
        openingLocked=true;

        document.getElementById("openingStockEntryRow").style.display="none";

        var noteNode=document.getElementById("openingStockLockNote");
        noteNode.style.display="block";
        noteNode.textContent=note||
            "Opening stock is locked because it is historical and can be entered only once.";

        document.getElementById("openingConversionInfo").style.display="none";
    }

    async function loadOptions(selectedLineId,selectedPriceLevelId){
        var result=await App.api("api/customers.php?options=1");

        lineSelect.setOptions(
            optionItems(result.data.lines,"id","line_name"),
            selectedLineId?String(selectedLineId):""
        );

        priceLevelSelect.setOptions(
            optionItems(result.data.price_levels,"id","price_level_name"),
            selectedPriceLevelId?String(selectedPriceLevelId):""
        );

        openingProducts=Array.isArray(result.data.opening_stock_products)
            ?result.data.opening_stock_products
            :Object.values(result.data.opening_stock_products||{});

        buildOpeningProductMap();
        openingProductSelect.setOptions(productOptions(openingProducts),"");

        return result;
    }

    function fillCustomer(row){
        document.getElementById("customerRef").value=row.ref||"";
        form.customer_code.value=row.customer_code||"";
        form.customer_name.value=row.customer_name||"";
        form.mobile.value=row.mobile||"";
        form.line_sequence.value=row.line_sequence==null?"":String(row.line_sequence);
        form.credit_limit.value=Number(row.credit_limit||0).toFixed(2);
        form.opening_balance.value=Number(row.opening_balance||0).toFixed(2);
        form.opening_can_balance.value=Number(row.opening_can_balance||0).toFixed(3);
        form.status.value=String(Number(row.status)===1?1:0);
        form.address.value=row.address||"";
    }

    function fillOpeningStock(state){
        state=state||{};
        openingLocked=!!state.locked;
        legacyOpeningBalance=Number(state.legacy_opening_can_balance||0);

        openingRows=(state.items||[]).map(function(row){
            var product=openingProductMap[String(row.product_id)]||{
                id:row.product_id,
                product_code:row.product_code,
                product_name:row.product_name,
                primary_unit:row.primary_unit||null,
                secondary_unit:row.secondary_unit||null
            };

            return {
                product_id:Number(row.product_id),
                product_name:row.product_name||product.product_name,
                primary_qty:Number(row.primary_qty||0),
                secondary_qty:Number(row.secondary_qty||0),
                product_snapshot:product
            };
        });

        renderOpeningRows();

        if(openingLocked){
            if(openingRows.length){
                applyOpeningLock(
                    "Opening stock is locked. Use customer can/container adjustment for later corrections."
                );
            }else if(legacyOpeningBalance>0){
                applyOpeningLock(
                    "Legacy Opening Can Balance: "+qty(legacyOpeningBalance)+
                    ". Product-wise breakdown is not available for this older balance."
                );
                document.getElementById("openingStockTotal").textContent=qty(legacyOpeningBalance);
                document.getElementById("openingCanBalance").value=legacyOpeningBalance.toFixed(3);
            }else{
                applyOpeningLock(
                    "Opening stock is locked because customer can/container movements already exist."
                );
            }
        }
    }

    async function load(){
        if(isLoading)return;
        isLoading=true;

        try{
            if(reference){
                document.getElementById("pageHeading").textContent="Edit Customer";
                saveButtonText.textContent="Update Customer";

                var recordResult=await App.api(
                    "api/customers.php?ref="+encodeURIComponent(reference)
                );

                var customer=recordResult.data.customer||{};

                await loadOptions(
                    customer.line_id,
                    customer.price_level_id
                );

                fillCustomer(customer);
                fillOpeningStock(recordResult.data.opening_stock||{});

                if(!hasAction(recordResult.data.allowed_actions,3)){
                    saveButton.disabled=true;
                }
            }else{
                var createResult=await loadOptions("","");
                form.customer_code.value=createResult.data.next_customer_code||"";

                if(!hasAction(createResult.data.allowed_actions,2)){
                    saveButton.disabled=true;
                }
            }

            refreshOpeningLiveCalculation();
            renderOpeningRows();

        }catch(error){
            saveButton.disabled=true;
            App.showError(error,"Unable to load Customer form.");
        }finally{
            isLoading=false;
        }
    }

    document.getElementById("openingStockProduct").addEventListener(
        "change",
        refreshOpeningLiveCalculation
    );

    document.getElementById("openingPrimaryQty").addEventListener(
        "input",
        refreshOpeningLiveCalculation
    );

    document.getElementById("openingSecondaryQty").addEventListener(
        "input",
        refreshOpeningLiveCalculation
    );

    document.getElementById("addOpeningStockButton").addEventListener(
        "click",
        addOpeningRow
    );

    document.getElementById("openingStockBody").addEventListener(
        "click",
        function(event){
            var button=event.target.closest(".js-remove-opening");
            if(!button||openingLocked)return;

            openingRows.splice(Number(button.dataset.index),1);
            renderOpeningRows();
        }
    );

    form.addEventListener("submit",async function(event){
        event.preventDefault();

        Validation.clearForm(form);
        if(!Validation.validateForm(form))return;

        var data=new FormData(form);

        data.set(
            "opening_stocks_json",
            JSON.stringify(
                openingRows.map(function(row){
                    return {
                        product_id:row.product_id,
                        primary_qty:row.primary_qty,
                        secondary_qty:row.secondary_qty
                    };
                })
            )
        );

        data.set(
            "opening_can_balance",
            (Math.round(openingTotal()*1000)/1000).toFixed(3)
        );

        if(reference)data.set("_method","PUT");

        saveButton.disabled=true;

        try{
            var result=await App.api(
                "api/customers.php",
                {method:"POST",body:data}
            );

            if(window.showToast){
                showToast(
                    result.message||"Customer saved successfully.",
                    {type:"success",duration:2}
                );
            }

            window.setTimeout(function(){
                location.href="customer-list.php";
            },700);

        }catch(error){
            Validation.applyErrors(form,error.errors||{});
            App.showError(error,"Unable to save Customer.");
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
