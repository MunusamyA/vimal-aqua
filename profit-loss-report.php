<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Profit & Loss Report';
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
<section class="page-content" id="profitLossApp">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>

<div class="page-heading">
    <div>
        <h1>Profit & Loss Report</h1>
        <p>Sales, purchase activity, cost of goods sold, expenses and profit for the selected period.</p>
    </div>
    <div class="heading-actions">
        <button class="btn gray" id="printButton" type="button"><i data-lucide="printer"></i>Print</button>
        <button class="btn btn-primary" id="refreshButton" type="button"><i data-lucide="refresh-cw"></i>Refresh</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-4">
                <label for="fromDate">From Date</label>
                <input class="input" id="fromDate" type="date">
            </div>
            <div class="field col-4">
                <label for="toDate">To Date</label>
                <input class="input" id="toDate" type="date">
            </div>
            <div class="field col-4">
                <label>&nbsp;</label>
                <button class="btn btn-primary" id="applyButton" type="button"><i data-lucide="filter"></i>Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="indian-rupee"></i></div>
        <div>
            <div class="kpi-label">Net Sales</div>
            <div class="kpi-value" id="kpiNetSales">₹0.00</div>
            <div class="kpi-meta" id="kpiSalesMeta">0 invoices</div>
        </div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="package-search"></i></div>
        <div>
            <div class="kpi-label">COGS</div>
            <div class="kpi-value" id="kpiCogs">₹0.00</div>
            <div class="kpi-meta">Sold quantity × product cost</div>
        </div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="trending-up"></i></div>
        <div>
            <div class="kpi-label">Gross Profit</div>
            <div class="kpi-value" id="kpiGrossProfit">₹0.00</div>
            <div class="kpi-meta">Net Sales − COGS</div>
        </div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="badge-indian-rupee"></i></div>
        <div>
            <div class="kpi-label">Net Profit / Loss</div>
            <div class="kpi-value" id="kpiNetProfit">₹0.00</div>
            <div class="kpi-meta" id="kpiProfitMeta">After expenses and stock loss</div>
        </div>
    </div>
</div>

<div class="app-split-grid">
    <div class="card">
        <div class="card-header">
            <div>
                <h2>Profit & Loss Statement</h2>
                <p class="muted" id="periodLabel">Selected period</p>
            </div>
        </div>
        <div class="card-body">
            <div class="app-summary-row"><span>Gross Sales</span><strong id="grossSales">₹0.00</strong></div>
            <div class="app-summary-row"><span>Less: Item Discount</span><strong id="salesItemDiscount">₹0.00</strong></div>
            <div class="app-summary-row"><span>Less: Overall Discount</span><strong id="salesOverallDiscount">₹0.00</strong></div>
            <div class="app-summary-row"><span>Less: Settlement Discount</span><strong id="customerSettlementDiscount">₹0.00</strong></div>
            <div class="app-summary-row"><span>Add: Other Charges</span><strong id="salesOtherCharges">₹0.00</strong></div>
            <div class="app-summary-row"><span>Round Off</span><strong id="salesRoundOff">₹0.00</strong></div>
            <div class="app-summary-row total"><span>Net Sales (Excl. GST)</span><strong id="netSales">₹0.00</strong></div>

            <div class="app-total-group">
                <div class="app-summary-row"><span>Cost of Goods Sold</span><strong id="cogs">₹0.00</strong></div>
                <div class="app-summary-row total"><span>Gross Profit</span><strong id="grossProfit">₹0.00</strong></div>
            </div>

            <div class="app-total-group">
                <div class="app-summary-row"><span>Operating Expenses</span><strong id="expenses">₹0.00</strong></div>
                <div class="app-summary-row"><span>Stock Loss / Adjustment Out</span><strong id="stockLoss">₹0.00</strong></div>
                <div class="app-summary-row"><span>Add: Supplier Settlement Discount</span><strong id="supplierSettlementIncome">₹0.00</strong></div>
                <div class="app-summary-row total"><span>Net Profit / Loss</span><strong id="netProfit">₹0.00</strong></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Purchase & Inventory Activity</h2>
                <p class="muted">Purchase activity is shown separately and is not deducted twice from COGS.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="app-summary-row"><span>Gross Purchases</span><strong id="grossPurchases">₹0.00</strong></div>
            <div class="app-summary-row"><span>Less: Item Discount</span><strong id="purchaseItemDiscount">₹0.00</strong></div>
            <div class="app-summary-row"><span>Less: Overall Discount</span><strong id="purchaseOverallDiscount">₹0.00</strong></div>
            <div class="app-summary-row"><span>Less: Supplier Settlement Discount</span><strong id="supplierSettlementDiscount">₹0.00</strong></div>
            <div class="app-summary-row"><span>Add: Other Charges</span><strong id="purchaseOtherCharges">₹0.00</strong></div>
            <div class="app-summary-row"><span>Round Off</span><strong id="purchaseRoundOff">₹0.00</strong></div>
            <div class="app-summary-row total"><span>Net Purchase Activity (Excl. GST)</span><strong id="netPurchases">₹0.00</strong></div>

            <div class="app-total-group">
                <div class="app-summary-row"><span>Purchase GST</span><strong id="purchaseTax">₹0.00</strong></div>
                <div class="app-summary-row"><span>Sales GST</span><strong id="salesTax">₹0.00</strong></div>
                <div class="app-summary-row"><span>Production Material Consumption</span><strong id="productionConsumption">₹0.00</strong></div>
                <div class="app-summary-row"><span>Posted Purchases</span><strong id="purchaseCount">0</strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div>
            <h2>Expense Breakdown</h2>
            <p class="muted">Active expenses within the selected period.</p>
        </div>
    </div>
    <div class="app-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Expense</th>
                    <th class="dt-body-right">Amount</th>
                </tr>
            </thead>
            <tbody id="expenseBody">
                <tr><td colspan="3" class="empty">No expenses.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div>
            <h2>Product Cost Breakdown</h2>
            <p class="muted">Posted invoice quantity multiplied by the Product Master purchase cost.</p>
        </div>
    </div>
    <div class="app-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th class="dt-body-right">Sold Base Qty</th>
                    <th class="dt-body-right">Cost / Base Unit</th>
                    <th class="dt-body-right">COGS</th>
                </tr>
            </thead>
            <tbody id="productCostBody">
                <tr><td colspan="5" class="empty">No posted invoice items.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function (window, document) {
    "use strict";

    function byId(id) { return document.getElementById(id); }
    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function money(value) {
        return "₹" + Number(value || 0).toLocaleString("en-IN", {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    function qty(value) {
        return Number(value || 0).toLocaleString("en-IN", {minimumFractionDigits:3, maximumFractionDigits:3});
    }
    function isoDate(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, "0");
        var d = String(date.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + d;
    }
    function setDefaultDates() {
        var today = new Date();
        var first = new Date(today.getFullYear(), today.getMonth(), 1);
        if (!byId("fromDate").value) byId("fromDate").value = isoDate(first);
        if (!byId("toDate").value) byId("toDate").value = isoDate(today);
    }
    function putMoney(id, value) { byId(id).textContent = money(value); }

    function renderExpenses(rows) {
        var body = byId("expenseBody");
        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="3" class="empty">No expenses.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (row, index) {
            return '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + esc(row.expense_name) + '</td>' +
                '<td class="dt-body-right"><strong>' + money(row.amount) + '</strong></td>' +
                '</tr>';
        }).join("");
    }

    function renderProductCosts(rows) {
        var body = byId("productCostBody");
        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="5" class="empty">No posted invoice items.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (row, index) {
            return '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + esc((row.product_code ? row.product_code + " - " : "") + row.product_name) + '</td>' +
                '<td class="dt-body-right">' + qty(row.sold_base_qty) + '</td>' +
                '<td class="dt-body-right">' + money(row.purchase_price) + '</td>' +
                '<td class="dt-body-right"><strong>' + money(row.cogs) + '</strong></td>' +
                '</tr>';
        }).join("");
    }

    function render(data) {
        var s = data.summary || {};
        var sales = data.sales || {};
        var purchase = data.purchases || {};

        putMoney("kpiNetSales", s.net_sales);
        putMoney("kpiCogs", s.cogs);
        putMoney("kpiGrossProfit", s.gross_profit);
        putMoney("kpiNetProfit", s.net_profit);
        byId("kpiSalesMeta").textContent = Number(s.sales_count || 0) + " invoices";
        byId("kpiProfitMeta").textContent = Number(s.net_profit || 0) >= 0 ? "Profit for selected period" : "Loss for selected period";

        putMoney("grossSales", sales.gross_sales);
        putMoney("salesItemDiscount", sales.item_discount);
        putMoney("salesOverallDiscount", sales.overall_discount);
        putMoney("customerSettlementDiscount", sales.customer_settlement_discount);
        putMoney("salesOtherCharges", sales.other_charges);
        putMoney("salesRoundOff", sales.round_off);
        putMoney("netSales", s.net_sales);
        putMoney("cogs", s.cogs);
        putMoney("grossProfit", s.gross_profit);
        putMoney("expenses", s.expenses);
        putMoney("stockLoss", s.stock_loss);
        putMoney("supplierSettlementIncome", s.supplier_settlement_discount);
        putMoney("netProfit", s.net_profit);

        putMoney("grossPurchases", purchase.gross_purchases);
        putMoney("purchaseItemDiscount", purchase.item_discount);
        putMoney("purchaseOverallDiscount", purchase.overall_discount);
        putMoney("supplierSettlementDiscount", purchase.supplier_settlement_discount);
        putMoney("purchaseOtherCharges", purchase.other_charges);
        putMoney("purchaseRoundOff", purchase.round_off);
        putMoney("netPurchases", purchase.net_purchases);
        putMoney("purchaseTax", purchase.tax_amount);
        putMoney("salesTax", sales.tax_amount);
        putMoney("productionConsumption", s.production_material_consumption);
        byId("purchaseCount").textContent = Number(purchase.purchase_count || 0).toLocaleString("en-IN");

        byId("periodLabel").textContent = (data.from_date || "") + " to " + (data.to_date || "");
        renderExpenses(data.expense_breakdown || []);
        renderProductCosts(data.product_cost_breakdown || []);

        if (window.lucide) lucide.createIcons();
    }

    async function loadReport() {
        var from = byId("fromDate").value;
        var to = byId("toDate").value;
        if (!from || !to) {
            if (window.showToast) showToast("Select From Date and To Date.", "warning");
            return;
        }
        if (from > to) {
            if (window.showToast) showToast("From Date cannot be after To Date.", "warning");
            return;
        }
        var query = new URLSearchParams({from:from, to:to});
        byId("applyButton").disabled = true;
        byId("refreshButton").disabled = true;
        try {
            var response = await App.api("api/profit-loss-report.php?" + query.toString());
            render(response.data || {});
        } catch (error) {
            App.showError(error, "Unable to load Profit & Loss Report.");
        } finally {
            byId("applyButton").disabled = false;
            byId("refreshButton").disabled = false;
        }
    }

    setDefaultDates();
    byId("applyButton").addEventListener("click", loadReport);
    byId("refreshButton").addEventListener("click", loadReport);
    byId("printButton").addEventListener("click", function () { window.print(); });
    byId("fromDate").addEventListener("change", loadReport);
    byId("toDate").addEventListener("change", loadReport);
    document.addEventListener("DOMContentLoaded", function () {
        if (window.lucide) lucide.createIcons();
        loadReport();
    });
})(window, document);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
</body>
</html>
