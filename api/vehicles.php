<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function vehicle_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Vehicle management is available only for tenant users.', 403);
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
    $context = $stmt->fetch();

    if (!$context) {
        json_error('Your assigned branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$context['branch_id'],
        'company_id' => (int)$context['company_id'],
        'branch_name' => (string)$context['branch_name'],
        'company_name' => (string)$context['company_name'],
    ];
}

function vehicle_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Vehicle reference is required.', 422, [
            'ref' => 'Vehicle reference is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'vehicle');
    } catch (Throwable $exception) {
        json_error($exception->getMessage(), 422, [
            'ref' => 'Invalid vehicle reference.',
        ]);
    }

    return 0;
}

function vehicle_status_value($value): int
{
    $status = (int)$value;
    if ($status !== 1 && $status !== 2) {
        json_error('Invalid vehicle status.', 422, [
            'status' => 'Status must be Active or Inactive.',
        ]);
    }
    return $status;
}

function vehicle_validate_no($value): string
{
    $vehicleNo = strtoupper(trim((string)$value));

    if ($vehicleNo === '') {
        json_error('Vehicle number is required.', 422, [
            'vehicle_no' => 'Vehicle number is required.',
        ]);
    }

    if (strlen($vehicleNo) > 30) {
        json_error('Vehicle number is too long.', 422, [
            'vehicle_no' => 'Vehicle number must be within 30 characters.',
        ]);
    }

    return $vehicleNo;
}

function vehicle_validate_name($value): ?string
{
    $vehicleName = trim((string)$value);
    if ($vehicleName === '') return null;

    if (strlen($vehicleName) > 100) {
        json_error('Vehicle name is too long.', 422, [
            'vehicle_name' => 'Vehicle name must be within 100 characters.',
        ]);
    }

    return $vehicleName;
}

function vehicle_assert_unique_no(int $branchId, string $vehicleNo, int $excludeId = 0): void
{
    $sql = 'SELECT id
            FROM vehicles
            WHERE branch_id = :branch_id
              AND LOWER(vehicle_no) = LOWER(:vehicle_no)';
    $params = [
        ':branch_id' => $branchId,
        ':vehicle_no' => $vehicleNo,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Vehicle number already exists in this branch.', 409, [
            'vehicle_no' => 'Use a unique vehicle number.',
        ]);
    }
}

function vehicle_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, vehicle_no, vehicle_name, status,
                created_by, created_at, updated_at
         FROM vehicles
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
        json_error('Vehicle was not found in your branch.', 404);
    }

    $reference = encryptReference('vehicle', (int)$row['id']);
    $row['branch_id'] = (int)$row['branch_id'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = $reference;
    $row['edit_url'] = 'vehicle-form.php?ref=' . rawurlencode($reference);
    unset($row['id']);

    return $row;
}

function vehicle_result_item(array $row): array
{
    $reference = encryptReference('vehicle', (int)$row['id']);
    unset($row['id']);
    $row['status'] = (int)$row['status'];
    $row['ref'] = $reference;
    $row['edit_url'] = 'vehicle-form.php?ref=' . rawurlencode($reference);
    return $row;
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('vehicle-list.php', ACTION_VIEW);
    $context = vehicle_tenant_context($access['user']);

    json_success('Vehicle form options loaded.', [
        'branch' => [
            'id' => $context['branch_id'],
            'branch_name' => $context['branch_name'],
            'company_name' => $context['company_name'],
        ],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('vehicle-list.php', ACTION_VIEW);
    $context = vehicle_tenant_context($access['user']);
    $vehicleId = vehicle_id_from_reference($_GET['ref']);

    json_success('Vehicle loaded.', [
        'vehicle' => vehicle_record((int)$context['branch_id'], $vehicleId),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('vehicle-list.php', ACTION_VIEW);
    $context = vehicle_tenant_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = (int)($_GET['length'] ?? 10);
    if ($length < 1) $length = 10;
    $length = min($length, 100000);

    $search = trim((string)($_GET['search']['value'] ?? ''));
    $baseWhere = ['branch_id = :branch_id'];
    $where = $baseWhere;
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = '(vehicle_no LIKE :search OR vehicle_name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*) FROM vehicles WHERE ' . implode(' AND ', $baseWhere)
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare(
        'SELECT COUNT(*) FROM vehicles WHERE ' . implode(' AND ', $where)
    );
    $filteredStmt->execute($params);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $orderColumns = [
        0 => 'vehicle_no',
        1 => 'vehicle_name',
        2 => 'status',
        3 => 'id',
    ];
    $orderIndex = 3;
    $orderDir = 'DESC';

    if (isset($_GET['order'][0]) && is_array($_GET['order'][0])) {
        $requestedIndex = (int)($_GET['order'][0]['column'] ?? 3);
        if (isset($orderColumns[$requestedIndex])) $orderIndex = $requestedIndex;
        $requestedDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc'));
        $orderDir = $requestedDir === 'asc' ? 'ASC' : 'DESC';
    }

    $sql = 'SELECT id, vehicle_no, vehicle_name, status, created_at
            FROM vehicles
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderColumns[$orderIndex] . ' ' . $orderDir . '
            LIMIT :start, :length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':branch_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = array_map('vehicle_result_item', $stmt->fetchAll());

    json_success('Vehicle DataTable loaded.', [
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
    $access = require_permission('vehicle-list.php', ACTION_VIEW);
    $context = vehicle_tenant_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $onlyActive = isset($_GET['active']) && (int)$_GET['active'] === 1;

    $sql = 'SELECT id, vehicle_no, vehicle_name, status, created_at
            FROM vehicles
            WHERE branch_id = :branch_id';
    if ($onlyActive) $sql .= ' AND status = 1';
    $sql .= ' ORDER BY vehicle_no ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);

    json_success('Vehicles loaded.', [
        'vehicles' => array_map('vehicle_result_item', $stmt->fetchAll()),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('vehicle-list.php', ACTION_CREATE);
    $user = $access['user'];
    $context = vehicle_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    $vehicleNo = vehicle_validate_no($data['vehicle_no'] ?? '');
    $vehicleName = vehicle_validate_name($data['vehicle_name'] ?? '');
    vehicle_assert_unique_no($branchId, $vehicleNo);

    try {
        $stmt = db()->prepare(
            'INSERT INTO vehicles
             (branch_id, vehicle_no, vehicle_name, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, :vehicle_no, :vehicle_name, 1, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':vehicle_no' => $vehicleNo,
            ':vehicle_name' => $vehicleName,
            ':created_by' => (int)$user['id'],
        ]);

        $vehicleId = (int)db()->lastInsertId();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $vehicleId,
        ]);

        $vehicle = vehicle_record($branchId, $vehicleId);
        json_success('Vehicle created successfully.', [
            'vehicle' => $vehicle,
            'ref' => $vehicle['ref'],
        ], 201);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            vehicle_assert_unique_no($branchId, $vehicleNo);
            json_error('Vehicle number already exists in this branch.', 409, [
                'vehicle_no' => 'Use a unique vehicle number.',
            ]);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('vehicle-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = vehicle_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, [
        'ref' => 'Vehicle reference is required.',
        'vehicle_no' => 'Vehicle number is required.',
    ]);

    $vehicleId = vehicle_id_from_reference($data['ref']);
    $old = vehicle_record($branchId, $vehicleId);
    $vehicleNo = vehicle_validate_no($data['vehicle_no']);
    $vehicleName = vehicle_validate_name($data['vehicle_name'] ?? '');
    vehicle_assert_unique_no($branchId, $vehicleNo, $vehicleId);

    try {
        $stmt = db()->prepare(
            'UPDATE vehicles
             SET vehicle_no = :vehicle_no,
                 vehicle_name = :vehicle_name,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );
        $stmt->execute([
            ':vehicle_no' => $vehicleNo,
            ':vehicle_name' => $vehicleName,
            ':id' => $vehicleId,
            ':branch_id' => $branchId,
        ]);

        audit_log((int)$user['id'], ACTION_UPDATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $vehicleId,
            'old_data' => $old,
        ]);

        $vehicle = vehicle_record($branchId, $vehicleId);
        json_success('Vehicle updated successfully.', [
            'vehicle' => $vehicle,
            'ref' => $vehicle['ref'],
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            vehicle_assert_unique_no($branchId, $vehicleNo, $vehicleId);
            json_error('Vehicle number already exists in this branch.', 409, [
                'vehicle_no' => 'Use a unique vehicle number.',
            ]);
        }
        throw $exception;
    }
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, [
        'ref' => 'Vehicle reference is required.',
        'status' => 'Status is required.',
    ]);

    $status = vehicle_status_value($data['status']);
    $access = require_permission(
        'vehicle-list.php',
        $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE
    );
    $user = $access['user'];
    $context = vehicle_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $vehicleId = vehicle_id_from_reference($data['ref']);
    $old = vehicle_record($branchId, $vehicleId);

    db()->prepare(
        'UPDATE vehicles
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id'
    )->execute([
        ':status' => $status,
        ':id' => $vehicleId,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $vehicleId,
        'old_data' => $old,
    ]);

    json_success($status === 1
        ? 'Vehicle activated successfully.'
        : 'Vehicle deactivated successfully.');
}

json_error('Method not allowed. Vehicle deletion is not enabled.', 405);
