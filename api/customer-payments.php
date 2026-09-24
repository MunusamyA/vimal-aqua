<?php
declare(strict_types=1);

require_once __DIR__ . '/_sales_common.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_CANCEL')) define('ACTION_CANCEL', 14);
if (!defined('ACTION_RECEIVE_PAYMENT')) define('ACTION_RECEIVE_PAYMENT', 29);

function payment_access(int $action = ACTION_VIEW): array
{
    return require_permission('sales-list.php', $action);
}

function payment_mode_label(int $mode): string
{
    return [1 => 'Cash', 2 => 'UPI', 3 => 'Bank', 4 => 'Cheque'][$mode] ?? 'Payment';
}

function payment_account_type_for_mode(int $mode): int
{
    if ($mode === 1) return 1; // Cash
    if ($mode === 2) return 3; // UPI
    return 2; // Bank / Cheque
}

function payment_nullable($value, int $max = 255): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

function payment_discount_type_label(int $type): string
{
    return [1 => 'None', 2 => 'Percentage', 3 => 'Amount'][$type] ?? 'None';
}

function payment_ensure_discount_schema(): void
{
    static $done = false;
    if ($done) return;

    $pdo = db();
    $stmt = $pdo->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_payments'"
    );
    $existing = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    $parts = [];
    if (!isset($existing['discount_type'])) {
        $parts[] = "ADD COLUMN `discount_type` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=None, 2=Percentage, 3=Amount' AFTER `amount`";
    }
    if (!isset($existing['discount_value'])) {
        $parts[] = "ADD COLUMN `discount_value` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`";
    }
    if (!isset($existing['discount_amount'])) {
        $parts[] = "ADD COLUMN `discount_amount` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `discount_value`";
    }
    if ($parts) {
        try {
            $pdo->exec('ALTER TABLE `customer_payments` ' . implode(', ', $parts));
        } catch (Throwable $e) {
            json_error('Unable to add Customer Payment discount fields. Please allow ALTER TABLE for this database user.', 500);
        }
    }
    $done = true;
}

function payment_parse_discount(array $data): array
{
    $type = (int)($data['discount_type'] ?? 1);
    if (!in_array($type, [1,2,3], true)) $type = 1;
    $valueText = trim((string)($data['discount_value'] ?? ''));
    $value = $valueText === '' ? 0.0 : aqua_decimal($valueText, 'discount_value', 'Discount Value', 2, false);
    $value = max(0, round($value, 2));

    if ($type === 1) $value = 0.0;
    if ($type === 2 && $value > 100.0) {
        json_error('Discount Percentage cannot be greater than 100.', 422, ['discount_value'=>'Maximum 100%.']);
    }
    return ['type'=>$type, 'value'=>$value];
}

function payment_effective_discount(int $type, float $value, float $currentOutstanding, float $usablePayment): float
{
    $currentOutstanding = max(0, round($currentOutstanding, 2));
    $usablePayment = max(0, round($usablePayment, 2));
    $remainingAfterPayment = max(0, round($currentOutstanding - min($usablePayment, $currentOutstanding), 2));
    if ($remainingAfterPayment <= 0.009 || $type === 1 || $value <= 0.0) return 0.0;

    $raw = $type === 2
        ? round($currentOutstanding * ($value / 100), 2)
        : round($value, 2);
    return max(0, round(min($raw, $remainingAfterPayment), 2));
}

function payment_customers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.opening_balance,c.line_id,l.line_name
         FROM customers c
         LEFT JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
         WHERE c.branch_id=:branch_id AND c.status=1
         ORDER BY c.customer_name,c.customer_code'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['line_id'] = (int)$row['line_id'];
        $row['opening_balance'] = (float)$row['opening_balance'];
    }
    unset($row);
    return $rows;
}

function payment_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_name,account_type
         FROM accounts
         WHERE branch_id=:branch_id AND status=1 AND account_type IN (1,2,3)
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['account_type'] = (int)$row['account_type'];
        $modes = [];
        if ($row['account_type'] === 1) $modes = [1];
        elseif ($row['account_type'] === 3) $modes = [2];
        elseif ($row['account_type'] === 2) $modes = [3,4];
        $row['payment_modes'] = $modes;
    }
    unset($row);
    return $rows;
}

function payment_fetch_header(PDO $pdo, int $branchId, int $paymentId, bool $lock = false): array
{
    $sql = 'SELECT cp.*,c.customer_code,c.customer_name
            FROM customer_payments cp
            INNER JOIN customers c ON c.id=cp.customer_id AND c.branch_id=cp.branch_id
            WHERE cp.id=:id AND cp.branch_id=:branch_id LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id'=>$paymentId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Customer Payment was not found.', 404);
    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['customer_id'] = (int)$row['customer_id'];
    $row['amount'] = (float)$row['amount'];
    $row['discount_type'] = (int)($row['discount_type'] ?? 1);
    $row['discount_value'] = (float)($row['discount_value'] ?? 0);
    $row['discount_amount'] = (float)($row['discount_amount'] ?? 0);
    $row['settlement_amount'] = round($row['amount'] + $row['discount_amount'], 2);
    $row['status'] = (int)$row['status'];
    return $row;
}

function payment_details(PDO $pdo, int $paymentId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,account_id,payment_mode,amount,reference_no,detail_date
         FROM customer_payment_details
         WHERE customer_payment_id=:payment_id
         ORDER BY payment_mode,id'
    );
    $stmt->execute([':payment_id'=>$paymentId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['account_id'] = (int)$row['account_id'];
        $row['payment_mode'] = (int)$row['payment_mode'];
        $row['amount'] = (float)$row['amount'];
    }
    unset($row);
    return $rows;
}

function payment_active_order_reserved_map(PDO $pdo, int $branchId, int $customerId, int $excludePaymentId = 0): array
{
    $sql = 'SELECT cpa.customer_payment_id,COALESCE(SUM(cpa.amount),0) amount
            FROM customer_payment_allocations cpa
            INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id
            INNER JOIN sales s ON s.id=cpa.sale_id
            WHERE cp.branch_id=:branch_id AND cp.customer_id=:customer_id AND cp.status=1
              AND s.branch_id=:branch_id2 AND s.customer_id=:customer_id2
              AND s.document_type=3 AND s.status<>3 AND s.delivery_status IN (1,2)';
    $params = [
        ':branch_id'=>$branchId, ':customer_id'=>$customerId,
        ':branch_id2'=>$branchId, ':customer_id2'=>$customerId,
    ];
    if ($excludePaymentId > 0) {
        $sql .= ' AND cp.id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludePaymentId;
    }
    $sql .= ' GROUP BY cpa.customer_payment_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int)$row['customer_payment_id']] = round((float)$row['amount'], 2);
    }
    return $map;
}

function payment_reserved_amount(PDO $pdo, int $paymentId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(cpa.amount),0)
         FROM customer_payment_allocations cpa
         INNER JOIN sales s ON s.id=cpa.sale_id
         WHERE cpa.customer_payment_id=:payment_id
           AND s.document_type=3 AND s.status<>3 AND s.delivery_status IN (1,2)'
    );
    $stmt->execute([':payment_id'=>$paymentId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function payment_refresh_sale_payment_header(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare('SELECT grand_total,document_type,status FROM sales WHERE id=:id LIMIT 1');
    $stmt->execute([':id'=>$saleId]);
    $sale = $stmt->fetch();
    if (!$sale) return;

    $paidStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(cpa.amount),0)
         FROM customer_payment_allocations cpa
         INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
         WHERE cpa.sale_id=:sale_id'
    );
    $paidStmt->execute([':sale_id'=>$saleId]);
    $paid = round((float)$paidStmt->fetchColumn(), 2);
    $grand = round((float)$sale['grand_total'], 2);
    $paidForHeader = min($grand, max(0, $paid));
    $balance = max(0, round($grand - $paidForHeader, 2));
    $status = $paidForHeader <= 0.009 ? 1 : ($balance <= 0.009 ? 3 : 2);
    $pdo->prepare(
        'UPDATE sales SET paid_amount=:paid,balance_amount=:balance,payment_status=:payment_status,updated_at=NOW() WHERE id=:id'
    )->execute([
        ':paid'=>$paidForHeader,
        ':balance'=>$balance,
        ':payment_status'=>$status,
        ':id'=>$saleId,
    ]);
}

/**
 * Rebuild customer receivables from source data.
 * Opening balance is the oldest receivable. Customer Order advances that are
 * still reserved on pending/partial Orders remain reserved. All posted Invoice
 * allocations are rebuilt FIFO from active Customer Payments.
 */
function payment_recalculate_customer(PDO $pdo, int $branchId, int $customerId): array
{
    $customerStmt = $pdo->prepare(
        'SELECT id,opening_balance FROM customers WHERE id=:id AND branch_id=:branch_id FOR UPDATE'
    );
    $customerStmt->execute([':id'=>$customerId, ':branch_id'=>$branchId]);
    $customer = $customerStmt->fetch();
    if (!$customer) json_error('Customer was not found.', 404);

    $openingOriginal = max(0, round((float)$customer['opening_balance'], 2));

    // Rebuild Invoice allocations. Pending/partial Customer Order advances remain reserved.
    $pdo->prepare(
        'DELETE cpa FROM customer_payment_allocations cpa
         INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id
         INNER JOIN sales s ON s.id=cpa.sale_id
         WHERE cp.branch_id=:branch_id AND cp.customer_id=:customer_id
           AND (
                cp.status<>1
                OR s.document_type=2
                OR (s.document_type=3 AND (s.status=3 OR s.delivery_status NOT IN (1,2)))
                OR s.document_type NOT IN (2,3)
           )'
    )->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);

    $reservedMap = payment_active_order_reserved_map($pdo, $branchId, $customerId);

    $invoiceStmt = $pdo->prepare(
        'SELECT id,sale_no,sale_date,grand_total
         FROM sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=2 AND status=2
         ORDER BY sale_date,id
         FOR UPDATE'
    );
    $invoiceStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    $invoices = [];
    foreach ($invoiceStmt->fetchAll() as $row) {
        $invoices[] = [
            'id'=>(int)$row['id'],
            'sale_no'=>(string)$row['sale_no'],
            'sale_date'=>(string)$row['sale_date'],
            'grand_total'=>round((float)$row['grand_total'], 2),
            'remaining'=>round((float)$row['grand_total'], 2),
            'paid'=>0.0,
        ];
    }

    $paymentStmt = $pdo->prepare(
        'SELECT id,payment_date,amount,discount_amount
         FROM customer_payments
         WHERE branch_id=:branch_id AND customer_id=:customer_id AND status=1
         ORDER BY payment_date,id
         FOR UPDATE'
    );
    $paymentStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    $payments = $paymentStmt->fetchAll();

    $openingRemaining = $openingOriginal;
    $advance = 0.0;
    $discountAppliedTotal = 0.0;
    $paymentBreakdown = [];
    $invoiceIndex = 0;
    $insertAllocation = $pdo->prepare(
        'INSERT INTO customer_payment_allocations(customer_payment_id,sale_id,amount)
         VALUES(:payment_id,:sale_id,:amount)
         ON DUPLICATE KEY UPDATE amount=amount+VALUES(amount)'
    );

    $applyToInvoices = static function(float &$available, array &$invoices, int &$invoiceIndex, int $paymentId, PDOStatement $insertAllocation, float &$bucket): void {
        while ($available > 0.009 && $invoiceIndex < count($invoices)) {
            while ($invoiceIndex < count($invoices) && $invoices[$invoiceIndex]['remaining'] <= 0.009) $invoiceIndex++;
            if ($invoiceIndex >= count($invoices)) break;
            $take = min($available, $invoices[$invoiceIndex]['remaining']);
            if ($take <= 0.009) break;
            $insertAllocation->execute([
                ':payment_id'=>$paymentId,
                ':sale_id'=>$invoices[$invoiceIndex]['id'],
                ':amount'=>round($take, 2),
            ]);
            $invoices[$invoiceIndex]['remaining'] = round($invoices[$invoiceIndex]['remaining'] - $take, 2);
            $invoices[$invoiceIndex]['paid'] = round($invoices[$invoiceIndex]['paid'] + $take, 2);
            $available = round($available - $take, 2);
            $bucket = round($bucket + $take, 2);
        }
    };

    foreach ($payments as $payment) {
        $paymentId = (int)$payment['id'];
        $reserved = round((float)($reservedMap[$paymentId] ?? 0), 2);
        $cashAvailable = max(0, round((float)$payment['amount'] - $reserved, 2));
        $discountAvailable = max(0, round((float)($payment['discount_amount'] ?? 0), 2));
        $breakdown = [
            'opening'=>0.0,
            'invoice'=>0.0,
            'discount'=>0.0,
            'advance'=>0.0,
            'reserved_order'=>$reserved,
        ];

        // Actual money is applied first. Only unused actual money can become Customer Advance.
        if ($openingRemaining > 0.009 && $cashAvailable > 0.009) {
            $take = min($openingRemaining, $cashAvailable);
            $openingRemaining = round($openingRemaining - $take, 2);
            $cashAvailable = round($cashAvailable - $take, 2);
            $breakdown['opening'] = round($breakdown['opening'] + $take, 2);
        }
        $applyToInvoices($cashAvailable, $invoices, $invoiceIndex, $paymentId, $insertAllocation, $breakdown['invoice']);
        if ($cashAvailable > 0.009) {
            $breakdown['advance'] = round($cashAvailable, 2);
            $advance = round($advance + $cashAvailable, 2);
        }

        // Settlement discount is a write-off only. It never creates Customer Advance.
        if ($openingRemaining > 0.009 && $discountAvailable > 0.009) {
            $take = min($openingRemaining, $discountAvailable);
            $openingRemaining = round($openingRemaining - $take, 2);
            $discountAvailable = round($discountAvailable - $take, 2);
            $breakdown['opening'] = round($breakdown['opening'] + $take, 2);
            $breakdown['discount'] = round($breakdown['discount'] + $take, 2);
            $discountAppliedTotal = round($discountAppliedTotal + $take, 2);
        }
        $before = $discountAvailable;
        $invoiceDiscount = 0.0;
        $applyToInvoices($discountAvailable, $invoices, $invoiceIndex, $paymentId, $insertAllocation, $invoiceDiscount);
        if ($invoiceDiscount > 0.009) {
            $breakdown['invoice'] = round($breakdown['invoice'] + $invoiceDiscount, 2);
            $breakdown['discount'] = round($breakdown['discount'] + $invoiceDiscount, 2);
            $discountAppliedTotal = round($discountAppliedTotal + $invoiceDiscount, 2);
        }
        // Any unused discount is intentionally ignored; discount never becomes an advance.
        $paymentBreakdown[$paymentId] = $breakdown;
    }

    $invoiceOutstanding = 0.0;
    $updateInvoice = $pdo->prepare(
        'UPDATE sales
         SET paid_amount=:paid,balance_amount=:balance,payment_status=:payment_status,updated_at=NOW()
         WHERE id=:id'
    );
    foreach ($invoices as $invoice) {
        $grand = round((float)$invoice['grand_total'], 2);
        $paid = min($grand, max(0, round((float)$invoice['paid'], 2)));
        $balance = max(0, round($grand - $paid, 2));
        $paymentStatus = $paid <= 0.009 ? 1 : ($balance <= 0.009 ? 3 : 2);
        $updateInvoice->execute([
            ':paid'=>$paid,
            ':balance'=>$balance,
            ':payment_status'=>$paymentStatus,
            ':id'=>$invoice['id'],
        ]);
        $invoiceOutstanding = round($invoiceOutstanding + $balance, 2);
    }

    $pdo->prepare(
        'UPDATE sales SET paid_amount=0,balance_amount=0,payment_status=1,updated_at=NOW()
         WHERE branch_id=:branch_id AND customer_id=:customer_id AND document_type=2 AND status=3'
    )->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);

    $reservedTotal = 0.0;
    foreach ($reservedMap as $value) $reservedTotal = round($reservedTotal + (float)$value, 2);

    return [
        'opening_original'=>$openingOriginal,
        'opening_due'=>$openingRemaining,
        'invoice_outstanding'=>$invoiceOutstanding,
        'total_outstanding'=>round($openingRemaining + $invoiceOutstanding, 2),
        'advance'=>$advance,
        'discount_applied'=>$discountAppliedTotal,
        'reserved_order_advance'=>$reservedTotal,
        'payment_breakdown'=>$paymentBreakdown,
    ];
}

function payment_receivable_snapshot(PDO $pdo, int $branchId, int $customerId, int $excludePaymentId = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT opening_balance,customer_code,customer_name
         FROM customers WHERE id=:id AND branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id'=>$customerId, ':branch_id'=>$branchId]);
    $customer = $stmt->fetch();
    if (!$customer) json_error('Customer was not found.', 404);

    $receivables = [];
    $opening = max(0, round((float)$customer['opening_balance'], 2));
    if ($opening > 0.009) {
        $receivables[] = [
            'source_type'=>'opening',
            'sale_ref'=>null,
            'sale_no'=>'Opening Balance',
            'date'=>null,
            'original'=>$opening,
            'already_paid'=>0.0,
            'balance'=>$opening,
        ];
    }

    $invoiceStmt = $pdo->prepare(
        'SELECT id,sale_no,sale_date,grand_total
         FROM sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=2 AND status=2
         ORDER BY sale_date,id'
    );
    $invoiceStmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    foreach ($invoiceStmt->fetchAll() as $row) {
        $grand = round((float)$row['grand_total'], 2);
        $receivables[] = [
            'source_type'=>'invoice',
            'sale_ref'=>encryptReference('sale', (int)$row['id']),
            'sale_no'=>(string)$row['sale_no'],
            'date'=>(string)$row['sale_date'],
            'original'=>$grand,
            'already_paid'=>0.0,
            'balance'=>$grand,
        ];
    }

    $reservedMap = payment_active_order_reserved_map($pdo, $branchId, $customerId, $excludePaymentId);
    $sql = 'SELECT id,amount,discount_amount FROM customer_payments
            WHERE branch_id=:branch_id AND customer_id=:customer_id AND status=1';
    $params = [':branch_id'=>$branchId, ':customer_id'=>$customerId];
    if ($excludePaymentId > 0) {
        $sql .= ' AND id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludePaymentId;
    }
    $sql .= ' ORDER BY payment_date,id';
    $paymentStmt = $pdo->prepare($sql);
    $paymentStmt->execute($params);

    $paymentsTotal = 0.0;
    $discountsTotal = 0.0;
    $discountAppliedTotal = 0.0;
    $reservedTotal = 0.0;
    $advance = 0.0;

    $apply = static function(float $available, array &$receivables): array {
        $applied = 0.0;
        if ($available <= 0.009) return [0.0, 0.0];
        foreach ($receivables as &$row) {
            if ($available <= 0.009) break;
            $due = max(0, round((float)$row['balance'], 2));
            if ($due <= 0.009) continue;
            $take = min($available, $due);
            $row['already_paid'] = round((float)$row['already_paid'] + $take, 2);
            $row['balance'] = round($due - $take, 2);
            $available = round($available - $take, 2);
            $applied = round($applied + $take, 2);
        }
        unset($row);
        return [$applied, max(0, round($available, 2))];
    };

    foreach ($paymentStmt->fetchAll() as $payment) {
        $paymentId = (int)$payment['id'];
        $amount = max(0, round((float)$payment['amount'], 2));
        $discount = max(0, round((float)($payment['discount_amount'] ?? 0), 2));
        $reserved = min($amount, max(0, round((float)($reservedMap[$paymentId] ?? 0), 2)));
        $paymentsTotal = round($paymentsTotal + $amount, 2);
        $discountsTotal = round($discountsTotal + $discount, 2);
        $reservedTotal = round($reservedTotal + $reserved, 2);

        [, $cashLeft] = $apply(max(0, round($amount - $reserved, 2)), $receivables);
        $advance = round($advance + $cashLeft, 2);
        [$discountApplied, ] = $apply($discount, $receivables);
        $discountAppliedTotal = round($discountAppliedTotal + $discountApplied, 2);
    }

    $outstanding = 0.0;
    foreach ($receivables as $row) $outstanding = round($outstanding + (float)$row['balance'], 2);

    return [
        'customer'=>[
            'id'=>$customerId,
            'customer_code'=>(string)$customer['customer_code'],
            'customer_name'=>(string)$customer['customer_name'],
        ],
        'receivables'=>$receivables,
        'opening_original'=>$opening,
        'payments_total'=>$paymentsTotal,
        'discounts_total'=>$discountsTotal,
        'discounts_applied'=>$discountAppliedTotal,
        'reserved_order_advance'=>$reservedTotal,
        'existing_advance'=>$advance,
        'current_outstanding'=>$outstanding,
    ];
}

function payment_parse_rows(array $data, int $branchId, string $headerDate): array
{
    $raw = $data['payments'] ?? ($data['payments_json'] ?? []);
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) json_error('Payment rows are invalid.', 422);

    $accountStmt = db()->prepare(
        'SELECT id,account_name,account_type,status
         FROM accounts WHERE id=:id AND branch_id=:branch_id LIMIT 1'
    );
    $seenModes = [];
    $rows = [];
    $total = 0.0;

    foreach ($raw as $row) {
        if (!is_array($row)) continue;
        $mode = (int)($row['payment_mode'] ?? 0);
        if ($mode < 1 || $mode > 4) continue;
        if (isset($seenModes[$mode])) json_error(payment_mode_label($mode) . ' can be entered only once.', 422);
        $seenModes[$mode] = true;

        $amountText = trim((string)($row['amount'] ?? ''));
        $amount = $amountText === '' ? 0.0 : aqua_decimal($amountText, 'amount', 'Amount', 2, false);
        if ($amount <= 0.009) continue;

        $accountId = (int)($row['account_id'] ?? 0);
        if ($accountId < 1) json_error('Select ' . payment_mode_label($mode) . ' Account.', 422);
        $accountStmt->execute([':id'=>$accountId, ':branch_id'=>$branchId]);
        $account = $accountStmt->fetch();
        if (!$account || (int)$account['status'] !== 1) json_error('Selected ' . payment_mode_label($mode) . ' Account is invalid or inactive.', 422);
        if ((int)$account['account_type'] !== payment_account_type_for_mode($mode)) {
            json_error('Selected Account does not match ' . payment_mode_label($mode) . ' mode.', 422);
        }

        $detailDate = trim((string)($row['detail_date'] ?? ''));
        if ($detailDate === '') $detailDate = $headerDate;
        $detailDate = aqua_date($detailDate, 'detail_date');

        $rows[] = [
            'payment_mode'=>$mode,
            'account_id'=>$accountId,
            'amount'=>round($amount, 2),
            'reference_no'=>payment_nullable($row['reference_no'] ?? null, 100),
            'detail_date'=>$detailDate,
        ];
        $total = round($total + $amount, 2);
    }

    usort($rows, static fn(array $a, array $b): int => $a['payment_mode'] <=> $b['payment_mode']);
    return ['rows'=>$rows, 'total'=>$total];
}

function payment_reverse_ledger(PDO $pdo, int $branchId, int $paymentId, int $userId, string $reason): void
{
    $headerStmt = $pdo->prepare('SELECT payment_no,payment_date FROM customer_payments WHERE id=:id LIMIT 1');
    $headerStmt->execute([':id'=>$paymentId]);
    $header = $headerStmt->fetch() ?: ['payment_no'=>'', 'payment_date'=>date('Y-m-d')];

    $stmt = $pdo->prepare(
        'SELECT account_id,amount,detail_date FROM customer_payment_details WHERE customer_payment_id=:payment_id ORDER BY id'
    );
    $stmt->execute([':payment_id'=>$paymentId]);
    $insert = $pdo->prepare(
        'INSERT INTO account_transactions
         (branch_id,transaction_date,account_id,transaction_type,source_type,source_id,amount,remarks,created_by,created_at)
         VALUES(:branch_id,:transaction_date,:account_id,2,5,:source_id,:amount,:remarks,:created_by,NOW())'
    );
    foreach ($stmt->fetchAll() as $row) {
        $amount = round((float)$row['amount'], 2);
        if ($amount <= 0.009) continue;
        $transactionDate = trim((string)($row['detail_date'] ?? '')) ?: (string)$header['payment_date'];
        $insert->execute([
            ':branch_id'=>$branchId,
            ':transaction_date'=>$transactionDate . ' 00:00:00',
            ':account_id'=>(int)$row['account_id'],
            ':source_id'=>$paymentId,
            ':amount'=>$amount,
            ':remarks'=>$reason . ' ' . (string)$header['payment_no'],
            ':created_by'=>$userId,
        ]);
    }
}

function payment_insert_details_and_ledger(PDO $pdo, int $branchId, int $paymentId, array $rows, int $userId, string $paymentNo): void
{
    $detailStmt = $pdo->prepare(
        'INSERT INTO customer_payment_details
         (customer_payment_id,account_id,payment_mode,amount,reference_no,detail_date)
         VALUES(:payment_id,:account_id,:payment_mode,:amount,:reference_no,:detail_date)'
    );
    $ledgerStmt = $pdo->prepare(
        'INSERT INTO account_transactions
         (branch_id,transaction_date,account_id,transaction_type,source_type,source_id,amount,remarks,created_by,created_at)
         VALUES(:branch_id,:transaction_date,:account_id,1,1,:source_id,:amount,:remarks,:created_by,NOW())'
    );
    foreach ($rows as $row) {
        $detailStmt->execute([
            ':payment_id'=>$paymentId,
            ':account_id'=>$row['account_id'],
            ':payment_mode'=>$row['payment_mode'],
            ':amount'=>$row['amount'],
            ':reference_no'=>$row['reference_no'],
            ':detail_date'=>$row['detail_date'],
        ]);
        $ledgerStmt->execute([
            ':branch_id'=>$branchId,
            ':transaction_date'=>$row['detail_date'] . ' 00:00:00',
            ':account_id'=>$row['account_id'],
            ':source_id'=>$paymentId,
            ':amount'=>$row['amount'],
            ':remarks'=>'Customer Payment ' . $paymentNo . ' - ' . payment_mode_label((int)$row['payment_mode']),
            ':created_by'=>$userId,
        ]);
    }
}

function payment_payload(PDO $pdo, int $branchId, int $paymentId): array
{
    $payment = payment_fetch_header($pdo, $branchId, $paymentId, false);
    $payment['ref'] = encryptReference('customer_payment', $paymentId);
    $payment['details'] = payment_details($pdo, $paymentId);
    $payment['reserved_order_advance'] = payment_reserved_amount($pdo, $paymentId);
    unset($payment['id'], $payment['branch_id']);
    return $payment;
}

function payment_save(array $data, array $access, array $context): array
{
    $branchId = (int)$context['branch_id'];
    $userId = (int)$access['user']['id'];
    aqua_require_action($access, ACTION_RECEIVE_PAYMENT, 'You do not have permission to receive Customer Payment.');

    $paymentId = null;
    if (!empty($data['ref'])) {
        $paymentId = aqua_ref_to_id($data['ref'], 'customer_payment', 'Customer Payment');
        aqua_require_action($access, ACTION_UPDATE, 'You do not have permission to edit Customer Payment.');
    }

    $paymentDate = aqua_date($data['payment_date'] ?? '', 'payment_date');
    $customerId = (int)($data['customer_id'] ?? 0);
    if ($customerId < 1) json_error('Select Customer.', 422, ['customer_id'=>'Select Customer.']);
    aqua_customer($branchId, $customerId);
    $remarks = payment_nullable($data['remarks'] ?? null, 255);
    $parsed = payment_parse_rows($data, $branchId, $paymentDate);
    $discount = payment_parse_discount($data);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $oldCustomerId = $customerId;
        $paymentNo = '';
        $reservedAmount = 0.0;

        if ($paymentId !== null) {
            $old = payment_fetch_header($pdo, $branchId, $paymentId, true);
            if ((int)$old['status'] !== 1) json_error('Cancelled Customer Payment cannot be edited.', 409);
            $oldCustomerId = (int)$old['customer_id'];
            $paymentNo = (string)$old['payment_no'];
            $reservedAmount = payment_reserved_amount($pdo, $paymentId);
            if ($reservedAmount > 0.009 && $oldCustomerId !== $customerId) {
                json_error('Customer cannot be changed because part of this Payment is reserved against a pending Customer Order.', 409);
            }
            if ((float)$parsed['total'] + 0.009 < $reservedAmount) {
                json_error('Payment Amount cannot be less than the reserved Customer Order advance of ₹' . number_format($reservedAmount, 2, '.', ''), 422);
            }
        }

        $snapshotBefore = payment_receivable_snapshot($pdo, $branchId, $customerId, $paymentId ?? 0);
        $usablePayment = max(0, round((float)$parsed['total'] - $reservedAmount, 2));
        $discountAmount = payment_effective_discount(
            (int)$discount['type'],
            (float)$discount['value'],
            (float)$snapshotBefore['current_outstanding'],
            $usablePayment
        );

        if ((float)$parsed['total'] <= 0.009 && $discountAmount <= 0.009) {
            json_error('Enter Payment Amount or Settlement Discount.', 422, ['payments'=>'Enter Payment Amount or Discount.']);
        }

        if ($paymentId !== null) {
            payment_reverse_ledger($pdo, $branchId, $paymentId, $userId, 'Customer Payment edit reversal');
            $pdo->prepare('DELETE FROM customer_payment_details WHERE customer_payment_id=:payment_id')
                ->execute([':payment_id'=>$paymentId]);
            $pdo->prepare(
                'UPDATE customer_payments
                 SET payment_date=:payment_date,customer_id=:customer_id,amount=:amount,
                     discount_type=:discount_type,discount_value=:discount_value,discount_amount=:discount_amount,
                     remarks=:remarks
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':payment_date'=>$paymentDate,
                ':customer_id'=>$customerId,
                ':amount'=>$parsed['total'],
                ':discount_type'=>$discount['type'],
                ':discount_value'=>$discount['value'],
                ':discount_amount'=>$discountAmount,
                ':remarks'=>$remarks,
                ':id'=>$paymentId,
                ':branch_id'=>$branchId,
            ]);
        } else {
            $paymentNo = aqua_generate_no_locked($pdo, $branchId, 'customer_payments', 'payment_no', 'CR');
            $pdo->prepare(
                'INSERT INTO customer_payments
                 (branch_id,payment_no,payment_date,customer_id,amount,discount_type,discount_value,discount_amount,remarks,status,created_by,created_at)
                 VALUES(:branch_id,:payment_no,:payment_date,:customer_id,:amount,:discount_type,:discount_value,:discount_amount,:remarks,1,:created_by,NOW())'
            )->execute([
                ':branch_id'=>$branchId,
                ':payment_no'=>$paymentNo,
                ':payment_date'=>$paymentDate,
                ':customer_id'=>$customerId,
                ':amount'=>$parsed['total'],
                ':discount_type'=>$discount['type'],
                ':discount_value'=>$discount['value'],
                ':discount_amount'=>$discountAmount,
                ':remarks'=>$remarks,
                ':created_by'=>$userId,
            ]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        payment_insert_details_and_ledger($pdo, $branchId, $paymentId, $parsed['rows'], $userId, $paymentNo);

        if ($oldCustomerId !== $customerId) payment_recalculate_customer($pdo, $branchId, $oldCustomerId);
        $summary = payment_recalculate_customer($pdo, $branchId, $customerId);

        $pdo->commit();
        return [
            'payment'=>payment_payload(db(), $branchId, $paymentId),
            'summary'=>$summary,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function payment_cancel(array $data, array $access, array $context): array
{
    aqua_require_action($access, ACTION_RECEIVE_PAYMENT, 'You do not have permission to manage Customer Payment.');
    aqua_require_action($access, ACTION_CANCEL, 'You do not have permission to delete/cancel Customer Payment.');

    $branchId = (int)$context['branch_id'];
    $userId = (int)$access['user']['id'];
    $paymentId = aqua_ref_to_id($data['ref'] ?? '', 'customer_payment', 'Customer Payment');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $payment = payment_fetch_header($pdo, $branchId, $paymentId, true);
        if ((int)$payment['status'] !== 1) json_error('Customer Payment is already cancelled.', 409);

        $orderStmt = $pdo->prepare(
            'SELECT DISTINCT s.id
             FROM customer_payment_allocations cpa
             INNER JOIN sales s ON s.id=cpa.sale_id AND s.document_type=3
             WHERE cpa.customer_payment_id=:payment_id'
        );
        $orderStmt->execute([':payment_id'=>$paymentId]);
        $orderIds = array_map('intval', $orderStmt->fetchAll(PDO::FETCH_COLUMN));

        payment_reverse_ledger($pdo, $branchId, $paymentId, $userId, 'Customer Payment cancellation reversal');
        $pdo->prepare('DELETE FROM customer_payment_allocations WHERE customer_payment_id=:payment_id')
            ->execute([':payment_id'=>$paymentId]);
        $pdo->prepare('UPDATE customer_payments SET status=0 WHERE id=:id AND branch_id=:branch_id')
            ->execute([':id'=>$paymentId, ':branch_id'=>$branchId]);

        foreach ($orderIds as $orderId) payment_refresh_sale_payment_header($pdo, $orderId);
        $summary = payment_recalculate_customer($pdo, $branchId, (int)$payment['customer_id']);

        $pdo->commit();
        return ['summary'=>$summary];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function payment_selected_sale(int $branchId, $ref): ?array
{
    if (!is_string($ref) || trim($ref) === '') return null;
    $saleId = aqua_ref_to_id($ref, 'sale', 'Sales');
    $stmt = db()->prepare(
        'SELECT s.id,s.sale_no,s.sale_date,s.customer_id,s.grand_total,s.paid_amount,s.balance_amount,s.payment_status,s.status,s.document_type,
                c.customer_name,c.customer_code
         FROM sales s
         INNER JOIN customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
         WHERE s.id=:id AND s.branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id'=>$saleId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Sales Invoice was not found.', 404);
    if ((int)$row['document_type'] !== 2) json_error('Payment can be opened from a Sales Invoice only.', 422);
    $row['ref'] = encryptReference('sale', $saleId);
    $row['customer_id'] = (int)$row['customer_id'];
    $row['grand_total'] = (float)$row['grand_total'];
    $row['paid_amount'] = (float)$row['paid_amount'];
    $row['balance_amount'] = (float)$row['balance_amount'];
    $row['payment_status'] = (int)$row['payment_status'];
    $row['status'] = (int)$row['status'];
    $row['document_type'] = (int)$row['document_type'];
    unset($row['id']);
    return $row;
}

payment_ensure_discount_schema();

$method = request_method();

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = payment_access(ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = (int)($_GET['draw'] ?? 1);
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));

    $statusRaw = trim((string)($_GET['status'] ?? ''));
    $customerRaw = trim((string)($_GET['customer_id'] ?? ''));
    $paymentModeRaw = trim((string)($_GET['payment_mode'] ?? ''));
    $dateFromRaw = trim((string)($_GET['date_from'] ?? ''));
    $dateToRaw = trim((string)($_GET['date_to'] ?? ''));

    $where = ['cp.branch_id=:branch_id'];
    $params = [':branch_id'=>$branchId];

    if ($statusRaw !== '') {
        $status = (int)$statusRaw;
        if (!in_array($status, [0,1], true)) {
            json_error('Invalid Customer Payment status.', 422);
        }
        $where[] = 'cp.status=:status';
        $params[':status'] = $status;
    }

    if ($customerRaw !== '') {
        $customerId = (int)$customerRaw;
        if ($customerId < 1) {
            json_error('Invalid Customer.', 422);
        }
        aqua_customer($branchId, $customerId);
        $where[] = 'cp.customer_id=:customer_id';
        $params[':customer_id'] = $customerId;
    }

    if ($paymentModeRaw !== '') {
        $paymentMode = (int)$paymentModeRaw;
        if (!in_array($paymentMode, [1,2,3,4], true)) {
            json_error('Invalid Payment Mode.', 422);
        }
        $where[] = 'EXISTS (
            SELECT 1
            FROM customer_payment_details fpd
            WHERE fpd.customer_payment_id=cp.id
              AND fpd.payment_mode=:payment_mode
              AND fpd.amount>0
        )';
        $params[':payment_mode'] = $paymentMode;
    }

    if ($dateFromRaw !== '') {
        $dateFrom = aqua_date($dateFromRaw, 'date_from');
        $where[] = 'cp.payment_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateToRaw !== '') {
        $dateTo = aqua_date($dateToRaw, 'date_to');
        $where[] = 'cp.payment_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    if ($dateFromRaw !== '' && $dateToRaw !== '' && $params[':date_from'] > $params[':date_to']) {
        json_error('From Date cannot be after To Date.', 422);
    }

    if ($search !== '') {
        $where[] = '(cp.payment_no LIKE :q OR c.customer_name LIKE :q OR c.customer_code LIKE :q OR c.mobile LIKE :q OR cp.remarks LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    $from = ' FROM customer_payments cp
              INNER JOIN customers c ON c.id=cp.customer_id AND c.branch_id=cp.branch_id ';

    $count = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    foreach ($params as $key=>$value) {
        $isInt = in_array($key, [':branch_id',':status',':customer_id',':payment_mode'], true);
        $count->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count->execute();
    $filtered = (int)$count->fetchColumn();

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM customer_payments WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id'=>$branchId]);
    $total = (int)$totalStmt->fetchColumn();

    $summarySql = 'SELECT
                        COUNT(*) AS receipt_count,
                        COALESCE(SUM(CASE WHEN cp.status=1 THEN 1 ELSE 0 END),0) AS active_count,
                        COALESCE(SUM(CASE WHEN cp.status=0 THEN 1 ELSE 0 END),0) AS cancelled_count,
                        COALESCE(SUM(CASE WHEN cp.status=1 THEN cp.amount ELSE 0 END),0) AS received_amount,
                        COALESCE(SUM(CASE WHEN cp.status=1 THEN cp.discount_amount ELSE 0 END),0) AS discount_amount,
                        COALESCE(SUM(CASE WHEN cp.status=1 THEN cp.amount + cp.discount_amount ELSE 0 END),0) AS settlement_amount
                   ' . $from . '
                   WHERE ' . implode(' AND ', $where);

    $summaryStmt = db()->prepare($summarySql);
    foreach ($params as $key=>$value) {
        $isInt = in_array($key, [':branch_id',':status',':customer_id',':payment_mode'], true);
        $summaryStmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch() ?: [];

    $sql = 'SELECT cp.id,cp.payment_no,cp.payment_date,cp.amount,cp.discount_type,cp.discount_value,cp.discount_amount,cp.status,cp.remarks,
                   c.customer_code,c.customer_name,
                   COALESCE((SELECT SUM(d.amount) FROM customer_payment_details d WHERE d.customer_payment_id=cp.id AND d.payment_mode=1),0) cash_amount,
                   COALESCE((SELECT SUM(d.amount) FROM customer_payment_details d WHERE d.customer_payment_id=cp.id AND d.payment_mode=2),0) upi_amount,
                   COALESCE((SELECT SUM(d.amount) FROM customer_payment_details d WHERE d.customer_payment_id=cp.id AND d.payment_mode=3),0) bank_amount,
                   COALESCE((SELECT SUM(d.amount) FROM customer_payment_details d WHERE d.customer_payment_id=cp.id AND d.payment_mode=4),0) cheque_amount
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY cp.payment_date DESC,cp.id DESC
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key=>$value) {
        $isInt = in_array($key, [':branch_id',':status',':customer_id',':payment_mode'], true);
        $stmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        foreach (['amount','discount_value','discount_amount','cash_amount','upi_amount','bank_amount','cheque_amount'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        $row['discount_type'] = (int)$row['discount_type'];
        $row['discount_type_label'] = payment_discount_type_label($row['discount_type']);
        $row['settlement_amount'] = round($row['amount'] + $row['discount_amount'], 2);
        $row['status'] = (int)$row['status'];
        $row['ref'] = encryptReference('customer_payment', $id);
        $row['edit_url'] = 'customer-payment-form.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
    }

    json_success('Customer Payments loaded.', [
        'allowed_actions'=>$access['actions'],
        'summary'=>[
            'receipt_count'=>(int)($summary['receipt_count'] ?? 0),
            'active_count'=>(int)($summary['active_count'] ?? 0),
            'cancelled_count'=>(int)($summary['cancelled_count'] ?? 0),
            'received_amount'=>(float)($summary['received_amount'] ?? 0),
            'discount_amount'=>(float)($summary['discount_amount'] ?? 0),
            'settlement_amount'=>(float)($summary['settlement_amount'] ?? 0),
        ],
        'datatable'=>[
            'draw'=>$draw,
            'recordsTotal'=>$total,
            'recordsFiltered'=>$filtered,
            'data'=>$rows,
        ],
    ]);
}

if ($method === 'GET' && isset($_GET['summary'])) {
    $access = payment_access(ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId < 1) json_error('Select Customer.', 422);
    aqua_customer($branchId, $customerId);
    $excludeId = 0;
    if (!empty($_GET['exclude_ref'])) $excludeId = aqua_ref_to_id($_GET['exclude_ref'], 'customer_payment', 'Customer Payment');
    $snapshot = payment_receivable_snapshot(db(), $branchId, $customerId, $excludeId);
    $snapshot['selected_sale'] = payment_selected_sale($branchId, $_GET['sale'] ?? null);
    json_success('Customer receivables loaded.', $snapshot);
}

if ($method === 'GET' && isset($_GET['bootstrap'])) {
    $access = payment_access(ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $selectedSale = payment_selected_sale($branchId, $_GET['sale'] ?? null);
    $payment = null;
    $paymentId = 0;
    if (!empty($_GET['ref'])) {
        $paymentId = aqua_ref_to_id($_GET['ref'], 'customer_payment', 'Customer Payment');
        $payment = payment_payload(db(), $branchId, $paymentId);
    }

    $selectedCustomerId = $payment ? (int)$payment['customer_id'] : ($selectedSale ? (int)$selectedSale['customer_id'] : 0);
    $snapshot = $selectedCustomerId > 0
        ? payment_receivable_snapshot(db(), $branchId, $selectedCustomerId, $paymentId)
        : null;

    json_success('Customer Payment form loaded.', [
        'allowed_actions'=>$access['actions'],
        'customers'=>payment_customers($branchId),
        'accounts'=>payment_accounts($branchId),
        'payment'=>$payment,
        'selected_sale'=>$selectedSale,
        'summary'=>$snapshot,
        'today'=>date('Y-m-d'),
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = trim((string)($data['action'] ?? 'save'));
    if ($action === 'cancel' || $action === 'delete') {
        $access = payment_access(ACTION_VIEW);
        $context = aqua_sales_context($access['user']);
        $result = payment_cancel($data, $access, $context);
        json_success('Customer Payment cancelled and customer FIFO recalculated.', $result);
    }

    $access = payment_access(ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $result = payment_save($data, $access, $context);
    json_success(!empty($data['ref']) ? 'Customer Payment updated and FIFO recalculated.' : 'Customer Payment saved and FIFO recalculated.', $result);
}

json_error('Unsupported Customer Payment API request.', 405);
