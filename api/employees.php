<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function employee_requested_branch(array $user, array $data = [], bool $requiredForPlatform = true)
{
    if ((int) $user['role_type'] !== 2) {
        return positive_id($user['branch_id'], 'branch_id');
    }
    $value = isset($data['branch_id']) ? $data['branch_id'] :
        (isset($_GET['branch_id']) ? $_GET['branch_id'] : null);
    if (!$requiredForPlatform && ($value === null || $value === '' || (int) $value === 0)) {
        return null;
    }
    return positive_id($value, 'branch_id');
}

function employee_branch_record(array $user, int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT b.id, b.company_id, b.branch_name, c.company_name
         FROM branches b INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :id AND b.status = 1 AND c.status = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $branchId]);
    $branch = $stmt->fetch();
    if (!$branch) {
        json_error('Selected branch is invalid or inactive.', 422);
    }
    if ((int) $user['role_type'] !== 2 && $branchId !== (int) $user['branch_id']) {
        json_error('You cannot access another branch.', 403);
    }
    return $branch;
}

function employee_role_for_branch(int $roleId, array $branch): array
{
    $stmt = db()->prepare(
        'SELECT id, role_name FROM roles
         WHERE id = :id AND company_id = :company_id AND role_type = 3 AND status = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $roleId, ':company_id' => (int) $branch['company_id']]);
    $role = $stmt->fetch();
    if (!$role) {
        json_error('Select an active tenant-created role belonging to this business.', 422);
    }
    return $role;
}

function normalize_employee_data(array $data): array
{
    foreach (['email', 'mobile', 'pan', 'aadhaar'] as $field) {
        if (array_key_exists($field, $data)) {
            $data[$field] = normalize_input_value($field, $data[$field]);
        }
    }
    return $data;
}

function validate_employee_data(array $data): void
{
    $errors = [];
    if (!empty($data['email']) && !valid_input_value('email', $data['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!empty($data['mobile']) && !valid_input_value('mobile', $data['mobile'])) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }
    if (!empty($data['pan']) && !valid_input_value('pan', $data['pan'])) {
        $errors['pan'] = 'PAN format must be ABCDE1234F.';
    }
    if (!empty($data['aadhaar']) && !valid_input_value('aadhaar', $data['aadhaar'])) {
        $errors['aadhaar'] = 'Enter a valid 12-digit Aadhaar number.';
    }
    if ($errors !== []) {
        json_error('Employee validation failed.', 422, $errors);
    }
}

function employee_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Encrypted employee reference is required.', 422, ['ref' => 'Employee reference is required.']);
        return 0;
    }
    try {
        return decryptReference(trim($value), 'employee');
    } catch (Throwable $exception) {
        json_error($exception->getMessage(), 422, ['ref' => 'Invalid employee reference.']);
        return 0;
    }
}

function employee_record_for_user(array $user, int $employeeId): array
{
    $stmt = db()->prepare(
        'SELECT e.id, e.user_id, e.branch_id, e.employee_code, e.name, e.email,
                e.mobile, e.pan, e.aadhaar, e.media_files, e.status,
                e.created_at, e.updated_at, u.role_id, u.username,
                u.status AS account_status, r.role_name, b.branch_name,
                b.company_id, c.company_name
         FROM employees e
         LEFT JOIN users u ON u.id = e.user_id
         LEFT JOIN roles r ON r.id = u.role_id
         INNER JOIN branches b ON b.id = e.branch_id
         INNER JOIN companies c ON c.id = b.company_id
         WHERE e.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $employeeId]);
    $employee = $stmt->fetch();
    if (!$employee) {
        json_error('Employee was not found.', 404);
    }
    if ((int) $user['role_type'] !== 2 && (int) $employee['branch_id'] !== (int) $user['branch_id']) {
        json_error('You cannot access an employee from another branch.', 403);
    }
    $employee['media_files'] = json_decode($employee['media_files'] ?: '[]', true);
    if (!is_array($employee['media_files'])) {
        $employee['media_files'] = [];
    }
    $employee['ref'] = encryptReference('employee', (int) $employee['id']);
    unset($employee['id']);
    return $employee;
}

function employee_options(array $user): void
{
    $branchId = employee_requested_branch($user, [], false);
    if ((int) $user['role_type'] === 2) {
        $branches = db()->query(
            'SELECT b.id, b.branch_name, c.company_name
             FROM branches b INNER JOIN companies c ON c.id = b.company_id
             WHERE b.status = 1 AND c.status = 1 ORDER BY c.company_name, b.branch_name'
        )->fetchAll();
    } else {
        $stmt = db()->prepare(
            'SELECT b.id, b.branch_name, c.company_name
             FROM branches b INNER JOIN companies c ON c.id = b.company_id WHERE b.id = :id'
        );
        $stmt->execute([':id' => (int) $user['branch_id']]);
        $branches = $stmt->fetchAll();
    }

    $roles = [];
    if ($branchId !== null) {
        $branch = employee_branch_record($user, (int) $branchId);
        $stmt = db()->prepare(
            'SELECT id, role_name FROM roles
             WHERE company_id = :company_id AND role_type = 3 AND status = 1
             ORDER BY role_name'
        );
        $stmt->execute([':company_id' => (int) $branch['company_id']]);
        $roles = $stmt->fetchAll();
    }

    json_success('Employee form options loaded.', [
        'branches' => $branches,
        'roles' => $roles,
        'selected_branch_id' => $branchId,
        'current_user' => [
            'role_type' => (int) $user['role_type'],
            'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
        ],
    ]);
}

function employee_list_options(array $user): void
{
    if ((int) $user['role_type'] === 2) {
        $branches = db()->query(
            'SELECT b.id, b.company_id, b.branch_name, c.company_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             WHERE b.status = 1 AND c.status = 1
             ORDER BY c.company_name, b.branch_name'
        )->fetchAll();

        $roles = db()->query(
            'SELECT r.id, r.company_id, r.role_name, c.company_name
             FROM roles r
             INNER JOIN companies c ON c.id = r.company_id
             WHERE r.role_type = 3 AND r.status = 1 AND c.status = 1
             ORDER BY c.company_name, r.role_name'
        )->fetchAll();
    } else {
        $branch = employee_branch_record($user, (int) $user['branch_id']);
        $branches = [[
            'id' => (int) $branch['id'],
            'company_id' => (int) $branch['company_id'],
            'branch_name' => (string) $branch['branch_name'],
            'company_name' => (string) $branch['company_name'],
        ]];

        $stmt = db()->prepare(
            'SELECT r.id, r.company_id, r.role_name, c.company_name
             FROM roles r
             INNER JOIN companies c ON c.id = r.company_id
             WHERE r.company_id = :company_id AND r.role_type = 3 AND r.status = 1
             ORDER BY r.role_name'
        );
        $stmt->execute([':company_id' => (int) $branch['company_id']]);
        $roles = $stmt->fetchAll();
    }

    json_success('Employee list filters loaded.', [
        'branches' => $branches,
        'roles' => $roles,
        'current_user' => [
            'role_type' => (int) $user['role_type'],
            'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
        ],
    ]);
}

function employee_result_item(array $row): array
{
    $reference = encryptReference('employee', (int) $row['id']);
    unset($row['id']);
    $row['ref'] = $reference;
    $row['edit_url'] = 'employee-form.php?ref=' . $reference;
    return $row;
}

$method = request_method();

if ($method === 'GET' && isset($_GET['list_options'])) {
    $access = require_permission('employee-list.php', ACTION_VIEW);
    employee_list_options($access['user']);
}

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('employee-form.php', ACTION_VIEW);
    employee_options($access['user']);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('employee-form.php', ACTION_VIEW);
    $employee = employee_record_for_user(
        $access['user'],
        employee_id_from_reference($_GET['ref'])
    );
    json_success('Employee loaded.', [
        'employee' => $employee,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('employee-list.php', ACTION_VIEW);
    $user = $access['user'];
    $branchId = employee_requested_branch($user, [], false);
    if ((int) $user['role_type'] !== 2 && $branchId === null) {
        $branchId = (int) $user['branch_id'];
    }

    $draw = isset($_GET['draw']) ? max(0, (int) $_GET['draw']) : 0;
    $start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
    $length = isset($_GET['length']) ? (int) $_GET['length'] : 10;
    if ($length < 1) $length = 10;
    $length = min($length, 100000);

    $searchValue = '';
    if (isset($_GET['search']) && is_array($_GET['search']) && isset($_GET['search']['value'])) {
        $searchValue = trim((string) $_GET['search']['value']);
    }

    $roleId = null;
    $roleRaw = trim((string) ($_GET['role_id'] ?? ''));
    if ($roleRaw !== '') {
        $roleId = positive_id($roleRaw, 'role_id');
    }

    $statusFilter = null;
    $statusRaw = trim((string) ($_GET['status'] ?? ''));
    if ($statusRaw !== '') {
        $statusFilter = (int) $statusRaw;
        if (!in_array($statusFilter, [0, 1], true)) {
            json_error('Invalid employee status filter.', 422);
        }
    }

    $baseFrom =
        ' FROM employees e
          LEFT JOIN users u ON u.id = e.user_id
          LEFT JOIN roles r ON r.id = u.role_id
          INNER JOIN branches b ON b.id = e.branch_id
          INNER JOIN companies c ON c.id = b.company_id';

    $where = [];
    $params = [];
    if ((int) $user['role_type'] !== 2 || $branchId !== null) {
        $where[] = 'e.branch_id = :branch_id';
        $params[':branch_id'] = $branchId === null ? (int) $user['branch_id'] : (int) $branchId;
    }
    $baseWhere = $where;

    if ($roleId !== null) {
        $where[] = 'u.role_id = :role_id';
        $params[':role_id'] = $roleId;
    }

    if ($statusFilter !== null) {
        $where[] = 'e.status = :status_filter';
        $params[':status_filter'] = $statusFilter;
    }

    if ($searchValue !== '') {
        $like = '%' . $searchValue . '%';
        $where[] = '(e.employee_code LIKE :search_code
                     OR e.name LIKE :search_name
                     OR e.email LIKE :search_email
                     OR e.mobile LIKE :search_mobile
                     OR u.username LIKE :search_username
                     OR r.role_name LIKE :search_role
                     OR b.branch_name LIKE :search_branch
                     OR c.company_name LIKE :search_company)';
        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_email'] = $like;
        $params[':search_mobile'] = $like;
        $params[':search_username'] = $like;
        $params[':search_role'] = $like;
        $params[':search_branch'] = $like;
        $params[':search_company'] = $like;
    }

    $totalParams = [];
    $totalWhere = $baseWhere;
    if ($branchId !== null || (int) $user['role_type'] !== 2) {
        $totalParams[':branch_id'] = $branchId === null ? (int) $user['branch_id'] : (int) $branchId;
    }
    $totalSql = 'SELECT COUNT(*)' . $baseFrom . ($totalWhere ? ' WHERE ' . implode(' AND ', $totalWhere) : '');
    $totalStmt = db()->prepare($totalSql);
    $totalStmt->execute($totalParams);
    $recordsTotal = (int) $totalStmt->fetchColumn();

    $filteredSql = 'SELECT COUNT(*)' . $baseFrom . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $filteredStmt = db()->prepare($filteredSql);
    $filteredStmt->execute($params);
    $recordsFiltered = (int) $filteredStmt->fetchColumn();

    $summarySql =
        'SELECT COUNT(*) AS total_employees,
                COALESCE(SUM(CASE WHEN e.status = 1 THEN 1 ELSE 0 END),0) AS active_employees,
                COALESCE(SUM(CASE WHEN e.status = 0 THEN 1 ELSE 0 END),0) AS inactive_employees,
                COALESCE(SUM(CASE WHEN e.user_id IS NOT NULL THEN 1 ELSE 0 END),0) AS login_accounts' .
        $baseFrom .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '');

    $summaryStmt = db()->prepare($summarySql);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        0 => 'e.employee_code', 1 => 'e.name', 2 => 'b.branch_name', 3 => 'r.role_name',
        4 => 'u.username', 5 => 'e.email', 6 => 'e.status', 7 => 'e.id'
    ];
    $orderIndex = 7;
    $orderDir = 'DESC';
    if (isset($_GET['order'][0]) && is_array($_GET['order'][0])) {
        $requestedIndex = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : 7;
        if (isset($orderColumns[$requestedIndex])) $orderIndex = $requestedIndex;
        $requestedDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc'));
        $orderDir = $requestedDir === 'asc' ? 'ASC' : 'DESC';
    }

    $sql =
        'SELECT e.id, e.employee_code, e.name, e.email, e.mobile, e.status,
                e.created_at, u.username, r.role_name, b.branch_name, c.company_name' .
        $baseFrom .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
        ' ORDER BY ' . $orderColumns[$orderIndex] . ' ' . $orderDir .
        ' LIMIT ' . $start . ', ' . $length;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = array_map('employee_result_item', $stmt->fetchAll());
    $formActions = effective_actions_for_menu($user, menu_by_path('employee-form.php'));

    json_success('Employee DataTable loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_employees' => (int) ($summary['total_employees'] ?? 0),
            'active_employees' => (int) ($summary['active_employees'] ?? 0),
            'inactive_employees' => (int) ($summary['inactive_employees'] ?? 0),
            'login_accounts' => (int) ($summary['login_accounts'] ?? 0),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
        'current_user' => ['role_type' => (int) $user['role_type']],
    ]);
}

if ($method === 'GET') {
    $access = require_permission('employee-list.php', ACTION_VIEW);
    $user = $access['user'];
    $branchId = employee_requested_branch($user, [], false);
    $sql =
        'SELECT e.id, e.employee_code, e.name, e.email, e.mobile, e.status,
                e.created_at, u.username, r.role_name, b.branch_name, c.company_name
         FROM employees e
         LEFT JOIN users u ON u.id = e.user_id
         LEFT JOIN roles r ON r.id = u.role_id
         INNER JOIN branches b ON b.id = e.branch_id
         INNER JOIN companies c ON c.id = b.company_id';
    $params = [];
    if ((int) $user['role_type'] !== 2 || $branchId !== null) {
        $branchId = $branchId === null ? (int) $user['branch_id'] : (int) $branchId;
        $sql .= ' WHERE e.branch_id = :branch_id';
        $params[':branch_id'] = $branchId;
    }
    $sql .= ' ORDER BY e.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $employees = array_map('employee_result_item', $stmt->fetchAll());
    $formActions = effective_actions_for_menu($user, menu_by_path('employee-form.php'));
    json_success('Employees loaded.', [
        'employees' => $employees,
        'allowed_actions' => $access['actions'],
        'form_actions' => $formActions,
        'current_user' => ['role_type' => (int) $user['role_type']],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('employee-form.php', ACTION_CREATE);
    $user = $access['user'];
    $data = normalize_employee_data(request_data());
    require_fields($data, [
        'branch_id' => 'Please select a branch.',
        'role_id' => 'Please select an employee role.',
        'employee_code' => 'Employee code is required.',
        'name' => 'Employee name is required.',
        'username' => 'Login username is required.',
        'password' => 'Login password is required.',
    ]);
    validate_employee_data($data);
    require_strong_password($data['password']);
    $branchId = employee_requested_branch($user, $data);
    $branch = employee_branch_record($user, $branchId);
    $roleId = positive_id($data['role_id'], 'role_id');
    employee_role_for_branch($roleId, $branch);
    $status = isset($data['status']) ? normalize_status($data['status']) : 1;
    $media = save_form_uploads('media_files', 'employees', 0, 10);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO users
             (company_id, branch_id, role_id, name, username, email, mobile,
              password_hash, status, created_by, created_at, updated_at)
             VALUES (:company_id, :branch_id, :role_id, :name, :username, :email,
                     :mobile, :password_hash, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => (int) $branch['company_id'],
            ':branch_id' => $branchId,
            ':role_id' => $roleId,
            ':name' => trim((string) $data['name']),
            ':username' => trim((string) $data['username']),
            ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
            ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
            ':password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);
        $employeeUserId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO employees
             (user_id, branch_id, employee_code, name, email, mobile, pan, aadhaar,
              media_files, status, created_by, created_at, updated_at)
             VALUES (:user_id, :branch_id, :employee_code, :name, :email, :mobile,
                     :pan, :aadhaar, :media_files, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':user_id' => $employeeUserId,
            ':branch_id' => $branchId,
            ':employee_code' => trim((string) $data['employee_code']),
            ':name' => trim((string) $data['name']),
            ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
            ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
            ':pan' => isset($data['pan']) ? strtoupper(trim((string) $data['pan'])) : null,
            ':aadhaar' => isset($data['aadhaar']) ? trim((string) $data['aadhaar']) : null,
            ':media_files' => json_encode($media),
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);
        $employeeId = (int) $pdo->lastInsertId();
        $pdo->commit();

        audit_log((int) $user['id'], ACTION_CREATE, [
            'company_id' => (int) $branch['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $employeeId,
        ]);
        json_success('Employee and login account created successfully.', [
            'ref' => encryptReference('employee', $employeeId),
        ], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Employee code or username already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('employee-form.php', ACTION_UPDATE);
    $user = $access['user'];
    $data = normalize_employee_data(request_data());
    require_fields($data, [
        'ref' => 'Employee reference is required.',
        'branch_id' => 'Please select a branch.',
        'role_id' => 'Please select an employee role.',
        'employee_code' => 'Employee code is required.',
        'name' => 'Employee name is required.',
        'username' => 'Login username is required.',
    ]);
    validate_employee_data($data);
    $employeeId = employee_id_from_reference($data['ref']);
    $old = employee_record_for_user($user, $employeeId);
    $branchId = employee_requested_branch($user, $data);
    $branch = employee_branch_record($user, $branchId);
    $roleId = positive_id($data['role_id'], 'role_id');
    employee_role_for_branch($roleId, $branch);
    $status = isset($data['status']) ? normalize_status($data['status']) : (int) $old['status'];
    $oldMedia = is_array($old['media_files']) ? $old['media_files'] : [];
    $newMedia = save_form_uploads('media_files', 'employees', 0, 10);
    $media = array_values(array_merge($oldMedia, $newMedia));

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $employeeUserId = empty($old['user_id']) ? 0 : (int) $old['user_id'];
        if ($employeeUserId === 0) {
            require_fields($data, [
                'password' => 'Login password is required for this employee.',
            ]);
            require_strong_password($data['password']);
            $stmt = $pdo->prepare(
                'INSERT INTO users
                 (company_id, branch_id, role_id, name, username, email, mobile,
                  password_hash, status, created_by, created_at, updated_at)
                 VALUES (:company_id, :branch_id, :role_id, :name, :username, :email,
                         :mobile, :password_hash, :status, :created_by, NOW(), NOW())'
            );
            $stmt->execute([
                ':company_id' => (int) $branch['company_id'], ':branch_id' => $branchId,
                ':role_id' => $roleId, ':name' => trim((string) $data['name']),
                ':username' => trim((string) $data['username']),
                ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
                ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
                ':password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
                ':status' => $status, ':created_by' => (int) $user['id'],
            ]);
            $employeeUserId = (int) $pdo->lastInsertId();
        } else {
            $sql =
                'UPDATE users SET company_id = :company_id, branch_id = :branch_id,
                 role_id = :role_id, name = :name, username = :username, email = :email,
                 mobile = :mobile, status = :status, updated_at = NOW()';
            $params = [
                ':company_id' => (int) $branch['company_id'], ':branch_id' => $branchId,
                ':role_id' => $roleId, ':name' => trim((string) $data['name']),
                ':username' => trim((string) $data['username']),
                ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
                ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
                ':status' => $status, ':id' => $employeeUserId,
            ];
            if (isset($data['password']) && (string) $data['password'] !== '') {
                require_strong_password($data['password']);
                $sql .= ', password_hash = :password_hash';
                $params[':password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
            }
            $sql .= ' WHERE id = :id';
            $pdo->prepare($sql)->execute($params);
        }

        $stmt = $pdo->prepare(
            'UPDATE employees SET user_id = :user_id, branch_id = :branch_id,
             employee_code = :employee_code, name = :name, email = :email,
             mobile = :mobile, pan = :pan, aadhaar = :aadhaar,
             media_files = :media_files, status = :status, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':user_id' => $employeeUserId, ':branch_id' => $branchId,
            ':employee_code' => trim((string) $data['employee_code']),
            ':name' => trim((string) $data['name']),
            ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
            ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
            ':pan' => isset($data['pan']) ? strtoupper(trim((string) $data['pan'])) : null,
            ':aadhaar' => isset($data['aadhaar']) ? trim((string) $data['aadhaar']) : null,
            ':media_files' => json_encode($media), ':status' => $status, ':id' => $employeeId,
        ]);
        $pdo->commit();

        audit_log((int) $user['id'], ACTION_UPDATE, [
            'company_id' => (int) $branch['company_id'], 'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'], 'record_id' => $employeeId,
        ]);
        json_success('Employee and login account updated successfully.', [
            'ref' => encryptReference('employee', $employeeId),
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Employee code or username already exists.', 409);
        }
        throw $exception;
    }
}

json_error('Method not allowed. Employee deletion is not enabled.', 405);
