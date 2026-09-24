<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Line Return';
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
<section class="page-content" id="lineReturnApp">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-heading">
    <div>
        <div class="heading-title-row">
            <h1>Line Return</h1>
            <span class="pill pending" id="returnStatusBadge">Select Truck Trip</span>
        </div>
        <p>Return remaining Filled stock, Empty cans and Damaged cans from Truck to Plant.</p>
    </div>
    <div class="heading-actions">
        <a class="btn gray" href="line-supply-list.php"><i data-lucide="list"></i><span>Line Supply List</span></a>
        <button class="btn btn-danger" id="exitButton" type="button"><i data-lucide="log-out"></i><span>Exit</span></button>
    </div>
</div>

<form id="lineReturnForm" novalidate>
    <input id="supplyRef" type="hidden">

    <div class="card form-section">
        <div class="card-header"><h2 class="section-heading"><i data-lucide="truck"></i>Truck Trip</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="field col-4" id="tripSelectField">
                    <label for="tripRef" class="required">Active Truck Trip</label>
                    <select class="select" id="tripRef"><option value="">Select Truck Trip</option></select>
                </div>
                <div class="field col-2">
                    <label>Supply No</label>
                    <input class="input" id="supplyNo" type="text" readonly placeholder="—">
                </div>
                <div class="field col-3">
                    <label>Truck</label>
                    <input class="input" id="vehicleText" type="text" readonly placeholder="—">
                </div>
                <div class="field col-3">
                    <label>Line</label>
                    <input class="input" id="lineText" type="text" readonly placeholder="—">
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card form-section" id="returnItemsCard" hidden>
        <div class="card-header">
            <div>
                <h2 class="section-heading"><i data-lucide="undo-2"></i>Truck Return</h2>
                <p class="muted">Actual return is prefilled with the expected Truck balance. Change only when physical quantity differs.</p>
            </div>
        </div>
        <div class="app-table-wrap">
            <table class="app-editable-table">
                <thead>
                    <tr>
                        <th class="cell-index">#</th>
                        <th class="cell-main">Product</th>
                        <th class="cell-qty">Loaded</th>
                        <th class="cell-qty">Sold</th>
                        <th class="cell-qty">Expected Filled</th>
                        <th class="cell-qty">Actual Filled Return</th>
                        <th class="cell-qty">Filled Short</th>
                        <th class="cell-qty">Empty Collected</th>
                        <th class="cell-qty">Actual Empty Return</th>
                        <th class="cell-qty">Empty Short</th>
                        <th class="cell-qty">Damaged Collected</th>
                        <th class="cell-qty">Actual Damaged Return</th>
                        <th class="cell-qty">Damaged Short</th>
                        <th class="cell-main">Reason</th>
                    </tr>
                </thead>
                <tbody id="returnItemsBody"><tr><td class="empty" colspan="14">Select a Truck Trip.</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="app-split-grid" id="bottomSection" hidden>
        <div class="card form-section">
            <div class="card-header"><h2 class="section-heading"><i data-lucide="wallet-cards"></i>Collection</h2></div>
            <div class="card-body">
                <div class="app-total-group">
                    <div class="app-total-line"><span>Total Sales</span><strong id="totalSales">₹0.00</strong></div>
                    <div class="app-total-line"><span>Cash</span><strong id="cashTotal">₹0.00</strong></div>
                    <div class="app-total-line"><span>UPI</span><strong id="upiTotal">₹0.00</strong></div>
                    <div class="app-total-line"><span>Bank</span><strong id="bankTotal">₹0.00</strong></div>
                    <div class="app-total-line"><span>Cheque</span><strong id="chequeTotal">₹0.00</strong></div>
                    <div class="app-total-line"><span>Total Received</span><strong id="receivedTotal">₹0.00</strong></div>
                    <div class="app-total-line"><span>Outstanding</span><strong id="outstandingTotal">₹0.00</strong></div>
                </div>
            </div>
        </div>

        <div class="card form-section">
            <div class="card-header"><h2 class="section-heading"><i data-lucide="clipboard-check"></i>Return Details</h2></div>
            <div class="card-body">
                <div class="field">
                    <label for="returnRemarks">Return Remarks</label>
                    <textarea class="input" id="returnRemarks" rows="3" maxlength="255" placeholder="Optional"></textarea>
                </div>
                <div class="app-entry-note" id="returnNote">Shortage reason is required only when Actual Return is less than expected.</div>
            </div>
        </div>
    </div>

    <div class="app-summary-actions" id="returnActions" hidden>
        <span class="muted" id="actionNote"></span>
        <div class="heading-actions">
            <button class="btn btn-primary" id="submitReturnButton" type="button" hidden><i data-lucide="undo-2"></i>Submit Truck Return</button>
            <button class="btn btn-primary" id="closeTripButton" type="button" hidden><i data-lucide="check-circle-2"></i>Close Truck Trip</button>
        </div>
    </div>
</form>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
(function(window,document){
    "use strict";

    var params=new URLSearchParams(window.location.search);
    var initialRef=params.get("ref")||"";
    var ACTION_FINALIZE=12;
    var ACTION_RETURN=32;
    var tripSelect=GlobalSelect.init("#tripRef",{placeholder:"Select Truck Trip"});

    var state={actions:[],trips:[],trip:null,items:[],financial:{},saving:false,loading:true};

    function byId(id){return document.getElementById(id)}
    function all(selector,root){return Array.prototype.slice.call((root||document).querySelectorAll(selector))}
    function n(value){var x=Number(value||0);return Number.isFinite(x)?x:0}
    function r3(value){return Math.round((n(value)+Number.EPSILON)*1000)/1000}
    function qty(value){return n(value).toLocaleString("en-IN",{minimumFractionDigits:3,maximumFractionDigits:3})}
    function money(value){return "₹"+n(value).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2})}

    function unitName(unitNameValue,shortNameValue){
        return shortNameValue||unitNameValue||"Base";
    }

    function baseUnitName(item){
        var pc=Math.max(1,n(item.primary_conversion_qty||1));
        var sc=item.secondary_product_unit_id
            ?Math.max(1,n(item.secondary_conversion_qty||1))
            :0;

        if(item.secondary_product_unit_id&&sc<=pc){
            return unitName(
                item.secondary_unit_name,
                item.secondary_short_name
            );
        }

        return unitName(
            item.primary_unit_name,
            item.primary_short_name
        );
    }

    function qtyWithUnit(item,value){
        return qty(value)+" "+baseUnitName(item);
    }
    function esc(value){return String(value==null?"":value).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;")}
    function has(action){return state.actions.map(Number).indexOf(Number(action))!==-1}
    function showSuccess(message){if(window.showToast)showToast(message,{type:"success",duration:2})}
    function showWarning(message){if(window.showToast)showToast(message,{type:"warning",duration:3});else window.alert(message)}
    function reportError(error,fallback){if(window.App&&App.showError)App.showError(error,fallback);else window.alert((error&&error.message)||fallback)}
    function refreshIcons(){if(window.lucide&&lucide.createIcons)lucide.createIcons()}

    function statusLabel(status){status=Number(status);if(status===2)return "Loaded";if(status===3)return "In Route";if(status===4)return "Returned";if(status===5)return "Closed";return "Trip"}
    function tripText(row){var text=(row.supply_no||"Trip")+" · "+(row.vehicle_no||"")+" · "+(row.line_name||"");if(Number(row.status)===4)text+=" · Returned";return text}

    function setTripOptions(selected){
        var options=state.trips.map(function(row){return{value:row.ref,text:tripText(row)}});
        tripSelect.setOptions(options,selected||"");
        byId("tripRef").value=selected||"";
    }

    function normalizeItem(row){
        var returned=Number(state.trip&&state.trip.status)>=4?n(row.returned_base_qty):n(row.expected_return_qty);
        var emptyReturned=Number(state.trip&&state.trip.status)>=4?n(row.empty_returned_to_plant_qty):n(row.empty_collected_qty);
        var damagedReturned=Number(state.trip&&state.trip.status)>=4?n(row.damaged_returned_to_plant_qty):n(row.damaged_collected_qty);
        return Object.assign({},row,{
            actual_return_qty:r3(returned),
            actual_empty_return_qty:r3(emptyReturned),
            actual_damaged_return_qty:r3(damagedReturned),
            reason:row.return_reason||""
        });
    }

    function renderItems(){
        var body=byId("returnItemsBody");
        if(!state.items.length){body.innerHTML='<tr><td class="empty" colspan="14">No Loaded Products.</td></tr>';return}
        var locked=Number(state.trip.status)>=4;
        body.innerHTML=state.items.map(function(x,i){
            var reusable=Number(x.container_type)===1;
            var sold=Math.max(0,r3(n(x.loaded_base_qty)-n(x.expected_return_qty)));
            var filledShort=Math.max(0,r3(n(x.expected_return_qty)-n(x.actual_return_qty)));
            var emptyShort=Math.max(0,r3(n(x.empty_collected_qty)-n(x.actual_empty_return_qty)));
            var damagedShort=Math.max(0,r3(n(x.damaged_collected_qty)-n(x.actual_damaged_return_qty)));
            return '<tr data-index="'+i+'">'+
                '<td class="cell-index">'+(i+1)+'</td>'+
                '<td class="cell-main"><strong>'+esc(x.product_name||'-')+'</strong><div class="muted">'+esc(x.product_code||'')+'</div></td>'+
                '<td class="cell-qty dt-body-right">'+qtyWithUnit(x,x.loaded_base_qty)+'</td>'+
                '<td class="cell-qty dt-body-right">'+qtyWithUnit(x,sold)+'</td>'+
                '<td class="cell-qty dt-body-right">'+qtyWithUnit(x,x.expected_return_qty)+'</td>'+
                '<td class="cell-qty"><input class="input js-filled" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" value="'+qty(x.actual_return_qty)+'" '+(locked?'readonly':'')+'></td>'+
                '<td class="cell-qty dt-body-right js-filled-short">'+qtyWithUnit(x,filledShort)+'</td>'+
                '<td class="cell-qty dt-body-right">'+(reusable?qty(x.empty_collected_qty):'—')+'</td>'+
                '<td class="cell-qty">'+(reusable?'<input class="input js-empty" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" value="'+qty(x.actual_empty_return_qty)+'" '+(locked?'readonly':'')+'>':'—')+'</td>'+
                '<td class="cell-qty dt-body-right js-empty-short">'+(reusable?qty(emptyShort):'—')+'</td>'+
                '<td class="cell-qty dt-body-right">'+(reusable?qty(x.damaged_collected_qty):'—')+'</td>'+
                '<td class="cell-qty">'+(reusable?'<input class="input js-damaged" type="text" inputmode="decimal" data-validation="decimal" data-decimal-places="3" value="'+qty(x.actual_damaged_return_qty)+'" '+(locked?'readonly':'')+'>':'—')+'</td>'+
                '<td class="cell-qty dt-body-right js-damaged-short">'+(reusable?qty(damagedShort):'—')+'</td>'+
                '<td class="cell-main"><input class="input js-reason" type="text" maxlength="255" value="'+esc(x.reason||'')+'" placeholder="'+((filledShort>0||emptyShort>0||damagedShort>0)?'Required':'Optional')+'" '+(locked?'readonly':'')+'></td>'+
            '</tr>';
        }).join("");
        refreshIcons();
    }

    function updateRow(row){
        var i=Number(row.getAttribute("data-index"));
        var x=state.items[i];
        if(!x)return;
        var filled=row.querySelector(".js-filled");
        var empty=row.querySelector(".js-empty");
        var damaged=row.querySelector(".js-damaged");
        var reason=row.querySelector(".js-reason");
        x.actual_return_qty=r3(filled?filled.value:0);
        x.actual_empty_return_qty=r3(empty?empty.value:0);
        x.actual_damaged_return_qty=r3(damaged?damaged.value:0);
        x.reason=reason?reason.value.trim():"";
        var fs=Math.max(0,r3(n(x.expected_return_qty)-n(x.actual_return_qty)));
        var es=Math.max(0,r3(n(x.empty_collected_qty)-n(x.actual_empty_return_qty)));
        var ds=Math.max(0,r3(n(x.damaged_collected_qty)-n(x.actual_damaged_return_qty)));
        var fsc=row.querySelector(".js-filled-short");if(fsc)fsc.textContent=qty(fs);
        var escell=row.querySelector(".js-empty-short");if(escell&&Number(x.container_type)===1)escell.textContent=qty(es);
        var dsc=row.querySelector(".js-damaged-short");if(dsc&&Number(x.container_type)===1)dsc.textContent=qty(ds);
        if(reason)reason.placeholder=(fs>0.0005||es>0.0005||ds>0.0005)?"Required":"Optional";
    }

    function renderFinancial(){
        var f=state.financial||{};
        byId("totalSales").textContent=money(f.total_sales);
        byId("cashTotal").textContent=money(f.cash);
        byId("upiTotal").textContent=money(f.upi);
        byId("bankTotal").textContent=money(f.bank);
        byId("chequeTotal").textContent=money(f.cheque);
        byId("receivedTotal").textContent=money(f.total_received);
        byId("outstandingTotal").textContent=money(f.outstanding);
    }

    function applyTrip(){
        var trip=state.trip;
        var ready=!!trip;
        byId("returnItemsCard").hidden=!ready;
        byId("bottomSection").hidden=!ready;
        byId("returnActions").hidden=!ready;
        if(!ready){
            byId("supplyNo").value="";byId("vehicleText").value="";byId("lineText").value="";
            return;
        }
        byId("supplyRef").value=trip.ref||"";
        byId("supplyNo").value=trip.supply_no||"";
        byId("vehicleText").value=(trip.vehicle_no||"")+(trip.vehicle_name?" - "+trip.vehicle_name:"");
        byId("lineText").value=(trip.line_code?trip.line_code+" - ":"")+(trip.line_name||"");
        byId("returnRemarks").value=trip.return_remarks||"";
        byId("returnRemarks").readOnly=Number(trip.status)>=4;
        byId("returnStatusBadge").textContent=statusLabel(trip.status);
        byId("returnStatusBadge").className="pill "+(Number(trip.status)===5?"active":"pending");

        var canReturn=(Number(trip.status)===2||Number(trip.status)===3)&&has(ACTION_RETURN);
        var canClose=Number(trip.status)===4&&has(ACTION_FINALIZE);
        byId("submitReturnButton").hidden=!canReturn;
        byId("closeTripButton").hidden=!canClose;
        byId("actionNote").textContent=Number(trip.status)===4?"Truck Return submitted. Close the trip after verification.":(Number(trip.status)===5?"Truck Trip closed.":"Verify physical return quantities before submitting.");
        renderItems();
        renderFinancial();
        refreshIcons();
    }

    function setTripData(data){
        state.trip=data.trip||null;
        state.financial=data.financial||{};
        state.actions=(data.allowed_actions||state.actions||[]).map(Number);
        state.items=(data.items||[]).map(normalizeItem);
        applyTrip();
    }

    async function loadTrip(ref){
        if(!ref){state.trip=null;state.items=[];state.financial={};applyTrip();return}
        try{
            var result=await App.api("api/line-supply.php?ref="+encodeURIComponent(ref));
            setTripData(result.data||{});
        }catch(error){reportError(error,"Unable to load Truck Return trip.")}
    }

    async function loadOptions(){
        try{
            var result=await App.api("api/line-supply.php?return_options=1");
            state.actions=((result.data&&result.data.allowed_actions)||[]).map(Number);
            state.trips=(result.data&&result.data.trips)||[];
            var selected=initialRef;
            if(selected&&!state.trips.some(function(x){return String(x.ref)===String(selected)})) selected="";
            if(!selected&&state.trips.length===1) selected=state.trips[0].ref;
            setTripOptions(selected);
            if(state.trips.length===1) byId("tripSelectField").hidden=true;
            if(selected) await loadTrip(selected);
        }catch(error){reportError(error,"Unable to load Truck Return options.")}
        finally{state.loading=false;refreshIcons()}
    }

    function readRows(){all("#returnItemsBody tr[data-index]").forEach(updateRow)}

    function validateReturn(){
        readRows();
        for(var i=0;i<state.items.length;i+=1){
            var x=state.items[i];
            if(n(x.actual_return_qty)>n(x.expected_return_qty)+0.0005){showWarning("Actual Filled Return cannot exceed expected Truck stock for "+(x.product_name||"Product")+".");return false}
            if(Number(x.container_type)===1){
                if(n(x.actual_empty_return_qty)>n(x.empty_collected_qty)+0.0005){showWarning("Actual Empty Return cannot exceed collected Empty cans for "+(x.product_name||"Product")+".");return false}
                if(n(x.actual_damaged_return_qty)>n(x.damaged_collected_qty)+0.0005){showWarning("Actual Damaged Return cannot exceed collected Damaged cans for "+(x.product_name||"Product")+".");return false}
            }
            var shortFilled=n(x.expected_return_qty)-n(x.actual_return_qty);
            var shortEmpty=Number(x.container_type)===1?n(x.empty_collected_qty)-n(x.actual_empty_return_qty):0;
            var shortDamaged=Number(x.container_type)===1?n(x.damaged_collected_qty)-n(x.actual_damaged_return_qty):0;
            if((shortFilled>0.0005||shortEmpty>0.0005||shortDamaged>0.0005)&&!String(x.reason||"").trim()){
                showWarning("Enter shortage reason for "+(x.product_name||"Product")+".");return false
            }
        }
        return true;
    }

    async function submitReturn(){
        if(state.saving||!state.trip||!validateReturn())return;
        if(!window.confirm("Submit Truck Return? Returned stock will move from Truck back to Plant."))return;
        state.saving=true;
        try{
            var payload={
                ref:state.trip.ref,
                intent:"return",
                return_remarks:byId("returnRemarks").value.trim(),
                return_items_json:JSON.stringify(state.items.map(function(x){return{
                    product_id:Number(x.product_id),
                    actual_return_qty:r3(x.actual_return_qty),
                    actual_empty_return_qty:r3(x.actual_empty_return_qty),
                    actual_damaged_return_qty:r3(x.actual_damaged_return_qty),
                    reason:String(x.reason||"").trim()
                }}))
            };
            var result=await App.api("api/line-supply.php",{method:"POST",body:payload});
            showSuccess(result.message||"Truck Return submitted.");
            setTripData(result.data||{});
            await loadOptions();
        }catch(error){reportError(error,"Unable to submit Truck Return.")}
        finally{state.saving=false}
    }

    async function closeTrip(){
        if(state.saving||!state.trip)return;
        if(!window.confirm("Close this Truck Trip? Closed trips are read-only."))return;
        state.saving=true;
        try{
            var result=await App.api("api/line-supply.php",{method:"POST",body:{ref:state.trip.ref,intent:"close"}});
            showSuccess(result.message||"Truck Trip closed.");
            setTripData(result.data||{});
            await loadOptions();
        }catch(error){reportError(error,"Unable to close Truck Trip.")}
        finally{state.saving=false}
    }

    byId("tripRef").addEventListener("change",function(){loadTrip(byId("tripRef").value||"")});
    byId("returnItemsBody").addEventListener("input",function(event){var row=event.target.closest("tr[data-index]");if(row)updateRow(row)});
    byId("submitReturnButton").addEventListener("click",submitReturn);
    byId("closeTripButton").addEventListener("click",closeTrip);
    byId("exitButton").addEventListener("click",function(){window.location.href="line-supply-list.php"});

    loadOptions();
})(window,document);
</script>
</body>
</html>
