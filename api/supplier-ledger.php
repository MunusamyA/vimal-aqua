<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);

const SL_PERMISSION_PATH = 'supplier-ledger.php';

function sl_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Supplier Ledger is available only for tenant users.', 403);
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

function sl_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));
    return $stmt && (bool)$stmt->fetchColumn();
}

function sl_ref_to_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error($label . ' reference is required.', 422);
    }

    try {
        $id = (int)decryptReference(trim($value), $purpose);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    if ($id < 1) json_error('Invalid ' . $label . ' reference.', 422);
    return $id;
}

function sl_optional_date($value, string $field): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (!$date || ($errors !== false && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field => 'Enter a valid date.']);
    }

    return $value;
}

function sl_suppliers(int $branchId, int $selectedSupplierId = 0): array
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
            'ref' => encryptReference('supplier', (int)$row['id']),
            'supplier_code' => (string)$row['supplier_code'],
            'supplier_name' => (string)$row['supplier_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'selected' => $selectedSupplierId > 0 && (int)$row['id'] === $selectedSupplierId,
        ];
    }
    return $rows;
}

function sl_supplier(PDO $pdo, int $branchId, int $supplierId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,supplier_code,supplier_name,contact_person,mobile,email,gstin,pan,address,opening_balance,status
         FROM suppliers
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $supplierId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_error('Supplier was not found in your branch.', 404);

    return [
        'id' => (int)$row['id'],
        'ref' => encryptReference('supplier', (int)$row['id']),
        'supplier_code' => (string)$row['supplier_code'],
        'supplier_name' => (string)$row['supplier_name'],
        'contact_person' => (string)($row['contact_person'] ?? ''),
        'mobile' => (string)($row['mobile'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'gstin' => (string)($row['gstin'] ?? ''),
        'pan' => (string)($row['pan'] ?? ''),
        'address' => (string)($row['address'] ?? ''),
        'opening_balance' => round((float)$row['opening_balance'], 2),
        'status' => (int)$row['status'],
    ];
}

function sl_payment_type_label(int $type): string
{
    if ($type === 1) return 'Opening Balance';
    if ($type === 2) return 'Overall FIFO';
    if ($type === 3) return 'Invoice';
    return 'Supplier Payment';
}

function sl_payment_mode_labels(PDO $pdo, int $branchId, int $supplierId): array
{
    $stmt = $pdo->prepare(
        "SELECT d.supplier_payment_id,
                GROUP_CONCAT(DISTINCT
                    CASE d.payment_mode
                        WHEN 1 THEN 'Cash'
                        WHEN 2 THEN 'UPI'
                        WHEN 3 THEN 'Bank Transfer'
                        WHEN 4 THEN 'Cheque'
                        ELSE 'Payment'
                    END
                    ORDER BY d.payment_mode SEPARATOR ' + '
                ) AS mode_label
         FROM supplier_payment_details d
         INNER JOIN supplier_payments sp
            ON sp.id=d.supplier_payment_id
           AND sp.branch_id=:branch_id
           AND sp.supplier_id=:supplier_id
           AND sp.status=1
         GROUP BY d.supplier_payment_id"
    );
    $stmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['supplier_payment_id']] = (string)($row['mode_label'] ?: '-');
    }
    return $map;
}

function sl_current_outstanding(PDO $pdo, int $branchId, int $supplierId, float $openingBalance): array
{
    $openingStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(a.amount),0)
         FROM supplier_payment_allocations a
         INNER JOIN supplier_payments sp
            ON sp.id=a.supplier_payment_id
           AND sp.branch_id=:branch_id
           AND sp.supplier_id=:supplier_id
           AND sp.status=1
         WHERE a.allocation_type=2'
    );
    $openingStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);
    $openingSettled = round((float)$openingStmt->fetchColumn(), 2);
    $openingPending = max(0.0, round($openingBalance - $openingSettled, 2));

    $purchaseStmt = $pdo->prepare(
        'SELECT p.id,p.purchase_no,p.purchase_date,p.supplier_invoice_no,p.grand_total,
                COALESCE(SUM(CASE WHEN sp.status=1 AND a.allocation_type=1 THEN a.amount ELSE 0 END),0) AS settled_amount
         FROM purchases p
         LEFT JOIN supplier_payment_allocations a
            ON a.purchase_id=p.id
           AND a.allocation_type=1
         LEFT JOIN supplier_payments sp
            ON sp.id=a.supplier_payment_id
           AND sp.branch_id=p.branch_id
         WHERE p.branch_id=:branch_id
           AND p.supplier_id=:supplier_id
           AND p.status=2
         GROUP BY p.id,p.purchase_no,p.purchase_date,p.supplier_invoice_no,p.grand_total
         ORDER BY p.purchase_date,p.id'
    );
    $purchaseStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

    $purchaseOutstanding = 0.0;
    $pendingRows = [];

    foreach ($purchaseStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $total = round((float)$row['grand_total'], 2);
        $settled = min($total, max(0.0, round((float)$row['settled_amount'], 2)));
        $pending = max(0.0, round($total - $settled, 2));
        $purchaseOutstanding = round($purchaseOutstanding + $pending, 2);

        if ($pending > 0.009) {
            $ref = encryptReference('purchase', (int)$row['id']);
            $pendingRows[] = [
                'purchase_no' => (string)$row['purchase_no'],
                'purchase_date' => (string)$row['purchase_date'],
                'supplier_invoice_no' => (string)($row['supplier_invoice_no'] ?? ''),
                'grand_total' => $total,
                'settled_amount' => $settled,
                'pending_amount' => $pending,
                'purchase_ref' => $ref,
                'pay_url' => 'supplier-payment.php?purchase_ref=' . rawurlencode($ref),
            ];
        }
    }

    return [
        'opening_pending' => $openingPending,
        'purchase_outstanding' => $purchaseOutstanding,
        'overall_outstanding' => round($openingPending + $purchaseOutstanding, 2),
        'pending_purchases' => $pendingRows,
    ];
}

function sl_payment_actions(array $user): array
{
    if (!function_exists('menu_by_path') || !function_exists('effective_actions_for_menu')) return [];
    $menu = menu_by_path('supplier-payment-list.php');
    if (!$menu) return [];
    return array_values(array_map('intval', effective_actions_for_menu($user, $menu)));
}

$method = request_method();

if ($method !== 'GET') {
    json_error('Method not allowed.', 405);
}

$access = require_permission(SL_PERMISSION_PATH, ACTION_VIEW);
$ctx = sl_context($access['user']);
$branchId = (int)$ctx['branch_id'];
$pdo = db();

if (isset($_GET['options'])) {
    json_success('Supplier Ledger options loaded.', [
        'suppliers' => sl_suppliers($branchId),
        'allowed_actions' => $access['actions'],
        'payment_actions' => sl_payment_actions($access['user']),
    ]);
}

if (!isset($_GET['datatable'])) {
    json_error('Unsupported Supplier Ledger request.', 404);
}

$draw = max(0, (int)($_GET['draw'] ?? 0));
$start = max(0, (int)($_GET['start'] ?? 0));
$length = max(1, min(100000, (int)($_GET['length'] ?? 25)));
$search = trim((string)($_GET['search']['value'] ?? ''));
$transactionType = strtolower(trim((string)($_GET['transaction_type'] ?? '')));
$dateFrom = sl_optional_date($_GET['date_from'] ?? '', 'date_from');
$dateTo = sl_optional_date($_GET['date_to'] ?? '', 'date_to');

if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
    json_error('From Date cannot be after To Date.', 422, [
        'date_from' => 'From Date cannot be after To Date.',
    ]);
}

$suppliers = sl_suppliers($branchId);
$paymentActions = sl_payment_actions($access['user']);

if (empty($_GET['supplier_ref'])) {
    json_success('Select a Supplier to view the ledger.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ],
        'supplier' => null,
        'summary' => [
            'current' => [
                'opening_pending' => 0,
                'purchase_outstanding' => 0,
                'overall_outstanding' => 0,
            ],
            'period' => [
                'opening_brought_forward' => 0,
                'purchases' => 0,
                'actual_payments' => 0,
                'discount_settlement' => 0,
                'total_credit' => 0,
                'closing_balance' => 0,
            ],
        ],
        'pending_purchases' => [],
        'suppliers' => $suppliers,
        'allowed_actions' => $access['actions'],
        'payment_actions' => $paymentActions,
    ]);
}

$supplierId = sl_ref_to_id($_GET['supplier_ref'], 'supplier', 'Supplier');
$suppliers = sl_suppliers($branchId, $supplierId);
$supplier = sl_supplier($pdo, $branchId, $supplierId);
$hasDiscount = sl_column_exists($pdo, 'supplier_payments', 'discount_amount');
$modeMap = sl_payment_mode_labels($pdo, $branchId, $supplierId);
$current = sl_current_outstanding($pdo, $branchId, $supplierId, (float)$supplier['opening_balance']);

$purchaseStmt = $pdo->prepare(
    'SELECT id,purchase_no,purchase_date,supplier_invoice_no,grand_total
     FROM purchases
     WHERE branch_id=:branch_id
       AND supplier_id=:supplier_id
       AND status=2
     ORDER BY purchase_date,id'
);
$purchaseStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

$transactions = [];
foreach ($purchaseStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $transactions[] = [
        'date' => (string)$row['purchase_date'],
        'sort_type' => 1,
        'sort_id' => (int)$row['id'],
        'type_key' => 'purchase',
        'reference' => (string)$row['purchase_no'],
        'transaction_label' => 'Purchase',
        'against' => (string)($row['supplier_invoice_no'] !== null && $row['supplier_invoice_no'] !== ''
            ? 'Supplier Invoice: ' . $row['supplier_invoice_no']
            : 'Posted Purchase'),
        'description' => 'Purchase liability',
        'mode_label' => '-',
        'debit' => round((float)$row['grand_total'], 2),
        'actual_payment' => 0.0,
        'discount' => 0.0,
        'credit' => 0.0,
        'running_balance' => 0.0,
    ];
}

$discountSelect = $hasDiscount ? 'sp.discount_amount' : '0';
$paymentStmt = $pdo->prepare(
    'SELECT sp.id,sp.payment_no,sp.payment_date,sp.payment_type,sp.target_purchase_id,sp.amount,' . $discountSelect . ' AS discount_amount,
            sp.remarks,tp.purchase_no AS target_purchase_no,tp.supplier_invoice_no AS target_supplier_invoice_no
     FROM supplier_payments sp
     LEFT JOIN purchases tp
        ON tp.id=sp.target_purchase_id
       AND tp.branch_id=sp.branch_id
     WHERE sp.branch_id=:branch_id
       AND sp.supplier_id=:supplier_id
       AND sp.status=1
     ORDER BY sp.payment_date,sp.id'
);
$paymentStmt->execute([':branch_id' => $branchId, ':supplier_id' => $supplierId]);

foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $type = (int)$row['payment_type'];
    $actual = round((float)$row['amount'], 2);
    $discount = round((float)$row['discount_amount'], 2);

    if ($type === 1) {
        $against = 'Opening Balance';
    } elseif ($type === 2) {
        $against = 'Overall FIFO';
    } elseif ($type === 3 && !empty($row['target_purchase_no'])) {
        $against = 'Invoice: ' . (string)$row['target_purchase_no'];
        if (!empty($row['target_supplier_invoice_no'])) {
            $against .= ' / ' . (string)$row['target_supplier_invoice_no'];
        }
    } else {
        $against = sl_payment_type_label($type);
    }

    $transactions[] = [
        'date' => (string)$row['payment_date'],
        'sort_type' => 2,
        'sort_id' => (int)$row['id'],
        'type_key' => 'payment',
        'reference' => (string)$row['payment_no'],
        'transaction_label' => sl_payment_type_label($type) . ' Payment',
        'against' => $against,
        'description' => (string)($row['remarks'] ?? ''),
        'mode_label' => $modeMap[(int)$row['id']] ?? '-',
        'debit' => 0.0,
        'actual_payment' => $actual,
        'discount' => $discount,
        'credit' => round($actual + $discount, 2),
        'running_balance' => 0.0,
    ];
}

usort($transactions, function(array $a, array $b): int {
    if ($a['date'] !== $b['date']) return strcmp($a['date'], $b['date']);
    if ((int)$a['sort_type'] !== (int)$b['sort_type']) return (int)$a['sort_type'] <=> (int)$b['sort_type'];
    return (int)$a['sort_id'] <=> (int)$b['sort_id'];
});

$running = round((float)$supplier['opening_balance'], 2);
$openingBroughtForward = $running;
$periodRows = [];
$periodPurchases = 0.0;
$periodPayments = 0.0;
$periodDiscounts = 0.0;
$periodCredits = 0.0;

foreach ($transactions as &$tx) {
    $running = round($running + (float)$tx['debit'] - (float)$tx['credit'], 2);
    $tx['running_balance'] = $running;

    if ($dateFrom !== null && $tx['date'] < $dateFrom) {
        $openingBroughtForward = $running;
        continue;
    }
    if ($dateTo !== null && $tx['date'] > $dateTo) {
        continue;
    }

    $periodRows[] = $tx;
    $periodPurchases = round($periodPurchases + (float)$tx['debit'], 2);
    $periodPayments = round($periodPayments + (float)$tx['actual_payment'], 2);
    $periodDiscounts = round($periodDiscounts + (float)$tx['discount'], 2);
    $periodCredits = round($periodCredits + (float)$tx['credit'], 2);
}
unset($tx);

$closingBalance = round($openingBroughtForward + $periodPurchases - $periodCredits, 2);

$recordsTotal = count($periodRows);
$filteredRows = [];
$needle = mb_strtolower($search);

foreach ($periodRows as $row) {
    if ($transactionType !== '' && $row['type_key'] !== $transactionType) continue;

    if ($needle !== '') {
        $haystack = mb_strtolower(
            (string)$row['reference'] . ' ' .
            (string)$row['transaction_label'] . ' ' .
            (string)$row['against'] . ' ' .
            (string)$row['description'] . ' ' .
            (string)$row['mode_label']
        );
        if (mb_strpos($haystack, $needle) === false) continue;
    }

    $filteredRows[] = $row;
}

/*
 * Always show Opening Balance / Brought Forward as the first ledger row
 * and Closing Balance as the final ledger row. These are real DataTable
 * rows, so they are also included in Copy/CSV/Excel/PDF/Print exports.
 */
$openingDate = $dateFrom ?? '';
$closingDate = $dateTo ?? '';
if ($closingDate === '' && !empty($periodRows)) {
    $lastPeriodRow = $periodRows[count($periodRows) - 1];
    $closingDate = (string)($lastPeriodRow['date'] ?? '');
}

$openingRow = [
    'date' => $openingDate,
    'reference' => 'OPENING',
    'transaction_label' => $dateFrom !== null ? 'Opening / Brought Forward' : 'Opening Balance',
    'against' => $dateFrom !== null ? 'Balance brought forward before selected From Date' : 'Supplier opening balance',
    'description' => '',
    'mode_label' => '-',
    'debit' => 0.0,
    'actual_payment' => 0.0,
    'discount' => 0.0,
    'credit' => 0.0,
    'running_balance' => round($openingBroughtForward, 2),
    'row_type' => 'opening_balance',
];

$closingRow = [
    'date' => $closingDate,
    'reference' => 'CLOSING',
    'transaction_label' => 'Closing Balance',
    'against' => $dateTo !== null ? 'Balance as of selected To Date' : 'Closing supplier payable balance',
    'description' => '',
    'mode_label' => '-',
    'debit' => 0.0,
    'actual_payment' => 0.0,
    'discount' => 0.0,
    'credit' => 0.0,
    'running_balance' => round($closingBalance, 2),
    'row_type' => 'closing_balance',
];

$displayRows = array_merge([$openingRow], $filteredRows, [$closingRow]);
$recordsFiltered = count($displayRows);
$recordsTotal = count($periodRows) + 2;
$pageRows = array_slice($displayRows, $start, $length);

foreach ($pageRows as &$row) {
    unset($row['sort_type'], $row['sort_id'], $row['type_key']);
}
unset($row);

json_success('Supplier Ledger loaded.', [
    'datatable' => [
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $pageRows,
    ],
    'supplier' => $supplier,
    'summary' => [
        'current' => [
            'opening_pending' => $current['opening_pending'],
            'purchase_outstanding' => $current['purchase_outstanding'],
            'overall_outstanding' => $current['overall_outstanding'],
        ],
        'period' => [
            'opening_brought_forward' => round($openingBroughtForward, 2),
            'purchases' => round($periodPurchases, 2),
            'actual_payments' => round($periodPayments, 2),
            'discount_settlement' => round($periodDiscounts, 2),
            'total_credit' => round($periodCredits, 2),
            'closing_balance' => round($closingBalance, 2),
        ],
    ],
    'pending_purchases' => $current['pending_purchases'],
    'suppliers' => $suppliers,
    'allowed_actions' => $access['actions'],
    'payment_actions' => $paymentActions,
]);
