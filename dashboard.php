<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
<title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

<?php render_frontend_config_script(); ?>
<script src="assets/js/runtime.js"></script>

<!-- USE EXISTING PROJECT CSS ONLY -->
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
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

<!-- =========================================================
     PAGE HEADING
========================================================= -->
<div class="page-head">
    <div>
        <h1 id="dashboardGreeting">Dashboard</h1>
        <p>
            Live branch overview for Sales, Purchase, Stock,
            Collections, Outstanding and Truck Operations.
        </p>
    </div>

    <div class="heading-actions">
        <span class="muted" id="lastUpdated">Loading...</span>

        <button class="btn gray" id="refreshDashboard" type="button">
            <i data-lucide="refresh-cw"></i>
            Refresh
        </button>
    </div>
</div>

<!-- =========================================================
     KPI CARDS
========================================================= -->
<div class="kpi-grid">

    <div class="card kpi-card">
        <div class="kpi-icon blue">
            <i data-lucide="indian-rupee"></i>
        </div>
        <div>
            <div class="kpi-label">Today Sales</div>
            <div class="kpi-value" id="kpiTodaySales">₹0.00</div>
            <div class="kpi-meta" id="kpiMonthSales">Month ₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="shopping-cart"></i>
        </div>
        <div>
            <div class="kpi-label">Today Purchase</div>
            <div class="kpi-value" id="kpiTodayPurchase">₹0.00</div>
            <div class="kpi-meta" id="kpiMonthPurchase">Month ₹0.00</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal">
            <i data-lucide="users"></i>
        </div>
        <div>
            <div class="kpi-label">Customer Outstanding</div>
            <div class="kpi-value" id="kpiCustomerOutstanding">₹0.00</div>
            <div class="kpi-meta">Opening + Posted Invoice Balance</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="truck"></i>
        </div>
        <div>
            <div class="kpi-label">Supplier Outstanding</div>
            <div class="kpi-value" id="kpiSupplierOutstanding">₹0.00</div>
            <div class="kpi-meta">Opening + Purchases − Settlements</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green">
            <i data-lucide="wallet"></i>
        </div>
        <div>
            <div class="kpi-label">Today Collection</div>
            <div class="kpi-value" id="kpiCollection">₹0.00</div>
            <div class="kpi-meta">Customer Receipts</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="receipt"></i>
        </div>
        <div>
            <div class="kpi-label">Today Expense</div>
            <div class="kpi-value" id="kpiExpense">₹0.00</div>
            <div class="kpi-meta">Active Expenses</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon blue">
            <i data-lucide="landmark"></i>
        </div>
        <div>
            <div class="kpi-label">Account Balance</div>
            <div class="kpi-value" id="kpiAccountBalance">₹0.00</div>
            <div class="kpi-meta">Money In − Money Out</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal">
            <i data-lucide="boxes"></i>
        </div>
        <div>
            <div class="kpi-label">Plant Stock</div>
            <div class="kpi-value" id="kpiPlantStock">0.000</div>
            <div class="kpi-meta">Normalized Base Quantity</div>
        </div>
    </div>

</div>

<!-- =========================================================
     QUICK ACTIONS - EXISTING FLOW COMPONENT
========================================================= -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Quick Actions</h2>
            <p class="muted">Frequently used operational screens.</p>
        </div>
    </div>

    <div class="card-body">

        <div class="flow-section">
            <div class="flow">

                <a class="flow-step" href="sales-form.php">
                    <i data-lucide="receipt-text"></i>
                    <span>New Sale</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="purchase-form.php">
                    <i data-lucide="shopping-cart"></i>
                    <span>Purchase</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="production-form.php">
                    <i data-lucide="factory"></i>
                    <span>Production</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="line-supply-form.php">
                    <i data-lucide="truck"></i>
                    <span>Truck Loading</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="line-return-form.php">
                    <i data-lucide="undo-2"></i>
                    <span>Truck Return</span>
                </a>

            </div>
        </div>

        <div class="flow-section">
            <div class="flow">

                <a class="flow-step" href="product-form.php">
                    <i data-lucide="package-plus"></i>
                    <span>Add Product</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="customer-form.php">
                    <i data-lucide="user-plus"></i>
                    <span>Add Customer</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="sales-list.php">
                    <i data-lucide="list"></i>
                    <span>Sales List</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="purchase-list.php">
                    <i data-lucide="list-checks"></i>
                    <span>Purchase List</span>
                </a>

                <span class="flow-arrow">›</span>

                <a class="flow-step" href="stock-movement-report.php">
                    <i data-lucide="arrow-left-right"></i>
                    <span>Stock Movement</span>
                </a>

            </div>
        </div>

    </div>
</div>

<!-- =========================================================
     MAIN CHART ROW
========================================================= -->
<div class="dashboard-grid-main">

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">7-Day Sales vs Purchase</h2>
                <p class="muted">Posted documents only.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap">
                <canvas id="dailySalesPurchaseChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">This Month Sales Mix</h2>
                <p class="muted">GST vs Non-GST invoice value.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap">
                <canvas id="salesMixChart"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- =========================================================
     SECONDARY CHART ROW
========================================================= -->
<div class="dashboard-grid-secondary">

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">6-Month Sales vs Purchase</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">7-Day Stock Flow</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap">
                <canvas id="stockFlowChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Top Products — 30 Days</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- =========================================================
     BOTTOM DATA CARDS
========================================================= -->
<div class="dashboard-grid-bottom">

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Activity</h2>
                <p class="muted">Latest posted transactions.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="activity-list" id="recentActivityList">
                <div class="empty-state">Loading...</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Top Customer Outstanding</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="activity-list" id="outstandingCustomersList">
                <div class="empty-state">Loading...</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Stock Warnings</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="activity-list" id="stockWarningsList">
                <div class="empty-state">Loading...</div>
            </div>
        </div>
    </div>

</div>

<!-- =========================================================
     OPERATIONS + TRUCKS
========================================================= -->
<div class="dashboard-grid-main">

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Active Truck Trips</h2>
                <p class="muted">Loaded / In Route / Returned trips.</p>
            </div>
            <a class="btn btn-soft btn-sm" href="line-supply-list.php">
                View All
            </a>
        </div>
        <div class="card-body">
            <div class="activity-list" id="activeTripsList">
                <div class="empty-state">Loading...</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Operational Counts</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="activity-list" id="operationalCounts">
                <div class="empty-state">Loading...</div>
            </div>
        </div>
    </div>

</div>

<script>
(function(window,document){
'use strict';

var charts={};

function byId(id){return document.getElementById(id)}
function n(v){var x=Number(v||0);return Number.isFinite(x)?x:0}

function money(v){
    return '₹'+n(v).toLocaleString('en-IN',{
        minimumFractionDigits:2,
        maximumFractionDigits:2
    });
}

function qty(v){
    return n(v).toLocaleString('en-IN',{
        minimumFractionDigits:3,
        maximumFractionDigits:3
    });
}

function esc(v){
    return String(v==null?'':v)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

function icons(){
    if(window.lucide&&lucide.createIcons){
        lucide.createIcons();
    }
}

function reportError(error,fallback){
    if(window.App&&App.showError){
        App.showError(error,fallback);
    }else{
        window.alert((error&&error.message)||fallback);
    }
}

function destroyChart(key){
    if(charts[key]){
        charts[key].destroy();
        charts[key]=null;
    }
}

function chartOptions(){
    return {
        responsive:true,
        maintainAspectRatio:false,
        interaction:{
            mode:'index',
            intersect:false
        },
        plugins:{
            legend:{
                display:true,
                labels:{
                    usePointStyle:true,
                    boxWidth:7
                }
            }
        },
        scales:{
            x:{
                grid:{display:false}
            },
            y:{
                beginAtZero:true,
                ticks:{
                    callback:function(value){
                        return Number(value).toLocaleString('en-IN',{
                            notation:'compact',
                            maximumFractionDigits:1
                        });
                    }
                }
            }
        }
    };
}

function createCharts(data){
    data=data||{};

    var daily=data.daily_sales_purchase||[];

    destroyChart('daily');
    charts.daily=new Chart(
        byId('dailySalesPurchaseChart'),
        {
            type:'bar',
            data:{
                labels:daily.map(function(x){return x.label}),
                datasets:[
                    {
                        label:'Sales',
                        data:daily.map(function(x){return n(x.sales)}),
                        borderWidth:1
                    },
                    {
                        label:'Purchase',
                        data:daily.map(function(x){return n(x.purchase)}),
                        borderWidth:1
                    }
                ]
            },
            options:chartOptions()
        }
    );

    var monthly=data.monthly_sales_purchase||[];

    destroyChart('monthly');
    charts.monthly=new Chart(
        byId('monthlyChart'),
        {
            type:'line',
            data:{
                labels:monthly.map(function(x){return x.label}),
                datasets:[
                    {
                        label:'Sales',
                        data:monthly.map(function(x){return n(x.sales)}),
                        tension:.3,
                        fill:false
                    },
                    {
                        label:'Purchase',
                        data:monthly.map(function(x){return n(x.purchase)}),
                        tension:.3,
                        fill:false
                    }
                ]
            },
            options:chartOptions()
        }
    );

    var flow=data.stock_flow||[];

    destroyChart('stock');
    charts.stock=new Chart(
        byId('stockFlowChart'),
        {
            type:'bar',
            data:{
                labels:flow.map(function(x){return x.label}),
                datasets:[
                    {
                        label:'Stock In',
                        data:flow.map(function(x){return n(x.stock_in)}),
                        borderWidth:1
                    },
                    {
                        label:'Stock Out',
                        data:flow.map(function(x){return n(x.stock_out)}),
                        borderWidth:1
                    }
                ]
            },
            options:chartOptions()
        }
    );

    var products=data.top_products||[];

    destroyChart('products');
    var topProductOptions=chartOptions();
    topProductOptions.indexAxis='y';

    charts.products=new Chart(
        byId('topProductsChart'),
        {
            type:'bar',
            data:{
                labels:products.map(function(x){
                    return x.product_name;
                }),
                datasets:[
                    {
                        label:'Sold Base Qty',
                        data:products.map(function(x){
                            return n(x.sold_base_qty);
                        }),
                        borderWidth:1
                    }
                ]
            },
            options:topProductOptions
        }
    );

    var mix=data.sales_mix||{};
    var gst=mix.gst||{};
    var non=mix.non_gst||{};

    destroyChart('mix');

    charts.mix=new Chart(
        byId('salesMixChart'),
        {
            type:'doughnut',
            data:{
                labels:['GST','Non-GST'],
                datasets:[
                    {
                        data:[
                            n(gst.amount),
                            n(non.amount)
                        ]
                    }
                ]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                cutout:'66%',
                plugins:{
                    legend:{
                        position:'bottom',
                        labels:{
                            usePointStyle:true
                        }
                    },
                    tooltip:{
                        callbacks:{
                            label:function(ctx){
                                return ctx.label+': '+money(ctx.raw);
                            }
                        }
                    }
                }
            }
        }
    );
}

function renderKpis(data){
    var k=data.kpis||{};

    byId('kpiTodaySales').textContent=
        money(k.today_sales);

    byId('kpiTodayPurchase').textContent=
        money(k.today_purchase);

    byId('kpiCustomerOutstanding').textContent=
        money(k.customer_outstanding);

    byId('kpiSupplierOutstanding').textContent=
        money(k.supplier_outstanding);

    byId('kpiCollection').textContent=
        money(k.today_collection);

    byId('kpiExpense').textContent=
        money(k.today_expense);

    byId('kpiAccountBalance').textContent=
        money(k.account_balance);

    byId('kpiPlantStock').textContent=
        qty(k.plant_stock_base);

    byId('kpiMonthSales').textContent=
        'Month '+money(k.month_sales);

    byId('kpiMonthPurchase').textContent=
        'Month '+money(k.month_purchase);
}

function activityRow(icon,title,sub,value,url,badge){
    var open=url
        ?'<a class="activity-item" href="'+esc(url)+'" style="text-decoration:none;color:inherit">'
        :'<div class="activity-item">';

    var close=url?'</a>':'</div>';

    return open+
        '<span class="activity-icon '+(badge||'')+'">'+
            '<i data-lucide="'+esc(icon||'activity')+'"></i>'+
        '</span>'+
        '<span class="activity-copy">'+
            '<strong>'+esc(title)+'</strong>'+
            '<span>'+esc(sub||'')+'</span>'+
        '</span>'+
        '<span class="activity-time">'+esc(value||'')+'</span>'+
    close;
}

function renderCounts(c){
    c=c||{};

    byId('operationalCounts').innerHTML=
        activityRow(
            'users',
            'Active Customers',
            'Customer Master',
            String(c.customers||0),
            'customer-list.php',
            'blue'
        )+
        activityRow(
            'truck',
            'Active Suppliers',
            'Supplier Master',
            String(c.suppliers||0),
            'supplier-list.php',
            'orange'
        )+
        activityRow(
            'package',
            'Active Products',
            'Product Master',
            String(c.products||0),
            'product-list.php',
            'teal'
        )+
        activityRow(
            'clipboard-list',
            'Pending Orders',
            'Not fully delivered',
            String(c.pending_orders||0),
            'sales-list.php',
            'orange'
        )+
        activityRow(
            'route',
            'Active Truck Trips',
            'Loaded / In Route',
            String(c.active_trips||0),
            'line-supply-list.php',
            'blue'
        )+
        activityRow(
            'circle-check-big',
            'Returned Trips',
            'Pending Close',
            String(c.returned_pending_close||0),
            'line-supply-list.php',
            'green'
        );

    icons();
}

function renderTrips(rows){
    rows=rows||[];
    var root=byId('activeTripsList');

    if(!rows.length){
        root.innerHTML=
            '<div class="empty-state" style="display:block">No Active Truck Trips.</div>';
        return;
    }

    root.innerHTML=rows.map(function(x){
        var sub=
            x.vehicle_no+
            ' · '+x.line_name+
            ' · Loaded '+qty(x.loaded_qty)+
            ' · Sold '+qty(x.sold_qty)+
            ' · Balance '+qty(x.balance_qty);

        return activityRow(
            'truck',
            x.supply_no+' · '+x.status_label,
            sub,
            x.supply_date,
            x.url,
            'blue'
        );
    }).join('');

    icons();
}

function renderOutstanding(rows){
    rows=rows||[];
    var root=byId('outstandingCustomersList');

    if(!rows.length){
        root.innerHTML=
            '<div class="empty-state" style="display:block">No Customer Outstanding.</div>';
        return;
    }

    root.innerHTML=rows.map(function(x){
        return activityRow(
            'user-round',
            x.customer_name,
            x.customer_code||'',
            money(x.outstanding),
            '',
            'orange'
        );
    }).join('');

    icons();
}

function renderActivity(rows){
    rows=rows||[];
    var root=byId('recentActivityList');

    if(!rows.length){
        root.innerHTML=
            '<div class="empty-state" style="display:block">No Recent Activity.</div>';
        return;
    }

    root.innerHTML=rows.map(function(x){
        return activityRow(
            x.icon||'activity',
            x.title+' · '+x.ref_no,
            x.date,
            n(x.amount)>0?money(x.amount):'',
            x.url||'',
            x.type==='sale'
                ?'green'
                :(x.type==='purchase'
                    ?'orange'
                    :(x.type==='expense'?'low':'blue'))
        );
    }).join('');

    icons();
}

function renderStockWarnings(rows){
    rows=rows||[];
    var root=byId('stockWarningsList');

    if(!rows.length){
        root.innerHTML=
            '<div class="empty-state" style="display:block">No Zero / Negative Stock Products.</div>';
        return;
    }

    root.innerHTML=rows.map(function(x){
        return activityRow(
            'triangle-alert',
            x.product_name,
            x.product_code||'',
            qty(x.stock_qty),
            'stock-movement-report.php',
            'low'
        );
    }).join('');

    icons();
}

function greeting(name){
    var hour=new Date().getHours();
    var text=
        hour<12
            ?'Good morning'
            :(hour<17?'Good afternoon':'Good evening');

    return text+(name?' '+name:'');
}

async function loadDashboard(){
    var button=byId('refreshDashboard');
    button.disabled=true;

    try{
        var result=await App.api('api/dashboard.php');
        var data=result.data||{};

        byId('dashboardGreeting').textContent=
            greeting(data.user&&data.user.name);

        renderKpis(data);
        renderCounts(data.counts);
        createCharts(data.charts||{});
        renderTrips(data.active_trips);
        renderOutstanding(data.top_outstanding_customers);
        renderActivity(data.recent_activity);
        renderStockWarnings(data.stock_warnings);

        byId('lastUpdated').textContent=
            'Updated '+(data.generated_at||'now');

        icons();

    }catch(error){

        reportError(
            error,
            'Unable to load Dashboard.'
        );

        byId('lastUpdated').textContent=
            'Unable to refresh';

    }finally{
        button.disabled=false;
    }
}

byId('refreshDashboard')
    .addEventListener('click',loadDashboard);

loadDashboard();
icons();

})(window,document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>

</main>
</div>
</body>
</html>
