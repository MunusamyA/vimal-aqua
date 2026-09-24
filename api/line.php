<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_ACTIVATE')) define('ACTION_ACTIVATE', 27);
if (!defined('ACTION_DEACTIVATE')) define('ACTION_DEACTIVATE', 28);

function line_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Line management is available only for tenant users.', 403);
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

function line_text($value, string $field, string $label, int $max): string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        json_error($label . ' is required.', 422, [$field => $label . ' is required.']);
    }
    if (mb_strlen($value) > $max) {
        json_error($label . ' is too long.', 422, [$field => $label . ' must be within ' . $max . ' characters.']);
    }
    return $value;
}

function line_status($value): int
{
    $status = (int)$value;
    if (!in_array($status, [0,1], true)) {
        json_error('Invalid Line status.', 422, ['status' => 'Status must be Active or Inactive.']);
    }
    return $status;
}

function line_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT line_code
         FROM `lines`
         WHERE branch_id=:branch_id
           AND line_code REGEXP '^LIN[0-9]+$'
         ORDER BY CAST(SUBSTRING(line_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^LIN([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'LIN' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function line_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id,branch_id,line_code,line_name,status,created_by,created_at,updated_at
         FROM `lines`
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) json_error('Line was not found in your branch.', 404);

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function line_unique(int $branchId, string $name, int $excludeId = 0): void
{
    $sql =
        'SELECT id
         FROM `lines`
         WHERE branch_id=:branch_id
           AND LOWER(line_name)=LOWER(:line_name)';
    $params = [
        ':branch_id' => $branchId,
        ':line_name' => $name,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Line name already exists in your branch.', 409, [
            'line_name' => 'Use a unique Line name.',
        ]);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('line-list.php', ACTION_VIEW);
    $user = $access['user'];
    $context = line_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    if (isset($_GET['options'])) {
        json_success('Line form options loaded.', [
            'next_line_code' => line_generate_code($branchId),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('Line loaded.', [
            'line' => line_record($branchId, positive_id($_GET['id'])),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['datatable'])) {
        $draw = max(1, (int)($_GET['draw'] ?? 1));
        $start = max(0, (int)($_GET['start'] ?? 0));
        $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
        $search = trim((string)($_GET['search']['value'] ?? ''));
        $statusFilter = (string)($_GET['status'] ?? '');

        $baseWhere = ['branch_id=:branch_id'];
        $where = $baseWhere;
        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $where[] = '(line_code LIKE :search_code OR line_name LIKE :search_name)';
            $term = '%' . $search . '%';
            $params[':search_code'] = $term;
            $params[':search_name'] = $term;
        }

        if ($statusFilter !== '') {
            $where[] = 'status=:status';
            $params[':status'] = line_status($statusFilter);
        }

        $totalStmt = db()->prepare(
            'SELECT COUNT(*) FROM `lines` WHERE ' . implode(' AND ', $baseWhere)
        );
        $totalStmt->execute([':branch_id' => $branchId]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*) FROM `lines` WHERE ' . implode(' AND ', $where)
        );
        $filteredStmt->execute($params);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $columns = ['line_code','line_name','status','id'];
        $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $orderColumn = $columns[$orderIndex] ?? 'line_code';

        $sql =
            'SELECT id,line_code,line_name,status
             FROM `lines`
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
            $row['id'] = (int)$row['id'];
            $row['status'] = (int)$row['status'];
        }
        unset($row);

        json_success('Lines loaded.', [
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
        'SELECT id,line_code,line_name,status
         FROM `lines`
         WHERE branch_id=:branch_id';

    if (isset($_GET['active']) && (int)$_GET['active'] === 1) {
        $sql .= ' AND status=1';
    }

    $sql .= ' ORDER BY line_name';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('Lines loaded.', [
        'lines' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('line-list.php', ACTION_CREATE);
    $user = $access['user'];
    $context = line_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    $name = line_text($data['line_name'] ?? '', 'line_name', 'Line name', 120);
    line_unique($branchId, $name);
    $code = line_generate_code($branchId);

    try {
        $stmt = db()->prepare(
            'INSERT INTO `lines`
             (branch_id,line_code,line_name,status,created_by,created_at,updated_at)
             VALUES
             (:branch_id,:line_code,:line_name,1,:created_by,NOW(),NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':line_code' => $code,
            ':line_name' => $name,
            ':created_by' => (int)$user['id'],
        ]);

        $id = (int)db()->lastInsertId();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
        ]);

        json_success('Line created successfully.', [
            'line' => line_record($branchId, $id),
        ], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_error('Line code or name already exists in your branch.', 409, [
                'line_name' => 'Use a unique Line name.',
            ]);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    $access = require_permission('line-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = line_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['id']);

    $id = positive_id($data['id']);
    $old = line_record($branchId, $id);
    $name = line_text($data['line_name'] ?? '', 'line_name', 'Line name', 120);
    line_unique($branchId, $name, $id);

    db()->prepare(
        'UPDATE `lines`
         SET line_name=:line_name,updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    )->execute([
        ':line_name' => $name,
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

    json_success('Line updated successfully.', [
        'line' => line_record($branchId, $id),
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['id','status']);

    $status = line_status($data['status']);
    $action = $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;
    $access = require_permission('line-list.php', $action);
    $user = $access['user'];
    $context = line_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $id = positive_id($data['id']);
    $old = line_record($branchId, $id);

    db()->prepare(
        'UPDATE `lines`
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

    json_success($status === 1 ? 'Line activated.' : 'Line deactivated.');
}

json_error('Method not allowed.', 405);
