<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Product List';
$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
];
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
    <?php foreach ($headStyles as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ($headScripts as $scriptUrl): ?>
    <script src="<?php echo web_h($scriptUrl); ?>"></script>
    <?php endforeach; ?>
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
<script src="assets/js/datatable.js"></script>

<div class="page-head">
    <div>
        <h1>Product List</h1>
        <p>Manage products, HSN/GST, units and customer price-level pricing.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="product-form.php" hidden>
        <i data-lucide="plus"></i>Add Product
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="package"></i></div><div><div class="kpi-label">Total Products</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">Active Products</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-off"></i></div><div><div class="kpi-label">Inactive Products</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="shopping-cart"></i></div><div><div class="kpi-label">Sale Allowed</div><div class="kpi-value" id="kpiSaleAllowed">0</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="masterSearch">Search</label>
                <input id="masterSearch" type="text" autocomplete="off" placeholder="Code, product, HSN...">
            </div>
            <div class="field col-3">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="field col-2">
                <label for="typeFilter">Product Type</label>
                <select id="typeFilter">
                    <option value="">All Types</option>
                    <option value="1">Raw Material</option>
                    <option value="2">Finished Product</option>
                    <option value="3">Consumable</option>
                </select>
            </div>
            <div class="field col-2">
                <label for="saleFilter">Sales</label>
                <select id="saleFilter">
                    <option value="">All</option>
                    <option value="1">Allowed</option>
                    <option value="0">Not Allowed</option>
                </select>
            </div>
            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="2">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <table id="masterTable" class="display data-table">
        <thead>
        <tr>
            <th>Code</th>
            <th>Product</th>
            <th>Type</th>
            <th>Category</th>
            <th>HSN</th>
            <th>Sales</th>
            <th>Base Price</th>
            <th>Primary Unit</th>
            <th>Secondary Unit</th>
            <th>Status</th>
            <th>Manage</th>
        </tr>
        </thead>
    </table>
</div>

<script>
(function ($, window, document) {
    "use strict";
    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var allowedActions=[];
    var searchTimer=null;
    var masterSearch=document.getElementById("masterSearch");
    var categoryFilter=document.getElementById("categoryFilter");
    var typeFilter=document.getElementById("typeFilter");
    var saleFilter=document.getElementById("saleFilter");
    var statusFilter=document.getElementById("statusFilter");
    var addButton=document.getElementById("addButton");
    var has=AppDataTable.has;
    var ACTION_CREATE=2,ACTION_UPDATE=3,ACTION_ACTIVATE=27,ACTION_DEACTIVATE=28;


    function setStats(rows){
        rows=rows||[];
        var active=0,inactive=0,saleAllowed=0;
        rows.forEach(function(row){
            if(Number(row.status)===1) active++; else inactive++;
            if(Number(row.sale_allowed)===1) saleAllowed++;
        });
        document.getElementById("kpiTotal").textContent=rows.length.toLocaleString("en-IN");
        document.getElementById("kpiActive").textContent=active.toLocaleString("en-IN");
        document.getElementById("kpiInactive").textContent=inactive.toLocaleString("en-IN");
        document.getElementById("kpiSaleAllowed").textContent=saleAllowed.toLocaleString("en-IN");
    }

    async function refreshStats(){
        try{
            var result=await App.api("api/products.php");
            setStats(result.data.products||[]);
        }catch(error){
            setStats([]);
        }
    }

    async function loadFilterOptions(){
        try{
            var result=await App.api("api/products.php?options=1");
            var options=(result.data&&result.data.options)||{};
            (options.categories||[]).forEach(function(row){
                var option=document.createElement("option");
                option.value=String(row.id);
                option.textContent=(row.category_code?row.category_code+" - ":"")+row.category_name;
                categoryFilter.appendChild(option);
            });
        }catch(error){
            App.showError(error,"Unable to load Product filters.");
        }
    }

    function escapeHtml(value){
        return String(value===null||value===undefined?"":value)
            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
    }
    function typeLabel(value){
        var type=Number(value||0);
        if(type===1)return "Raw Material";
        if(type===3)return "Consumable";
        return "Finished Product";
    }
    function money(value){
        var number=Number(value||0);
        if(!Number.isFinite(number))number=0;
        return "₹"+number.toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});
    }
    function statusHtml(value,type){
        var active=Number(value)===1;
        if(type!=="display")return Number(value);
        return active
            ?'<span class="dt-status active">Active</span>'
            :'<span class="dt-status inactive">Inactive</span>';
    }

    var table=AppDataTable.init("#masterTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading products...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[0,"asc"]],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"csvHtml5",text:"CSV",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"excelHtml5",text:"Excel",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"pdfHtml5",text:"PDF",title:"Product List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"print",text:"Print",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}
        ],
        ajax:function(data,callback){
            var params=new URLSearchParams();
            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value||"");
            if(categoryFilter.value!=="")params.set("category_id",categoryFilter.value);
            if(typeFilter.value!=="")params.set("product_type",typeFilter.value);
            if(saleFilter.value!=="")params.set("sale_allowed",saleFilter.value);
            if(statusFilter.value!=="")params.set("status",statusFilter.value);
            if(data.order&&data.order[0]){
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/products.php?"+params.toString()).then(function(result){
                allowedActions=(result.data.allowed_actions||[]).map(Number);
                addButton.hidden=!has(allowedActions,ACTION_CREATE);
                AppDataTable.applyExportPermissions(table,allowedActions);
                callback(result.data.datatable);
            }).catch(function(error){
                addButton.hidden=true;
                App.showError(error,"Unable to load products.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:"product_code",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"product_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"product_type",render:function(v,t){var x=typeLabel(v);return t==="display"?escapeHtml(x):Number(v||0);}},
            {data:"category_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"hsn_code",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"sale_allowed",render:function(v,t){var x=Number(v)===1?"Yes":"No";return t==="display"?escapeHtml(x):Number(v||0);}},
            {data:"purchase_price",className:"dt-body-right",render:function(v,t){return t==="display"?escapeHtml(money(v)):Number(v||0);}},
            {data:"primary_unit_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"secondary_unit_name",defaultContent:"-",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"status",render:function(v,t){return statusHtml(v,t);}},
            {
                data:null,orderable:false,searchable:false,className:"table-action-icons",
                render:function(data,type,row){
                    if(type!=="display")return "";
                    var html="";
                    if(has(allowedActions,ACTION_UPDATE)){
                        html+=App.iconActionHtml({href:row.edit_url,icon:"pencil",label:"Edit Product"});
                    }
                    if(Number(row.status)===1&&has(allowedActions,ACTION_DEACTIVATE)){
                        html+='<button type="button" class="table-icon-action danger js-status" data-ref="'+escapeHtml(row.ref)+'" data-status="2" title="Deactivate Product" aria-label="Deactivate Product"><i data-lucide="circle-off"></i></button>';
                    }else if(Number(row.status)!==1&&has(allowedActions,ACTION_ACTIVATE)){
                        html+='<button type="button" class="table-icon-action js-status" data-ref="'+escapeHtml(row.ref)+'" data-status="1" title="Activate Product" aria-label="Activate Product"><i data-lucide="circle-check"></i></button>';
                    }
                    return html||'<span class="muted">View only</span>';
                }
            }
        ],
        language:{emptyTable:"No products found.",zeroRecords:"No matching products found."},
        drawCallback:function(){if(window.lucide)window.lucide.createIcons();}
    });

    (function removeDefaultSearchRow(){
        var tableElement=document.getElementById("masterTable");
        var card=tableElement?tableElement.closest(".table-card"):null;
        var searchRow=card?card.querySelector(".app-table-search-row"):null;
        if(searchRow)searchRow.remove();
    })();

    masterSearch.addEventListener("input",function(){
        window.clearTimeout(searchTimer);
        searchTimer=window.setTimeout(function(){table.search(masterSearch.value.trim()).draw();},350);
    });
    [categoryFilter,typeFilter,saleFilter,statusFilter].forEach(function(filter){
        filter.addEventListener("change",function(){table.ajax.reload(null,true);});
    });

    document.addEventListener("click",function(event){
        var button=event.target.closest(".js-status");
        if(!button)return;
        var status=Number(button.getAttribute("data-status"));
        var requiredAction=status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE;
        if(!has(allowedActions,requiredAction))return;
        if(!window.confirm(status===1?"Activate this Product?":"Deactivate this Product?"))return;

        button.disabled=true;
        App.api("api/products.php",{
            method:"PATCH",
            body:{ref:button.getAttribute("data-ref"),status:status}
        }).then(function(result){
            if(window.showToast)showToast(result.message||"Product status updated.",{type:"success",duration:2});
            table.ajax.reload(null,false);
            refreshStats();
        }).catch(function(error){
            App.showError(error,"Unable to change Product status.");
        }).finally(function(){button.disabled=false;});
    });
    loadFilterOptions();
    refreshStats();
})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
