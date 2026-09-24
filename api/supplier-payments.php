<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_CANCEL')) define('ACTION_CANCEL', 14);

/*
 * Vimal Aqua - Supplier Payment / Settlement API
 *
 * payment_type:
 *   1 = Opening Balance
 *   2 = Overall Outstanding (FIFO: opening balance first, then oldest purchase)
 *   3 = Invoice / Particular Purchase
 *
 * allocation_type:
 *   1 = Purchase
 *   2 = Opening Balance
 *
 * This module replays every active payment of the affected supplier whenever a
 * payment is edited or deleted. That keeps FIFO allocations and purchase
 * payment_status values correct even when an old payment changes.
 *
 * Permission note:
 * Supplier Payment uses its own dedicated menu permission.
 */
const SP_PERMISSION_PATH = 'supplier-payment-list.php';
const SP_SYNTHETIC_NEW_ID = 9223372036854770000;

function sp_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Supplier Payment is available only for tenant users.', 403);
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
    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function sp_require_schema(): void
{
    $required = [
        'supplier_payments' => ['payment_type', 'target_purchase_id', 'discount_type', 'discount_value', 'discount_amount'],
        'supplier_payment_allocations' => ['allocation_type', 'discount_amount'],
    ];

    foreach ($required as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = db()->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . db()->quote($column));
            if (!$stmt || !$stmt->fetchColumn()) {
                json_error(
                    'Supplier Payment database update is required. Run supplier-payment-migration.sql first. Missing: ' .
                    $table . '.' . $column,
                    500
                );
            }
        }
    }

    $stmt = db()->query("SHOW COLUMNS FROM supplier_payment_allocations LIKE 'purchase_id'");
    $purchaseColumn = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!$purchaseColumn || strtoupper((string)($purchaseColumn['Null'] ?? 'NO')) !== 'YES') {
        json_error(
            'Supplier Payment database update is required. supplier_payment_allocations.purchase_id must allow NULL. Run supplier-payment-migration.sql first.',
            500
        );
    }
}

function sp_ref_to_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error($label . ' reference is required.', 422);
    }

    try {
        $id = (int)decryptReference(trim($value), $purpose);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    if ($id < 1) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    return $id;
}

function sp_type_ref(int $type): string
{
    return encryptReference('supplier_payment_type', $type);
}

function sp_type_from_ref($value): int
{
    $type = sp_ref_to_id($value, 'supplier_payment_type', 'Payment Type');
    if (!in_array($type, [1, 2, 3], true)) {
        json_error('Invalid Payment Type.', 422);
    }
    return $type;
}

function sp_type_label(int $type): string
{
    return [
        1 => 'Opening Balance',
        2 => 'Overall',
        3 => 'Invoice',
    ][$type] ?? 'Unknown';
}

function sp_nullable($value, int $max = 255): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if ($max > 0 && mb_strlen($value) > $max) {
        json_error('Entered value is too long.', 422);
    }
    return $value;
}

function sp_money($value, string $label = 'Amount'): float
{
    $text = trim((string)($value ?? ''));
    if ($text === '') return 0.0;
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/', $text)) {
        json_error($label . ' must be a valid amount.', 422);
    }
    return round((float)$text, 2);
}

function sp_discount_type($value): int
{
    $type = (int)($value ?? 1);
    if (!in_array($type, [1, 2, 3], true)) {
        json_error('Select a valid Discount Type.', 422, ['discount_type' => 'Invalid Discount Type.']);
    }
    return $type;
}

function sp_discount_value($value, int $type): float
{
    if ($type === 1) return 0.0;
    $amount = sp_money($value, $type === 2 ? 'Discount Percentage' : 'Discount Amount');
    if ($type === 2 && $amount > 100.0) {
        json_error('Discount Percentage cannot exceed 100%.', 422, ['discount_value' => 'Maximum percentage is 100.']);
    }
    return $amount;
}

function sp_calculate_discount(int $type, float $value, float $targetOutstanding): float
{
    if ($type === 1 || $value <= 0.0 || $targetOutstanding <= 0.0) return 0.0;
    if ($type === 2) return round($targetOutstanding * $value / 100, 2);
    return round($value, 2);
}

function sp_date($value, string $field = 'payment_date', bool $required = true): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        if (!$required) return null;
        json_error('Payment Date is required.', 422, [$field => 'Payment Date is required.']);
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field => 'Enter a valid date.']);
    }

    return $value;
}

function sp_supplier(PDO $pdo, int $branchId, int $supplierId, bool $activeOnly = true): array
{
    $sql = 'SELECT id,branch_id,supplier_code,supplier_name,mobile,opening_balance,status
            FROM suppliers
            WHERE id=:id AND branch_id=:branch_id';
    if ($activeOnly) $sql .= ' AND status=1';
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $supplierId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Selected Supplier is inactive or unavailable.', 422, [
            'supplier_ref' => 'Select an active Supplier.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['opening_balance'] = (float)$row['opening_balance'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function sp_suppliers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,supplier_code,supplier_name,mobile
         FROM suppliers
         WHERE branch_id=:branch_id AND status=1
         ORDER BY supplier_name,supplier_code'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'ref' => encryptReference('supplier', (int)$row['id']),
            'supplier_code' => (string)$row['supplier_code'],
            'supplier_name' => (string)$row['supplier_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
        ];
    }
    return $rows;
}

function sp_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'account_code' => (string)$row['account_code'],
            'account_name' => (string)$row['account_name'],
            'account_type' => (int)$row['account_type'],
        ];
    }
    return $rows;
}

function sp_account(PDO $pdo, int $branchId, int $accountId, int $requiredType): void
{
    $stmt = $pdo->prepare(
        'SELECT id,account_type,status
         FROM accounts
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['status'] !== 1) {
        json_error('Selected Account is inactive or unavailable.', 422);
    }

    if ((int)$row['account_type'] !== $requiredType) {
        $labels = [1 => 'Cash', 2 => 'Bank', 3 => 'UPI', 4 => 'Card', 5 => 'Other'];
        json_error('Select a valid ' . ($labels[$requiredType] ?? 'Account') . ' Account.', 422);
    }
}

function sp_parse_payment_details(PDO $pdo, int $branchId, array $data): array
{
    $raw = $data['payments'] ?? [];
    if (!is_array($raw)) {
        json_error('Payment details must be a list.', 422);
    }

    // payment_mode => required account_type in the Aqua accounts table.
    $requiredAccountType = [1 => 1, 2 => 3, 3 => 2, 4 => 2];
    $rows = [];
    $seen = [];
    $total = 0.0;

    foreach ($raw as $item) {
        if (!is_array($item)) continue;

        $mode = (int)($item['payment_mode'] ?? 0);
        if (!isset($requiredAccountType[$mode])) continue;

        $amount = sp_money($item['amount'] ?? 0, 'Payment Amount');
        if ($amount <= 0.001) continue;

        if (isset($seen[$mode])) {
            json_error('Only one row is allowed for each Payment Mode.', 422);
        }
        $seen[$mode] = true;

        $accountId = (int)($item['account_id'] ?? 0);
        if ($accountId < 1) {
            json_error('Select an Account for every entered Payment Amount.', 422);
        }
        sp_account($pdo, $branchId, $accountId, $requiredAccountType[$mode]);

        $referenceNo = sp_nullable($item['reference_no'] ?? null, 100);
        $detailDate = sp_date($item['detail_date'] ?? '', 'detail_date', false);
        if ($mode === 4 && $detailDate === null) {
            json_error('Cheque Date is required when Cheque Amount is entered.', 422);
        }

        $rows[] = [
            'payment_mode' => $mode,
            'account_id' => $accountId,
            'amount' => $amount,
            'reference_no' => $referenceNo,
            'detail_date' => $detailDate,
        ];
        $total = round($total + $amount, 2);
    }


    return ['rows' => $rows, 'total' => $total];
}

function sp_generate_no(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT payment_no
         FROM supplier_payments
         WHERE branch_id=:branch_id AND payment_no REGEXP '^SPY[0-9]+$'
         ORDER BY CAST(SUBSTRING(payment_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^SPY([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }

    return 'SPY' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function sp_purchase_from_ref(PDO $pdo, int $branchId, string $ref): array
{
    $purchaseId = sp_ref_to_id($ref, 'purchase', 'Purchase');
    $stmt = $pdo->prepare(
        'SELECT p.id,p.purchase_no,p.purchase_date,p.supplier_id,p.supplier_invoice_no,p.grand_total,p.status,
                s.supplier_code,s.supplier_name
         FROM purchases p
         INNER JOIN suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
         WHERE p.id=:id AND p.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $purchaseId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['status'] !== 2) {
        json_error('Selected Purchase is unavailable or not posted.', 422);
    }

    $row['id'] = (int)$row['id'];
    $row['supplier_id'] = (int)$row['supplier_id'];
    $row['grand_total'] = (float)$row['grand_total'];
    $row['ref'] = encryptReference('purchase', (int)$row['id']);
    $row['supplier_ref'] = encryptReference('supplier', (int)$row['supplier_id']);
    return $row;
}

/**
 * Returns the target purchase for legacy purchase-created payments.
 * Existing Purchase posting already creates one supplier_payment_allocation,
 * so old rows remain replayable even before purchase_create_payment is updated
 * to write target_purchase_id explicitly.
 */
function sp_legacy_targets(PDO $pdo, int $branchId, int $supplierId): array
{
    $stmt = $pdo->prepare(
        'SELECT sp.id AS payment_id, MIN(a.purchase_id) AS purchase_id
         FROM supplier_payments sp
         INNER JOIN supplier_payment_allocations a
            ON a.supplier_payment_id=sp.id
           AND a.purchase_id IS NOT NULL
         WHERE sp.branch_id=:branch_id
           AND sp.supplier_id=:supplier_id
         GROUP BY sp.id'
    );
    $stmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['payment_id']] = (int)$row['purchase_id'];
    }
    return $map;
}

/**
 * Simulate the complete supplier ledger from zero.
 *
 * $candidate is used for preview/edit validation and has:
 * id,payment_no,payment_date,payment_type,target_purchase_id,amount
 *
 * $replacePaymentId > 0 means the candidate replaces that payment.
 * $deletePaymentId > 0 means that payment is omitted.
 */
function sp_simulate_supplier(
    PDO $pdo,
    int $branchId,
    int $supplierId,
    ?array $candidate = null,
    int $replacePaymentId = 0,
    int $deletePaymentId = 0
): array {
    $supplier = sp_supplier($pdo, $branchId, $supplierId, false);

    $purchaseStmt = $pdo->prepare(
        'SELECT id,purchase_no,purchase_date,supplier_invoice_no,grand_total,payment_status,status
         FROM purchases
         WHERE branch_id=:branch_id
           AND supplier_id=:supplier_id
           AND status=2
         ORDER BY purchase_date ASC,id ASC'
    );
    $purchaseStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

    $purchases = [];
    $purchaseOrder = [];
    foreach ($purchaseStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id'];
        $grandTotal = round((float)$row['grand_total'], 2);
        $purchases[$id] = [
            'id' => $id,
            'ref' => encryptReference('purchase', $id),
            'purchase_no' => (string)$row['purchase_no'],
            'purchase_date' => (string)$row['purchase_date'],
            'supplier_invoice_no' => (string)($row['supplier_invoice_no'] ?? ''),
            'grand_total' => $grandTotal,
            'paid' => 0.0,
            'pending' => $grandTotal,
        ];
        $purchaseOrder[] = $id;
    }

    $legacyTargets = sp_legacy_targets($pdo, $branchId, $supplierId);

    $paymentStmt = $pdo->prepare(
        'SELECT id,payment_no,payment_date,payment_type,target_purchase_id,amount,
                discount_type,discount_value,discount_amount
         FROM supplier_payments
         WHERE branch_id=:branch_id
           AND supplier_id=:supplier_id
           AND status=1
         ORDER BY payment_date ASC,id ASC'
    );
    $paymentStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

    $payments = [];
    $candidateInserted = false;
    foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id'];
        if ($deletePaymentId > 0 && $id === $deletePaymentId) continue;
        if ($replacePaymentId > 0 && $id === $replacePaymentId) {
            if ($candidate !== null) {
                $payments[] = $candidate;
                $candidateInserted = true;
            }
            continue;
        }

        $targetPurchaseId = $row['target_purchase_id'] === null
            ? ($legacyTargets[$id] ?? null)
            : (int)$row['target_purchase_id'];

        $payments[] = [
            'id' => $id,
            'payment_no' => (string)$row['payment_no'],
            'payment_date' => (string)$row['payment_date'],
            'payment_type' => (int)($row['payment_type'] ?: 3),
            'target_purchase_id' => $targetPurchaseId,
            'amount' => round((float)$row['amount'], 2),
            'discount_type' => (int)($row['discount_type'] ?: 1),
            'discount_value' => round((float)($row['discount_value'] ?? 0), 2),
            'is_candidate' => false,
        ];
    }

    if ($candidate !== null && !$candidateInserted) $payments[] = $candidate;

    usort($payments, static function (array $a, array $b): int {
        $dateCompare = strcmp((string)$a['payment_date'], (string)$b['payment_date']);
        if ($dateCompare !== 0) return $dateCompare;
        return ((int)$a['id']) <=> ((int)$b['id']);
    });

    $openingPending = round((float)$supplier['opening_balance'], 2);
    $allocationsByPayment = [];
    $discountsByPayment = [];
    $candidateKey = null;
    $candidateTargetTotal = 0.0;
    $candidateDiscountAmount = 0.0;
    $candidateSettlementTotal = 0.0;

    foreach ($payments as $payment) {
        $paymentId = (int)$payment['id'];
        $paymentNo = (string)($payment['payment_no'] ?? 'New Payment');
        $type = (int)$payment['payment_type'];
        $actualPayment = round((float)$payment['amount'], 2);
        $discountType = sp_discount_type($payment['discount_type'] ?? 1);
        $discountValue = sp_discount_value($payment['discount_value'] ?? 0, $discountType);
        $targetPurchaseId = !empty($payment['target_purchase_id']) ? (int)$payment['target_purchase_id'] : null;
        $isCandidate = !empty($payment['is_candidate']);

        if (!in_array($type, [1, 2, 3], true)) {
            json_error('Invalid payment type found in ' . $paymentNo . '.', 422);
        }

        $targetTotalBefore = 0.0;
        if ($type === 1) {
            $targetTotalBefore = $openingPending;
        } elseif ($type === 3) {
            if (!$targetPurchaseId || !isset($purchases[$targetPurchaseId])) {
                json_error($paymentNo . ' has an invalid Invoice target.', 422);
            }
            $targetTotalBefore = (float)$purchases[$targetPurchaseId]['pending'];
        } else {
            $purchaseOutstandingBefore = 0.0;
            foreach ($purchaseOrder as $purchaseId) $purchaseOutstandingBefore += (float)$purchases[$purchaseId]['pending'];
            $targetTotalBefore = round($openingPending + $purchaseOutstandingBefore, 2);
        }
        $targetTotalBefore = round($targetTotalBefore, 2);

        $discountAmount = sp_calculate_discount($discountType, $discountValue, $targetTotalBefore);
        $settlementAmount = round($actualPayment + $discountAmount, 2);

        if ($settlementAmount <= 0.001) {
            json_error($paymentNo . ' must contain a Payment Amount or Settlement Discount.', 422);
        }
        if ($settlementAmount > $targetTotalBefore + 0.001) {
            json_error(
                $paymentNo . ' settlement exceeds the selected outstanding. Maximum settlement is ' .
                number_format($targetTotalBefore, 2, '.', '') . '.',
                422
            );
        }

        $allocations = [];
        $remaining = $settlementAmount;

        if ($type === 1) {
            $before = $openingPending;
            $applied = min($remaining, $openingPending);
            if ($applied > 0.001) {
                $openingPending = round($openingPending - $applied, 2);
                $remaining = round($remaining - $applied, 2);
                $allocations[] = [
                    'allocation_type' => 2,
                    'purchase_id' => null,
                    'purchase_ref' => null,
                    'label' => 'Opening Balance',
                    'pending_before' => $before,
                    'allocated_amount' => round($applied, 2),
                    'discount_amount' => 0.0,
                    'payment_amount' => round($applied, 2),
                    'pending_after' => $openingPending,
                ];
            }
        } elseif ($type === 3) {
            $before = (float)$purchases[$targetPurchaseId]['pending'];
            $purchases[$targetPurchaseId]['paid'] = round((float)$purchases[$targetPurchaseId]['paid'] + $settlementAmount, 2);
            $purchases[$targetPurchaseId]['pending'] = round($before - $settlementAmount, 2);
            $remaining = 0.0;
            $allocations[] = [
                'allocation_type' => 1,
                'purchase_id' => $targetPurchaseId,
                'purchase_ref' => $purchases[$targetPurchaseId]['ref'],
                'label' => $purchases[$targetPurchaseId]['purchase_no'],
                'pending_before' => $before,
                'allocated_amount' => $settlementAmount,
                'discount_amount' => 0.0,
                'payment_amount' => $settlementAmount,
                'pending_after' => (float)$purchases[$targetPurchaseId]['pending'],
            ];
        } else {
            if ($openingPending > 0.001 && $remaining > 0.001) {
                $before = $openingPending;
                $applied = min($openingPending, $remaining);
                $openingPending = round($openingPending - $applied, 2);
                $remaining = round($remaining - $applied, 2);
                $allocations[] = [
                    'allocation_type' => 2,
                    'purchase_id' => null,
                    'purchase_ref' => null,
                    'label' => 'Opening Balance',
                    'pending_before' => $before,
                    'allocated_amount' => round($applied, 2),
                    'discount_amount' => 0.0,
                    'payment_amount' => round($applied, 2),
                    'pending_after' => $openingPending,
                ];
            }

            foreach ($purchaseOrder as $purchaseId) {
                if ($remaining <= 0.001) break;
                $pending = (float)$purchases[$purchaseId]['pending'];
                if ($pending <= 0.001) continue;
                $before = $pending;
                $applied = min($pending, $remaining);
                $purchases[$purchaseId]['paid'] = round((float)$purchases[$purchaseId]['paid'] + $applied, 2);
                $purchases[$purchaseId]['pending'] = round($pending - $applied, 2);
                $remaining = round($remaining - $applied, 2);
                $allocations[] = [
                    'allocation_type' => 1,
                    'purchase_id' => $purchaseId,
                    'purchase_ref' => $purchases[$purchaseId]['ref'],
                    'label' => $purchases[$purchaseId]['purchase_no'],
                    'pending_before' => $before,
                    'allocated_amount' => round($applied, 2),
                    'discount_amount' => 0.0,
                    'payment_amount' => round($applied, 2),
                    'pending_after' => (float)$purchases[$purchaseId]['pending'],
                ];
            }
        }

        if ($remaining > 0.009) json_error($paymentNo . ' could not be fully allocated after recalculation.', 422);

        // Actual money settles the oldest targets first. Discount is assigned to the
        // last portion of the settlement so the audit trail clearly separates cash vs waiver.
        $discountRemaining = $discountAmount;
        for ($i = count($allocations) - 1; $i >= 0 && $discountRemaining > 0.001; $i--) {
            $part = min((float)$allocations[$i]['allocated_amount'], $discountRemaining);
            $allocations[$i]['discount_amount'] = round($part, 2);
            $allocations[$i]['payment_amount'] = round((float)$allocations[$i]['allocated_amount'] - $part, 2);
            $discountRemaining = round($discountRemaining - $part, 2);
        }

        $allocationsByPayment[$paymentId] = $allocations;
        $discountsByPayment[$paymentId] = $discountAmount;
        if ($isCandidate) {
            $candidateKey = $paymentId;
            $candidateTargetTotal = $targetTotalBefore;
            $candidateDiscountAmount = $discountAmount;
            $candidateSettlementTotal = $settlementAmount;
        }
    }

    $purchaseOutstanding = 0.0;
    $purchaseRows = [];
    foreach ($purchaseOrder as $purchaseId) {
        $purchase = $purchases[$purchaseId];
        $purchase['paid'] = round((float)$purchase['paid'], 2);
        $purchase['pending'] = max(0.0, round((float)$purchase['pending'], 2));
        $purchaseOutstanding += $purchase['pending'];
        $purchaseRows[] = $purchase;
    }
    $purchaseOutstanding = round($purchaseOutstanding, 2);

    return [
        'supplier' => [
            'id' => (int)$supplier['id'],
            'ref' => encryptReference('supplier', (int)$supplier['id']),
            'supplier_code' => (string)$supplier['supplier_code'],
            'supplier_name' => (string)$supplier['supplier_name'],
            'mobile' => (string)($supplier['mobile'] ?? ''),
            'opening_balance' => (float)$supplier['opening_balance'],
        ],
        'opening_outstanding' => max(0.0, round($openingPending, 2)),
        'purchase_outstanding' => $purchaseOutstanding,
        'overall_outstanding' => max(0.0, round($openingPending + $purchaseOutstanding, 2)),
        'purchases' => $purchaseRows,
        'allocations_by_payment' => $allocationsByPayment,
        'discounts_by_payment' => $discountsByPayment,
        'candidate_allocations' => $candidateKey !== null ? ($allocationsByPayment[$candidateKey] ?? []) : [],
        'candidate_target_total' => $candidateTargetTotal,
        'candidate_discount_amount' => $candidateDiscountAmount,
        'candidate_settlement_total' => $candidateSettlementTotal,
    ];
}

function sp_supplier_context(PDO $pdo, int $branchId, int $supplierId, int $excludePaymentId = 0): array
{
    $simulation = sp_simulate_supplier($pdo, $branchId, $supplierId, null, 0, $excludePaymentId);
    unset($simulation['allocations_by_payment'], $simulation['discounts_by_payment'], $simulation['candidate_allocations'], $simulation['candidate_target_total'], $simulation['candidate_discount_amount'], $simulation['candidate_settlement_total']);
    return $simulation;
}

function sp_replay_supplier(PDO $pdo, int $branchId, int $supplierId): array
{
    $simulation = sp_simulate_supplier($pdo, $branchId, $supplierId);

    $paymentIdsStmt = $pdo->prepare(
        'SELECT id
         FROM supplier_payments
         WHERE branch_id=:branch_id AND supplier_id=:supplier_id AND status=1'
    );
    $paymentIdsStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);
    $paymentIds = array_map('intval', $paymentIdsStmt->fetchAll(PDO::FETCH_COLUMN));

    if ($paymentIds) {
        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $delete = $pdo->prepare('DELETE FROM supplier_payment_allocations WHERE supplier_payment_id IN (' . $placeholders . ')');
        $delete->execute($paymentIds);
    }

    $insert = $pdo->prepare(
        'INSERT INTO supplier_payment_allocations
         (supplier_payment_id,purchase_id,allocation_type,amount,discount_amount)
         VALUES(:supplier_payment_id,:purchase_id,:allocation_type,:amount,:discount_amount)'
    );

    foreach ($simulation['allocations_by_payment'] as $paymentId => $allocations) {
        foreach ($allocations as $allocation) {
            $insert->execute([
                ':supplier_payment_id' => (int)$paymentId,
                ':purchase_id' => $allocation['purchase_id'],
                ':allocation_type' => (int)$allocation['allocation_type'],
                ':amount' => round((float)$allocation['allocated_amount'], 2),
                ':discount_amount' => round((float)($allocation['discount_amount'] ?? 0), 2),
            ]);
        }
    }

    $updateDiscount = $pdo->prepare(
        'UPDATE supplier_payments SET discount_amount=:discount_amount WHERE id=:id AND branch_id=:branch_id'
    );
    foreach ($simulation['discounts_by_payment'] as $paymentId => $discountAmount) {
        if ((int)$paymentId === SP_SYNTHETIC_NEW_ID) continue;
        $updateDiscount->execute([
            ':discount_amount' => round((float)$discountAmount, 2),
            ':id' => (int)$paymentId,
            ':branch_id' => $branchId,
        ]);
    }

    $updatePurchase = $pdo->prepare(
        'UPDATE purchases
         SET payment_status=:payment_status,updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );

    foreach ($simulation['purchases'] as $purchase) {
        $grandTotal = round((float)$purchase['grand_total'], 2);
        $paid = round((float)$purchase['paid'], 2);
        $status = 1;
        if ($paid > 0.009 && $paid + 0.009 < $grandTotal) $status = 2;
        if ($paid + 0.009 >= $grandTotal) $status = 3;

        $updatePurchase->execute([
            ':payment_status' => $status,
            ':id' => (int)$purchase['id'],
            ':branch_id' => $branchId,
        ]);
    }

    return $simulation;
}

function sp_replace_payment_details(
    PDO $pdo,
    int $branchId,
    int $paymentId,
    string $paymentDate,
    ?string $remarks,
    int $userId,
    array $rows
): void {
    $pdo->prepare('DELETE FROM supplier_payment_details WHERE supplier_payment_id=:id')
        ->execute([':id' => $paymentId]);

    $pdo->prepare(
        'DELETE FROM account_transactions
         WHERE branch_id=:branch_id AND source_type=2 AND source_id=:source_id'
    )->execute([':branch_id' => $branchId, ':source_id' => $paymentId]);

    $detailInsert = $pdo->prepare(
        'INSERT INTO supplier_payment_details
         (supplier_payment_id,account_id,payment_mode,amount,reference_no,detail_date)
         VALUES(:supplier_payment_id,:account_id,:payment_mode,:amount,:reference_no,:detail_date)'
    );

    $accountInsert = $pdo->prepare(
        'INSERT INTO account_transactions
         (branch_id,transaction_date,account_id,transaction_type,source_type,source_id,amount,remarks,created_by,created_at)
         VALUES(:branch_id,:transaction_date,:account_id,2,2,:source_id,:amount,:remarks,:created_by,NOW())'
    );

    foreach ($rows as $row) {
        $detailInsert->execute([
            ':supplier_payment_id' => $paymentId,
            ':account_id' => (int)$row['account_id'],
            ':payment_mode' => (int)$row['payment_mode'],
            ':amount' => round((float)$row['amount'], 2),
            ':reference_no' => $row['reference_no'],
            ':detail_date' => $row['detail_date'],
        ]);

        $transactionDate = ($row['detail_date'] ?: $paymentDate) . ' ' . date('H:i:s');
        $accountInsert->execute([
            ':branch_id' => $branchId,
            ':transaction_date' => $transactionDate,
            ':account_id' => (int)$row['account_id'],
            ':source_id' => $paymentId,
            ':amount' => round((float)$row['amount'], 2),
            ':remarks' => $remarks,
            ':created_by' => $userId,
        ]);
    }
}

function sp_payment_record(PDO $pdo, int $branchId, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*,s.supplier_code,s.supplier_name,s.mobile
         FROM supplier_payments p
         INNER JOIN suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
         WHERE p.id=:id AND p.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['status'] !== 1) {
        json_error('Supplier Payment was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['supplier_id'] = (int)$row['supplier_id'];
    $row['payment_type'] = (int)($row['payment_type'] ?: 3);
    $row['target_purchase_id'] = $row['target_purchase_id'] === null ? null : (int)$row['target_purchase_id'];
    $row['amount'] = (float)$row['amount'];
    $row['discount_type'] = (int)($row['discount_type'] ?: 1);
    $row['discount_value'] = (float)($row['discount_value'] ?? 0);
    $row['discount_amount'] = (float)($row['discount_amount'] ?? 0);
    $row['settlement_amount'] = round($row['amount'] + $row['discount_amount'], 2);
    $row['ref'] = encryptReference('supplier_payment', (int)$row['id']);
    $row['supplier_ref'] = encryptReference('supplier', (int)$row['supplier_id']);
    $row['payment_type_ref'] = sp_type_ref((int)$row['payment_type']);

    $detailStmt = $pdo->prepare(
        'SELECT d.id,d.account_id,d.payment_mode,d.amount,d.reference_no,d.detail_date,
                a.account_code,a.account_name,a.account_type
         FROM supplier_payment_details d
         INNER JOIN accounts a ON a.id=d.account_id
         WHERE d.supplier_payment_id=:id
         ORDER BY d.payment_mode,d.id'
    );
    $detailStmt->execute([':id' => $id]);
    $row['payments'] = [];
    foreach ($detailStmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
        $row['payments'][] = [
            'id' => (int)$detail['id'],
            'account_id' => (int)$detail['account_id'],
            'payment_mode' => (int)$detail['payment_mode'],
            'amount' => (float)$detail['amount'],
            'reference_no' => (string)($detail['reference_no'] ?? ''),
            'detail_date' => (string)($detail['detail_date'] ?? ''),
            'account_code' => (string)$detail['account_code'],
            'account_name' => (string)$detail['account_name'],
        ];
    }

    $allocationStmt = $pdo->prepare(
        'SELECT a.allocation_type,a.purchase_id,a.amount,a.discount_amount,p.purchase_no
         FROM supplier_payment_allocations a
         LEFT JOIN purchases p ON p.id=a.purchase_id
         WHERE a.supplier_payment_id=:id
         ORDER BY a.id'
    );
    $allocationStmt->execute([':id' => $id]);
    $row['allocations'] = [];
    $legacyTargetId = null;
    foreach ($allocationStmt->fetchAll(PDO::FETCH_ASSOC) as $allocation) {
        $purchaseId = $allocation['purchase_id'] === null ? null : (int)$allocation['purchase_id'];
        if ($legacyTargetId === null && $purchaseId !== null) $legacyTargetId = $purchaseId;
        $row['allocations'][] = [
            'allocation_type' => (int)$allocation['allocation_type'],
            'purchase_id' => $purchaseId,
            'purchase_no' => (string)($allocation['purchase_no'] ?? ''),
            'amount' => (float)$allocation['amount'],
            'discount_amount' => (float)($allocation['discount_amount'] ?? 0),
            'payment_amount' => round((float)$allocation['amount'] - (float)($allocation['discount_amount'] ?? 0), 2),
        ];
    }

    if ($row['payment_type'] === 3 && !$row['target_purchase_id']) {
        $row['target_purchase_id'] = $legacyTargetId;
    }
    $row['purchase_id'] = $row['target_purchase_id'];
    $row['purchase_ref'] = $row['target_purchase_id']
        ? encryptReference('purchase', (int)$row['target_purchase_id'])
        : '';

    unset($row['id']);
    return $row;
}

function sp_candidate_from_request(
    array $data,
    int $paymentId,
    string $paymentNo,
    string $paymentDate,
    int $paymentType,
    ?int $targetPurchaseId,
    float $amount,
    int $discountType,
    float $discountValue
): array {
    return [
        'id' => $paymentId > 0 ? $paymentId : SP_SYNTHETIC_NEW_ID,
        'payment_no' => $paymentNo !== '' ? $paymentNo : 'New Payment',
        'payment_date' => $paymentDate,
        'payment_type' => $paymentType,
        'target_purchase_id' => $targetPurchaseId,
        'amount' => $amount,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'is_candidate' => true,
    ];
}

function sp_target_purchase_id(PDO $pdo, int $branchId, int $supplierId, int $paymentType, array $data): ?int
{
    if ($paymentType !== 3) return null;

    $purchaseId = sp_ref_to_id($data['purchase_ref'] ?? '', 'purchase', 'Purchase');
    $stmt = $pdo->prepare(
        'SELECT id
         FROM purchases
         WHERE id=:id AND branch_id=:branch_id AND supplier_id=:supplier_id AND status=2
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $purchaseId,
        ':branch_id' => $branchId,
        ':supplier_id' => $supplierId,
    ]);
    if (!$stmt->fetchColumn()) {
        json_error('Selected Invoice is invalid or does not belong to this Supplier.', 422, [
            'purchase_ref' => 'Select a posted Invoice for this Supplier.',
        ]);
    }
    return $purchaseId;
}

$method = request_method();
sp_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission(SP_PERMISSION_PATH, ACTION_VIEW);
    $ctx = sp_context($access['user']);

    $payload = [
        'allowed_actions' => $access['actions'],
        'suppliers' => sp_suppliers($ctx['branch_id']),
        'accounts' => sp_accounts($ctx['branch_id']),
        'payment_types' => [
            ['type' => 1, 'value' => sp_type_ref(1), 'label' => 'Opening Balance'],
            ['type' => 2, 'value' => sp_type_ref(2), 'label' => 'Overall'],
            ['type' => 3, 'value' => sp_type_ref(3), 'label' => 'Invoice'],
        ],
        'today' => date('Y-m-d'),
    ];

    if (!empty($_GET['purchase_ref'])) {
        $purchase = sp_purchase_from_ref(db(), $ctx['branch_id'], (string)$_GET['purchase_ref']);
        $supplierContext = sp_supplier_context(db(), $ctx['branch_id'], (int)$purchase['supplier_id']);
        $pending = 0.0;
        foreach ($supplierContext['purchases'] as $p) {
            if ((int)$p['id'] === (int)$purchase['id']) {
                $pending = (float)$p['pending'];
                break;
            }
        }
        if ($pending <= 0.001) {
            json_error('This Invoice is already fully paid.', 422);
        }
        $purchase['pending'] = $pending;
        $payload['direct_purchase'] = $purchase;
        $payload['supplier_context'] = $supplierContext;
    }

    json_success('Supplier Payment options loaded.', $payload);
}

if ($method === 'GET' && isset($_GET['supplier_context'])) {
    $access = require_permission(SP_PERMISSION_PATH, ACTION_VIEW);
    $ctx = sp_context($access['user']);
    $supplierId = sp_ref_to_id($_GET['supplier_ref'] ?? '', 'supplier', 'Supplier');
    $excludePaymentId = 0;
    if (!empty($_GET['payment_ref'])) {
        $excludePaymentId = sp_ref_to_id($_GET['payment_ref'], 'supplier_payment', 'Supplier Payment');
    }
    json_success(
        'Supplier outstanding loaded.',
        sp_supplier_context(db(), $ctx['branch_id'], $supplierId, $excludePaymentId)
    );
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission(SP_PERMISSION_PATH, ACTION_VIEW);
    $ctx = sp_context($access['user']);
    $id = sp_ref_to_id($_GET['ref'], 'supplier_payment', 'Supplier Payment');
    $record = sp_payment_record(db(), $ctx['branch_id'], $id);

    json_success('Supplier Payment loaded.', [
        'payment' => $record,
        'allowed_actions' => $access['actions'],
        'accounts' => sp_accounts($ctx['branch_id']),
        'payment_types' => [
            ['type' => 1, 'value' => sp_type_ref(1), 'label' => 'Opening Balance'],
            ['type' => 2, 'value' => sp_type_ref(2), 'label' => 'Overall'],
            ['type' => 3, 'value' => sp_type_ref(3), 'label' => 'Invoice'],
        ],
        'supplier_context' => sp_supplier_context(db(), $ctx['branch_id'], (int)$record['supplier_id'], $id),
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission(SP_PERMISSION_PATH, ACTION_VIEW);
    $ctx = sp_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $typeFilter = trim((string)($_GET['payment_type'] ?? ''));
    $modeFilter = trim((string)($_GET['payment_mode'] ?? ''));

    $supplierId = 0;
    if (!empty($_GET['supplier_ref'])) {
        $supplierId = sp_ref_to_id($_GET['supplier_ref'], 'supplier', 'Supplier');
    }

    $where = ['sp.branch_id=:branch_id', 'sp.status=1'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $term = '%' . $search . '%';
        $where[] = '(sp.payment_no LIKE :s_no
                     OR s.supplier_code LIKE :s_code
                     OR s.supplier_name LIKE :s_name
                     OR p.purchase_no LIKE :s_purchase
                     OR sp.remarks LIKE :s_remarks)';
        $params[':s_no'] = $term;
        $params[':s_code'] = $term;
        $params[':s_name'] = $term;
        $params[':s_purchase'] = $term;
        $params[':s_remarks'] = $term;
    }

    if ($supplierId > 0) {
        $where[] = 'sp.supplier_id=:supplier_id';
        $params[':supplier_id'] = $supplierId;
    }
    if ($dateFrom !== '') {
        $where[] = 'sp.payment_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'sp.payment_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }
    if ($typeFilter !== '' && in_array((int)$typeFilter, [1, 2, 3], true)) {
        $where[] = 'sp.payment_type=:payment_type';
        $params[':payment_type'] = (int)$typeFilter;
    }

    if ($modeFilter !== '') {
        $mode = (int)$modeFilter;
        if (!in_array($mode, [1,2,3,4], true)) {
            json_error('Invalid Payment Mode.', 422);
        }
        $where[] = 'EXISTS (
            SELECT 1
            FROM supplier_payment_details md
            WHERE md.supplier_payment_id=sp.id
              AND md.payment_mode=:payment_mode
        )';
        $params[':payment_mode'] = $mode;
    }

    $from =
        ' FROM supplier_payments sp
          INNER JOIN suppliers s ON s.id=sp.supplier_id AND s.branch_id=sp.branch_id
          LEFT JOIN purchases p ON p.id=sp.target_purchase_id AND p.branch_id=sp.branch_id';

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)' . $from . ' WHERE sp.branch_id=:branch_id AND sp.status=1'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summarySql =
        'SELECT COUNT(*) AS total_payments,
                COALESCE(SUM(sp.amount),0) AS payment_amount,
                COALESCE(SUM(sp.discount_amount),0) AS discount_amount,
                COALESCE(SUM(sp.amount + sp.discount_amount),0) AS settled_amount' .
        $from .
        ' WHERE ' . implode(' AND ', $where);

    $summaryStmt = db()->prepare($summarySql);
    foreach ($params as $key => $value) {
        $type = in_array($key, [':branch_id', ':supplier_id', ':payment_type', ':payment_mode'], true)
            ? PDO::PARAM_INT
            : PDO::PARAM_STR;
        $summaryStmt->bindValue($key, $value, $type);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        0 => 'sp.payment_no',
        1 => 'sp.payment_date',
        2 => 's.supplier_name',
        3 => 'sp.payment_type',
        4 => 'p.purchase_no',
        6 => 'sp.amount',
        7 => 'sp.discount_amount',
        8 => '(sp.amount + sp.discount_amount)',
        9 => 'sp.id',
    ];
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderBy = $columns[$orderIndex] ?? 'sp.payment_date';

    $sql =
        'SELECT sp.id,sp.payment_no,sp.payment_date,sp.payment_type,sp.target_purchase_id,sp.amount,sp.discount_type,sp.discount_value,sp.discount_amount,sp.remarks,
                s.supplier_code,s.supplier_name,
                p.purchase_no,
                (SELECT GROUP_CONCAT(DISTINCT CASE d.payment_mode
                    WHEN 1 THEN \'Cash\'
                    WHEN 2 THEN \'UPI\'
                    WHEN 3 THEN \'Bank\'
                    WHEN 4 THEN \'Cheque\'
                    ELSE \'Payment\' END
                    ORDER BY d.payment_mode SEPARATOR \' + \')
                 FROM supplier_payment_details d
                 WHERE d.supplier_payment_id=sp.id) AS mode_label'
        . $from .
        ' WHERE ' . implode(' AND ', $where) .
        ' ORDER BY ' . $orderBy . ' ' . $orderDir . ',sp.id DESC
          LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $type = in_array($key, [':branch_id', ':supplier_id', ':payment_type', ':payment_mode'], true)
            ? PDO::PARAM_INT
            : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id'];
        $type = (int)($row['payment_type'] ?: 3);

        $against = 'FIFO';
        if ($type === 1) $against = 'Opening Balance';
        if ($type === 3) {
            $against = (string)($row['purchase_no'] ?: 'Invoice');
            if (!$row['purchase_no']) {
                $legacy = db()->prepare(
                    'SELECT p.purchase_no
                     FROM supplier_payment_allocations a
                     INNER JOIN purchases p ON p.id=a.purchase_id
                     WHERE a.supplier_payment_id=:id AND a.purchase_id IS NOT NULL
                     ORDER BY a.id LIMIT 1'
                );
                $legacy->execute([':id' => $id]);
                $against = (string)($legacy->fetchColumn() ?: 'Invoice');
            }
        }

        $ref = encryptReference('supplier_payment', $id);
        $rows[] = [
            'payment_no' => (string)$row['payment_no'],
            'payment_date' => (string)$row['payment_date'],
            'supplier_label' => (string)$row['supplier_code'] . ' - ' . (string)$row['supplier_name'],
            'payment_type' => $type,
            'payment_type_label' => sp_type_label($type),
            'against_label' => $against,
            'mode_label' => (string)($row['mode_label'] ?: '-'),
            'amount' => (float)$row['amount'],
            'discount_type' => (int)($row['discount_type'] ?: 1),
            'discount_value' => (float)($row['discount_value'] ?? 0),
            'discount_amount' => (float)($row['discount_amount'] ?? 0),
            'settlement_amount' => round((float)$row['amount'] + (float)($row['discount_amount'] ?? 0), 2),
            'ref' => $ref,
            'edit_url' => 'supplier-payment.php?ref=' . rawurlencode($ref),
            'view_url' => 'supplier-payment.php?ref=' . rawurlencode($ref) . '&view=1',
        ];
    }

    json_success('Supplier Payments loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_payments' => (int)($summary['total_payments'] ?? 0),
            'payment_amount' => (float)($summary['payment_amount'] ?? 0),
            'discount_amount' => (float)($summary['discount_amount'] ?? 0),
            'settled_amount' => (float)($summary['settled_amount'] ?? 0),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $access['actions'],
        'suppliers' => sp_suppliers($branchId),
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'preview') {
        $access = require_permission(SP_PERMISSION_PATH, ACTION_VIEW);
        $ctx = sp_context($access['user']);
        $pdo = db();

        $supplierId = sp_ref_to_id($data['supplier_ref'] ?? '', 'supplier', 'Supplier');
        sp_supplier($pdo, $ctx['branch_id'], $supplierId, true);

        $paymentType = sp_type_from_ref($data['payment_type_ref'] ?? '');
        $paymentDate = sp_date($data['payment_date'] ?? '', 'payment_date', true);
        $details = sp_parse_payment_details($pdo, $ctx['branch_id'], $data);
        $discountType = sp_discount_type($data['discount_type'] ?? 1);
        $discountValue = sp_discount_value($data['discount_value'] ?? 0, $discountType);
        $targetPurchaseId = sp_target_purchase_id($pdo, $ctx['branch_id'], $supplierId, $paymentType, $data);

        $paymentId = 0;
        $paymentNo = 'New Payment';
        if (!empty($data['ref'])) {
            $paymentId = sp_ref_to_id($data['ref'], 'supplier_payment', 'Supplier Payment');
            $stmt = $pdo->prepare(
                'SELECT payment_no,supplier_id
                 FROM supplier_payments
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1'
            );
            $stmt->execute([':id' => $paymentId, ':branch_id' => $ctx['branch_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) json_error('Supplier Payment was not found.', 404);
            $paymentNo = (string)$old['payment_no'];
        }

        $candidate = sp_candidate_from_request(
            $data,
            $paymentId,
            $paymentNo,
            $paymentDate,
            $paymentType,
            $targetPurchaseId,
            $details['total'],
            $discountType,
            $discountValue
        );

        $simulation = sp_simulate_supplier(
            $pdo,
            $ctx['branch_id'],
            $supplierId,
            $candidate,
            $paymentId,
            0
        );

        json_success('Supplier Payment preview calculated.', [
            'targets_total' => (float)$simulation['candidate_target_total'],
            'actual_payment' => (float)$details['total'],
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => (float)$simulation['candidate_discount_amount'],
            'settlement_total' => (float)$simulation['candidate_settlement_total'],
            'remaining_outstanding' => max(0.0, round((float)$simulation['candidate_target_total'] - (float)$simulation['candidate_settlement_total'], 2)),
            'allocations' => $simulation['candidate_allocations'],
            'final_context' => [
                'opening_outstanding' => $simulation['opening_outstanding'],
                'purchase_outstanding' => $simulation['purchase_outstanding'],
                'overall_outstanding' => $simulation['overall_outstanding'],
            ],
        ]);
    }

    if ($action === 'delete') {
        $access = require_permission(SP_PERMISSION_PATH, ACTION_CANCEL);
        $ctx = sp_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $id = sp_ref_to_id($data['ref'] ?? '', 'supplier_payment', 'Supplier Payment');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM supplier_payments
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) json_error('Supplier Payment was not found.', 404);

            $supplierId = (int)$old['supplier_id'];

            $pdo->prepare(
                'UPDATE supplier_payments
                 SET status=0
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([':id' => $id, ':branch_id' => $branchId]);

            $pdo->prepare('DELETE FROM supplier_payment_allocations WHERE supplier_payment_id=:id')
                ->execute([':id' => $id]);
            $pdo->prepare('DELETE FROM supplier_payment_details WHERE supplier_payment_id=:id')
                ->execute([':id' => $id]);
            $pdo->prepare(
                'DELETE FROM account_transactions
                 WHERE branch_id=:branch_id AND source_type=2 AND source_id=:id'
            )->execute([':branch_id' => $branchId, ':id' => $id]);

            sp_replay_supplier($pdo, $branchId, $supplierId);

            $pdo->commit();

            audit_log($userId, ACTION_CANCEL, [
                'company_id' => $ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
                'new_data' => ['status' => 0, 'supplier_ledger_replayed' => true],
            ]);

            json_success('Supplier Payment deleted and supplier balances recalculated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Supplier Payment action.', 404);
    }

    $isEdit = !empty($data['ref']);
    $access = require_permission(SP_PERMISSION_PATH, $isEdit ? ACTION_UPDATE : ACTION_CREATE);
    $ctx = sp_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $pdo = db();

    $pdo->beginTransaction();
    try {
        $paymentId = 0;
        $paymentNo = '';
        $old = null;
        $oldSupplierId = 0;

        if ($isEdit) {
            $paymentId = sp_ref_to_id($data['ref'], 'supplier_payment', 'Supplier Payment');
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM supplier_payments
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $paymentId, ':branch_id' => $branchId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) json_error('Supplier Payment was not found.', 404);
            $paymentNo = (string)$old['payment_no'];
            $oldSupplierId = (int)$old['supplier_id'];
        } else {
            $paymentNo = sp_generate_no($pdo, $branchId);
        }

        $supplierId = sp_ref_to_id($data['supplier_ref'] ?? '', 'supplier', 'Supplier');
        sp_supplier($pdo, $branchId, $supplierId, true);

        $paymentType = sp_type_from_ref($data['payment_type_ref'] ?? '');
        $paymentDate = sp_date($data['payment_date'] ?? '', 'payment_date', true);
        $remarks = sp_nullable($data['notes'] ?? null, 255);
        $details = sp_parse_payment_details($pdo, $branchId, $data);
        $discountType = sp_discount_type($data['discount_type'] ?? 1);
        $discountValue = sp_discount_value($data['discount_value'] ?? 0, $discountType);
        $targetPurchaseId = sp_target_purchase_id($pdo, $branchId, $supplierId, $paymentType, $data);

        $candidate = sp_candidate_from_request(
            $data,
            $paymentId,
            $paymentNo,
            $paymentDate,
            $paymentType,
            $targetPurchaseId,
            $details['total'],
            $discountType,
            $discountValue
        );

        // Validate the resulting ledger before any permanent mutation.
        if ($isEdit && $oldSupplierId > 0 && $oldSupplierId !== $supplierId) {
            sp_simulate_supplier($pdo, $branchId, $oldSupplierId, null, 0, $paymentId);
            $candidateSimulation = sp_simulate_supplier($pdo, $branchId, $supplierId, $candidate, 0, 0);
        } else {
            $candidateSimulation = sp_simulate_supplier($pdo, $branchId, $supplierId, $candidate, $paymentId, 0);
        }
        $computedDiscountAmount = round((float)$candidateSimulation['candidate_discount_amount'], 2);

        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE supplier_payments
                 SET payment_date=:payment_date,
                     supplier_id=:supplier_id,
                     payment_type=:payment_type,
                     target_purchase_id=:target_purchase_id,
                     amount=:amount,
                     discount_type=:discount_type,
                     discount_value=:discount_value,
                     discount_amount=:discount_amount,
                     remarks=:remarks,
                     status=1
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':payment_date' => $paymentDate,
                ':supplier_id' => $supplierId,
                ':payment_type' => $paymentType,
                ':target_purchase_id' => $targetPurchaseId,
                ':amount' => $details['total'],
                ':discount_type' => $discountType,
                ':discount_value' => $discountValue,
                ':discount_amount' => $computedDiscountAmount,
                ':remarks' => $remarks,
                ':id' => $paymentId,
                ':branch_id' => $branchId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO supplier_payments
                 (branch_id,payment_no,payment_date,supplier_id,payment_type,target_purchase_id,amount,discount_type,discount_value,discount_amount,remarks,status,created_by,created_at)
                 VALUES
                 (:branch_id,:payment_no,:payment_date,:supplier_id,:payment_type,:target_purchase_id,:amount,:discount_type,:discount_value,:discount_amount,:remarks,1,:created_by,NOW())'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':supplier_id' => $supplierId,
                ':payment_type' => $paymentType,
                ':target_purchase_id' => $targetPurchaseId,
                ':amount' => $details['total'],
                ':discount_type' => $discountType,
                ':discount_value' => $discountValue,
                ':discount_amount' => $computedDiscountAmount,
                ':remarks' => $remarks,
                ':created_by' => $userId,
            ]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        sp_replace_payment_details(
            $pdo,
            $branchId,
            $paymentId,
            $paymentDate,
            $remarks,
            $userId,
            $details['rows']
        );

        if ($isEdit && $oldSupplierId > 0 && $oldSupplierId !== $supplierId) {
            sp_replay_supplier($pdo, $branchId, $oldSupplierId);
        }
        sp_replay_supplier($pdo, $branchId, $supplierId);

        $pdo->commit();

        audit_log($userId, $isEdit ? ACTION_UPDATE : ACTION_CREATE, [
            'company_id' => $ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $paymentId,
            'old_data' => $old,
            'new_data' => [
                'payment_no' => $paymentNo,
                'supplier_id' => $supplierId,
                'payment_type' => $paymentType,
                'target_purchase_id' => $targetPurchaseId,
                'amount' => $details['total'],
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $computedDiscountAmount,
                'settlement_amount' => round($details['total'] + $computedDiscountAmount, 2),
                'supplier_ledger_replayed' => true,
            ],
        ]);

        json_success(
            $isEdit ? 'Supplier Payment updated and balances recalculated successfully.' : 'Supplier Payment posted successfully.',
            ['payment' => sp_payment_record(db(), $branchId, $paymentId)],
            $isEdit ? 200 : 201
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

json_error('Method not allowed.', 405);
