<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Supplier Form';
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

<div class="page-head">
    <div>
        <h1 id="pageHeading">Add Supplier</h1>
        <p>Create or update a branch supplier.</p>
    </div>
    <a class="btn gray" href="supplier-list.php"><i data-lucide="list"></i>Supplier List</a>
</div>

<form id="supplierForm" novalidate>
    <input type="hidden" name="ref" id="supplierRef">

    <div class="card form-card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="building-2"></i>Supplier Information</h2>
            </div>
        </div>

        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="supplierCode">Supplier Code</label>
                    <input id="supplierCode" name="supplier_code" type="text" maxlength="30" readonly aria-readonly="true" placeholder="Auto generated">
                </div>

                <div class="field col-5">
                    <label for="supplierName" class="required">Supplier Name</label>
                    <input id="supplierName" name="supplier_name" type="text" maxlength="150" required
                           data-required-message="Supplier name is required."
                           placeholder="Enter supplier name">
                </div>

                <div class="field col-4">
                    <label for="contactPerson">Contact Person</label>
                    <input id="contactPerson" name="contact_person" type="text" maxlength="120" placeholder="Enter contact person">
                </div>

                <div class="field col-4">
                    <label for="supplierMobile">Mobile</label>
                    <input id="supplierMobile" name="mobile" type="text" inputmode="numeric" maxlength="10"
                           data-validation="mobile"
                           data-mobile-message="Enter a valid 10-digit mobile number."
                           placeholder="Enter mobile number">
                </div>

                <div class="field col-4">
                    <label for="supplierEmail">Email</label>
                    <input id="supplierEmail" name="email" type="text" inputmode="email" maxlength="190"
                           data-validation="email"
                           data-email-message="Enter a valid email address."
                           placeholder="Enter email">
                </div>

                <div class="field col-4">
                    <label for="supplierGstin">GSTIN</label>
                    <input id="supplierGstin" name="gstin" type="text" maxlength="15"
                           data-validation="gst"
                           data-gst-message="Enter a valid GST number."
                           placeholder="29ABCDE1234F1Z5">
                </div>

                <div class="field col-4">
                    <label for="supplierPan">PAN</label>
                    <input id="supplierPan" name="pan" type="text" maxlength="10"
                           data-validation="pan"
                           data-pan-message="Enter a valid PAN number."
                           placeholder="ABCDE1234F">
                </div>

                <div class="field col-4">
                    <label for="openingBalance">Opening Balance</label>
                    <input id="openingBalance" name="opening_balance" type="text" inputmode="decimal"
                           data-validation="decimal"
                           data-decimal-places="2"
                           data-decimal-message="Enter a valid opening balance."
                           placeholder="0.00">
                </div>

                <div class="field col-4">
                    <label for="supplierStatus">Status</label>
                    <select id="supplierStatus" name="status">
                        <option value="1">Active</option>
                        <option value="2">Inactive</option>
                    </select>
                </div>

                <div class="field col-12">
                    <label for="supplierAddress">Address</label>
                    <textarea id="supplierAddress" name="address" rows="3" placeholder="Enter supplier address"></textarea>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit">
                    <i data-lucide="save"></i><span id="saveButtonText">Save Supplier</span>
                </button>
                <a class="btn gray" href="supplier-list.php">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
(function (window, document) {
    "use strict";

    var form=document.getElementById("supplierForm");
    var saveButton=document.getElementById("saveButton");
    var saveButtonText=document.getElementById("saveButtonText");
    var reference=new URLSearchParams(location.search).get("ref")||"";
    var isLoading=false;

    function hasAction(actions,id){
        return (actions||[]).map(Number).indexOf(Number(id))!==-1;
    }

    function fillSupplier(row){
        document.getElementById("supplierRef").value=row.ref||"";
        form.supplier_code.value=row.supplier_code||"";
        form.supplier_name.value=row.supplier_name||"";
        form.contact_person.value=row.contact_person||"";
        form.mobile.value=row.mobile||"";
        form.email.value=row.email||"";
        form.gstin.value=row.gstin||"";
        form.pan.value=row.pan||"";
        form.address.value=row.address||"";
        form.opening_balance.value=Number(row.opening_balance||0).toFixed(2);
        form.status.value=String(Number(row.status)===1?1:2);
    }

    async function load(){
        if(isLoading)return;
        isLoading=true;
        try{
            if(reference){
                document.getElementById("pageHeading").textContent="Edit Supplier";
                saveButtonText.textContent="Update Supplier";
                var result=await App.api("api/suppliers.php?ref="+encodeURIComponent(reference));
                fillSupplier(result.data.supplier||{});
                if(!hasAction(result.data.allowed_actions,3))saveButton.disabled=true;
            }else{
                var createResult=await App.api("api/suppliers.php?options=1");
                form.supplier_code.value=createResult.data.next_supplier_code||"";
                if(!hasAction(createResult.data.allowed_actions,2))saveButton.disabled=true;
            }
        }catch(error){
            saveButton.disabled=true;
            App.showError(error,"Unable to load Supplier form.");
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
            var result=await App.api("api/suppliers.php",{method:"POST",body:data});
            if(window.showToast)showToast(result.message||"Supplier saved successfully.",{type:"success",duration:2});
            window.setTimeout(function(){location.href="supplier-list.php";},700);
        }catch(error){
            Validation.applyErrors(form,error.errors||{});
            App.showError(error,"Unable to save Supplier.");
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
