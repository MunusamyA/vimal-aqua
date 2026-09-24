<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Supplier Payment';
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
        <h1 id="pageHeading">Supplier Payment</h1>
        <p>Pay Opening Balance, Overall outstanding by FIFO, or one particular Invoice.</p>
    </div>
    <a class="btn gray" href="supplier-payment-list.php">
        <i data-lucide="list"></i>Payment List
    </a>
</div>

<form id="paymentForm" novalidate>
    <input type="hidden" id="paymentRef">

    <div class="card form-card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="hand-coins"></i>Supplier Payment Information</h2>
                <p>Overall automatically allocates Opening Balance first, then oldest pending Invoice.</p>
            </div>
        </div>

        <div class="card-body">
            <div class="app-section-title">Payment Information</div>
            <div class="form-row">
                <div class="field col-3">
                    <label for="paymentNumber">Payment No</label>
                    <input class="input" id="paymentNumber" type="text" readonly aria-readonly="true" value="Auto generated">
                </div>

                <div class="field col-3">
                    <label for="paymentDate" class="required">Payment Date</label>
                    <input class="input" id="paymentDate" type="date" required>
                </div>

                <div class="field col-3">
                    <label for="supplierRef" class="required">Supplier</label>
                    <select class="select" id="supplierRef" required data-placeholder="Select Supplier">
                        <option value="">Select Supplier</option>
                    </select>
                </div>

                <div class="field col-3">
                    <label for="paymentTypeRef" class="required">Payment For</label>
                    <select class="select" id="paymentTypeRef" required data-placeholder="Select Payment Type">
                        <option value="">Select Payment Type</option>
                    </select>
                </div>
            </div>

            <div class="form-row" id="purchaseField" hidden>
                <div class="field col-6">
                    <label for="purchaseRef" class="required">Invoice / Purchase</label>
                    <select class="select" id="purchaseRef" data-placeholder="Select Invoice">
                        <option value="">Select Invoice</option>
                    </select>
                </div>
            </div>

            <div class="app-section-title">Supplier Outstanding</div>
            <div class="kpi-grid supplier-outstanding-stats">
                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="landmark"></i></span>
                    <div>
                        <div class="kpi-label">Opening Balance Pending</div>
                        <div class="kpi-value" id="openingOutstanding">₹0.00</div>
                        <div class="kpi-meta"><span>Supplier opening due</span></div>
                    </div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon blue"><i data-lucide="receipt-text"></i></span>
                    <div>
                        <div class="kpi-label">Purchase Outstanding</div>
                        <div class="kpi-value" id="purchaseOutstanding">₹0.00</div>
                        <div class="kpi-meta"><span>Posted invoice due</span></div>
                    </div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon teal"><i data-lucide="wallet-cards"></i></span>
                    <div>
                        <div class="kpi-label">Overall Outstanding</div>
                        <div class="kpi-value" id="overallOutstanding">₹0.00</div>
                        <div class="kpi-meta"><span>Opening + purchases</span></div>
                    </div>
                </article>
            </div>

            <div class="app-split-grid form-section">
                <div class="app-side-card">
                    <div class="app-side-card-head">Payment Details</div>
                    <div class="app-side-card-body">
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
                                <tbody id="paymentRows">
                                <tr class="payment-row" data-mode="1" data-account-type="1">
                                    <td><strong>Cash</strong></td>
                                    <td><select class="select pay-account" data-placeholder="Select Cash Account"><option value="">Select Cash Account</option></select></td>
                                    <td><input class="input pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                                    <td><input class="input pay-reference" type="text" maxlength="100" placeholder="Optional"></td>
                                    <td><input class="input pay-date" type="date"></td>
                                </tr>
                                <tr class="payment-row" data-mode="2" data-account-type="3">
                                    <td><strong>UPI</strong></td>
                                    <td><select class="select pay-account" data-placeholder="Select UPI Account"><option value="">Select UPI Account</option></select></td>
                                    <td><input class="input pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                                    <td><input class="input pay-reference" type="text" maxlength="100" placeholder="UTR / Ref No"></td>
                                    <td><input class="input pay-date" type="date"></td>
                                </tr>
                                <tr class="payment-row" data-mode="3" data-account-type="2">
                                    <td><strong>Bank</strong></td>
                                    <td><select class="select pay-account" data-placeholder="Select Bank Account"><option value="">Select Bank Account</option></select></td>
                                    <td><input class="input pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                                    <td><input class="input pay-reference" type="text" maxlength="100" placeholder="Transaction / Ref No"></td>
                                    <td><input class="input pay-date" type="date"></td>
                                </tr>
                                <tr class="payment-row" data-mode="4" data-account-type="2">
                                    <td><strong>Cheque</strong></td>
                                    <td><select class="select pay-account" data-placeholder="Select Bank Account"><option value="">Select Bank Account</option></select></td>
                                    <td><input class="input pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                                    <td><input class="input pay-reference" type="text" maxlength="100" placeholder="Cheque No"></td>
                                    <td><input class="input pay-date" type="date"></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="app-total-line">
                            <span>Actual Payment</span>
                            <strong id="actualPaymentDisplay">₹0.00</strong>
                        </div>

                        <div class="form-row" style="margin-top:16px">
                            <div class="field col-4">
                                <label for="discountType">Discount Type</label>
                                <select class="select" id="discountType">
                                    <option value="1">None</option>
                                    <option value="2">Percentage</option>
                                    <option value="3">Amount</option>
                                </select>
                            </div>
                            <div class="field col-4">
                                <label for="discountValue" id="discountValueLabel">Discount Value</label>
                                <input class="input" id="discountValue" type="text" inputmode="decimal" placeholder="0.00" disabled>
                            </div>
                            <div class="field col-4">
                                <label for="discountAmountDisplay">Discount Amount</label>
                                <input class="input" id="discountAmountDisplay" type="text" value="0.00" readonly aria-readonly="true">
                            </div>
                        </div>

                        <div class="app-total-line">
                            <span>Total Settlement</span>
                            <strong id="settlementTotalDisplay">₹0.00</strong>
                        </div>

                        <div class="field" style="margin-top:16px">
                            <label for="notes">Remarks</label>
                            <textarea id="notes" rows="3" maxlength="255" placeholder="Optional payment remarks"></textarea>
                        </div>
                    </div>
                </div>

                <div class="app-side-card">
                    <div class="app-side-card-head">Selected Settlement</div>
                    <div class="app-side-card-body">
                        <div class="app-summary-row"><span>Payment For</span><strong id="selectedPaymentFor">—</strong></div>
                        <div class="app-summary-row"><span>Invoice / Target</span><strong id="selectedPurchase">—</strong></div>
                        <div class="app-summary-row total"><span>Target Outstanding</span><strong id="targetOutstandingDisplay">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Actual Payment</span><strong id="summaryPayment">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Settlement Discount</span><strong id="summaryDiscount">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Total Settlement</span><strong id="summarySettlement">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Balance After</span><strong id="remainingOutstandingDisplay">₹0.00</strong></div>
                    </div>

                    <div class="app-side-card-head" style="margin-top:16px">Allocation Preview</div>
                    <div class="app-side-card-body">
                        <div class="app-table-wrap">
                            <table class="app-editable-table">
                                <thead>
                                <tr><th>Target</th><th>Payment</th><th>Discount</th><th>Settled</th><th>Balance</th></tr>
                                </thead>
                                <tbody id="allocationBody">
                                <tr><td colspan="5" class="muted">Select Supplier and Payment Type.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <div class="buttons">
                <a class="btn gray" href="supplier-payment-list.php">Cancel</a>
                <button class="btn btn-primary" id="saveButton" type="submit">
                    <i data-lucide="hand-coins"></i><span id="saveButtonText">Post Payment</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
(function(window,document){
    'use strict';

    var form=document.getElementById('paymentForm');
    var params=new URLSearchParams(location.search);
    var editRef=params.get('ref')||'';
    var directPurchaseRef=params.get('purchase_ref')||'';
    var viewMode=params.get('view')==='1';

    var accounts=[];
    var currentContext=null;
    var previewTimer=null;
    var previewSerial=0;
    var contextSerial=0;
    var directLock=false;
    var editTargetPurchaseId=0;
    var editPaymentType=0;

    var supplierSelect=null,typeSelect=null,purchaseSelect=null,accountSelects=[];

    function el(id){return document.getElementById(id);}
    function esc(v){return App.escapeHtml(String(v==null?'':v));}
    function n(v){var x=Number(String(v==null?'':v).replace(/,/g,'').trim()||0);return Number.isFinite(x)?x:0;}
    function money(v){return n(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
    function has(actions,id){return (actions||[]).map(Number).indexOf(Number(id))!==-1;}
    function refresh(gs){if(gs&&typeof gs.refresh==='function')gs.refresh();}

    function initSelects(){
        if(!window.GlobalSelect)return;
        supplierSelect=GlobalSelect.init(el('supplierRef'),{placeholder:'Select Supplier'});
        typeSelect=GlobalSelect.init(el('paymentTypeRef'),{placeholder:'Select Payment Type'});
        purchaseSelect=GlobalSelect.init(el('purchaseRef'),{placeholder:'Select Invoice'});
    }

    function selectedType(){
        var option=el('paymentTypeRef').selectedOptions[0];
        return option?Number(option.dataset.type||0):0;
    }

    function fillSuppliers(rows){
        el('supplierRef').innerHTML='<option value="">Select Supplier</option>';
        (rows||[]).forEach(function(r){
            var option=document.createElement('option');
            option.value=r.ref;
            option.dataset.supplierId=r.id;
            option.textContent=r.supplier_code+' - '+r.supplier_name+(r.mobile?' | '+r.mobile:'');
            el('supplierRef').appendChild(option);
        });
        refresh(supplierSelect);
    }

    function fillTypes(rows){
        el('paymentTypeRef').innerHTML='<option value="">Select Payment Type</option>';
        (rows||[]).forEach(function(r){
            var option=document.createElement('option');
            option.value=r.value;
            option.dataset.type=r.type;
            option.textContent=r.label;
            el('paymentTypeRef').appendChild(option);
        });
        refresh(typeSelect);
    }

    function fillAccountOptions(){
        accountSelects=[];
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){
            var requiredType=Number(row.dataset.accountType||0);
            var select=row.querySelector('.pay-account');
            var old=select.value;
            var placeholder=requiredType===1?'Select Cash Account':(requiredType===3?'Select UPI Account':'Select Bank Account');
            select.innerHTML='<option value="">'+placeholder+'</option>';

            accounts.forEach(function(a){
                if(Number(a.account_type)!==requiredType)return;
                var option=document.createElement('option');
                option.value=a.id;
                option.textContent=a.account_code+' - '+a.account_name;
                select.appendChild(option);
            });

            if(old)select.value=old;
            if(window.GlobalSelect){
                if(select._globalSelect&&select._globalSelect.destroy)select._globalSelect.destroy();
                accountSelects.push(GlobalSelect.init(select,{placeholder:placeholder}));
            }
        });
    }

    function collectPayments(){
        var rows=[];
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){
            rows.push({
                payment_mode:Number(row.dataset.mode||0),
                account_id:row.querySelector('.pay-account').value,
                amount:row.querySelector('.pay-amount').value.trim(),
                reference_no:row.querySelector('.pay-reference').value.trim(),
                detail_date:row.querySelector('.pay-date').value
            });
        });
        return rows;
    }

    function actualPayment(){
        var total=0;
        document.querySelectorAll('.pay-amount').forEach(function(input){
            if(n(input.value)>0)total+=n(input.value);
        });
        total=Math.round(total*100)/100;
        el('actualPaymentDisplay').textContent='₹'+money(total);
        el('summaryPayment').textContent='₹'+money(total);
        return total;
    }

    function payload(action){
        return {
            action:action||'preview',
            ref:editRef||'',
            supplier_ref:el('supplierRef').value,
            payment_type_ref:el('paymentTypeRef').value,
            purchase_ref:el('purchaseRef').value,
            payment_date:el('paymentDate').value,
            discount_type:Number(el('discountType').value||1),
            discount_value:el('discountValue').value.trim(),
            payments:collectPayments(),
            notes:el('notes').value.trim()
        };
    }

    function targetOutstanding(){
        var type=selectedType();
        var purchaseOption=el('purchaseRef').selectedOptions[0];
        if(!currentContext)return 0;
        if(type===1)return n(currentContext.opening_outstanding);
        if(type===2)return n(currentContext.overall_outstanding);
        if(type===3&&purchaseOption&&purchaseOption.value)return n(purchaseOption.dataset.pending);
        return 0;
    }

    function discountAmount(target){
        var type=Number(el('discountType').value||1);
        var value=n(el('discountValue').value);
        if(type===1||value<=0||target<=0)return 0;
        if(type===2)return Math.round((target*value/100)*100)/100;
        return Math.round(value*100)/100;
    }

    function updateDiscountUi(){
        var type=Number(el('discountType').value||1);
        el('discountValue').disabled=type===1||viewMode;
        el('discountValueLabel').textContent=type===2?'Discount Percentage':(type===3?'Discount Amount':'Discount Value');
        if(type===1)el('discountValue').value='';
    }

    function clearPreview(message){
        el('allocationBody').innerHTML='<tr><td colspan="5" class="muted">'+esc(message||'Enter Payment Amount or Discount.')+'</td></tr>';
    }

    function renderPreview(data){
        el('targetOutstandingDisplay').textContent='₹'+money(data.targets_total);
        el('summaryDiscount').textContent='₹'+money(data.discount_amount);
        el('summarySettlement').textContent='₹'+money(data.settlement_total);
        el('discountAmountDisplay').value=money(data.discount_amount);
        el('settlementTotalDisplay').textContent='₹'+money(data.settlement_total);
        el('remainingOutstandingDisplay').textContent='₹'+money(data.remaining_outstanding);
        var rows=data.allocations||[];
        if(!rows.length){clearPreview('No amount allocated yet.');return;}
        el('allocationBody').innerHTML=rows.map(function(r){
            return '<tr><td>'+esc(r.label)+'</td><td>₹'+money(r.payment_amount)+'</td><td>₹'+money(r.discount_amount)+'</td><td>₹'+money(r.allocated_amount)+'</td><td>₹'+money(r.pending_after)+'</td></tr>';
        }).join('');
    }

    function updateSummary(){
        var payment=actualPayment();
        var type=selectedType();
        var typeOption=el('paymentTypeRef').selectedOptions[0];
        var purchaseOption=el('purchaseRef').selectedOptions[0];

        el('selectedPaymentFor').textContent=typeOption&&typeOption.value?typeOption.textContent:'—';
        el('selectedPurchase').textContent=type===1?'Opening Balance':(type===2?'FIFO Allocation':(purchaseOption&&purchaseOption.value?purchaseOption.textContent.split(' | ')[0]:'—'));

        var target=targetOutstanding();
        var discount=discountAmount(target);
        var settlement=Math.round((payment+discount)*100)/100;

        el('targetOutstandingDisplay').textContent='₹'+money(target);
        el('summaryDiscount').textContent='₹'+money(discount);
        el('summarySettlement').textContent='₹'+money(settlement);
        el('discountAmountDisplay').value=money(discount);
        el('settlementTotalDisplay').textContent='₹'+money(settlement);
        el('remainingOutstandingDisplay').textContent='₹'+money(Math.max(0,target-settlement));
    }

    function updateTypeAvailability(){
        if(!currentContext)return;
        document.querySelectorAll('#paymentTypeRef option').forEach(function(option){
            var type=Number(option.dataset.type||0);
            if(!type)return;
            var disabled=(type===1&&n(currentContext.opening_outstanding)<=0.001)||(type===2&&n(currentContext.overall_outstanding)<=0.001)||(type===3&&!(currentContext.purchases||[]).some(function(p){return n(p.pending)>0.001||Number(p.id)===editTargetPurchaseId;}));
            if(editRef&&type===editPaymentType)disabled=false;
            if(directLock&&type===3)disabled=false;
            option.disabled=disabled;
            option.textContent=option.textContent.replace(/ \(No Pending\)$/,'')+(disabled?' (No Pending)':'');
        });
        var selected=el('paymentTypeRef').selectedOptions[0];
        if(selected&&selected.disabled&&!directLock)el('paymentTypeRef').value='';
        refresh(typeSelect);
    }

    function setContext(ctx){
        currentContext=ctx||null;
        el('openingOutstanding').textContent='₹'+money(ctx?ctx.opening_outstanding:0);
        el('purchaseOutstanding').textContent='₹'+money(ctx?ctx.purchase_outstanding:0);
        el('overallOutstanding').textContent='₹'+money(ctx?ctx.overall_outstanding:0);

        var old=el('purchaseRef').value;
        el('purchaseRef').innerHTML='<option value="">Select Invoice</option>';
        ((ctx&&ctx.purchases)||[]).forEach(function(r){
            var option=document.createElement('option');
            option.value=r.ref;
            option.dataset.purchaseId=r.id;
            option.dataset.pending=r.pending;
            option.textContent=r.purchase_no+' | '+r.purchase_date+' | Pending ₹'+money(r.pending);
            if(n(r.pending)<=0.001&&Number(r.id)!==editTargetPurchaseId)option.disabled=true;
            el('purchaseRef').appendChild(option);
        });
        if(Array.prototype.some.call(el('purchaseRef').options,function(o){return o.value===old;}))el('purchaseRef').value=old;
        refresh(purchaseSelect);
        updateTypeAvailability();
        updateTypeUi();
    }

    async function loadContext(){
        var ref=el('supplierRef').value;
        var serial=++contextSerial;
        if(!ref){setContext(null);return;}
        try{
            var url='api/supplier-payments.php?supplier_context=1&supplier_ref='+encodeURIComponent(ref);
            if(editRef)url+='&payment_ref='+encodeURIComponent(editRef);
            var result=await App.api(url);
            if(serial!==contextSerial)return;
            setContext(result.data);
            schedulePreview();
        }catch(error){
            if(serial!==contextSerial)return;
            setContext(null);
            App.showError(error,'Unable to load Supplier outstanding.');
        }
    }

    function updateTypeUi(){
        var type=selectedType();
        el('purchaseField').hidden=type!==3;
        if(type!==3){
            el('purchaseRef').value='';
            refresh(purchaseSelect);
        }
        updateSummary();
        schedulePreview();
    }

    async function preview(){
        var serial=++previewSerial;
        updateSummary();

        if(!el('supplierRef').value||!el('paymentTypeRef').value){
            clearPreview('Select Supplier and Payment Type.');
            return;
        }
        if(selectedType()===3&&!el('purchaseRef').value){
            clearPreview('Select Invoice.');
            return;
        }
        if(!el('paymentDate').value){
            clearPreview('Select Payment Date.');
            return;
        }
        if(actualPayment()+discountAmount(targetOutstanding())<=0.001){
            clearPreview('Enter Payment Amount or Discount.');
            return;
        }

        try{
            var result=await App.api('api/supplier-payments.php',{method:'POST',body:payload('preview')});
            if(serial!==previewSerial)return;
            renderPreview(result.data);
        }catch(error){
            if(serial!==previewSerial)return;
            clearPreview(error&&error.message?error.message:'Check Payment values.');
        }
    }

    function schedulePreview(){
        updateSummary();
        clearTimeout(previewTimer);
        previewTimer=setTimeout(preview,280);
    }

    function setPaymentRows(rows){
        var map={};
        (rows||[]).forEach(function(r){map[String(r.payment_mode)]=r;});
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){
            var data=map[String(row.dataset.mode)]||{};
            var select=row.querySelector('.pay-account');
            select.value=data.account_id||'';
            row.querySelector('.pay-amount').value=Number(data.amount||0)>0?String(Number(data.amount)):'';
            row.querySelector('.pay-reference').value=data.reference_no||'';
            row.querySelector('.pay-date').value=data.detail_date||'';
        });
        accountSelects.forEach(refresh);
        updateSummary();
    }

    function selectSupplierById(id){
        var option=Array.prototype.find.call(el('supplierRef').options,function(o){return Number(o.dataset.supplierId||0)===Number(id);});
        el('supplierRef').value=option?option.value:'';
        refresh(supplierSelect);
    }

    function selectTypeByNumber(type){
        var option=Array.prototype.find.call(el('paymentTypeRef').options,function(o){return Number(o.dataset.type||0)===Number(type);});
        el('paymentTypeRef').value=option?option.value:'';
        refresh(typeSelect);
    }

    function selectPurchaseById(id){
        var option=Array.prototype.find.call(el('purchaseRef').options,function(o){return Number(o.dataset.purchaseId||0)===Number(id);});
        if(option)el('purchaseRef').value=option.value;
        refresh(purchaseSelect);
    }

    function applyLocks(){
        if(directLock){
            el('supplierRef').disabled=true;
            el('paymentTypeRef').disabled=true;
            el('purchaseRef').disabled=true;
            refresh(supplierSelect);refresh(typeSelect);refresh(purchaseSelect);
        }
        if(viewMode){
            el('pageHeading').textContent='View Supplier Payment';
            el('saveButton').hidden=true;
            form.querySelectorAll('input,select,textarea').forEach(function(control){
                if(control.type!=='hidden')control.disabled=true;
            });
            refresh(supplierSelect);refresh(typeSelect);refresh(purchaseSelect);accountSelects.forEach(refresh);
        }
    }

    async function load(){
        try{
            var optionsUrl='api/supplier-payments.php?options=1';
            if(!editRef&&directPurchaseRef)optionsUrl+='&purchase_ref='+encodeURIComponent(directPurchaseRef);
            var options=await App.api(optionsUrl);

            accounts=options.data.accounts||[];
            fillSuppliers(options.data.suppliers||[]);
            fillTypes(options.data.payment_types||[]);
            fillAccountOptions();

            if(editRef){
                var result=await App.api('api/supplier-payments.php?ref='+encodeURIComponent(editRef));
                var payment=result.data.payment;

                el('pageHeading').textContent=viewMode?'View Supplier Payment':'Edit Supplier Payment';
                el('paymentNumber').value=payment.payment_no||'';
                el('paymentDate').value=payment.payment_date||'';
                el('notes').value=payment.remarks||'';
                el('discountType').value=String(Number(payment.discount_type||1));
                el('discountValue').value=Number(payment.discount_value||0)>0?String(Number(payment.discount_value)):'';
                updateDiscountUi();
                editTargetPurchaseId=Number(payment.purchase_id||0);
                editPaymentType=Number(payment.payment_type||0);

                selectSupplierById(payment.supplier_id);
                selectTypeByNumber(payment.payment_type);
                setContext(result.data.supplier_context);
                if(editTargetPurchaseId)selectPurchaseById(editTargetPurchaseId);
                setPaymentRows(payment.payments||[]);

                if(!viewMode){
                    el('saveButtonText').textContent='Update Payment';
                    if(!has(result.data.allowed_actions,3))el('saveButton').disabled=true;
                }
            }else if(options.data.direct_purchase){
                var direct=options.data.direct_purchase;
                directLock=true;
                el('pageHeading').textContent='Pay Supplier Invoice';
                el('paymentNumber').value='Auto generated on save';
                el('paymentDate').value=options.data.today||new Date().toISOString().slice(0,10);

                selectSupplierById(direct.supplier_id);
                setContext(options.data.supplier_context||null);
                selectTypeByNumber(3);
                selectPurchaseById(direct.id);
                updateTypeUi();

                if(!has(options.data.allowed_actions,2))el('saveButton').disabled=true;
            }else{
                el('paymentDate').value=options.data.today||new Date().toISOString().slice(0,10);
                el('paymentNumber').value='Auto generated on save';
                if(!has(options.data.allowed_actions,2))el('saveButton').disabled=true;
            }

            applyLocks();
            updateSummary();
            if(window.lucide)window.lucide.createIcons();
        }catch(error){
            el('saveButton').disabled=true;
            App.showError(error,'Unable to load Supplier Payment form.');
        }
    }

    initSelects();
    updateDiscountUi();

    el('supplierRef').addEventListener('change',loadContext);
    el('paymentTypeRef').addEventListener('change',updateTypeUi);
    el('purchaseRef').addEventListener('change',schedulePreview);
    el('paymentDate').addEventListener('change',schedulePreview);
    el('discountType').addEventListener('change',function(){updateDiscountUi();schedulePreview();});
    el('discountValue').addEventListener('input',schedulePreview);
    el('paymentRows').addEventListener('input',function(event){if(event.target.matches('input'))schedulePreview();});
    el('paymentRows').addEventListener('change',function(){schedulePreview();});

    form.addEventListener('submit',async function(event){
        event.preventDefault();
        if(viewMode||el('saveButton').disabled)return;

        if(!el('supplierRef').value){App.showError(null,'Select Supplier.');return;}
        if(!el('paymentTypeRef').value){App.showError(null,'Select Payment Type.');return;}
        if(!el('paymentDate').value){App.showError(null,'Select Payment Date.');return;}
        if(selectedType()===3&&!el('purchaseRef').value){App.showError(null,'Select Invoice.');return;}
        if(Number(el('discountType').value||1)===2&&n(el('discountValue').value)>100){App.showError(null,'Discount Percentage cannot exceed 100%.');return;}
        if(actualPayment()+discountAmount(targetOutstanding())<=0.001){App.showError(null,'Enter Payment Amount or Discount.');return;}

        el('saveButton').disabled=true;
        try{
            var result=await App.api('api/supplier-payments.php',{method:'POST',body:payload('save')});
            if(window.showToast)showToast(result.message||'Supplier Payment saved successfully.',{type:'success',duration:2});
            window.setTimeout(function(){location.href='supplier-payment-list.php';},500);
        }catch(error){
            el('saveButton').disabled=false;
            App.showError(error,'Unable to save Supplier Payment.');
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
