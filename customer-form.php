<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Form1';
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

                <div class="field col-4">
                    <label for="openingCanBalance">Opening Can Balance</label>
                    <input id="openingCanBalance" name="opening_can_balance" type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="3"
                           data-regex="^(?:[0-9]+(?:\.[0-9]{1,3})?|\.[0-9]{1,3})$"
                           data-regex-message="Enter zero or a positive quantity."
                           placeholder="0.000">
                </div>

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
    var isLoading=false;

    function hasAction(actions,id){
        return (actions||[]).map(Number).indexOf(Number(id))!==-1;
    }

    function optionItems(rows,valueKey,textKey){
        return (rows||[]).map(function(row){
            return {value:String(row[valueKey]),text:String(row[textKey]||"")};
        });
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

    async function load(){
        if(isLoading)return;
        isLoading=true;

        try{
            if(reference){
                document.getElementById("pageHeading").textContent="Edit Customer";
                saveButtonText.textContent="Update Customer";

                var recordResult=await App.api("api/customers.php?ref="+encodeURIComponent(reference));
                var customer=recordResult.data.customer||{};

                await loadOptions(customer.line_id,customer.price_level_id);
                fillCustomer(customer);

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
        }catch(error){
            saveButton.disabled=true;
            App.showError(error,"Unable to load Customer form.");
        }finally{
            isLoading=false;
        }
    }

    form.addEventListener("submit",async function(event){
        event.preventDefault();

        Validation.clearForm(form);
        if(!Validation.validateForm(form))return;

        var data=new FormData(form);
        if(reference)data.set("_method","PUT");

        saveButton.disabled=true;
        try{
            var result=await App.api("api/customers.php",{method:"POST",body:data});
            if(window.showToast)showToast(result.message||"Customer saved successfully.",{type:"success",duration:2});
            window.setTimeout(function(){location.href="customer-list.php";},700);
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
