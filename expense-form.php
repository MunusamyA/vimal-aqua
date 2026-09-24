<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Expense Form';
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
        <h1 id="pageHeading">Add Expense</h1>
        <p>Select an Expense Name and split the payment through Cash, UPI, Bank or Cheque.</p>
    </div>
    <a class="btn gray" href="expense-list.php"><i data-lucide="list"></i>Expense List</a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="receipt"></i></span>
        <div>
            <div class="kpi-label">Expense Amount</div>
            <div class="kpi-value" id="expenseAmountDisplay">₹0.00</div>
            <div class="kpi-meta"><span>Total expense</span></div>
        </div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Payment Total</div>
            <div class="kpi-value" id="paymentTotalDisplay">₹0.00</div>
            <div class="kpi-meta"><span>Cash / UPI / Bank / Cheque</span></div>
        </div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="scale"></i></span>
        <div>
            <div class="kpi-label">Difference</div>
            <div class="kpi-value" id="differenceDisplay">₹0.00</div>
            <div class="kpi-meta"><span>Must become ₹0.00</span></div>
        </div>
    </article>
</div>

<form id="expenseForm" novalidate>
    <div class="card form-card form-section">
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="receipt-text"></i>Expense Information</h2>
                <p class="card-description">Payment Total must be equal to the Expense Amount.</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-row">
                <div class="field col-3">
                    <label for="expenseNo">Expense No</label>
                    <input id="expenseNo" type="text" value="Auto generated" readonly aria-readonly="true">
                </div>

                <div class="field col-3">
                    <label for="expenseDate" class="required">Expense Date</label>
                    <input id="expenseDate" type="date" required>
                </div>

                <div class="field col-6">
                    <label for="expenseName" class="required">Expense Name</label>
                    <select id="expenseName" required data-placeholder="Select Expense Name">
                        <option value="">Select Expense Name</option>
                    </select>
                </div>

                <div class="field col-6" id="newExpenseNameField" hidden>
                    <label for="newExpenseName" class="required">New Expense Name</label>
                    <input id="newExpenseName" type="text" maxlength="150" placeholder="Enter new Expense Name">
                </div>

                <div class="field col-3">
                    <label for="amount" class="required">Expense Amount</label>
                    <input id="amount" type="text" inputmode="decimal" placeholder="0.00" required>
                </div>

                <div class="field col-9">
                    <label for="remarks">Remarks</label>
                    <input id="remarks" type="text" maxlength="255" placeholder="Optional remarks">
                </div>
            </div>

            <div class="app-section-title">Payment Details</div>
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
                        <td><select class="pay-account"><option value="">Select Cash Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="Optional"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    <tr class="payment-row" data-mode="2" data-account-type="3">
                        <td><strong>UPI</strong></td>
                        <td><select class="pay-account"><option value="">Select UPI Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="UTR / Ref No"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    <tr class="payment-row" data-mode="3" data-account-type="2">
                        <td><strong>Bank</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="Transaction / Ref No"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    <tr class="payment-row" data-mode="4" data-account-type="2">
                        <td><strong>Cheque</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="100" placeholder="Cheque No"></td>
                        <td><input class="pay-date" type="date"></td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="card-footer">
            <div class="buttons">
                <a class="btn gray" href="expense-list.php">Cancel</a>
                <button class="btn btn-primary" id="saveButton" type="submit">
                    <i data-lucide="save"></i><span id="saveButtonText">Save Expense</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
(function(window,document){
    'use strict';

    var form=document.getElementById('expenseForm');
    var params=new URLSearchParams(location.search);
    var reference=params.get('ref')||'';
    var accounts=[];
    var actions=[];
    var saving=false;
    var nameSelect=null;
    var accountSelects=[];

    function el(id){return document.getElementById(id);}
    function n(v){var x=Number(String(v==null?'':v).replace(/,/g,'').trim()||0);return Number.isFinite(x)?x:0;}
    function money(v){return n(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
    function has(id){return actions.map(Number).indexOf(Number(id))!==-1;}
    function refresh(gs){if(gs&&typeof gs.refresh==='function')gs.refresh();}

    function setExpenseNames(rows,selected){
        var select=el('expenseName');
        select.innerHTML='<option value="">Select Expense Name</option>';
        (rows||[]).forEach(function(row){
            var option=document.createElement('option');
            option.value=row.value||row.label||'';
            option.textContent=row.label||row.value||'';
            select.appendChild(option);
        });
        var add=document.createElement('option');
        add.value='__new__';add.textContent='+ Add New Expense Name';select.appendChild(add);
        if(selected)select.value=selected;
        refresh(nameSelect);
        syncNewExpenseName();
    }

    function syncNewExpenseName(){
        var isNew=el('expenseName').value==='__new__';
        el('newExpenseNameField').hidden=!isNew;
        el('newExpenseName').required=isNew;
        if(!isNew)el('newExpenseName').value='';
    }

    function fillAccountOptions(){
        accountSelects=[];
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){
            var type=Number(row.dataset.accountType||0);
            var select=row.querySelector('.pay-account');
            var current=select.value;
            var placeholder=type===1?'Select Cash Account':(type===3?'Select UPI Account':'Select Bank Account');
            select.innerHTML='<option value="">'+placeholder+'</option>';
            accounts.forEach(function(a){
                if(Number(a.account_type)!==type)return;
                var option=document.createElement('option');
                option.value=String(a.id);
                option.textContent=(a.account_code?a.account_code+' - ':'')+a.account_name;
                select.appendChild(option);
            });
            if(current)select.value=current;
            if(window.GlobalSelect)accountSelects.push(GlobalSelect.init(select,{placeholder:placeholder}));
        });
    }

    function setPaymentRows(rows){
        var map={};
        (rows||[]).forEach(function(r){map[String(r.payment_mode)]=r;});
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row,index){
            var p=map[String(row.dataset.mode)]||{};
            var select=row.querySelector('.pay-account');
            select.value=p.account_id?String(p.account_id):'';
            row.querySelector('.pay-amount').value=n(p.amount)>0?n(p.amount).toFixed(2):'';
            row.querySelector('.pay-reference').value=p.reference_no||'';
            row.querySelector('.pay-date').value=p.detail_date||el('expenseDate').value||'';
            refresh(accountSelects[index]);
        });
        calculate();
    }

    function collectPayments(){
        var rows=[];
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){
            rows.push({
                payment_mode:Number(row.dataset.mode||0),
                account_id:Number(row.querySelector('.pay-account').value||0),
                amount:row.querySelector('.pay-amount').value.trim(),
                reference_no:row.querySelector('.pay-reference').value.trim(),
                detail_date:row.querySelector('.pay-date').value||el('expenseDate').value
            });
        });
        return rows;
    }

    function calculate(){
        var expense=n(el('amount').value),paid=0;
        document.querySelectorAll('.pay-amount').forEach(function(input){paid+=n(input.value);});
        paid=Math.round(paid*100)/100;
        var difference=Math.round((expense-paid)*100)/100;
        el('expenseAmountDisplay').textContent='₹'+money(expense);
        el('paymentTotalDisplay').textContent='₹'+money(paid);
        el('differenceDisplay').textContent='₹'+money(Math.abs(difference));
        return {expense:expense,paid:paid,difference:difference};
    }

    function payload(){
        return {
            ref:reference||'',
            expense_date:el('expenseDate').value,
            expense_name:el('expenseName').value,
            new_expense_name:el('newExpenseName').value.trim(),
            amount:el('amount').value.trim(),
            remarks:el('remarks').value.trim(),
            payment_details_json:JSON.stringify(collectPayments())
        };
    }

    function validateClient(){
        var c=calculate();
        if(!el('expenseDate').value){App.showError(null,'Select Expense Date.');return false;}
        if(!el('expenseName').value){App.showError(null,'Select Expense Name.');return false;}
        if(el('expenseName').value==='__new__'&&!el('newExpenseName').value.trim()){App.showError(null,'Enter New Expense Name.');return false;}
        if(c.expense<=0){App.showError(null,'Expense Amount must be greater than zero.');return false;}
        if(Math.abs(c.difference)>0.009){App.showError(null,'Payment Total must be equal to Expense Amount.');return false;}

        var valid=true,message='';
        document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){
            var amount=n(row.querySelector('.pay-amount').value);
            if(amount<=0)return;
            if(!row.querySelector('.pay-account').value){valid=false;message='Select Account for every entered Payment Amount.';return;}
            if(Number(row.dataset.mode)===4&&!row.querySelector('.pay-reference').value.trim()){
                valid=false;message='Enter Cheque Number for Cheque payment.';
            }
        });
        if(!valid){App.showError(null,message);return false;}
        return true;
    }

    async function load(){
        try{
            if(window.GlobalSelect)nameSelect=GlobalSelect.init(el('expenseName'),{placeholder:'Select Expense Name'});
            var options=await App.api('api/expenses.php?options=1');
            actions=(options.data.allowed_actions||[]).map(Number);
            accounts=options.data.accounts||[];
            setExpenseNames(options.data.expense_names||[],'');
            fillAccountOptions();

            if(reference){
                el('pageHeading').textContent='Edit Expense';
                el('saveButtonText').textContent='Update Expense';
                var result=await App.api('api/expenses.php?ref='+encodeURIComponent(reference));
                actions=(result.data.allowed_actions||actions).map(Number);
                accounts=result.data.accounts||accounts;
                var e=result.data.expense||{};
                el('expenseNo').value=e.expense_no||'';
                el('expenseDate').value=e.expense_date||'';
                setExpenseNames(result.data.expense_names||[],e.expense_name||'');
                el('amount').value=n(e.amount).toFixed(2);
                el('remarks').value=e.remarks||'';
                setPaymentRows(e.payments||[]);
                if(Number(e.status)!==1||!has(3))el('saveButton').disabled=true;
            }else{
                el('expenseDate').value=options.data.today||new Date().toISOString().slice(0,10);
                document.querySelectorAll('.pay-date').forEach(function(input){input.value=el('expenseDate').value;});
                if(!has(2))el('saveButton').disabled=true;
                calculate();
            }
            if(window.lucide)window.lucide.createIcons();
        }catch(error){
            el('saveButton').disabled=true;
            App.showError(error,'Unable to load Expense form.');
        }
    }

    el('expenseName').addEventListener('change',syncNewExpenseName);
    el('amount').addEventListener('input',calculate);
    el('expenseDate').addEventListener('change',function(){
        document.querySelectorAll('.pay-date').forEach(function(input){if(!input.value)input.value=el('expenseDate').value;});
    });
    el('paymentRows').addEventListener('input',function(event){if(event.target.matches('input'))calculate();});
    el('paymentRows').addEventListener('change',calculate);

    form.addEventListener('submit',async function(event){
        event.preventDefault();
        if(saving||el('saveButton').disabled)return;
        if(!validateClient())return;
        saving=true;el('saveButton').disabled=true;
        try{
            var result=await App.api('api/expenses.php',{method:reference?'PUT':'POST',body:payload()});
            if(window.showToast)showToast(result.message||'Expense saved successfully.',{type:'success',duration:2});
            window.setTimeout(function(){location.href='expense-list.php';},650);
        }catch(error){
            App.showError(error,reference?'Unable to update Expense.':'Unable to save Expense.');
            saving=false;el('saveButton').disabled=false;
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
