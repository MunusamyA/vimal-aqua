<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);

function pl_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Profit & Loss Report is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
    ];
}

function pl_date($value, string $field): string
{
    $value = trim((string)$value);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        json_error('Invalid report date.', 422, [$field=>'Enter a valid date.']);
    }
    return $date->format('Y-m-d');
}

function pl_one(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: [];
}

function pl_num(array $row, string $key): float
{
    return round((float)($row[$key] ?? 0), 2);
}

function pl_sales(PDO $pdo, int $branchId, string $from, string $to): array
{
    $row = pl_one(
        $pdo,
        'SELECT COUNT(*) sale_count,
                COALESCE(SUM(subtotal),0) gross_sales,
                COALESCE(SUM(item_discount_total),0) item_discount,
                COALESCE(SUM(overall_discount_amount),0) overall_discount,
                COALESCE(SUM(tax_amount),0) tax_amount,
                COALESCE(SUM(other_charges),0) other_charges,
                COALESCE(SUM(round_off),0) round_off,
                COALESCE(SUM(grand_total),0) grand_total
         FROM sales
         WHERE branch_id=:branch_id
           AND document_type=2
           AND status=2
           AND sale_date BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );

    $discountRow = pl_one(
        $pdo,
        'SELECT COALESCE(SUM(discount_amount),0) discount_amount
         FROM customer_payments
         WHERE branch_id=:branch_id
           AND status=1
           AND payment_date BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );

    $gross = pl_num($row, 'gross_sales');
    $itemDiscount = pl_num($row, 'item_discount');
    $overallDiscount = pl_num($row, 'overall_discount');
    $otherCharges = pl_num($row, 'other_charges');
    $roundOff = pl_num($row, 'round_off');
    $settlementDiscount = pl_num($discountRow, 'discount_amount');

    return [
        'sale_count'=>(int)($row['sale_count'] ?? 0),
        'gross_sales'=>$gross,
        'item_discount'=>$itemDiscount,
        'overall_discount'=>$overallDiscount,
        'customer_settlement_discount'=>$settlementDiscount,
        'tax_amount'=>pl_num($row, 'tax_amount'),
        'other_charges'=>$otherCharges,
        'round_off'=>$roundOff,
        'grand_total'=>pl_num($row, 'grand_total'),
        'net_sales'=>round($gross - $itemDiscount - $overallDiscount - $settlementDiscount + $otherCharges + $roundOff, 2),
    ];
}

function pl_purchases(PDO $pdo, int $branchId, string $from, string $to): array
{
    $row = pl_one(
        $pdo,
        'SELECT COUNT(*) purchase_count,
                COALESCE(SUM(subtotal),0) gross_purchases,
                COALESCE(SUM(item_discount_total),0) item_discount,
                COALESCE(SUM(overall_discount_amount),0) overall_discount,
                COALESCE(SUM(tax_amount),0) tax_amount,
                COALESCE(SUM(other_charges),0) other_charges,
                COALESCE(SUM(round_off),0) round_off,
                COALESCE(SUM(grand_total),0) grand_total
         FROM purchases
         WHERE branch_id=:branch_id
           AND status=2
           AND purchase_date BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );

    $discountRow = pl_one(
        $pdo,
        'SELECT COALESCE(SUM(spa.discount_amount),0) discount_amount
         FROM supplier_payment_allocations spa
         INNER JOIN supplier_payments sp
            ON sp.id=spa.supplier_payment_id
           AND sp.branch_id=:branch_id
           AND sp.status=1
         WHERE spa.allocation_type=1
           AND sp.payment_date BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );

    $gross = pl_num($row, 'gross_purchases');
    $itemDiscount = pl_num($row, 'item_discount');
    $overallDiscount = pl_num($row, 'overall_discount');
    $otherCharges = pl_num($row, 'other_charges');
    $roundOff = pl_num($row, 'round_off');
    $supplierDiscount = pl_num($discountRow, 'discount_amount');

    return [
        'purchase_count'=>(int)($row['purchase_count'] ?? 0),
        'gross_purchases'=>$gross,
        'item_discount'=>$itemDiscount,
        'overall_discount'=>$overallDiscount,
        'supplier_settlement_discount'=>$supplierDiscount,
        'tax_amount'=>pl_num($row, 'tax_amount'),
        'other_charges'=>$otherCharges,
        'round_off'=>$roundOff,
        'grand_total'=>pl_num($row, 'grand_total'),
        'net_purchases'=>round($gross - $itemDiscount - $overallDiscount - $supplierDiscount + $otherCharges + $roundOff, 2),
    ];
}

function pl_product_costs(PDO $pdo, int $branchId, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT p.id product_id,p.product_code,p.product_name,p.purchase_price,
                COALESCE(SUM(si.base_qty),0) sold_base_qty,
                COALESCE(SUM(si.base_qty * p.purchase_price),0) cogs
         FROM sales s
         INNER JOIN sales_items si ON si.sale_id=s.id
         INNER JOIN products p ON p.id=si.product_id AND p.branch_id=s.branch_id
         WHERE s.branch_id=:branch_id
           AND s.document_type=2
           AND s.status=2
           AND s.sale_date BETWEEN :from_date AND :to_date
         GROUP BY p.id,p.product_code,p.product_name,p.purchase_price
         ORDER BY cogs DESC,p.product_name'
    );
    $stmt->execute([':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['product_id'] = (int)$row['product_id'];
        $row['purchase_price'] = round((float)$row['purchase_price'], 2);
        $row['sold_base_qty'] = round((float)$row['sold_base_qty'], 3);
        $row['cogs'] = round((float)$row['cogs'], 2);
    }
    unset($row);
    return $rows;
}

function pl_expenses(PDO $pdo, int $branchId, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT expense_name,COALESCE(SUM(amount),0) amount
         FROM expenses
         WHERE branch_id=:branch_id
           AND status=1
           AND expense_date BETWEEN :from_date AND :to_date
         GROUP BY expense_name
         ORDER BY amount DESC,expense_name'
    );
    $stmt->execute([':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['amount'] = round((float)$row['amount'], 2);
    }
    unset($row);
    return $rows;
}

function pl_production_consumption(PDO $pdo, int $branchId, string $from, string $to): float
{
    $row = pl_one(
        $pdo,
        'SELECT COALESCE(SUM(pm.base_qty * p.purchase_price),0) amount
         FROM production pr
         INNER JOIN production_materials pm ON pm.production_id=pr.id
         INNER JOIN products p ON p.id=pm.product_id AND p.branch_id=pr.branch_id
         WHERE pr.branch_id=:branch_id
           AND pr.status=2
           AND pr.production_date BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );
    return pl_num($row, 'amount');
}

function pl_stock_loss(PDO $pdo, int $branchId, string $from, string $to): float
{
    $plant = pl_one(
        $pdo,
        'SELECT COALESCE(SUM(sm.quantity_out * p.purchase_price),0) amount
         FROM stock_movements sm
         INNER JOIN products p ON p.id=sm.product_id AND p.branch_id=sm.branch_id
         WHERE sm.branch_id=:branch_id
           AND sm.movement_type=6
           AND DATE(sm.movement_date) BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );

    $vehicle = pl_one(
        $pdo,
        'SELECT COALESCE(SUM(vsm.quantity_out * p.purchase_price),0) amount
         FROM vehicle_stock_movements vsm
         INNER JOIN products p ON p.id=vsm.product_id AND p.branch_id=vsm.branch_id
         WHERE vsm.branch_id=:branch_id
           AND vsm.movement_type IN (4,6)
           AND DATE(vsm.movement_date) BETWEEN :from_date AND :to_date',
        [':branch_id'=>$branchId, ':from_date'=>$from, ':to_date'=>$to]
    );

    return round(pl_num($plant, 'amount') + pl_num($vehicle, 'amount'), 2);
}

$method = request_method();
if ($method !== 'GET') {
    json_error('Method not allowed.', 405);
}

/*
 * The Profit & Loss menu can be added later with its own permission row.
 * Until then, use the existing Purchase Report view permission so this report
 * works immediately in the current database without introducing extra schema.
 */
$access = require_permission('purchase-report.php', ACTION_VIEW);
$context = pl_context($access['user']);
$branchId = (int)$context['branch_id'];

$from = isset($_GET['from']) && trim((string)$_GET['from']) !== ''
    ? pl_date($_GET['from'], 'from')
    : date('Y-m-01');
$to = isset($_GET['to']) && trim((string)$_GET['to']) !== ''
    ? pl_date($_GET['to'], 'to')
    : date('Y-m-d');

if ($from > $to) {
    json_error('From Date cannot be after To Date.', 422, ['from'=>'Select a valid date range.']);
}

$pdo = db();
$sales = pl_sales($pdo, $branchId, $from, $to);
$purchases = pl_purchases($pdo, $branchId, $from, $to);
$productCosts = pl_product_costs($pdo, $branchId, $from, $to);
$expenseBreakdown = pl_expenses($pdo, $branchId, $from, $to);

$cogs = round(array_sum(array_map(static fn(array $row): float => (float)$row['cogs'], $productCosts)), 2);
$expenses = round(array_sum(array_map(static fn(array $row): float => (float)$row['amount'], $expenseBreakdown)), 2);
$stockLoss = pl_stock_loss($pdo, $branchId, $from, $to);
$productionConsumption = pl_production_consumption($pdo, $branchId, $from, $to);
$grossProfit = round((float)$sales['net_sales'] - $cogs, 2);
$netProfit = round($grossProfit - $expenses - $stockLoss + (float)$purchases['supplier_settlement_discount'], 2);

json_success('Profit & Loss Report loaded.', [
    'from_date'=>$from,
    'to_date'=>$to,
    'company_name'=>$context['company_name'],
    'branch_name'=>$context['branch_name'],
    'sales'=>$sales,
    'purchases'=>$purchases,
    'summary'=>[
        'sales_count'=>(int)$sales['sale_count'],
        'net_sales'=>(float)$sales['net_sales'],
        'cogs'=>$cogs,
        'gross_profit'=>$grossProfit,
        'expenses'=>$expenses,
        'stock_loss'=>$stockLoss,
        'supplier_settlement_discount'=>(float)$purchases['supplier_settlement_discount'],
        'production_material_consumption'=>$productionConsumption,
        'net_profit'=>$netProfit,
    ],
    'expense_breakdown'=>$expenseBreakdown,
    'product_cost_breakdown'=>$productCosts,
    'allowed_actions'=>$access['actions'],
    'cost_note'=>'COGS uses sales_items.base_qty × products.purchase_price because the current database does not store a historical cost snapshot on each sales item.',
]);
