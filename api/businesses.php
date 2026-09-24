<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function business_record_for_user(array $user, int $companyId): array
{
    $stmt = db()->prepare(
        'SELECT c.*,
                (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id) AS branch_count
         FROM companies c WHERE c.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $companyId]);
    $company = $stmt->fetch();
    if (!$company) {
        json_error('Business was not found.', 404);
    }
    if ((int) $user['role_type'] !== 2 && (int) $company['id'] !== (int) $user['company_id']) {
        json_error('You cannot access another business.', 403);
    }
    return $company;
}

function validate_business_contact(array $data): void
{
    $errors = [];
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!empty($data['mobile']) && !preg_match('/^[6-9][0-9]{9}$/', (string) $data['mobile'])) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }
    if ($errors !== []) {
        json_error('Business validation failed.', 422, $errors);
    }
}

function validate_business_admin_contact(array $data): void
{
    $errors = [];
    if (!empty($data['admin_email']) &&
        !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = 'Enter a valid email address.';
    }
    if (!empty($data['admin_mobile']) &&
        !preg_match('/^[6-9][0-9]{9}$/', (string) $data['admin_mobile'])) {
        $errors['admin_mobile'] = 'Enter a valid 10-digit mobile number.';
    }
    if ($errors !== []) {
        json_error('Branch Admin validation failed.', 422, $errors);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('business-list.php', ACTION_VIEW);
    $user = $access['user'];

    if (isset($_GET['id'])) {
        $company = business_record_for_user($user, positive_id($_GET['id']));
        json_success('Business loaded.', [
            'business' => $company,
            'allowed_actions' => $access['actions'],
        ]);
    }

    if ((int) $user['role_type'] === 2) {
        $stmt = db()->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id) AS branch_count
             FROM companies c ORDER BY c.id DESC'
        );
    } else {
        $stmt = db()->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id) AS branch_count
             FROM companies c WHERE c.id = :company_id'
        );
        $stmt->execute([':company_id' => (int) $user['company_id']]);
    }
    json_success('Businesses loaded.', [
        'businesses' => $stmt->fetchAll(),
        'allowed_actions' => $access['actions'],
        'current_user' => [
            'role_type' => (int) $user['role_type'],
            'company_id' => $user['company_id'] === null ? null : (int) $user['company_id'],
        ],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('business-list.php', ACTION_CREATE);
    $platformUser = require_platform_user();
    $data = request_data();
    require_fields($data, [
        'company_name', 'company_code', 'branch_name', 'branch_code',
        'plan_role_id', 'admin_name', 'username', 'password'
    ]);
    validate_business_contact([
        'email' => isset($data['company_email']) ? $data['company_email'] : '',
        'mobile' => isset($data['company_mobile']) ? $data['company_mobile'] : '',
    ]);
    validate_business_admin_contact($data);
    require_strong_password($data['password']);

    $planRoleId = positive_id($data['plan_role_id'], 'plan_role_id');
    $planStmt = db()->prepare(
        'SELECT id FROM roles
         WHERE id = :id AND role_type = 1 AND company_id IS NULL AND status = 1 LIMIT 1'
    );
    $planStmt->execute([':id' => $planRoleId]);
    if (!$planStmt->fetch()) {
        json_error('Selected plan is invalid or inactive.', 422);
    }

    $status = isset($data['status']) ? normalize_status($data['status']) : 1;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO companies
             (company_name, company_code, email, mobile, status, created_by, created_at, updated_at)
             VALUES (:name, :code, :email, :mobile, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => trim((string) $data['company_name']),
            ':code' => trim((string) $data['company_code']),
            ':email' => isset($data['company_email']) ? trim((string) $data['company_email']) : null,
            ':mobile' => isset($data['company_mobile']) ? trim((string) $data['company_mobile']) : null,
            ':status' => $status,
            ':created_by' => (int) $platformUser['id'],
        ]);
        $companyId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO branches
             (company_id, branch_name, branch_code, role_id, status, created_by, created_at, updated_at)
             VALUES (:company_id, :name, :code, :role_id, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => trim((string) $data['branch_name']),
            ':code' => trim((string) $data['branch_code']),
            ':role_id' => $planRoleId,
            ':status' => $status,
            ':created_by' => (int) $platformUser['id'],
        ]);
        $branchId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO users
             (company_id, branch_id, role_id, name, username, email, mobile,
              password_hash, status, created_by, created_at, updated_at)
             VALUES (:company_id, :branch_id, :role_id, :name, :username,
                     :email, :mobile, :password_hash, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':branch_id' => $branchId,
            ':role_id' => $planRoleId,
            ':name' => trim((string) $data['admin_name']),
            ':username' => trim((string) $data['username']),
            ':email' => isset($data['admin_email']) ? trim((string) $data['admin_email']) : null,
            ':mobile' => isset($data['admin_mobile']) ? trim((string) $data['admin_mobile']) : null,
            ':password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
            ':status' => $status,
            ':created_by' => (int) $platformUser['id'],
        ]);
        $adminUserId = (int) $pdo->lastInsertId();
        $pdo->commit();

        audit_log((int) $platformUser['id'], ACTION_CREATE, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $companyId,
        ]);
        json_success('Business, main branch and Branch Admin created successfully.', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'admin_user_id' => $adminUserId,
        ], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Company code, branch code or username already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('business-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $data = request_data();
    require_fields($data, ['id', 'company_name', 'company_code']);
    validate_business_contact($data);
    $companyId = positive_id($data['id']);
    $company = business_record_for_user($user, $companyId);
    $status = (int) $company['status'];
    if ((int) $user['role_type'] === 2 && isset($data['status'])) {
        $status = normalize_status($data['status']);
    }

    $stmt = db()->prepare(
        'UPDATE companies SET company_name = :name, company_code = :code,
         email = :email, mobile = :mobile, status = :status, updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':name' => trim((string) $data['company_name']),
        ':code' => trim((string) $data['company_code']),
        ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
        ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
        ':status' => $status,
        ':id' => $companyId,
    ]);
    audit_log((int) $user['id'], ACTION_UPDATE, [
        'company_id' => $companyId,
        'branch_id' => $user['branch_id'],
        'menu_id' => (int) $access['menu']['id'],
        'record_id' => $companyId,
    ]);
    json_success('Business updated successfully.');
}

if ($method === 'PATCH') {
    $access = require_permission('business-list.php', ACTION_UPDATE);
    $user = require_platform_user();
    $data = request_data();
    require_fields($data, ['id', 'status']);
    $companyId = positive_id($data['id']);
    business_record_for_user($user, $companyId);
    db()->prepare('UPDATE companies SET status = :status, updated_at = NOW() WHERE id = :id')
        ->execute([':status' => normalize_status($data['status']), ':id' => $companyId]);
    json_success('Business status updated successfully.');
}

json_error('Method not allowed.', 405);
