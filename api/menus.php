<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function validate_menu_parent($parentId, int $menuId = 0)
{
    if ($parentId === null || $parentId === '' || (int)$parentId === 0) {
        return null;
    }

    $parentId = positive_id($parentId, 'parent_id');

    if ($parentId === $menuId) {
        json_error('A menu cannot be its own parent.', 422);
    }

    $stmt = db()->prepare(
        'SELECT id,parent_id FROM menus WHERE id=:id LIMIT 1'
    );
    $stmt->execute([':id' => $parentId]);
    $parent = $stmt->fetch();

    if (!$parent) {
        json_error('Selected parent menu was not found.', 422);
    }

    $current = $parent;
    $guard = 0;

    while (
        $menuId > 0 &&
        $current &&
        $current['parent_id'] !== null &&
        $guard < 25
    ) {
        if ((int)$current['parent_id'] === $menuId) {
            json_error('Circular menu hierarchy is not allowed.', 422);
        }

        $stmt->execute([
            ':id' => (int)$current['parent_id']
        ]);

        $current = $stmt->fetch();
        $guard++;
    }

    return $parentId;
}

function validated_menu_actions($value): string
{
    $actions = normalize_csv_ids($value);
    $validIds = active_action_ids();

    if ($actions === [] || array_diff($actions, $validIds) !== []) {
        json_error('Select valid active menu actions.', 422);
    }

    if (!in_array(ACTION_VIEW, $actions, true)) {
        array_unshift($actions, ACTION_VIEW);
    }

    return csv_ids($actions);
}

function menu_bind(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('sidebar-list.php', ACTION_VIEW);
    require_platform_user();

    if (isset($_GET['id'])) {
        $id = positive_id($_GET['id']);

        $stmt = db()->prepare(
            'SELECT m.*,p.menu_name AS parent_name
             FROM menus m
             LEFT JOIN menus p ON p.id=m.parent_id
             WHERE m.id=:id
             LIMIT 1'
        );

        $stmt->execute([':id' => $id]);
        $menu = $stmt->fetch();

        if (!$menu) {
            json_error('Menu was not found.', 404);
        }

        json_success('Menu loaded.', [
            'menu' => $menu,
            'actions' => permission_actions_list(true),
            'action_names' => action_names(true),
        ]);
    }

    if (isset($_GET['datatable']) && (int)$_GET['datatable'] === 1) {
        $draw = max(0, (int)($_GET['draw'] ?? 0));
        $start = max(0, (int)($_GET['start'] ?? 0));

        $requestedLength = (int)($_GET['length'] ?? 10);
        $length = $requestedLength < 0
            ? 100000
            : max(1, min(100000, $requestedLength));

        $search = trim((string)($_GET['search']['value'] ?? ''));
        $parentRaw = trim((string)($_GET['parent_filter'] ?? ''));
        $statusRaw = trim((string)($_GET['status'] ?? ''));

        $baseFrom =
            ' FROM menus m
              LEFT JOIN menus p ON p.id=m.parent_id ';

        $where = [];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';

            $where[] =
                '(m.menu_name LIKE :search_name
                  OR m.menu_path LIKE :search_path
                  OR COALESCE(p.menu_name, \'Main menu\') LIKE :search_parent
                  OR COALESCE(m.available_action_ids, \'\') LIKE :search_actions)';

            $params[':search_name'] = $term;
            $params[':search_path'] = $term;
            $params[':search_parent'] = $term;
            $params[':search_actions'] = $term;
        }

        if ($parentRaw !== '') {
            if ($parentRaw === 'main') {
                $where[] = 'm.parent_id IS NULL';
            } else {
                $parentId = positive_id($parentRaw, 'parent_filter');
                $where[] = 'm.parent_id=:parent_filter';
                $params[':parent_filter'] = $parentId;
            }
        }

        if ($statusRaw !== '') {
            $status = (int)$statusRaw;

            if (!in_array($status, [0,1], true)) {
                json_error('Invalid Menu status.', 422);
            }

            $where[] = 'm.status=:status';
            $params[':status'] = $status;
        }

        $whereSql = $where
            ? ' WHERE ' . implode(' AND ', $where)
            : '';

        $recordsTotal = (int)db()->query(
            'SELECT COUNT(*) FROM menus'
        )->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*)' . $baseFrom . $whereSql
        );
        menu_bind($filteredStmt, $params);
        $filteredStmt->execute();
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT COUNT(*) AS total_menus,
                    COALESCE(SUM(CASE WHEN m.status=1 THEN 1 ELSE 0 END),0) AS active_menus,
                    COALESCE(SUM(CASE WHEN m.status=0 THEN 1 ELSE 0 END),0) AS inactive_menus,
                    COALESCE(SUM(CASE WHEN m.parent_id IS NULL THEN 1 ELSE 0 END),0) AS main_menus' .
            $baseFrom .
            $whereSql
        );
        menu_bind($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            0 => 'm.sort_order',
            1 => 'm.menu_name',
            2 => 'p.menu_name',
            3 => 'm.menu_path',
            4 => 'm.available_action_ids',
            5 => 'm.status',
            6 => 'm.id',
        ];

        $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir =
            strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderColumn = $columns[$orderIndex] ?? 'm.sort_order';

        $sql =
            'SELECT m.id,m.parent_id,m.menu_name,m.menu_path,m.icon,
                    m.available_action_ids,m.sort_order,m.status,
                    p.menu_name AS parent_name' .
            $baseFrom .
            $whereSql .
            ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',m.id ASC
              LIMIT :start,:length';

        $stmt = db()->prepare($sql);
        menu_bind($stmt, $params);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] =
                $row['parent_id'] === null
                    ? null
                    : (int)$row['parent_id'];
            $row['sort_order'] = (int)$row['sort_order'];
            $row['status'] = (int)$row['status'];
            $rows[] = $row;
        }

        $parents = db()->query(
            'SELECT id,menu_name
             FROM menus
             ORDER BY menu_name,id'
        )->fetchAll(PDO::FETCH_ASSOC);

        json_success('Menus loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_menus' => (int)($summary['total_menus'] ?? 0),
                'active_menus' => (int)($summary['active_menus'] ?? 0),
                'inactive_menus' => (int)($summary['inactive_menus'] ?? 0),
                'main_menus' => (int)($summary['main_menus'] ?? 0),
            ],
            'parents' => $parents,
            'allowed_actions' => $access['actions'],
        ]);
    }

    $stmt = db()->query(
        'SELECT m.*,p.menu_name AS parent_name
         FROM menus m
         LEFT JOIN menus p ON p.id=m.parent_id
         ORDER BY m.sort_order ASC,m.id ASC'
    );

    json_success('Menus loaded.', [
        'menus' => $stmt->fetchAll(),
        'actions' => permission_actions_list(true),
        'action_names' => action_names(true),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('sidebar-list.php', ACTION_CREATE);
    $user = require_platform_user();
    $data = request_data();

    require_fields($data, [
        'menu_name',
        'menu_path',
        'available_action_ids'
    ]);

    $parentId = validate_menu_parent(
        $data['parent_id'] ?? null
    );

    $actions = validated_menu_actions(
        $data['available_action_ids']
    );

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO menus
             (parent_id,menu_name,menu_path,icon,available_action_ids,sort_order,status,created_at,updated_at)
             VALUES (:parent_id,:menu_name,:menu_path,:icon,:actions,:sort_order,:status,NOW(),NOW())'
        );

        $stmt->execute([
            ':parent_id' => $parentId,
            ':menu_name' => trim((string)$data['menu_name']),
            ':menu_path' => trim((string)$data['menu_path']),
            ':icon' => isset($data['icon'])
                ? trim((string)$data['icon'])
                : null,
            ':actions' => $actions,
            ':sort_order' => isset($data['sort_order'])
                ? (int)$data['sort_order']
                : 0,
            ':status' => isset($data['status'])
                ? normalize_status($data['status'])
                : 1,
        ]);

        $menuId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO role_permissions
             (role_id,menu_id,action_ids,status,created_at,updated_at)
             VALUES (:role_id,:menu_id,:actions,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                action_ids=VALUES(action_ids),
                status=1,
                updated_at=NOW()'
        );

        $stmt->execute([
            ':role_id' => (int)$user['role_id'],
            ':menu_id' => $menuId,
            ':actions' => $actions,
        ]);

        $pdo->commit();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $menuId,
        ]);

        json_success(
            'Sidebar menu created successfully.',
            ['menu_id' => $menuId],
            201
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $exception instanceof PDOException &&
            $exception->getCode() === '23000'
        ) {
            json_error(
                'Menu path already exists.',
                409
            );
        }

        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('sidebar-list.php', ACTION_UPDATE);
    $user = require_platform_user();
    $data = request_data();

    require_fields($data, [
        'id',
        'menu_name',
        'menu_path',
        'available_action_ids'
    ]);

    $menuId = positive_id($data['id']);

    $stmt = db()->prepare(
        'SELECT * FROM menus WHERE id=:id LIMIT 1'
    );

    $stmt->execute([':id' => $menuId]);
    $old = $stmt->fetch();

    if (!$old) {
        json_error('Menu was not found.', 404);
    }

    $parentId = validate_menu_parent(
        $data['parent_id'] ?? null,
        $menuId
    );

    $actions = validated_menu_actions(
        $data['available_action_ids']
    );

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE menus
             SET parent_id=:parent_id,
                 menu_name=:menu_name,
                 menu_path=:menu_path,
                 icon=:icon,
                 available_action_ids=:actions,
                 sort_order=:sort_order,
                 status=:status,
                 updated_at=NOW()
             WHERE id=:id'
        );

        $stmt->execute([
            ':parent_id' => $parentId,
            ':menu_name' => trim((string)$data['menu_name']),
            ':menu_path' => trim((string)$data['menu_path']),
            ':icon' => isset($data['icon'])
                ? trim((string)$data['icon'])
                : null,
            ':actions' => $actions,
            ':sort_order' => isset($data['sort_order'])
                ? (int)$data['sort_order']
                : 0,
            ':status' => isset($data['status'])
                ? normalize_status($data['status'])
                : 1,
            ':id' => $menuId,
        ]);

        $allowed = normalize_csv_ids($actions);

        $permissionStmt = $pdo->prepare(
            'SELECT id,action_ids,status
             FROM role_permissions
             WHERE menu_id=:menu_id'
        );

        $permissionStmt->execute([
            ':menu_id' => $menuId
        ]);

        $updateStmt = $pdo->prepare(
            'UPDATE role_permissions
             SET action_ids=:actions,
                 status=:status,
                 updated_at=NOW()
             WHERE id=:id'
        );

        foreach ($permissionStmt->fetchAll() as $permission) {
            $clean = array_values(
                array_intersect(
                    normalize_csv_ids($permission['action_ids']),
                    $allowed
                )
            );

            $updateStmt->execute([
                ':actions' => implode(',', $clean),
                ':status' =>
                    $clean === []
                        ? 0
                        : (int)$permission['status'],
                ':id' => (int)$permission['id'],
            ]);
        }

        if (
            (int)$user['role_type'] === 2 &&
            (string)$user['role_name'] === 'Platform Owner'
        ) {
            $ownerPermission = $pdo->prepare(
                'INSERT INTO role_permissions
                 (role_id,menu_id,action_ids,status,created_at,updated_at)
                 VALUES (:role_id,:menu_id,:actions,1,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE
                    action_ids=VALUES(action_ids),
                    status=1,
                    updated_at=NOW()'
            );

            $ownerPermission->execute([
                ':role_id' => (int)$user['role_id'],
                ':menu_id' => $menuId,
                ':actions' => $actions,
            ]);
        }

        $pdo->commit();

        audit_log((int)$user['id'], ACTION_UPDATE, [
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $menuId,
            'old_data' => $old,
        ]);

        json_success(
            'Sidebar menu updated successfully.'
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

json_error('Method not allowed.', 405);
