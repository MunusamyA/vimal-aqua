<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);

function report_permission_path(string $report): string
{
    $map = [
        'sales' => 'sales-report.php',
        'productwise' => 'productwise-report.php',
        'gst' => 'gst-report.php',
        'daily_ledger' => 'daily-ledger.php',
        'account_ledger' => 'account-ledger.php',
        'stock' => 'stock-details-report.php',
    ];

    if (!isset($map[$report])) {
        json_error('Invalid report.', 422);
    }

    return $map[$report];
}

function report_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Reports are available only for tenant users.', 403);
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

function report_date($value, string $label): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        ($errors && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) ||
        $date->format('Y-m-d') !== $value
    ) {
        json_error('Enter a valid ' . $label . '.', 422);
    }

    return $value;
}

function report_date_range(): array
{
    $from = report_date($_GET['date_from'] ?? null, 'From Date');
    $to = report_date($_GET['date_to'] ?? null, 'To Date');

    if ($from !== null && $to !== null && $from > $to) {
        json_error('From Date cannot be after To Date.', 422);
    }

    return [$from, $to];
}

function report_ref_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value) === '') return 0;

    try {
        $id = (int)decryptReference(trim($value), $purpose);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    if ($id < 1) json_error('Invalid ' . $label . ' reference.', 422);
    return $id;
}

function report_dt(): array
{
    $requestedLength = (int)($_GET['length'] ?? 25);
    // DataTables export helpers commonly request length=-1 for "all rows".
    // Treat that as a safe large export instead of clamping it to one row.
    $length = $requestedLength < 0 ? 100000 : max(1, min(100000, $requestedLength));

    return [
        'draw' => max(0, (int)($_GET['draw'] ?? 0)),
        'start' => max(0, (int)($_GET['start'] ?? 0)),
        'length' => $length,
        'search' => trim((string)($_GET['search']['value'] ?? '')),
        'order_index' => max(0, (int)($_GET['order'][0]['column'] ?? 0)),
        'order_dir' => strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC',
    ];
}

function report_bind(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
        }
    }
}

function report_customers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,customer_code,customer_name
         FROM customers
         WHERE branch_id=:branch_id AND status=1
         ORDER BY customer_name,customer_code'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('customer', (int)$row['id']),
            'customer_code' => (string)$row['customer_code'],
            'customer_name' => (string)$row['customer_name'],
        ];
    }
    return $rows;
}

function report_products(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT p.id,p.product_code,p.product_name,c.category_name
         FROM products p
         INNER JOIN categories c ON c.id=p.category_id AND c.branch_id=p.branch_id
         WHERE p.branch_id=:branch_id AND p.status=1
         ORDER BY p.product_name,p.product_code'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('product', (int)$row['id']),
            'product_code' => (string)$row['product_code'],
            'product_name' => (string)$row['product_name'],
            'category_name' => (string)$row['category_name'],
        ];
    }
    return $rows;
}

function report_categories(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,category_code,category_name
         FROM categories
         WHERE branch_id=:branch_id AND status=1
         ORDER BY category_name,category_code'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('category', (int)$row['id']),
            'category_code' => (string)$row['category_code'],
            'category_name' => (string)$row['category_name'],
        ];
    }
    return $rows;
}

function report_accounts(int $branchId): array
{
    $labels = [1 => 'Cash', 2 => 'Bank', 3 => 'UPI', 4 => 'Card', 5 => 'Other'];
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,opening_balance
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name,account_code'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (int)$row['account_type'];
        $rows[] = [
            'ref' => encryptReference('account', (int)$row['id']),
            'account_code' => (string)$row['account_code'],
            'account_name' => (string)$row['account_name'],
            'account_type' => $type,
            'account_type_label' => $labels[$type] ?? 'Other',
            'opening_balance' => (float)$row['opening_balance'],
        ];
    }
    return $rows;
}

function report_source_label(int $sourceType): string
{
    return [
        1 => 'Customer Payment',
        2 => 'Supplier Payment',
        3 => 'Expense',
        4 => 'Opening Balance',
        5 => 'Adjustment',
    ][$sourceType] ?? 'Transaction';
}

function report_account_source_reference(array $row): string
{
    $sourceType = (int)($row['source_type'] ?? 0);
    $sourceId = (int)($row['source_id'] ?? 0);

    if ($sourceType === 1 && !empty($row['customer_payment_no'])) return (string)$row['customer_payment_no'];
    if ($sourceType === 2 && !empty($row['supplier_payment_no'])) return (string)$row['supplier_payment_no'];
    if ($sourceType === 3 && $sourceId > 0) return 'EXP' . str_pad((string)$sourceId, 4, '0', STR_PAD_LEFT);
    if ($sourceType === 4) return 'Opening Balance';
    if ($sourceType === 5 && $sourceId > 0) return 'Adjustment #' . $sourceId;
    return $sourceId > 0 ? '#' . $sourceId : '-';
}

function report_sales(int $branchId, array $actions): void
{
    $dt = report_dt();
    [$dateFrom, $dateTo] = report_date_range();

    $where = ['s.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($dt['search'] !== '') {
        $like = '%' . $dt['search'] . '%';
        $where[] = '(s.sale_no LIKE :search_sale OR c.customer_code LIKE :search_code OR c.customer_name LIKE :search_name OR s.remarks LIKE :search_remarks)';
        $params[':search_sale'] = $like;
        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_remarks'] = $like;
    }

    $customerId = report_ref_id($_GET['customer_ref'] ?? '', 'customer', 'Customer');
    if ($customerId > 0) {
        $where[] = 's.customer_id=:customer_id';
        $params[':customer_id'] = $customerId;
    }

    foreach ([
        'tax_mode' => [0,1],
        'document_type' => [1,2,3],
        'payment_status' => [1,2,3],
        'status' => [1,2,3],
    ] as $key => $allowed) {
        $raw = trim((string)($_GET[$key] ?? ''));
        if ($raw === '') continue;
        $value = (int)$raw;
        if (!in_array($value, $allowed, true)) json_error('Invalid ' . str_replace('_', ' ', $key) . '.', 422);
        $where[] = 's.' . $key . '=:' . $key;
        $params[':' . $key] = $value;
    }

    if ($dateFrom !== null) {
        $where[] = 's.sale_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== null) {
        $where[] = 's.sale_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    $from = ' FROM sales s INNER JOIN customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id ';

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM sales WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    report_bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT COUNT(*) AS sale_count,
                COALESCE(SUM(s.grand_total),0) AS grand_total,
                COALESCE(SUM(s.paid_amount),0) AS paid_amount,
                COALESCE(SUM(s.balance_amount),0) AS balance_amount,
                COALESCE(SUM(s.tax_amount),0) AS tax_amount
         ' . $from . ' WHERE ' . implode(' AND ', $where)
    );
    report_bind($summaryStmt, $params);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        0 => 's.sale_no', 1 => 's.sale_date', 2 => 'c.customer_name', 3 => 's.document_type',
        4 => 's.tax_mode', 5 => 's.grand_total', 6 => 's.paid_amount', 7 => 's.balance_amount',
        8 => 's.payment_status', 9 => 's.status', 10 => 's.id',
    ];
    $orderColumn = $columns[$dt['order_index']] ?? 's.sale_date';

    $sql = 'SELECT s.id,s.sale_no,s.sale_date,s.sale_type,s.document_type,s.tax_mode,
                   s.subtotal,s.item_discount_total,s.overall_discount_amount,s.tax_amount,
                   s.other_charges,s.round_off,s.grand_total,s.paid_amount,s.balance_amount,
                   s.payment_status,s.status,c.customer_code,c.customer_name
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderColumn . ' ' . $dt['order_dir'] . ',s.id DESC
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    report_bind($stmt, $params);
    $stmt->bindValue(':start', $dt['start'], PDO::PARAM_INT);
    $stmt->bindValue(':length', $dt['length'], PDO::PARAM_INT);
    $stmt->execute();

    $docLabels = [1 => 'Quotation', 2 => 'Sales Invoice', 3 => 'Customer Order'];
    $saleTypeLabels = [1 => 'Direct Sale', 2 => 'Customer Order', 3 => 'Line Supply'];
    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id'];
        $ref = encryptReference('sale', $id);
        $rows[] = [
            'sale_no' => (string)$row['sale_no'],
            'sale_date' => (string)$row['sale_date'],
            'customer_label' => (string)$row['customer_code'] . ' - ' . (string)$row['customer_name'],
            'sale_type' => (int)$row['sale_type'],
            'sale_type_label' => $saleTypeLabels[(int)$row['sale_type']] ?? 'Sale',
            'document_type' => (int)$row['document_type'],
            'document_type_label' => $docLabels[(int)$row['document_type']] ?? 'Document',
            'tax_mode' => (int)$row['tax_mode'],
            'subtotal' => (float)$row['subtotal'],
            'discount_amount' => round((float)$row['item_discount_total'] + (float)$row['overall_discount_amount'], 2),
            'tax_amount' => (float)$row['tax_amount'],
            'other_charges' => (float)$row['other_charges'],
            'round_off' => (float)$row['round_off'],
            'grand_total' => (float)$row['grand_total'],
            'paid_amount' => (float)$row['paid_amount'],
            'balance_amount' => (float)$row['balance_amount'],
            'payment_status' => (int)$row['payment_status'],
            'status' => (int)$row['status'],
            'view_url' => 'sales.php?ref=' . rawurlencode($ref),
        ];
    }

    json_success('Sales Report loaded.', [
        'datatable' => [
            'draw' => $dt['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'sale_count' => (int)($summary['sale_count'] ?? 0),
            'grand_total' => (float)($summary['grand_total'] ?? 0),
            'paid_amount' => (float)($summary['paid_amount'] ?? 0),
            'balance_amount' => (float)($summary['balance_amount'] ?? 0),
            'tax_amount' => (float)($summary['tax_amount'] ?? 0),
        ],
        'allowed_actions' => $actions,
    ]);
}

function report_productwise(int $branchId, array $actions): void
{
    $dt = report_dt();
    [$dateFrom, $dateTo] = report_date_range();

    $where = ['s.branch_id=:branch_id', 's.status=2'];
    $params = [':branch_id' => $branchId];

    /*
     * Product-wise sales should not be restricted only to document_type=2.
     * By default include posted Sales Invoices (2) and Customer Orders (3).
     * Quotations (1) are excluded because they are not completed sales.
     * A specific document can be selected from the Product-wise report filter.
     */
    $documentRaw = trim((string)($_GET['document_type'] ?? ''));
    if ($documentRaw !== '') {
        $documentType = (int)$documentRaw;
        if (!in_array($documentType, [2,3], true)) {
            json_error('Invalid Document Type.', 422);
        }
        $where[] = 's.document_type=:document_type';
        $params[':document_type'] = $documentType;
    } else {
        $where[] = 's.document_type IN (2,3)';
    }

    if ($dt['search'] !== '') {
        $like = '%' . $dt['search'] . '%';
        $where[] = '(p.product_code LIKE :search_code OR p.product_name LIKE :search_name OR c.category_name LIKE :search_category OR h.hsn_code LIKE :search_hsn)';
        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_category'] = $like;
        $params[':search_hsn'] = $like;
    }

    $productId = report_ref_id($_GET['product_ref'] ?? '', 'product', 'Product');
    if ($productId > 0) {
        $where[] = 'si.product_id=:product_id';
        $params[':product_id'] = $productId;
    }

    $categoryId = report_ref_id($_GET['category_ref'] ?? '', 'category', 'Category');
    if ($categoryId > 0) {
        $where[] = 'p.category_id=:category_id';
        $params[':category_id'] = $categoryId;
    }

    $taxRaw = trim((string)($_GET['tax_mode'] ?? ''));
    if ($taxRaw !== '') {
        $taxMode = (int)$taxRaw;
        if (!in_array($taxMode, [0,1], true)) json_error('Invalid Tax Mode.', 422);
        $where[] = 's.tax_mode=:tax_mode';
        $params[':tax_mode'] = $taxMode;
    }

    if ($dateFrom !== null) {
        $where[] = 's.sale_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== null) {
        $where[] = 's.sale_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    $from = ' FROM sales_items si
              INNER JOIN sales s ON s.id=si.sale_id
              INNER JOIN products p ON p.id=si.product_id AND p.branch_id=s.branch_id
              INNER JOIN categories c ON c.id=p.category_id AND c.branch_id=p.branch_id
              LEFT JOIN hsn_master h ON h.id=p.hsn_id AND h.branch_id=p.branch_id
              LEFT JOIN product_units ppu ON ppu.product_id=p.id AND ppu.unit_type=1 AND ppu.status=1
              LEFT JOIN units u ON u.id=ppu.unit_id AND u.branch_id=p.branch_id ';

    $countStmt = db()->prepare('SELECT COUNT(DISTINCT p.id)' . $from . ' WHERE ' . implode(' AND ', $where));
    report_bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $totalStmt = db()->prepare(
        'SELECT COUNT(*) FROM products WHERE branch_id=:branch_id AND status=1'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT COUNT(DISTINCT s.id) AS invoice_count,
                COUNT(DISTINCT p.id) AS product_count,
                COALESCE(SUM(si.base_qty),0) AS total_qty,
                COALESCE(SUM(si.net_amount-si.tax_amount),0) AS taxable_value,
                COALESCE(SUM(si.tax_amount),0) AS tax_amount,
                COALESCE(SUM(si.net_amount),0) AS net_sales
         ' . $from . ' WHERE ' . implode(' AND ', $where)
    );
    report_bind($summaryStmt, $params);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    /* Match the frontend table exactly:
       0 Code, 1 Product, 2 Category, 3 HSN, 4 Documents, 5 Qty,
       6 Gross, 7 Discount, 8 Taxable, 9 GST, 10 Net Sales. */
    $columns = [
        0 => 'p.product_code',
        1 => 'p.product_name',
        2 => 'c.category_name',
        3 => 'h.hsn_code',
        4 => 'invoice_count',
        5 => 'total_qty',
        6 => 'gross_amount',
        7 => 'discount_amount',
        8 => 'taxable_value',
        9 => 'tax_amount',
        10 => 'net_sales',
    ];
    $orderColumn = $columns[$dt['order_index']] ?? 'p.product_name';

    $sql = 'SELECT p.id,p.product_code,p.product_name,c.category_name,h.hsn_code,
                   COALESCE(u.short_name,u.unit_name,\'\') AS primary_unit,
                   COUNT(DISTINCT s.id) AS invoice_count,
                   SUM(si.base_qty) AS total_qty,
                   SUM(si.gross_amount) AS gross_amount,
                   SUM(si.discount_amount+si.overall_discount_amount) AS discount_amount,
                   SUM(si.net_amount-si.tax_amount) AS taxable_value,
                   SUM(si.tax_amount) AS tax_amount,
                   SUM(si.net_amount) AS net_sales
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY p.id,p.product_code,p.product_name,c.category_name,h.hsn_code,u.short_name,u.unit_name
            ORDER BY ' . $orderColumn . ' ' . $dt['order_dir'] . ',p.id ASC
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    report_bind($stmt, $params);
    $stmt->bindValue(':start', $dt['start'], PDO::PARAM_INT);
    $stmt->bindValue(':length', $dt['length'], PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'product_code' => (string)$row['product_code'],
            'product_name' => (string)$row['product_name'],
            'category_name' => (string)$row['category_name'],
            'hsn_code' => (string)($row['hsn_code'] ?? ''),
            'primary_unit' => (string)($row['primary_unit'] ?? ''),
            'invoice_count' => (int)$row['invoice_count'],
            'total_qty' => (float)$row['total_qty'],
            'gross_amount' => (float)$row['gross_amount'],
            'discount_amount' => (float)$row['discount_amount'],
            'taxable_value' => (float)$row['taxable_value'],
            'tax_amount' => (float)$row['tax_amount'],
            'net_sales' => (float)$row['net_sales'],
        ];
    }

    json_success('Product-wise Report loaded.', [
        'datatable' => [
            'draw' => $dt['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'invoice_count' => (int)($summary['invoice_count'] ?? 0),
            'product_count' => (int)($summary['product_count'] ?? 0),
            'total_qty' => (float)($summary['total_qty'] ?? 0),
            'taxable_value' => (float)($summary['taxable_value'] ?? 0),
            'tax_amount' => (float)($summary['tax_amount'] ?? 0),
            'net_sales' => (float)($summary['net_sales'] ?? 0),
        ],
        'allowed_actions' => $actions,
    ]);
}

function report_gst_subqueries(int $branchId, string $type, ?string $dateFrom, ?string $dateTo, string $search, array &$params): array
{
    $queries = [];

    if ($type === 'sales' || $type === 'both') {
        $where = ['s.branch_id=:gst_sales_branch', 's.status=2', 's.document_type IN (2,3)', 's.tax_mode=1'];
        $params[':gst_sales_branch'] = $branchId;

        if ($dateFrom !== null) {
            $where[] = 's.sale_date>=:gst_sales_from';
            $params[':gst_sales_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where[] = 's.sale_date<=:gst_sales_to';
            $params[':gst_sales_to'] = $dateTo;
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(p.product_code LIKE :gst_sales_search_code OR p.product_name LIKE :gst_sales_search_name OR h.hsn_code LIKE :gst_sales_search_hsn)';
            $params[':gst_sales_search_code'] = $like;
            $params[':gst_sales_search_name'] = $like;
            $params[':gst_sales_search_hsn'] = $like;
        }

        $queries[] = "SELECT 'Sales' AS report_type,
                             COALESCE(h.hsn_code,'No HSN') AS hsn_code,
                             si.tax_percentage AS gst_rate,
                             COUNT(DISTINCT s.id) AS document_count,
                             SUM(si.net_amount-si.tax_amount) AS taxable_value,
                             SUM(si.tax_amount) AS gst_amount,
                             SUM(si.net_amount) AS net_value
                      FROM sales_items si
                      INNER JOIN sales s ON s.id=si.sale_id
                      INNER JOIN products p ON p.id=si.product_id AND p.branch_id=s.branch_id
                      LEFT JOIN hsn_master h ON h.id=p.hsn_id AND h.branch_id=p.branch_id
                      WHERE " . implode(' AND ', $where) . "
                      GROUP BY COALESCE(h.hsn_code,'No HSN'),si.tax_percentage";
    }

    if ($type === 'purchase' || $type === 'both') {
        $where = ['pc.branch_id=:gst_purchase_branch', 'pc.status=2'];
        $params[':gst_purchase_branch'] = $branchId;

        if ($dateFrom !== null) {
            $where[] = 'pc.purchase_date>=:gst_purchase_from';
            $params[':gst_purchase_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where[] = 'pc.purchase_date<=:gst_purchase_to';
            $params[':gst_purchase_to'] = $dateTo;
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(p.product_code LIKE :gst_purchase_search_code OR p.product_name LIKE :gst_purchase_search_name OR h.hsn_code LIKE :gst_purchase_search_hsn)';
            $params[':gst_purchase_search_code'] = $like;
            $params[':gst_purchase_search_name'] = $like;
            $params[':gst_purchase_search_hsn'] = $like;
        }

        $queries[] = "SELECT 'Purchase' AS report_type,
                             COALESCE(h.hsn_code,'No HSN') AS hsn_code,
                             pi.tax_percentage AS gst_rate,
                             COUNT(DISTINCT pc.id) AS document_count,
                             SUM(pi.net_amount-pi.tax_amount) AS taxable_value,
                             SUM(pi.tax_amount) AS gst_amount,
                             SUM(pi.net_amount) AS net_value
                      FROM purchase_items pi
                      INNER JOIN purchases pc ON pc.id=pi.purchase_id
                      INNER JOIN product_units pu ON pu.id=pi.product_unit_id
                      INNER JOIN products p ON p.id=pu.product_id AND p.branch_id=pc.branch_id
                      LEFT JOIN hsn_master h ON h.id=p.hsn_id AND h.branch_id=p.branch_id
                      WHERE " . implode(' AND ', $where) . "
                      GROUP BY COALESCE(h.hsn_code,'No HSN'),pi.tax_percentage";
    }

    return $queries;
}

function report_gst(int $branchId, array $actions): void
{
    $dt = report_dt();
    [$dateFrom, $dateTo] = report_date_range();
    $type = strtolower(trim((string)($_GET['gst_type'] ?? 'both')));
    if (!in_array($type, ['sales','purchase','both'], true)) json_error('Invalid GST Report Type.', 422);

    $params = [];
    $queries = report_gst_subqueries($branchId, $type, $dateFrom, $dateTo, $dt['search'], $params);
    if (!$queries) json_error('No GST report source selected.', 422);

    $union = implode(' UNION ALL ', $queries);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM (' . $union . ') gst_rows');
    report_bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();
    $recordsTotal = $recordsFiltered;

    $summaryStmt = db()->prepare(
        "SELECT COALESCE(SUM(CASE WHEN report_type='Sales' THEN taxable_value ELSE 0 END),0) AS sales_taxable,
                COALESCE(SUM(CASE WHEN report_type='Sales' THEN gst_amount ELSE 0 END),0) AS sales_gst,
                COALESCE(SUM(CASE WHEN report_type='Purchase' THEN taxable_value ELSE 0 END),0) AS purchase_taxable,
                COALESCE(SUM(CASE WHEN report_type='Purchase' THEN gst_amount ELSE 0 END),0) AS purchase_gst
         FROM (" . $union . ') gst_summary'
    );
    report_bind($summaryStmt, $params);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        0 => 'report_type', 1 => 'hsn_code', 2 => 'gst_rate', 3 => 'document_count',
        4 => 'taxable_value', 5 => 'gst_amount', 6 => 'net_value',
    ];
    $orderColumn = $columns[$dt['order_index']] ?? 'report_type';

    $sql = 'SELECT * FROM (' . $union . ') gst_rows
            ORDER BY ' . $orderColumn . ' ' . $dt['order_dir'] . ',hsn_code ASC,gst_rate ASC
            LIMIT :start,:length';
    $stmt = db()->prepare($sql);
    report_bind($stmt, $params);
    $stmt->bindValue(':start', $dt['start'], PDO::PARAM_INT);
    $stmt->bindValue(':length', $dt['length'], PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'report_type' => (string)$row['report_type'],
            'hsn_code' => (string)$row['hsn_code'],
            'gst_rate' => (float)$row['gst_rate'],
            'document_count' => (int)$row['document_count'],
            'taxable_value' => (float)$row['taxable_value'],
            'gst_amount' => (float)$row['gst_amount'],
            'net_value' => (float)$row['net_value'],
        ];
    }

    json_success('GST Report loaded.', [
        'datatable' => [
            'draw' => $dt['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'sales_taxable' => (float)($summary['sales_taxable'] ?? 0),
            'sales_gst' => (float)($summary['sales_gst'] ?? 0),
            'purchase_taxable' => (float)($summary['purchase_taxable'] ?? 0),
            'purchase_gst' => (float)($summary['purchase_gst'] ?? 0),
        ],
        'allowed_actions' => $actions,
    ]);
}

function report_ledger_base_from(): string
{
    return ' FROM account_transactions atx
             INNER JOIN accounts a ON a.id=atx.account_id AND a.branch_id=atx.branch_id
             LEFT JOIN customer_payments cp ON atx.source_type=1 AND cp.id=atx.source_id AND cp.branch_id=atx.branch_id
             LEFT JOIN supplier_payments sp ON atx.source_type=2 AND sp.id=atx.source_id AND sp.branch_id=atx.branch_id ';
}

function report_daily_ledger(int $branchId, array $actions): void
{
    $dt = report_dt();
    $day = report_date($_GET['ledger_date'] ?? date('Y-m-d'), 'Ledger Date') ?? date('Y-m-d');

    $accountId = report_ref_id($_GET['account_ref'] ?? '', 'account', 'Account');
    $baseWhere = ['atx.branch_id=:branch_id', 'atx.transaction_date>=:day_start', 'atx.transaction_date<:day_end'];
    $params = [
        ':branch_id' => $branchId,
        ':day_start' => $day . ' 00:00:00',
        ':day_end' => date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00',
    ];

    if ($accountId > 0) {
        $baseWhere[] = 'atx.account_id=:account_id';
        $params[':account_id'] = $accountId;
    }

    $where = $baseWhere;
    if ($dt['search'] !== '') {
        $like = '%' . $dt['search'] . '%';
        $where[] = '(a.account_code LIKE :search_code OR a.account_name LIKE :search_name OR atx.remarks LIKE :search_remarks OR cp.payment_no LIKE :search_customer_payment OR sp.payment_no LIKE :search_supplier_payment)';
        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_remarks'] = $like;
        $params[':search_customer_payment'] = $like;
        $params[':search_supplier_payment'] = $like;
    }

    $from = report_ledger_base_from();

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM account_transactions WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    report_bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $openingWhere = ['branch_id=:opening_branch', 'transaction_date<:opening_day'];
    $openingParams = [':opening_branch' => $branchId, ':opening_day' => $day . ' 00:00:00'];
    if ($accountId > 0) {
        $openingWhere[] = 'account_id=:opening_account';
        $openingParams[':opening_account'] = $accountId;
    }
    $openingStmt = db()->prepare(
        'SELECT COALESCE(SUM(CASE WHEN transaction_type=1 THEN amount ELSE -amount END),0)
         FROM account_transactions WHERE ' . implode(' AND ', $openingWhere)
    );
    report_bind($openingStmt, $openingParams);
    $openingStmt->execute();
    $opening = (float)$openingStmt->fetchColumn();

    $daySummaryWhere = ['branch_id=:sum_branch', 'transaction_date>=:sum_start', 'transaction_date<:sum_end'];
    $daySummaryParams = [
        ':sum_branch' => $branchId,
        ':sum_start' => $day . ' 00:00:00',
        ':sum_end' => date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00',
    ];
    if ($accountId > 0) {
        $daySummaryWhere[] = 'account_id=:sum_account';
        $daySummaryParams[':sum_account'] = $accountId;
    }
    $summaryStmt = db()->prepare(
        'SELECT COALESCE(SUM(CASE WHEN transaction_type=1 THEN amount ELSE 0 END),0) AS money_in,
                COALESCE(SUM(CASE WHEN transaction_type=2 THEN amount ELSE 0 END),0) AS money_out
         FROM account_transactions WHERE ' . implode(' AND ', $daySummaryWhere)
    );
    report_bind($summaryStmt, $daySummaryParams);
    $summaryStmt->execute();
    $flow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $moneyIn = (float)($flow['money_in'] ?? 0);
    $moneyOut = (float)($flow['money_out'] ?? 0);
    $closing = round($opening + $moneyIn - $moneyOut, 2);

    // Match Daily Ledger frontend columns exactly.
    $columns = [
        0 => 'atx.transaction_date',
        1 => 'a.account_name',
        2 => 'a.account_type',
        3 => 'atx.source_type',
        4 => 'atx.source_id',
        5 => 'atx.remarks',
        6 => 'CASE WHEN atx.transaction_type=1 THEN atx.amount ELSE 0 END',
        7 => 'CASE WHEN atx.transaction_type=2 THEN atx.amount ELSE 0 END',
        8 => 'day_running',
    ];
    $orderColumn = $columns[$dt['order_index']] ?? 'atx.transaction_date';

    $sql = 'SELECT atx.id,atx.transaction_date,atx.transaction_type,atx.source_type,atx.source_id,atx.amount,atx.remarks,
                   a.account_code,a.account_name,a.account_type,cp.payment_no AS customer_payment_no,sp.payment_no AS supplier_payment_no,
                   SUM(CASE WHEN atx.transaction_type=1 THEN atx.amount ELSE -atx.amount END)
                       OVER (ORDER BY atx.transaction_date ASC,atx.id ASC) AS day_running
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderColumn . ' ' . $dt['order_dir'] . ',atx.id ' . $dt['order_dir'] . '
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    report_bind($stmt, $params);
    $stmt->bindValue(':start', $dt['start'], PDO::PARAM_INT);
    $stmt->bindValue(':length', $dt['length'], PDO::PARAM_INT);
    $stmt->execute();

    $accountTypeLabels = [1 => 'Cash', 2 => 'Bank', 3 => 'UPI', 4 => 'Card', 5 => 'Other'];
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isIn = (int)$row['transaction_type'] === 1;
        $rows[] = [
            'transaction_date' => (string)$row['transaction_date'],
            'account_label' => (string)$row['account_code'] . ' - ' . (string)$row['account_name'],
            'account_type_label' => $accountTypeLabels[(int)$row['account_type']] ?? 'Other',
            'source_type' => (int)$row['source_type'],
            'source_label' => report_source_label((int)$row['source_type']),
            'reference' => report_account_source_reference($row),
            'remarks' => (string)($row['remarks'] ?? ''),
            'money_in' => $isIn ? (float)$row['amount'] : 0.0,
            'money_out' => $isIn ? 0.0 : (float)$row['amount'],
            'running_balance' => round($opening + (float)$row['day_running'], 2),
        ];
    }

    json_success('Daily Ledger loaded.', [
        'datatable' => [
            'draw' => $dt['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'opening_balance' => $opening,
            'money_in' => $moneyIn,
            'money_out' => $moneyOut,
            'closing_balance' => $closing,
        ],
        'allowed_actions' => $actions,
    ]);
}

function report_account_ledger(int $branchId, array $actions): void
{
    $dt = report_dt();
    [$dateFrom, $dateTo] = report_date_range();
    $accountId = report_ref_id($_GET['account_ref'] ?? '', 'account', 'Account');

    if ($accountId < 1) {
        json_success('Select an Account.', [
            'datatable' => ['draw' => $dt['draw'], 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []],
            'summary' => ['opening_balance' => 0, 'money_in' => 0, 'money_out' => 0, 'closing_balance' => 0],
            'allowed_actions' => $actions,
        ]);
    }

    $accountStmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type
         FROM accounts WHERE id=:id AND branch_id=:branch_id AND status=1 LIMIT 1'
    );
    $accountStmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) json_error('Selected Account is invalid or inactive.', 422);

    $where = ['atx.branch_id=:branch_id', 'atx.account_id=:account_id'];
    $params = [':branch_id' => $branchId, ':account_id' => $accountId];

    if ($dateFrom !== null) {
        $where[] = 'atx.transaction_date>=:date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== null) {
        $where[] = 'atx.transaction_date<:date_to_next';
        $params[':date_to_next'] = date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00';
    }

    if ($dt['search'] !== '') {
        $like = '%' . $dt['search'] . '%';
        $where[] = '(atx.remarks LIKE :search_remarks OR cp.payment_no LIKE :search_customer_payment OR sp.payment_no LIKE :search_supplier_payment)';
        $params[':search_remarks'] = $like;
        $params[':search_customer_payment'] = $like;
        $params[':search_supplier_payment'] = $like;
    }

    $from = report_ledger_base_from();

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM account_transactions WHERE branch_id=:branch_id AND account_id=:account_id');
    $totalStmt->execute([':branch_id' => $branchId, ':account_id' => $accountId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    report_bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $opening = 0.0;
    if ($dateFrom !== null) {
        $openingStmt = db()->prepare(
            'SELECT COALESCE(SUM(CASE WHEN transaction_type=1 THEN amount ELSE -amount END),0)
             FROM account_transactions
             WHERE branch_id=:branch_id AND account_id=:account_id AND transaction_date<:date_from'
        );
        $openingStmt->execute([
            ':branch_id' => $branchId,
            ':account_id' => $accountId,
            ':date_from' => $dateFrom . ' 00:00:00',
        ]);
        $opening = (float)$openingStmt->fetchColumn();
    }

    $flowWhere = ['branch_id=:sum_branch', 'account_id=:sum_account'];
    $flowParams = [':sum_branch' => $branchId, ':sum_account' => $accountId];
    if ($dateFrom !== null) {
        $flowWhere[] = 'transaction_date>=:sum_from';
        $flowParams[':sum_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== null) {
        $flowWhere[] = 'transaction_date<:sum_to';
        $flowParams[':sum_to'] = date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00';
    }
    $summaryStmt = db()->prepare(
        'SELECT COALESCE(SUM(CASE WHEN transaction_type=1 THEN amount ELSE 0 END),0) AS money_in,
                COALESCE(SUM(CASE WHEN transaction_type=2 THEN amount ELSE 0 END),0) AS money_out
         FROM account_transactions WHERE ' . implode(' AND ', $flowWhere)
    );
    report_bind($summaryStmt, $flowParams);
    $summaryStmt->execute();
    $flow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $moneyIn = (float)($flow['money_in'] ?? 0);
    $moneyOut = (float)($flow['money_out'] ?? 0);
    $closing = round($opening + $moneyIn - $moneyOut, 2);

    // Match Account Ledger frontend columns exactly.
    $columns = [
        0 => 'atx.transaction_date',
        1 => 'atx.source_type',
        2 => 'atx.source_id',
        3 => 'atx.remarks',
        4 => 'CASE WHEN atx.transaction_type=1 THEN atx.amount ELSE 0 END',
        5 => 'CASE WHEN atx.transaction_type=2 THEN atx.amount ELSE 0 END',
        6 => 'period_running',
    ];
    $orderColumn = $columns[$dt['order_index']] ?? 'atx.transaction_date';

    $sql = 'SELECT atx.id,atx.transaction_date,atx.transaction_type,atx.source_type,atx.source_id,atx.amount,atx.remarks,
                   cp.payment_no AS customer_payment_no,sp.payment_no AS supplier_payment_no,
                   SUM(CASE WHEN atx.transaction_type=1 THEN atx.amount ELSE -atx.amount END)
                       OVER (ORDER BY atx.transaction_date ASC,atx.id ASC) AS period_running
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderColumn . ' ' . $dt['order_dir'] . ',atx.id ' . $dt['order_dir'] . '
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    report_bind($stmt, $params);
    $stmt->bindValue(':start', $dt['start'], PDO::PARAM_INT);
    $stmt->bindValue(':length', $dt['length'], PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isIn = (int)$row['transaction_type'] === 1;
        $rows[] = [
            'transaction_date' => (string)$row['transaction_date'],
            'source_type' => (int)$row['source_type'],
            'source_label' => report_source_label((int)$row['source_type']),
            'reference' => report_account_source_reference($row),
            'remarks' => (string)($row['remarks'] ?? ''),
            'money_in' => $isIn ? (float)$row['amount'] : 0.0,
            'money_out' => $isIn ? 0.0 : (float)$row['amount'],
            'running_balance' => round($opening + (float)$row['period_running'], 2),
        ];
    }

    $accountTypeLabels = [1 => 'Cash', 2 => 'Bank', 3 => 'UPI', 4 => 'Card', 5 => 'Other'];

    json_success('Account Ledger loaded.', [
        'datatable' => [
            'draw' => $dt['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'opening_balance' => $opening,
            'money_in' => $moneyIn,
            'money_out' => $moneyOut,
            'closing_balance' => $closing,
        ],
        'account' => [
            'account_code' => (string)$account['account_code'],
            'account_name' => (string)$account['account_name'],
            'account_type_label' => $accountTypeLabels[(int)$account['account_type']] ?? 'Other',
        ],
        'allowed_actions' => $actions,
    ]);
}

function report_stock(int $branchId, array $actions): void
{
    $dt = report_dt();
    [$dateFrom, $dateTo] = report_date_range();

    $where = ['p.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($dt['search'] !== '') {
        $like = '%' . $dt['search'] . '%';
        $where[] = '(p.product_code LIKE :search_code OR p.product_name LIKE :search_name OR c.category_name LIKE :search_category)';
        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_category'] = $like;
    }

    $productId = report_ref_id($_GET['product_ref'] ?? '', 'product', 'Product');
    if ($productId > 0) {
        $where[] = 'p.id=:product_id';
        $params[':product_id'] = $productId;
    }

    $categoryId = report_ref_id($_GET['category_ref'] ?? '', 'category', 'Category');
    if ($categoryId > 0) {
        $where[] = 'p.category_id=:category_id';
        $params[':category_id'] = $categoryId;
    }

    $typeRaw = trim((string)($_GET['product_type'] ?? ''));
    if ($typeRaw !== '') {
        $productType = (int)$typeRaw;
        if (!in_array($productType, [1,2,3], true)) json_error('Invalid Product Type.', 422);
        $where[] = 'p.product_type=:product_type';
        $params[':product_type'] = $productType;
    }

    $statusRaw = trim((string)($_GET['status'] ?? '1'));
    if ($statusRaw !== '') {
        $status = (int)$statusRaw;
        if (!in_array($status, [1,2], true)) json_error('Invalid Product Status.', 422);
        $where[] = 'p.status=:status';
        $params[':status'] = $status;
    }

    /* Dates are validated by report_date_range(). Quote once and use them inside
       the stock aggregation so PDO never has to bind the same named placeholder
       repeatedly in several CASE expressions. */
    $openingCutoff = $dateFrom !== null ? $dateFrom . ' 00:00:00' : null;
    $periodEnd = $dateTo !== null ? date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00' : null;
    $openingCondition = $openingCutoff !== null
        ? 'sm.movement_date < ' . db()->quote($openingCutoff)
        : '0=1';
    $periodParts = ['1=1'];
    if ($openingCutoff !== null) $periodParts[] = 'sm.movement_date >= ' . db()->quote($openingCutoff);
    if ($periodEnd !== null) $periodParts[] = 'sm.movement_date < ' . db()->quote($periodEnd);
    $periodCondition = implode(' AND ', $periodParts);

    $movementAgg = "LEFT JOIN (
        SELECT sm.branch_id,sm.product_id,
               COALESCE(SUM(CASE WHEN {$openingCondition} THEN sm.quantity_in-sm.quantity_out ELSE 0 END),0) AS opening_qty,
               COALESCE(SUM(CASE WHEN {$periodCondition} THEN sm.quantity_in ELSE 0 END),0) AS stock_in,
               COALESCE(SUM(CASE WHEN {$periodCondition} THEN sm.quantity_out ELSE 0 END),0) AS stock_out,
               COALESCE(SUM(CASE WHEN {$periodCondition} AND sm.movement_type=1 THEN sm.quantity_in ELSE 0 END),0) AS purchase_in,
               COALESCE(SUM(CASE WHEN {$periodCondition} AND sm.movement_type=7 THEN sm.quantity_in ELSE 0 END),0) AS production_in,
               COALESCE(SUM(CASE WHEN {$periodCondition} AND sm.movement_type=2 THEN sm.quantity_out ELSE 0 END),0) AS sale_out
        FROM stock_movements sm
        WHERE sm.branch_id=" . (int)$branchId . "
        GROUP BY sm.branch_id,sm.product_id
    ) mx ON mx.product_id=p.id AND mx.branch_id=p.branch_id";

    $from = ' FROM products p
              INNER JOIN categories c ON c.id=p.category_id AND c.branch_id=p.branch_id
              LEFT JOIN product_units ppu ON ppu.product_id=p.id AND ppu.unit_type=1 AND ppu.status=1
              LEFT JOIN units u ON u.id=ppu.unit_id AND u.branch_id=p.branch_id
              ' . $movementAgg;

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM products WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    report_bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summarySql = 'SELECT COUNT(*) AS product_count,
                          COALESCE(SUM(CASE WHEN x.closing_qty>0.0005 THEN 1 ELSE 0 END),0) AS in_stock_count,
                          COALESCE(SUM(CASE WHEN x.closing_qty<=0.0005 THEN 1 ELSE 0 END),0) AS zero_stock_count,
                          COALESCE(SUM(x.stock_value),0) AS stock_value
                   FROM (
                       SELECT p.id,
                              (COALESCE(mx.opening_qty,0)+COALESCE(mx.stock_in,0)-COALESCE(mx.stock_out,0)) AS closing_qty,
                              ((COALESCE(mx.opening_qty,0)+COALESCE(mx.stock_in,0)-COALESCE(mx.stock_out,0))*p.purchase_price) AS stock_value
                       ' . $from . '
                       WHERE ' . implode(' AND ', $where) . '
                   ) x';
    $summaryStmt = db()->prepare($summarySql);
    report_bind($summaryStmt, $params);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Match Stock Details frontend columns exactly, including Unit at index 4.
    $columns = [
        0 => 'p.product_code',
        1 => 'p.product_name',
        2 => 'c.category_name',
        3 => 'p.product_type',
        4 => "COALESCE(u.short_name,u.unit_name,'')",
        5 => 'opening_qty',
        6 => 'stock_in',
        7 => 'stock_out',
        8 => 'closing_qty',
        9 => 'purchase_in',
        10 => 'production_in',
        11 => 'sale_out',
        12 => 'p.purchase_price',
        13 => 'stock_value',
    ];
    $orderColumn = $columns[$dt['order_index']] ?? 'p.product_name';

    $sql = 'SELECT p.product_code,p.product_name,p.product_type,p.purchase_price,p.status,
                   c.category_name,COALESCE(u.short_name,u.unit_name,\'\') AS primary_unit,
                   COALESCE(mx.opening_qty,0) AS opening_qty,
                   COALESCE(mx.stock_in,0) AS stock_in,
                   COALESCE(mx.stock_out,0) AS stock_out,
                   (COALESCE(mx.opening_qty,0)+COALESCE(mx.stock_in,0)-COALESCE(mx.stock_out,0)) AS closing_qty,
                   COALESCE(mx.purchase_in,0) AS purchase_in,
                   COALESCE(mx.production_in,0) AS production_in,
                   COALESCE(mx.sale_out,0) AS sale_out,
                   ((COALESCE(mx.opening_qty,0)+COALESCE(mx.stock_in,0)-COALESCE(mx.stock_out,0))*p.purchase_price) AS stock_value
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderColumn . ' ' . $dt['order_dir'] . ',p.id ASC
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    report_bind($stmt, $params);
    $stmt->bindValue(':start', $dt['start'], PDO::PARAM_INT);
    $stmt->bindValue(':length', $dt['length'], PDO::PARAM_INT);
    $stmt->execute();

    $typeLabels = [1 => 'Raw Material', 2 => 'Finished Product', 3 => 'Consumable'];
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'product_code' => (string)$row['product_code'],
            'product_name' => (string)$row['product_name'],
            'category_name' => (string)$row['category_name'],
            'product_type' => (int)$row['product_type'],
            'product_type_label' => $typeLabels[(int)$row['product_type']] ?? 'Product',
            'primary_unit' => (string)($row['primary_unit'] ?? ''),
            'purchase_price' => (float)$row['purchase_price'],
            'opening_qty' => (float)$row['opening_qty'],
            'stock_in' => (float)$row['stock_in'],
            'stock_out' => (float)$row['stock_out'],
            'closing_qty' => (float)$row['closing_qty'],
            'purchase_in' => (float)$row['purchase_in'],
            'production_in' => (float)$row['production_in'],
            'sale_out' => (float)$row['sale_out'],
            'stock_value' => (float)$row['stock_value'],
            'status' => (int)$row['status'],
        ];
    }

    json_success('Stock Details Report loaded.', [
        'datatable' => [
            'draw' => $dt['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'product_count' => (int)($summary['product_count'] ?? 0),
            'in_stock_count' => (int)($summary['in_stock_count'] ?? 0),
            'zero_stock_count' => (int)($summary['zero_stock_count'] ?? 0),
            'stock_value' => (float)($summary['stock_value'] ?? 0),
        ],
        'allowed_actions' => $actions,
    ]);
}

$method = request_method();
if ($method !== 'GET') json_error('Method not allowed.', 405);

$report = strtolower(trim((string)($_GET['report'] ?? '')));
$permissionPath = report_permission_path($report);
$access = require_permission($permissionPath, ACTION_VIEW);
$context = report_context($access['user']);
$branchId = (int)$context['branch_id'];
$actions = array_map('intval', $access['actions'] ?? []);

if (isset($_GET['options'])) {
    $payload = [
        'allowed_actions' => $actions,
        'today' => date('Y-m-d'),
        'branch' => $context,
    ];

    if (in_array($report, ['sales'], true)) {
        $payload['customers'] = report_customers($branchId);
    }
    if (in_array($report, ['productwise','stock'], true)) {
        $payload['products'] = report_products($branchId);
        $payload['categories'] = report_categories($branchId);
    }
    if (in_array($report, ['daily_ledger','account_ledger'], true)) {
        $payload['accounts'] = report_accounts($branchId);
    }

    json_success('Report options loaded.', $payload);
}

if (!isset($_GET['datatable'])) json_error('Invalid report request.', 422);

switch ($report) {
    case 'sales':
        report_sales($branchId, $actions);
        break;
    case 'productwise':
        report_productwise($branchId, $actions);
        break;
    case 'gst':
        report_gst($branchId, $actions);
        break;
    case 'daily_ledger':
        report_daily_ledger($branchId, $actions);
        break;
    case 'account_ledger':
        report_account_ledger($branchId, $actions);
        break;
    case 'stock':
        report_stock($branchId, $actions);
        break;
    default:
        json_error('Invalid report.', 422);
}
