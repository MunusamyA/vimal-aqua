<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_ACTIVATE')) define('ACTION_ACTIVATE', 27);
if (!defined('ACTION_DEACTIVATE')) define('ACTION_DEACTIVATE', 28);

function supplier_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Supplier management is available only for tenant users.', 403);
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

function supplier_nullable($value, int $max = 0): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if ($max > 0 && mb_strlen($value) > $max) {
        json_error('Entered value is too long.', 422);
    }
    return $value;
}

function supplier_status($value): int
{
    $status = (int)$value;
    if (!in_array($status, [1,2], true)) {
        json_error('Invalid Supplier status.', 422, ['status' => 'Status must be Active or Inactive.']);
    }
    return $status;
}

function supplier_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT supplier_code
         FROM suppliers
         WHERE branch_id=:branch_id
           AND supplier_code REGEXP '^SUP[0-9]+$'
         ORDER BY CAST(SUBSTRING(supplier_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^SUP([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'SUP' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function supplier_id_from_ref($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Supplier reference is required.', 422, ['ref' => 'Supplier reference is required.']);
    }

    try {
        $id = (int)decryptReference(trim($value), 'supplier');
    } catch (Throwable $e) {
        json_error('Invalid Supplier reference.', 422, ['ref' => 'Invalid Supplier reference.']);
    }

    if ($id < 1) json_error('Invalid Supplier reference.', 422);
    return $id;
}

function supplier_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id,branch_id,supplier_code,supplier_name,contact_person,mobile,email,
                gstin,pan,address,opening_balance,status,created_by,created_at,updated_at
         FROM suppliers
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) json_error('Supplier was not found in your branch.', 404);

    $ref = encryptReference('supplier', (int)$row['id']);
    unset($row['id']);
    $row['branch_id'] = (int)$row['branch_id'];
    $row['opening_balance'] = (float)$row['opening_balance'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = $ref;
    $row['edit_url'] = 'supplier-form.php?ref=' . $ref;

    return $row;
}

function supplier_validate(array $data): array
{
    $errors = [];

    $name = trim((string)($data['supplier_name'] ?? ''));
    if ($name === '') $errors['supplier_name'] = 'Supplier name is required.';
    elseif (mb_strlen($name) > 150) $errors['supplier_name'] = 'Supplier name must be within 150 characters.';

    $contact = supplier_nullable($data['contact_person'] ?? null);
    if ($contact !== null && mb_strlen($contact) > 120) {
        $errors['contact_person'] = 'Contact person must be within 120 characters.';
    }

    $mobile = supplier_nullable($data['mobile'] ?? null);
    if ($mobile !== null && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }

    $email = supplier_nullable($data['email'] ?? null);
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    $gstin = supplier_nullable($data['gstin'] ?? null);
    if ($gstin !== null) {
        $gstin = strtoupper($gstin);
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            $errors['gstin'] = 'Enter a valid GST number.';
        }
    }

    $pan = supplier_nullable($data['pan'] ?? null);
    if ($pan !== null) {
        $pan = strtoupper($pan);
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            $errors['pan'] = 'Enter a valid PAN number.';
        }
    }

    $opening = trim((string)($data['opening_balance'] ?? ''));
    if ($opening === '') $opening = '0';
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/', $opening)) {
        $errors['opening_balance'] = 'Enter a valid opening balance.';
    }

    if ($errors) json_error('Please correct the highlighted Supplier fields.', 422, $errors);

    return [
        'supplier_name' => $name,
        'contact_person' => $contact,
        'mobile' => $mobile,
        'email' => $email,
        'gstin' => $gstin,
        'pan' => $pan,
        'address' => supplier_nullable($data['address'] ?? null),
        'opening_balance' => round((float)$opening, 2),
        'status' => supplier_status($data['status'] ?? 1),
    ];
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('supplier-list.php', ACTION_VIEW);
    $context = supplier_context($access['user']);

    json_success('Supplier form options loaded.', [
        'next_supplier_code' => supplier_generate_code((int)$context['branch_id']),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('supplier-list.php', ACTION_VIEW);
    $context = supplier_context($access['user']);

    json_success('Supplier loaded.', [
        'supplier' => supplier_record(
            (int)$context['branch_id'],
            supplier_id_from_ref($_GET['ref'])
        ),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('supplier-list.php', ACTION_VIEW);
    $context = supplier_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = max(1, (int)($_GET['draw'] ?? 1));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $statusFilter = (string)($_GET['status'] ?? '');
    $gstFilter = trim((string)($_GET['gst_filter'] ?? ''));

    $baseWhere = ['branch_id=:branch_id'];
    $where = $baseWhere;
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = '(supplier_code LIKE :search_code
                     OR supplier_name LIKE :search_name
                     OR contact_person LIKE :search_contact
                     OR mobile LIKE :search_mobile
                     OR email LIKE :search_email
                     OR gstin LIKE :search_gstin
                     OR pan LIKE :search_pan)';
        $term = '%' . $search . '%';
        $params[':search_code'] = $term;
        $params[':search_name'] = $term;
        $params[':search_contact'] = $term;
        $params[':search_mobile'] = $term;
        $params[':search_email'] = $term;
        $params[':search_gstin'] = $term;
        $params[':search_pan'] = $term;
    }

    if ($gstFilter !== '') {
        if ($gstFilter === 'with') {
            $where[] = '(gstin IS NOT NULL AND TRIM(gstin) <> \'\')';
        } elseif ($gstFilter === 'without') {
            $where[] = '(gstin IS NULL OR TRIM(gstin) = \'\')';
        } else {
            json_error('Invalid GSTIN filter.', 422);
        }
    }

    if ($statusFilter !== '') {
        $where[] = 'status=:status';
        $params[':status'] = supplier_status($statusFilter);
    }

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM suppliers WHERE ' . implode(' AND ', $baseWhere));
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare('SELECT COUNT(*) FROM suppliers WHERE ' . implode(' AND ', $where));
    $filteredStmt->execute($params);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT COUNT(*) AS total_suppliers,
                COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END),0) AS active_suppliers,
                COALESCE(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END),0) AS inactive_suppliers,
                COALESCE(SUM(opening_balance),0) AS opening_balance
         FROM suppliers
         WHERE ' . implode(' AND ', $where)
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        0 => 'supplier_code',
        1 => 'supplier_name',
        2 => 'contact_person',
        3 => 'mobile',
        4 => 'gstin',
        5 => 'opening_balance',
        6 => 'status',
        7 => 'id',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderColumn = $columns[$orderIndex] ?? 'supplier_code';

    $sql =
        'SELECT id,supplier_code,supplier_name,contact_person,mobile,email,gstin,opening_balance,status
         FROM suppliers
         WHERE ' . implode(' AND ', $where) .
        ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',id ASC
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
        $ref = encryptReference('supplier', (int)$row['id']);
        unset($row['id']);
        $row['opening_balance'] = (float)$row['opening_balance'];
        $row['status'] = (int)$row['status'];
        $row['ref'] = $ref;
        $row['edit_url'] = 'supplier-form.php?ref=' . $ref;
    }
    unset($row);

    json_success('Suppliers loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_suppliers' => (int)($summary['total_suppliers'] ?? 0),
            'active_suppliers' => (int)($summary['active_suppliers'] ?? 0),
            'inactive_suppliers' => (int)($summary['inactive_suppliers'] ?? 0),
            'opening_balance' => (float)($summary['opening_balance'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET') {
    $access = require_permission('supplier-list.php', ACTION_VIEW);
    $context = supplier_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $stmt = db()->prepare(
        'SELECT id,supplier_code,supplier_name,contact_person,mobile,email,gstin,pan,address,opening_balance,status
         FROM suppliers
         WHERE branch_id=:branch_id
         ORDER BY supplier_name'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['opening_balance'] = (float)$row['opening_balance'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('Suppliers loaded.', [
        'suppliers' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('supplier-list.php', ACTION_CREATE);
    $user = $access['user'];
    $context = supplier_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();
    $clean = supplier_validate($data);
    $code = supplier_generate_code($branchId);

    try {
        $stmt = db()->prepare(
            'INSERT INTO suppliers
             (branch_id,supplier_code,supplier_name,contact_person,mobile,email,gstin,pan,address,
              opening_balance,status,created_by,created_at,updated_at)
             VALUES
             (:branch_id,:supplier_code,:supplier_name,:contact_person,:mobile,:email,:gstin,:pan,:address,
              :opening_balance,:status,:created_by,NOW(),NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':supplier_code' => $code,
            ':supplier_name' => $clean['supplier_name'],
            ':contact_person' => $clean['contact_person'],
            ':mobile' => $clean['mobile'],
            ':email' => $clean['email'],
            ':gstin' => $clean['gstin'],
            ':pan' => $clean['pan'],
            ':address' => $clean['address'],
            ':opening_balance' => $clean['opening_balance'],
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

        json_success('Supplier created successfully.', [
            'supplier' => supplier_record($branchId, $id),
        ], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_error('Supplier code already exists in this branch.', 409);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    $access = require_permission('supplier-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = supplier_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['ref' => 'Supplier reference is required.']);

    $id = supplier_id_from_ref($data['ref']);
    $old = supplier_record($branchId, $id);
    $clean = supplier_validate($data);

    $stmt = db()->prepare(
        'UPDATE suppliers
         SET supplier_name=:supplier_name,
             contact_person=:contact_person,
             mobile=:mobile,
             email=:email,
             gstin=:gstin,
             pan=:pan,
             address=:address,
             opening_balance=:opening_balance,
             status=:status,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );
    $stmt->execute([
        ':supplier_name' => $clean['supplier_name'],
        ':contact_person' => $clean['contact_person'],
        ':mobile' => $clean['mobile'],
        ':email' => $clean['email'],
        ':gstin' => $clean['gstin'],
        ':pan' => $clean['pan'],
        ':address' => $clean['address'],
        ':opening_balance' => $clean['opening_balance'],
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

    json_success('Supplier updated successfully.', [
        'supplier' => supplier_record($branchId, $id),
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['ref','status']);

    $status = supplier_status($data['status']);
    $action = $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;
    $access = require_permission('supplier-list.php', $action);
    $user = $access['user'];
    $context = supplier_context($user);
    $branchId = (int)$context['branch_id'];
    $id = supplier_id_from_ref($data['ref']);
    $old = supplier_record($branchId, $id);

    db()->prepare(
        'UPDATE suppliers
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

    json_success($status === 1 ? 'Supplier activated.' : 'Supplier deactivated.');
}

json_error('Method not allowed.', 405);
