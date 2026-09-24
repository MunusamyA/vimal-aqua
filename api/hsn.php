<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_ACTIVATE')) define('ACTION_ACTIVATE', 27);
if (!defined('ACTION_DEACTIVATE')) define('ACTION_DEACTIVATE', 28);

function hsn_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('HSN management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :branch_id
           AND b.status = 1
           AND c.status = 1
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch();

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

function hsn_status($value): int
{
    $status = (int)$value;
    if (!in_array($status, [1, 2], true)) {
        json_error('Invalid HSN status.', 422, [
            'status' => 'Status must be Active or Inactive.',
        ]);
    }
    return $status;
}

function hsn_rate($value, string $field, string $label): float
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return 0.0;

    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/', $value)) {
        json_error('HSN validation failed.', 422, [
            $field => 'Enter a valid ' . $label . '.',
        ]);
    }

    return round((float)$value, 2);
}

function hsn_validate(array $data): array
{
    $errors = [];

    $code = strtoupper(trim((string)($data['hsn_code'] ?? '')));
    if ($code === '') {
        $errors['hsn_code'] = 'HSN Code is required.';
    } elseif (mb_strlen($code) > 20) {
        $errors['hsn_code'] = 'HSN Code must be within 20 characters.';
    }

    $description = trim((string)($data['description'] ?? ''));
    if ($description !== '' && mb_strlen($description) > 180) {
        $errors['description'] = 'Description must be within 180 characters.';
    }

    if ($errors) {
        json_error('HSN validation failed.', 422, $errors);
    }

    return [
        'hsn_code' => $code,
        'description' => $description === '' ? null : $description,
        'gst_rate' => hsn_rate($data['gst_rate'] ?? 0, 'gst_rate', 'GST %'),
        'cgst_rate' => hsn_rate($data['cgst_rate'] ?? 0, 'cgst_rate', 'CGST %'),
        'sgst_rate' => hsn_rate($data['sgst_rate'] ?? 0, 'sgst_rate', 'SGST %'),
        'igst_rate' => hsn_rate($data['igst_rate'] ?? 0, 'igst_rate', 'IGST %'),
        'cess_rate' => hsn_rate($data['cess_rate'] ?? 0, 'cess_rate', 'Cess %'),
    ];
}

function hsn_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, hsn_code, description,
                gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate,
                status, created_by, created_at, updated_at
         FROM hsn_master
         WHERE id = :id
           AND branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        json_error('HSN was not found in your branch.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    foreach (['gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate'] as $key) {
        $row[$key] = (float)$row[$key];
    }
    $row['status'] = (int)$row['status'];

    return $row;
}

function hsn_unique(int $branchId, string $code, int $excludeId = 0): void
{
    $sql = 'SELECT id FROM hsn_master WHERE branch_id = :branch_id AND hsn_code = :hsn_code';
    $params = [':branch_id' => $branchId, ':hsn_code' => $code];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('HSN Code already exists in this branch.', 409, [
            'hsn_code' => 'Use a unique HSN Code.',
        ]);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('hsn-master.php', ACTION_VIEW);
    $user = $access['user'];
    $context = hsn_context($user);
    $branchId = (int)$context['branch_id'];

    if (isset($_GET['options'])) {
        json_success('HSN form options loaded.', [
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('HSN loaded.', [
            'hsn' => hsn_record($branchId, positive_id($_GET['id'])),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['datatable'])) {
        $draw = max(1, (int)($_GET['draw'] ?? 1));
        $start = max(0, (int)($_GET['start'] ?? 0));
        $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
        $search = trim((string)($_GET['search']['value'] ?? ''));
        $statusFilter = (string)($_GET['status'] ?? '');

        $baseWhere = ['branch_id = :branch_id'];
        $where = $baseWhere;
        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $where[] = '(hsn_code LIKE :search_code OR description LIKE :search_description)';
            $term = '%' . $search . '%';
            $params[':search_code'] = $term;
            $params[':search_description'] = $term;
        }

        if ($statusFilter !== '') {
            $where[] = 'status = :status';
            $params[':status'] = hsn_status($statusFilter);
        }

        $totalStmt = db()->prepare(
            'SELECT COUNT(*) FROM hsn_master WHERE ' . implode(' AND ', $baseWhere)
        );
        $totalStmt->execute([':branch_id' => $branchId]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*) FROM hsn_master WHERE ' . implode(' AND ', $where)
        );
        $filteredStmt->execute($params);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $columns = [
            0 => 'hsn_code',
            1 => 'description',
            2 => 'gst_rate',
            3 => 'cgst_rate',
            4 => 'sgst_rate',
            5 => 'igst_rate',
            6 => 'cess_rate',
            7 => 'status',
            8 => 'id',
        ];

        $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $orderColumn = $columns[$orderIndex] ?? 'hsn_code';

        $sql =
            'SELECT id, hsn_code, description,
                    gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate, status
             FROM hsn_master
             WHERE ' . implode(' AND ', $where) .
            ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ', id ASC
              LIMIT :start, :length';

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
            $row['id'] = (int)$row['id'];
            foreach (['gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate'] as $key) {
                $row[$key] = (float)$row[$key];
            }
            $row['status'] = (int)$row['status'];
        }
        unset($row);

        json_success('HSN DataTable loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'allowed_actions' => $access['actions'],
        ]);
    }

    $sql =
        'SELECT id, hsn_code, description,
                gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate, status
         FROM hsn_master
         WHERE branch_id = :branch_id';

    if (isset($_GET['active']) && (int)$_GET['active'] === 1) {
        $sql .= ' AND status = 1';
    }

    $sql .= ' ORDER BY hsn_code ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        foreach (['gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('HSN records loaded.', [
        'hsn_codes' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('hsn-master.php', ACTION_CREATE);
    $user = $access['user'];
    $context = hsn_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();
    $clean = hsn_validate($data);

    hsn_unique($branchId, $clean['hsn_code']);

    $stmt = db()->prepare(
        'INSERT INTO hsn_master
         (branch_id, hsn_code, description, gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate,
          status, created_by, created_at, updated_at)
         VALUES
         (:branch_id, :hsn_code, :description, :gst_rate, :cgst_rate, :sgst_rate, :igst_rate, :cess_rate,
          1, :created_by, NOW(), NOW())'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':hsn_code' => $clean['hsn_code'],
        ':description' => $clean['description'],
        ':gst_rate' => $clean['gst_rate'],
        ':cgst_rate' => $clean['cgst_rate'],
        ':sgst_rate' => $clean['sgst_rate'],
        ':igst_rate' => $clean['igst_rate'],
        ':cess_rate' => $clean['cess_rate'],
        ':created_by' => (int)$user['id'],
    ]);

    $id = (int)db()->lastInsertId();

    audit_log((int)$user['id'], ACTION_CREATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
    ]);

    json_success('HSN created successfully.', [
        'hsn' => hsn_record($branchId, $id),
    ], 201);
}

if ($method === 'PUT') {
    $access = require_permission('hsn-master.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = hsn_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['id' => 'HSN record ID is required.']);

    $id = positive_id($data['id']);
    $old = hsn_record($branchId, $id);
    $clean = hsn_validate($data);

    hsn_unique($branchId, $clean['hsn_code'], $id);

    db()->prepare(
        'UPDATE hsn_master
         SET hsn_code = :hsn_code,
             description = :description,
             gst_rate = :gst_rate,
             cgst_rate = :cgst_rate,
             sgst_rate = :sgst_rate,
             igst_rate = :igst_rate,
             cess_rate = :cess_rate,
             updated_at = NOW()
         WHERE id = :id AND branch_id = :branch_id'
    )->execute([
        ':hsn_code' => $clean['hsn_code'],
        ':description' => $clean['description'],
        ':gst_rate' => $clean['gst_rate'],
        ':cgst_rate' => $clean['cgst_rate'],
        ':sgst_rate' => $clean['sgst_rate'],
        ':igst_rate' => $clean['igst_rate'],
        ':cess_rate' => $clean['cess_rate'],
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

    json_success('HSN updated successfully.', [
        'hsn' => hsn_record($branchId, $id),
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, [
        'id' => 'HSN record ID is required.',
        'status' => 'Status is required.',
    ]);

    $status = hsn_status($data['status']);
    $action = $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;
    $access = require_permission('hsn-master.php', $action);
    $user = $access['user'];
    $context = hsn_context($user);
    $branchId = (int)$context['branch_id'];
    $id = positive_id($data['id']);
    $old = hsn_record($branchId, $id);

    db()->prepare(
        'UPDATE hsn_master
         SET status = :status, updated_at = NOW()
         WHERE id = :id AND branch_id = :branch_id'
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

    json_success($status === 1 ? 'HSN activated.' : 'HSN deactivated.');
}

json_error('Method not allowed.', 405);
