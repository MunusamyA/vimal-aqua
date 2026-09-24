<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function account_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Account management is available only for tenant users.', 403);
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
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$context['branch_id'],
        'company_id' => (int)$context['company_id'],
        'branch_name' => (string)$context['branch_name'],
        'company_name' => (string)$context['company_name'],
    ];
}

function account_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT account_code
         FROM accounts
         WHERE branch_id = :branch_id
           AND account_code REGEXP '^ACC[0-9]+$'
         ORDER BY CAST(SUBSTRING(account_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string)($stmt->fetchColumn() ?: '');
    $nextNumber = 1;
    if ($lastCode !== '' && preg_match('/^ACC([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return 'ACC' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function account_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, account_code, account_name, account_type,
                opening_balance, description, status, created_by, created_at, updated_at
         FROM accounts
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
        json_error('Account was not found in your branch.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['account_type'] = (int)$row['account_type'];
    $row['status'] = (int)$row['status'];
    $row['opening_balance'] = number_format((float)$row['opening_balance'], 2, '.', '');
    $row['account_type_label'] = account_type_label((int)$row['account_type']);
    return $row;
}

function account_validate_name($value): string
{
    $name = trim((string)$value);
    if ($name === '') {
        json_error('Account name is required.', 422, [
            'account_name' => 'Account name is required.',
        ]);
    }
    if (strlen($name) > 120) {
        json_error('Account name is too long.', 422, [
            'account_name' => 'Account name must be within 120 characters.',
        ]);
    }
    return $name;
}

function account_validate_type($value): int
{
    $type = (int)$value;
    if (!in_array($type, [1, 2, 3, 4, 5], true)) {
        json_error('Select a valid account type.', 422, [
            'account_type' => 'Select Cash, Bank, UPI, Card or Other.',
        ]);
    }
    return $type;
}

function account_validate_opening_balance($value): string
{
    if ($value === null || $value === '') return '0.00';
    if (!is_numeric($value)) {
        json_error('Opening balance must be numeric.', 422, [
            'opening_balance' => 'Enter a valid opening balance.',
        ]);
    }

    $amount = round((float)$value, 2);
    if ($amount < 0) {
        json_error('Opening balance cannot be negative.', 422, [
            'opening_balance' => 'Opening balance cannot be negative.',
        ]);
    }
    if ($amount > 999999999999.99) {
        json_error('Opening balance is too large.', 422, [
            'opening_balance' => 'Opening balance is too large.',
        ]);
    }

    return number_format($amount, 2, '.', '');
}

function account_validate_description($value): ?string
{
    $description = trim((string)$value);
    if ($description === '') return null;
    if (strlen($description) > 255) {
        json_error('Account description is too long.', 422, [
            'description' => 'Description must be within 255 characters.',
        ]);
    }
    return $description;
}

function account_validate_status($value): int
{
    $status = (int)$value;
    if (!in_array($status, [1, 2], true)) {
        json_error('Invalid account status.', 422, ['status' => 'Status must be Active or Inactive.']);
    }
    return $status;
}

function account_assert_unique_name(int $branchId, string $name, int $excludeId = 0): void
{
    $sql = 'SELECT id FROM accounts
            WHERE branch_id = :branch_id
              AND LOWER(account_name) = LOWER(:account_name)';
    $params = [
        ':branch_id' => $branchId,
        ':account_name' => $name,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Account name already exists in your branch.', 409, [
            'account_name' => 'Use a unique account name.',
        ]);
    }
}

function account_type_label(int $type): string
{
    $labels = [
        1 => 'Cash',
        2 => 'Bank',
        3 => 'UPI',
        4 => 'Card',
        5 => 'Other',
    ];
    return $labels[$type] ?? 'Other';
}

/**
 * Keep accounts.opening_balance and account_transactions opening entry in sync.
 * Existing schema uses source_type=4 for Opening Balance.
 */
function account_sync_opening_balance(PDO $pdo, int $branchId, int $accountId, string $amount, int $userId): void
{
    $numericAmount = round((float)$amount, 2);

    $stmt = $pdo->prepare(
        'SELECT id
         FROM account_transactions
         WHERE branch_id = :branch_id
           AND account_id = :account_id
           AND source_type = 4
           AND source_id = :source_id
         ORDER BY id ASC'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':account_id' => $accountId,
        ':source_id' => $accountId,
    ]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($numericAmount <= 0) {
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $delete = $pdo->prepare('DELETE FROM account_transactions WHERE id IN (' . $placeholders . ')');
            $delete->execute($ids);
        }
        return;
    }

    if ($ids !== []) {
        $primaryId = array_shift($ids);
        $update = $pdo->prepare(
            'UPDATE account_transactions
             SET transaction_type = 1,
                 amount = :amount,
                 remarks = :remarks
             WHERE id = :id'
        );
        $update->execute([
            ':amount' => number_format($numericAmount, 2, '.', ''),
            ':remarks' => 'Opening balance',
            ':id' => $primaryId,
        ]);

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $delete = $pdo->prepare('DELETE FROM account_transactions WHERE id IN (' . $placeholders . ')');
            $delete->execute($ids);
        }
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO account_transactions
         (branch_id, transaction_date, account_id, transaction_type, source_type,
          source_id, amount, remarks, created_by, created_at)
         VALUES
         (:branch_id, NOW(), :account_id, 1, 4, :source_id, :amount, :remarks, :created_by, NOW())'
    );
    $insert->execute([
        ':branch_id' => $branchId,
        ':account_id' => $accountId,
        ':source_id' => $accountId,
        ':amount' => number_format($numericAmount, 2, '.', ''),
        ':remarks' => 'Opening balance',
        ':created_by' => $userId,
    ]);
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('account-list.php', ACTION_VIEW);
    $user = $access['user'];
    $context = account_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    if (isset($_GET['options'])) {
        json_success('Account form options loaded.', [
            'next_account_code' => account_generate_code($branchId),
            'account_types' => [
                ['value' => 1, 'label' => 'Cash'],
                ['value' => 2, 'label' => 'Bank'],
                ['value' => 3, 'label' => 'UPI'],
                ['value' => 4, 'label' => 'Card'],
                ['value' => 5, 'label' => 'Other'],
            ],
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('Account loaded.', [
            'account' => account_record($branchId, positive_id($_GET['id'])),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['datatable']) && (int)$_GET['datatable'] === 1) {
        $draw = max(1, (int)($_GET['draw'] ?? 1));
        $start = max(0, (int)($_GET['start'] ?? 0));
        $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
        $search = trim((string)($_GET['search']['value'] ?? ''));
        $statusFilter = null;
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $statusFilter = account_validate_status($_GET['status']);
        }

        $baseWhere = ['branch_id = :branch_id'];
        $where = $baseWhere;
        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $where[] = '(account_code LIKE :search OR account_name LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($statusFilter !== null) {
            $where[] = 'status = :status';
            $params[':status'] = $statusFilter;
        }

        $totalStmt = db()->prepare(
            'SELECT COUNT(*) FROM accounts WHERE ' . implode(' AND ', $baseWhere)
        );
        $totalStmt->execute([':branch_id' => $branchId]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*) FROM accounts WHERE ' . implode(' AND ', $where)
        );
        $filteredStmt->execute($params);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $columns = ['account_code', 'account_name', 'account_type', 'opening_balance', 'description', 'status', 'id'];
        $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $orderBy = $columns[$orderColumn] ?? 'account_code';

        $sql = 'SELECT id, account_code, account_name, account_type, opening_balance, description, status
                FROM accounts
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy . ' ' . $orderDir . ', id ASC
                LIMIT :start, :length';

        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $isInt = $key === ':status' || $key === ':branch_id';
            $stmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'account_code' => (string)$row['account_code'],
                'account_name' => (string)$row['account_name'],
                'account_type' => (int)$row['account_type'],
                'account_type_label' => account_type_label((int)$row['account_type']),
                'opening_balance' => number_format((float)$row['opening_balance'], 2, '.', ''),
                'description' => $row['description'],
                'status' => (int)$row['status'],
            ];
        }

        json_success('Account records loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'allowed_actions' => $access['actions'],
            'action_codes' => [
                'create' => ACTION_CREATE,
                'update' => ACTION_UPDATE,
                'activate' => ACTION_ACTIVATE,
                'deactivate' => ACTION_DEACTIVATE,
            ],
        ]);
    }

    $onlyActive = isset($_GET['active']) && (int)$_GET['active'] === 1;
    $sql = 'SELECT id, account_code, account_name, account_type, opening_balance, description, status
            FROM accounts
            WHERE branch_id = :branch_id';
    if ($onlyActive) $sql .= ' AND status = 1';
    $sql .= ' ORDER BY account_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['account_type'] = (int)$row['account_type'];
        $row['status'] = (int)$row['status'];
        $row['opening_balance'] = number_format((float)$row['opening_balance'], 2, '.', '');
        $row['account_type_label'] = account_type_label((int)$row['account_type']);
    }
    unset($row);

    json_success('Accounts loaded.', [
        'accounts' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('account-list.php', ACTION_CREATE);
    $user = $access['user'];
    $context = account_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    $name = account_validate_name($data['account_name'] ?? '');
    $type = account_validate_type($data['account_type'] ?? '');
    $openingBalance = account_validate_opening_balance($data['opening_balance'] ?? 0);
    $description = account_validate_description($data['description'] ?? '');
    account_assert_unique_name($branchId, $name);
    $code = account_generate_code($branchId);

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO accounts
             (branch_id, account_code, account_name, account_type, opening_balance,
              description, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, :account_code, :account_name, :account_type, :opening_balance,
              :description, 1, :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':account_code' => $code,
            ':account_name' => $name,
            ':account_type' => $type,
            ':opening_balance' => $openingBalance,
            ':description' => $description,
            ':created_by' => (int)$user['id'],
        ]);

        $newId = (int)$pdo->lastInsertId();
        account_sync_opening_balance($pdo, $branchId, $newId, $openingBalance, (int)$user['id']);
        $pdo->commit();

        $new = account_record($branchId, $newId);
        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $newId,
            'new_data' => $new,
        ]);

        json_success('Account created successfully.', [
            'account' => $new,
        ], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Account code or account name already exists in your branch.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('account-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = account_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['id']);
    $id = positive_id($data['id']);
    $old = account_record($branchId, $id);

    $name = account_validate_name($data['account_name'] ?? '');
    $type = account_validate_type($data['account_type'] ?? '');
    $openingBalance = account_validate_opening_balance($data['opening_balance'] ?? 0);
    $description = account_validate_description($data['description'] ?? '');
    account_assert_unique_name($branchId, $name, $id);

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE accounts
             SET account_name = :account_name,
                 account_type = :account_type,
                 opening_balance = :opening_balance,
                 description = :description,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );

        $stmt->execute([
            ':account_name' => $name,
            ':account_type' => $type,
            ':opening_balance' => $openingBalance,
            ':description' => $description,
            ':id' => $id,
            ':branch_id' => $branchId,
        ]);

        account_sync_opening_balance($pdo, $branchId, $id, $openingBalance, (int)$user['id']);
        $pdo->commit();

        $new = account_record($branchId, $id);
        audit_log((int)$user['id'], ACTION_UPDATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
            'new_data' => $new,
        ]);

        json_success('Account updated successfully.', [
            'account' => $new,
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Account code or account name already exists in your branch.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['id', 'status']);

    $id = positive_id($data['id']);
    $status = account_validate_status($data['status']);
    $access = require_permission('account-list.php', $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE);
    $user = $access['user'];
    $context = account_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $old = account_record($branchId, $id);

    db()->prepare(
        'UPDATE accounts
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id'
    )->execute([
        ':status' => $status,
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    $new = account_record($branchId, $id);
    audit_log((int)$user['id'], $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
        'new_data' => $new,
    ]);

    json_success($status === 1 ? 'Account activated.' : 'Account deactivated.', [
        'account' => $new,
    ]);
}

json_error('Method not allowed.', 405);
