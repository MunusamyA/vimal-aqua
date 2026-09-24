<?php
declare(strict_types=1);

require_once __DIR__ . '/_sales_common.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);

function customer_ledger_access(): array
{
    // Customer Ledger follows Customer Master view permission until a dedicated menu permission is added.
    return require_permission('customer-list.php', ACTION_VIEW);
}

function customer_ledger_date(?string $value, string $field, string $fallback): string
{
    $value = trim((string)$value);
    if ($value === '') return $fallback;
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        json_error('Enter a valid ' . $field . '.', 422, [$field => 'Enter a valid date.']);
    }
    return $value;
}

function customer_ledger_customers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.opening_balance,c.line_id,l.line_name
         FROM customers c
         LEFT JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
         WHERE c.branch_id=:branch_id AND c.status=1
         ORDER BY c.customer_name,c.customer_code'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'ref'=>encryptReference('customer', (int)$row['id']),
            'customer_code'=>(string)$row['customer_code'],
            'customer_name'=>(string)$row['customer_name'],
            'mobile'=>(string)($row['mobile'] ?? ''),
            'opening_balance'=>(float)$row['opening_balance'],
            'line_name'=>(string)($row['line_name'] ?? ''),
        ];
    }
    return $rows;
}

function customer_ledger_customer(PDO $pdo, int $branchId, int $customerId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.address,c.opening_balance,c.opening_can_balance,
                c.credit_limit,c.line_id,l.line_name
         FROM customers c
         LEFT JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
         WHERE c.id=:id AND c.branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id'=>$customerId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Customer was not found.', 404);
    return [
        'ref'=>encryptReference('customer', (int)$row['id']),
        'customer_code'=>(string)$row['customer_code'],
        'customer_name'=>(string)$row['customer_name'],
        'mobile'=>(string)($row['mobile'] ?? ''),
        'address'=>(string)($row['address'] ?? ''),
        'line_name'=>(string)($row['line_name'] ?? ''),
        'opening_balance'=>(float)$row['opening_balance'],
        'opening_can_balance'=>(float)$row['opening_can_balance'],
        'credit_limit'=>(float)$row['credit_limit'],
    ];
}



/**
 * Customer Can Summary
 * Tracks reusable can movement from Sales.
 */
function customer_ledger_can_summary(
    PDO $pdo,
    int $branchId,
    int $customerId,
    string $fromDate,
    string $toDate
): array {

    $stmt = $pdo->prepare(
        "
        SELECT
            COALESCE(SUM(
                CASE 
                    WHEN movement_type = 1 THEN qty
                    ELSE 0
                END
            ),0) AS delivered_can,

            COALESCE(SUM(
                CASE 
                    WHEN movement_type = 2 THEN qty
                    ELSE 0
                END
            ),0) AS returned_can,

            COALESCE(SUM(
                CASE 
                    WHEN movement_type = 3 THEN qty
                    ELSE 0
                END
            ),0) AS damaged_can

        FROM can_movements

        WHERE branch_id = :branch_id
        AND customer_id = :customer_id
        AND DATE(movement_date) BETWEEN :from_date AND :to_date
        "
    );


    $stmt->execute([
        ':branch_id'=>$branchId,
        ':customer_id'=>$customerId,
        ':from_date'=>$fromDate,
        ':to_date'=>$toDate
    ]);


    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];


    $customer = customer_ledger_customer(
        $pdo,
        $branchId,
        $customerId
    );


    $opening = round(
        (float)$customer['opening_can_balance'],
        3
    );


    $delivered = round(
        (float)($row['delivered_can'] ?? 0),
        3
    );


    $returned = round(
        (float)($row['returned_can'] ?? 0),
        3
    );


    $damaged = round(
        (float)($row['damaged_can'] ?? 0),
        3
    );


    return [

        'opening_can'=>$opening,

        'delivered_can'=>$delivered,

        'returned_can'=>$returned,

        'damaged_can'=>$damaged,

        'pending_can'=>round(
            $opening
            +
            $delivered
            -
            $returned
            -
            $damaged,
            3
        )
    ];
}
function customer_ledger_balance_before(PDO $pdo, int $branchId, int $customerId, string $fromDate): array
{
    $customer = customer_ledger_customer($pdo, $branchId, $customerId);
    $opening = max(0, round((float)$customer['opening_balance'], 2));

    $invoiceStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(grand_total),0)
         FROM sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=2 AND status=2 AND sale_date<:from_date'
    );
    $invoiceStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId, ':from_date'=>$fromDate]);
    $invoiceDebit = round((float)$invoiceStmt->fetchColumn(), 2);

    $paymentStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) AS receipts,COALESCE(SUM(discount_amount),0) AS discounts
         FROM customer_payments
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND status=1 AND payment_date<:from_date'
    );
    $paymentStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId, ':from_date'=>$fromDate]);
    $payment = $paymentStmt->fetch() ?: ['receipts'=>0,'discounts'=>0];
    $receiptCredit = round((float)$payment['receipts'], 2);
    $discountCredit = round((float)$payment['discounts'], 2);

    return [
        'opening_master'=>$opening,
        'invoice_debit'=>$invoiceDebit,
        'receipt_credit'=>$receiptCredit,
        'discount_credit'=>$discountCredit,
        'balance'=>round($opening + $invoiceDebit - $receiptCredit - $discountCredit, 2),
    ];
}

function customer_ledger_period_rows(PDO $pdo, int $branchId, int $customerId, string $fromDate, string $toDate): array
{
    $rows = [];

    $invoiceStmt = $pdo->prepare(
        'SELECT id,sale_no,sale_date,grand_total,remarks,created_at
         FROM sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=2 AND status=2
           AND sale_date BETWEEN :from_date AND :to_date
         ORDER BY sale_date,created_at,id'
    );
    $invoiceStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId, ':from_date'=>$fromDate, ':to_date'=>$toDate]);
    foreach ($invoiceStmt->fetchAll() as $row) {
        $rows[] = [
            'sort_date'=>(string)$row['sale_date'],
            'sort_time'=>(string)$row['created_at'],
            'sort_type'=>1,
            'sort_id'=>(int)$row['id'],
            'date'=>(string)$row['sale_date'],
            'type'=>'Sales Invoice',
            'reference'=>(string)$row['sale_no'],
            'reference_ref'=>encryptReference('sale', (int)$row['id']),
            'reference_url'=>'sales-form.php?ref=' . rawurlencode(encryptReference('sale', (int)$row['id'])),
            'debit'=>round((float)$row['grand_total'], 2),
            'credit'=>0.0,
            'remarks'=>(string)($row['remarks'] ?? ''),
        ];
    }

    $paymentStmt = $pdo->prepare(
        'SELECT id,payment_no,payment_date,amount,discount_amount,remarks,created_at
         FROM customer_payments
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND status=1 AND payment_date BETWEEN :from_date AND :to_date
         ORDER BY payment_date,created_at,id'
    );
    $paymentStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId, ':from_date'=>$fromDate, ':to_date'=>$toDate]);
    foreach ($paymentStmt->fetchAll() as $row) {
        $paymentRef = encryptReference('customer_payment', (int)$row['id']);
        $amount = round((float)$row['amount'], 2);
        $discount = round((float)$row['discount_amount'], 2);
        if ($amount > 0.009) {
            $rows[] = [
                'sort_date'=>(string)$row['payment_date'],
                'sort_time'=>(string)$row['created_at'],
                'sort_type'=>2,
                'sort_id'=>(int)$row['id'],
                'date'=>(string)$row['payment_date'],
                'type'=>'Customer Payment',
                'reference'=>(string)$row['payment_no'],
                'reference_ref'=>$paymentRef,
                'reference_url'=>'customer-payment-form.php?ref=' . rawurlencode($paymentRef),
                'debit'=>0.0,
                'credit'=>$amount,
                'remarks'=>(string)($row['remarks'] ?? ''),
            ];
        }
        if ($discount > 0.009) {
            $rows[] = [
                'sort_date'=>(string)$row['payment_date'],
                'sort_time'=>(string)$row['created_at'],
                'sort_type'=>3,
                'sort_id'=>(int)$row['id'],
                'date'=>(string)$row['payment_date'],
                'type'=>'Settlement Discount',
                'reference'=>(string)$row['payment_no'],
                'reference_ref'=>$paymentRef,
                'reference_url'=>'customer-payment-form.php?ref=' . rawurlencode($paymentRef),
                'debit'=>0.0,
                'credit'=>$discount,
                'remarks'=>'Customer payment settlement discount',
            ];
        }
    }

    usort($rows, static function(array $a, array $b): int {
        return [$a['sort_date'],$a['sort_time'],$a['sort_type'],$a['sort_id']]
            <=> [$b['sort_date'],$b['sort_time'],$b['sort_type'],$b['sort_id']];
    });

    foreach ($rows as &$row) {
        unset($row['sort_date'],$row['sort_time'],$row['sort_type'],$row['sort_id']);
    }
    unset($row);
    return $rows;
}

function customer_ledger_current_position(PDO $pdo, int $branchId, int $customerId): array
{
    $customer = customer_ledger_customer($pdo, $branchId, $customerId);
    $opening = max(0, round((float)$customer['opening_balance'], 2));

    $invoiceStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(grand_total),0),COALESCE(SUM(balance_amount),0)
         FROM sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=2 AND status=2'
    );
    $invoiceStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    $invoice = $invoiceStmt->fetch(PDO::FETCH_NUM) ?: [0,0];

    $paymentStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0),COALESCE(SUM(discount_amount),0)
         FROM customer_payments
         WHERE branch_id=:branch_id AND customer_id=:customer_id AND status=1'
    );
    $paymentStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_NUM) ?: [0,0];

    $ledgerBalance = round($opening + (float)$invoice[0] - (float)$payment[0] - (float)$payment[1], 2);
    return [
        'opening_balance'=>$opening,
        'invoice_total'=>round((float)$invoice[0], 2),
        'invoice_outstanding'=>round((float)$invoice[1], 2),
        'payments'=>round((float)$payment[0], 2),
        'settlement_discount'=>round((float)$payment[1], 2),
        'ledger_balance'=>$ledgerBalance,
        'due'=>max(0, $ledgerBalance),
        'advance'=>max(0, -$ledgerBalance),
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') json_error('Unsupported Customer Ledger API request.', 405);

$access = customer_ledger_access();
$context = aqua_sales_context($access['user']);
$branchId = (int)$context['branch_id'];

if (isset($_GET['options'])) {
    json_success('Customer Ledger options loaded.', [
        'allowed_actions'=>$access['actions'],
        'customers'=>customer_ledger_customers($branchId),
        'today'=>date('Y-m-d'),
    ]);
}

$customerRef = trim((string)($_GET['customer_ref'] ?? ''));
if ($customerRef === '') json_error('Select Customer.', 422, ['customer_ref'=>'Select Customer.']);
$customerId = aqua_ref_to_id($customerRef, 'customer', 'Customer');
$customer = customer_ledger_customer(db(), $branchId, $customerId);

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$fromDate = customer_ledger_date($_GET['from_date'] ?? '', 'from_date', $monthStart);
$toDate = customer_ledger_date($_GET['to_date'] ?? '', 'to_date', $today);
if ($fromDate > $toDate) json_error('From Date cannot be after To Date.', 422);

$opening = customer_ledger_balance_before(db(), $branchId, $customerId, $fromDate);
$rows = customer_ledger_period_rows(db(), $branchId, $customerId, $fromDate, $toDate);
$running = round((float)$opening['balance'], 2);
$periodDebit = 0.0;
$periodCredit = 0.0;
foreach ($rows as &$row) {
    $periodDebit = round($periodDebit + (float)$row['debit'], 2);
    $periodCredit = round($periodCredit + (float)$row['credit'], 2);
    $running = round($running + (float)$row['debit'] - (float)$row['credit'], 2);
    $row['balance'] = $running;
    $row['balance_type'] = $running > 0.009 ? 'Due' : ($running < -0.009 ? 'Advance' : 'Settled');
}
unset($row);

$current = customer_ledger_current_position(db(), $branchId, $customerId);
json_success('Customer Ledger loaded.', [
    'allowed_actions'=>$access['actions'],
    'customer'=>$customer,
    'period'=>['from_date'=>$fromDate,'to_date'=>$toDate],
    'opening_bf'=>round((float)$opening['balance'], 2),
    'period_debit'=>$periodDebit,
    'period_credit'=>$periodCredit,
    'closing_balance'=>$running,
    'closing_type'=>$running > 0.009 ? 'Due' : ($running < -0.009 ? 'Advance' : 'Settled'),
    'current'=>$current,
    'can_summary'=>customer_ledger_can_summary(db(), $branchId, $customerId, $fromDate, $toDate),
    'rows'=>$rows,
]);