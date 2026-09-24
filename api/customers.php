<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_ACTIVATE')) define('ACTION_ACTIVATE', 27);
if (!defined('ACTION_DEACTIVATE')) define('ACTION_DEACTIVATE', 28);

function customer_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Customer management is available only for tenant users.', 403);
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
    $row = $stmt->fetch();

    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function customer_nullable($value): ?string
{
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

function customer_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT customer_code
         FROM customers
         WHERE branch_id=:branch_id
           AND customer_code REGEXP '^CUS[0-9]+$'
         ORDER BY CAST(SUBSTRING(customer_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^CUS([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'CUS' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function customer_id_from_ref($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Customer reference is required.', 422, ['ref' => 'Customer reference is required.']);
    }

    try {
        $id = (int)decryptReference(trim($value), 'customer');
    } catch (Throwable $e) {
        json_error('Invalid Customer reference.', 422, ['ref' => 'Invalid Customer reference.']);
    }

    if ($id < 1) json_error('Invalid Customer reference.', 422);
    return $id;
}

function customer_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT c.id,c.branch_id,c.customer_code,c.customer_name,c.mobile,c.address,
                c.line_id,c.line_sequence,c.price_level_id,c.credit_limit,c.opening_balance,
                c.opening_can_balance,c.status,c.created_by,c.created_at,c.updated_at,
                l.line_code,l.line_name,p.price_level_name
         FROM customers c
         INNER JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
         INNER JOIN price_levels p ON p.id=c.price_level_id AND p.branch_id=c.branch_id
         WHERE c.id=:id AND c.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) json_error('Customer was not found in your branch.', 404);

    $ref = encryptReference('customer', (int)$row['id']);
    unset($row['id']);
    $row['branch_id'] = (int)$row['branch_id'];
    $row['line_id'] = (int)$row['line_id'];
    $row['line_sequence'] = $row['line_sequence'] === null ? null : (int)$row['line_sequence'];
    $row['price_level_id'] = (int)$row['price_level_id'];
    $row['credit_limit'] = (float)$row['credit_limit'];
    $row['opening_balance'] = (float)$row['opening_balance'];
    $row['opening_can_balance'] = (float)$row['opening_can_balance'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = $ref;
    $row['edit_url'] = 'customer-form.php?ref=' . $ref;

    return $row;
}

function customer_options(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,line_code,line_name
         FROM `lines`
         WHERE branch_id=:branch_id AND status=1
         ORDER BY line_name'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $lines = $stmt->fetchAll();

    $stmt = db()->prepare(
        'SELECT id,price_level_name
         FROM price_levels
         WHERE branch_id=:branch_id AND status=1
         ORDER BY price_level_name'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $priceLevels = $stmt->fetchAll();

    foreach ($lines as &$row) $row['id'] = (int)$row['id'];
    unset($row);
    foreach ($priceLevels as &$row) $row['id'] = (int)$row['id'];
    unset($row);

    return ['lines' => $lines, 'price_levels' => $priceLevels];
}

function customer_assert_line(int $branchId, int $lineId): void
{
    $stmt = db()->prepare(
        'SELECT id
         FROM `lines`
         WHERE id=:id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $stmt->execute([':id' => $lineId, ':branch_id' => $branchId]);

    if (!$stmt->fetchColumn()) {
        json_error('Selected Line is invalid or inactive.', 422, [
            'line_id' => 'Select an active Line.',
        ]);
    }
}

function customer_assert_price_level(int $branchId, int $priceLevelId): void
{
    $stmt = db()->prepare(
        'SELECT id
         FROM price_levels
         WHERE id=:id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $stmt->execute([':id' => $priceLevelId, ':branch_id' => $branchId]);

    if (!$stmt->fetchColumn()) {
        json_error('Selected Price Level is invalid or inactive.', 422, [
            'price_level_id' => 'Select an active Price Level.',
        ]);
    }
}

function customer_validate(array $data, int $branchId): array
{
    $errors = [];

    $name = trim((string)($data['customer_name'] ?? ''));
    if ($name === '') $errors['customer_name'] = 'Customer name is required.';
    elseif (mb_strlen($name) > 150) $errors['customer_name'] = 'Customer name must be within 150 characters.';

    $mobile = customer_nullable($data['mobile'] ?? null);
    if ($mobile !== null && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }

    $lineId = (int)($data['line_id'] ?? 0);
    if ($lineId < 1) $errors['line_id'] = 'Please select a Line.';

    $lineSequence = trim((string)($data['line_sequence'] ?? ''));
    if ($lineSequence !== '' && !preg_match('/^[1-9][0-9]*$/', $lineSequence)) {
        $errors['line_sequence'] = 'Enter a positive whole number.';
    }

    $priceLevelId = (int)($data['price_level_id'] ?? 0);
    if ($priceLevelId < 1) $errors['price_level_id'] = 'Please select a Price Level.';

    $creditLimit = trim((string)($data['credit_limit'] ?? ''));
    if ($creditLimit === '') $creditLimit = '0';
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/', $creditLimit)) {
        $errors['credit_limit'] = 'Enter a valid Credit Limit.';
    }

    $openingBalance = trim((string)($data['opening_balance'] ?? ''));
    if ($openingBalance === '') $openingBalance = '0';
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/', $openingBalance)) {
        $errors['opening_balance'] = 'Enter a valid Opening Balance.';
    }

    $openingCanBalance = trim((string)($data['opening_can_balance'] ?? ''));
    if ($openingCanBalance === '') $openingCanBalance = '0';
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,3})?|\.[0-9]{1,3})$/', $openingCanBalance)) {
        $errors['opening_can_balance'] = 'Enter a valid Opening Can Balance.';
    }

    $status = (int)($data['status'] ?? 1);
    if (!in_array($status, [0,1], true)) {
        $errors['status'] = 'Status must be Active or Inactive.';
    }

    if ($errors) json_error('Please correct the highlighted Customer fields.', 422, $errors);

    customer_assert_line($branchId, $lineId);
    customer_assert_price_level($branchId, $priceLevelId);

    return [
        'customer_name' => $name,
        'mobile' => $mobile,
        'address' => customer_nullable($data['address'] ?? null),
        'line_id' => $lineId,
        'line_sequence' => $lineSequence === '' ? null : (int)$lineSequence,
        'price_level_id' => $priceLevelId,
        'credit_limit' => round((float)$creditLimit, 2),
        'opening_balance' => round((float)$openingBalance, 2),
        'opening_can_balance' => round((float)$openingCanBalance, 3),
        'status' => $status,
    ];
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('customer-list.php', ACTION_VIEW);
    $context = customer_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $options = customer_options($branchId);

    json_success('Customer form options loaded.', [
        'next_customer_code' => customer_generate_code($branchId),
        'lines' => $options['lines'],
        'price_levels' => $options['price_levels'],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('customer-list.php', ACTION_VIEW);
    $context = customer_context($access['user']);

    json_success('Customer loaded.', [
        'customer' => customer_record(
            (int)$context['branch_id'],
            customer_id_from_ref($_GET['ref'])
        ),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('customer-list.php', ACTION_VIEW);
    $context = customer_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = max(1, (int)($_GET['draw'] ?? 1));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $statusFilter = (string)($_GET['status'] ?? '');

    $baseFrom =
        ' FROM customers c
          INNER JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
          INNER JOIN price_levels p ON p.id=c.price_level_id AND p.branch_id=c.branch_id';

    $baseWhere = ['c.branch_id=:branch_id'];
    $where = $baseWhere;
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = '(c.customer_code LIKE :search_code
                     OR c.customer_name LIKE :search_name
                     OR c.mobile LIKE :search_mobile
                     OR c.address LIKE :search_address
                     OR l.line_code LIKE :search_line_code
                     OR l.line_name LIKE :search_line_name
                     OR p.price_level_name LIKE :search_price_level)';
        $term = '%' . $search . '%';
        $params[':search_code'] = $term;
        $params[':search_name'] = $term;
        $params[':search_mobile'] = $term;
        $params[':search_address'] = $term;
        $params[':search_line_code'] = $term;
        $params[':search_line_name'] = $term;
        $params[':search_price_level'] = $term;
    }

    if ($statusFilter !== '') {
        $status = (int)$statusFilter;
        if (!in_array($status, [0,1], true)) {
            json_error('Invalid Customer status filter.', 422);
        }
        $where[] = 'c.status=:status';
        $params[':status'] = $status;
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)' . $baseFrom . ' WHERE ' . implode(' AND ', $baseWhere)
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare(
        'SELECT COUNT(*)' . $baseFrom . ' WHERE ' . implode(' AND ', $where)
    );
    $filteredStmt->execute($params);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $columns = [
        0 => 'c.customer_code',
        1 => 'c.customer_name',
        2 => 'c.mobile',
        3 => 'l.line_name',
        4 => 'c.line_sequence',
        5 => 'p.price_level_name',
        6 => 'c.credit_limit',
        7 => 'c.opening_balance',
        8 => 'c.opening_can_balance',
        9 => 'c.status',
        10 => 'c.id',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderColumn = $columns[$orderIndex] ?? 'c.customer_code';

    $sql =
        'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.line_sequence,
                c.credit_limit,c.opening_balance,c.opening_can_balance,c.status,
                l.line_name,p.price_level_name' .
        $baseFrom .
        ' WHERE ' . implode(' AND ', $where) .
        ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',c.id ASC
          LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $type = ($key === ':branch_id' || $key === ':status') ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $ref = encryptReference('customer', (int)$row['id']);
        unset($row['id']);
        $row['line_sequence'] = $row['line_sequence'] === null ? null : (int)$row['line_sequence'];
        $row['credit_limit'] = (float)$row['credit_limit'];
        $row['opening_balance'] = (float)$row['opening_balance'];
        $row['opening_can_balance'] = (float)$row['opening_can_balance'];
        $row['status'] = (int)$row['status'];
        $row['ref'] = $ref;
        $row['edit_url'] = 'customer-form.php?ref=' . $ref;
    }
    unset($row);

    json_success('Customers loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET') {
    $access = require_permission('customer-list.php', ACTION_VIEW);
    $context = customer_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $stmt = db()->prepare(
        'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.address,c.line_id,c.line_sequence,
                c.price_level_id,c.credit_limit,c.opening_balance,c.opening_can_balance,c.status,
                l.line_name,p.price_level_name
         FROM customers c
         INNER JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
         INNER JOIN price_levels p ON p.id=c.price_level_id AND p.branch_id=c.branch_id
         WHERE c.branch_id=:branch_id
         ORDER BY c.customer_name'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['line_id'] = (int)$row['line_id'];
        $row['line_sequence'] = $row['line_sequence'] === null ? null : (int)$row['line_sequence'];
        $row['price_level_id'] = (int)$row['price_level_id'];
        $row['credit_limit'] = (float)$row['credit_limit'];
        $row['opening_balance'] = (float)$row['opening_balance'];
        $row['opening_can_balance'] = (float)$row['opening_can_balance'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('Customers loaded.', [
        'customers' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('customer-list.php', ACTION_CREATE);
    $user = $access['user'];
    $context = customer_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();
    $clean = customer_validate($data, $branchId);
    $code = customer_generate_code($branchId);

    try {
        $stmt = db()->prepare(
            'INSERT INTO customers
             (branch_id,customer_code,customer_name,mobile,address,line_id,line_sequence,
              price_level_id,credit_limit,opening_balance,opening_can_balance,status,
              created_by,created_at,updated_at)
             VALUES
             (:branch_id,:customer_code,:customer_name,:mobile,:address,:line_id,:line_sequence,
              :price_level_id,:credit_limit,:opening_balance,:opening_can_balance,:status,
              :created_by,NOW(),NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':customer_code' => $code,
            ':customer_name' => $clean['customer_name'],
            ':mobile' => $clean['mobile'],
            ':address' => $clean['address'],
            ':line_id' => $clean['line_id'],
            ':line_sequence' => $clean['line_sequence'],
            ':price_level_id' => $clean['price_level_id'],
            ':credit_limit' => $clean['credit_limit'],
            ':opening_balance' => $clean['opening_balance'],
            ':opening_can_balance' => $clean['opening_can_balance'],
            ':status' => $clean['status'],
            ':created_by' => (int)$user['id'],
        ]);

        $id = (int)db()->lastInsertId();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
        ]);

        json_success('Customer created successfully.', [
            'customer' => customer_record($branchId, $id),
        ], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_error('Customer code already exists in this branch.', 409);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    $access = require_permission('customer-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = customer_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['ref' => 'Customer reference is required.']);

    $id = customer_id_from_ref($data['ref']);
    $old = customer_record($branchId, $id);
    $clean = customer_validate($data, $branchId);

    $stmt = db()->prepare(
        'UPDATE customers
         SET customer_name=:customer_name,
             mobile=:mobile,
             address=:address,
             line_id=:line_id,
             line_sequence=:line_sequence,
             price_level_id=:price_level_id,
             credit_limit=:credit_limit,
             opening_balance=:opening_balance,
             opening_can_balance=:opening_can_balance,
             status=:status,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );
    $stmt->execute([
        ':customer_name' => $clean['customer_name'],
        ':mobile' => $clean['mobile'],
        ':address' => $clean['address'],
        ':line_id' => $clean['line_id'],
        ':line_sequence' => $clean['line_sequence'],
        ':price_level_id' => $clean['price_level_id'],
        ':credit_limit' => $clean['credit_limit'],
        ':opening_balance' => $clean['opening_balance'],
        ':opening_can_balance' => $clean['opening_can_balance'],
        ':status' => $clean['status'],
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], ACTION_UPDATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
    ]);

    json_success('Customer updated successfully.', [
        'customer' => customer_record($branchId, $id),
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['ref','status']);

    $status = (int)$data['status'];
    if (!in_array($status, [0,1], true)) {
        json_error('Invalid Customer status.', 422, ['status' => 'Status must be Active or Inactive.']);
    }

    $action = $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;
    $access = require_permission('customer-list.php', $action);
    $user = $access['user'];
    $context = customer_context($user);
    $branchId = (int)$context['branch_id'];
    $id = customer_id_from_ref($data['ref']);
    $old = customer_record($branchId, $id);

    db()->prepare(
        'UPDATE customers
         SET status=:status,updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    )->execute([
        ':status' => $status,
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], $action, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
    ]);

    json_success($status === 1 ? 'Customer activated.' : 'Customer deactivated.');
}

json_error('Method not allowed.', 405);
