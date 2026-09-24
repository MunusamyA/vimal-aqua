<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function subcategory_tenant_context(array $user): array
{
    if ((int) ($user['role_type'] ?? 0) === 2) {
        json_error('Subcategory management is available only for tenant users.', 403);
    }

    $branchId = (int) ($user['branch_id'] ?? 0);

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
        'branch_id' => (int) $row['branch_id'],
        'company_id' => (int) $row['company_id'],
        'branch_name' => (string) $row['branch_name'],
        'company_name' => (string) $row['company_name'],
    ];
}

function subcategory_text($value, string $field, string $label, int $max): string
{
    $value = trim((string) ($value ?? ''));

    if ($value === '') {
        json_error($label . ' is required.', 422, [$field => $label . ' is required.']);
    }

    if (mb_strlen($value) > $max) {
        json_error($label . ' is too long.', 422, [$field => $label . ' must be within ' . $max . ' characters.']);
    }

    return $value;
}

function subcategory_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT sc.subcategory_code
         FROM subcategories sc
         INNER JOIN categories c ON c.id=sc.category_id
         WHERE c.branch_id=:branch_id
           AND sc.subcategory_code REGEXP '^SUB[0-9]+$'
         ORDER BY CAST(SUBSTRING(sc.subcategory_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^SUB([0-9]+)$/i', $last, $m)) {
        $next = (int) $m[1] + 1;
    }

    return 'SUB' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function subcategory_category(int $branchId, int $categoryId): array
{
    $stmt = db()->prepare(
        'SELECT id,category_code,category_name
         FROM categories
         WHERE id=:id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $categoryId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Select an active Category from your branch.', 422, [
            'category_id' => 'Select a valid active Category.'
        ]);
    }

    return $row;
}

function subcategory_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT sc.id,sc.category_id,sc.subcategory_code,sc.subcategory_name,sc.status,
                sc.created_by,sc.created_at,sc.updated_at,
                c.category_code,c.category_name,c.branch_id
         FROM subcategories sc
         INNER JOIN categories c ON c.id=sc.category_id
         WHERE sc.id=:id AND c.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Subcategory was not found in your branch.', 404);
    }

    foreach (['id', 'category_id', 'branch_id', 'status'] as $key) {
        $row[$key] = (int) $row[$key];
    }

    return $row;
}

function subcategory_unique(int $branchId, int $categoryId, string $name, int $exclude = 0): void
{
    $sql =
        'SELECT sc.id
         FROM subcategories sc
         INNER JOIN categories c ON c.id=sc.category_id
         WHERE c.branch_id=:branch_id
           AND sc.category_id=:category_id
           AND LOWER(sc.subcategory_name)=LOWER(:name)';

    $params = [
        ':branch_id' => $branchId,
        ':category_id' => $categoryId,
        ':name' => $name,
    ];

    if ($exclude > 0) {
        $sql .= ' AND sc.id<>:id';
        $params[':id'] = $exclude;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Subcategory name already exists under this Category.', 409, [
            'subcategory_name' => 'Use a unique Subcategory name for this Category.'
        ]);
    }
}

function subcategory_status($value): int
{
    $status = (int) $value;

    if (!in_array($status, [1, 2], true)) {
        json_error('Invalid Subcategory status.', 422, [
            'status' => 'Status must be Active or Inactive.'
        ]);
    }

    return $status;
}

function subcategory_bind(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('subcategory-list.php', ACTION_VIEW);
    $user = $access['user'];
    $ctx = subcategory_tenant_context($user);
    $branchId = $ctx['branch_id'];

    if (isset($_GET['options'])) {
        $stmt = db()->prepare(
            'SELECT id,category_code,category_name
             FROM categories
             WHERE branch_id=:branch_id AND status=1
             ORDER BY category_name,category_code'
        );
        $stmt->execute([':branch_id' => $branchId]);

        json_success('Subcategory form options loaded.', [
            'next_subcategory_code' => subcategory_generate_code($branchId),
            'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('Subcategory loaded.', [
            'subcategory' => subcategory_record($branchId, positive_id($_GET['id'])),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['datatable'])) {
        $draw = max(0, (int) ($_GET['draw'] ?? 0));
        $start = max(0, (int) ($_GET['start'] ?? 0));
        $length = max(1, min(100000, (int) ($_GET['length'] ?? 10)));
        $search = trim((string) ($_GET['search']['value'] ?? ''));

        $baseFrom =
            ' FROM subcategories sc
              INNER JOIN categories c ON c.id=sc.category_id ';

        $where = ['c.branch_id=:branch_id'];
        $baseWhere = $where;

        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] =
                '(sc.subcategory_code LIKE :search_code
                  OR sc.subcategory_name LIKE :search_name
                  OR c.category_code LIKE :search_category_code
                  OR c.category_name LIKE :search_category_name)';

            $params[':search_code'] = $like;
            $params[':search_name'] = $like;
            $params[':search_category_code'] = $like;
            $params[':search_category_name'] = $like;
        }

        $categoryRaw = trim((string) ($_GET['category_id'] ?? ''));
        if ($categoryRaw !== '') {
            $categoryId = positive_id($categoryRaw, 'category_id');
            $where[] = 'sc.category_id=:category_id';
            $params[':category_id'] = $categoryId;
        }

        $statusRaw = trim((string) ($_GET['status'] ?? ''));
        if ($statusRaw !== '') {
            $status = subcategory_status($statusRaw);
            $where[] = 'sc.status=:status';
            $params[':status'] = $status;
        }

        $totalStmt = db()->prepare(
            'SELECT COUNT(*)' . $baseFrom . ' WHERE ' . implode(' AND ', $baseWhere)
        );
        $totalStmt->execute([':branch_id' => $branchId]);
        $recordsTotal = (int) $totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*)' . $baseFrom . ' WHERE ' . implode(' AND ', $where)
        );
        subcategory_bind($filteredStmt, $params);
        $filteredStmt->execute();
        $recordsFiltered = (int) $filteredStmt->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT COUNT(*) AS matching_count,
                    COALESCE(SUM(CASE WHEN sc.status=1 THEN 1 ELSE 0 END),0) AS active_count,
                    COALESCE(SUM(CASE WHEN sc.status=2 THEN 1 ELSE 0 END),0) AS inactive_count' .
            $baseFrom .
            ' WHERE ' . implode(' AND ', $where)
        );
        subcategory_bind($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $allSummaryStmt = db()->prepare(
            'SELECT COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN sc.status=1 THEN 1 ELSE 0 END),0) AS active_count,
                    COALESCE(SUM(CASE WHEN sc.status=2 THEN 1 ELSE 0 END),0) AS inactive_count' .
            $baseFrom .
            ' WHERE c.branch_id=:branch_id'
        );
        $allSummaryStmt->execute([':branch_id' => $branchId]);
        $allSummary = $allSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            0 => 'sc.subcategory_code',
            1 => 'c.category_name',
            2 => 'sc.subcategory_name',
            3 => 'sc.status',
            4 => 'sc.id',
        ];

        $orderIndex = (int) ($_GET['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $orderColumn = $columns[$orderIndex] ?? 'sc.subcategory_code';

        $sql =
            'SELECT sc.id,sc.category_id,sc.subcategory_code,sc.subcategory_name,sc.status,
                    c.category_code,c.category_name' .
            $baseFrom .
            ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',sc.id ASC
              LIMIT :start,:length';

        $stmt = db()->prepare($sql);
        subcategory_bind($stmt, $params);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (int) $row['id'];
            $row['category_id'] = (int) $row['category_id'];
            $row['status'] = (int) $row['status'];
            $rows[] = $row;
        }

        json_success('Subcategories loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_count' => (int) ($allSummary['total_count'] ?? 0),
                'active_count' => (int) ($allSummary['active_count'] ?? 0),
                'inactive_count' => (int) ($allSummary['inactive_count'] ?? 0),
                'matching_count' => (int) ($summary['matching_count'] ?? 0),
            ],
            'allowed_actions' => $access['actions'],
        ]);
    }

    $sql =
        'SELECT sc.id,sc.category_id,sc.subcategory_code,sc.subcategory_name,sc.status,c.category_name
         FROM subcategories sc
         INNER JOIN categories c ON c.id=sc.category_id
         WHERE c.branch_id=:branch_id';

    if (isset($_GET['active']) && (int) $_GET['active'] === 1) {
        $sql .= ' AND sc.status=1 AND c.status=1';
    }

    $sql .= ' ORDER BY c.category_name,sc.subcategory_name';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);

    json_success('Subcategories loaded.', [
        'subcategories' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('subcategory-list.php', ACTION_CREATE);
    $user = $access['user'];
    $ctx = subcategory_tenant_context($user);
    $branchId = $ctx['branch_id'];
    $data = request_data();

    $categoryId = positive_id($data['category_id'] ?? 0, 'category_id');
    subcategory_category($branchId, $categoryId);

    $name = subcategory_text(
        $data['subcategory_name'] ?? '',
        'subcategory_name',
        'Subcategory name',
        120
    );

    subcategory_unique($branchId, $categoryId, $name);
    $code = subcategory_generate_code($branchId);

    $stmt = db()->prepare(
        'INSERT INTO subcategories
         (category_id,subcategory_code,subcategory_name,status,created_by,created_at,updated_at)
         VALUES (:category_id,:code,:name,1,:created_by,NOW(),NOW())'
    );
    $stmt->execute([
        ':category_id' => $categoryId,
        ':code' => $code,
        ':name' => $name,
        ':created_by' => (int) $user['id'],
    ]);

    $id = (int) db()->lastInsertId();

    audit_log((int) $user['id'], ACTION_CREATE, [
        'company_id' => $ctx['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'record_id' => $id,
    ]);

    json_success('Subcategory created successfully.', [
        'subcategory' => subcategory_record($branchId, $id)
    ], 201);
}

if ($method === 'PUT') {
    $access = require_permission('subcategory-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $ctx = subcategory_tenant_context($user);
    $branchId = $ctx['branch_id'];
    $data = request_data();

    require_fields($data, ['id', 'category_id']);

    $id = positive_id($data['id']);
    $old = subcategory_record($branchId, $id);
    $categoryId = positive_id($data['category_id'], 'category_id');

    subcategory_category($branchId, $categoryId);

    $name = subcategory_text(
        $data['subcategory_name'] ?? '',
        'subcategory_name',
        'Subcategory name',
        120
    );

    subcategory_unique($branchId, $categoryId, $name, $id);

    db()->prepare(
        'UPDATE subcategories
         SET category_id=:category_id,subcategory_name=:name,updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':category_id' => $categoryId,
        ':name' => $name,
        ':id' => $id,
    ]);

    audit_log((int) $user['id'], ACTION_UPDATE, [
        'company_id' => $ctx['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
    ]);

    json_success('Subcategory updated successfully.', [
        'subcategory' => subcategory_record($branchId, $id)
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();

    require_fields($data, ['id', 'status']);

    $status = subcategory_status($data['status']);
    $access = require_permission(
        'subcategory-list.php',
        $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE
    );

    $user = $access['user'];
    $ctx = subcategory_tenant_context($user);
    $branchId = $ctx['branch_id'];
    $id = positive_id($data['id']);
    $old = subcategory_record($branchId, $id);

    db()->prepare(
        'UPDATE subcategories SET status=:status,updated_at=NOW() WHERE id=:id'
    )->execute([
        ':status' => $status,
        ':id' => $id,
    ]);

    audit_log(
        (int) $user['id'],
        $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE,
        [
            'company_id' => $ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]
    );

    json_success(
        $status === 1 ? 'Subcategory activated.' : 'Subcategory deactivated.'
    );
}

json_error('Method not allowed.', 405);
