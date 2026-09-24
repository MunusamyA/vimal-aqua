<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function validate_menu_parent($parentId, int $menuId = 0)
{
    if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
        return null;
    }

    $parentId = positive_id($parentId, 'parent_id');
    if ($parentId === $menuId) {
        json_error('A menu cannot be its own parent.', 422);
    }

    $stmt = db()->prepare('SELECT id, parent_id FROM menus WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $parentId]);
    $parent = $stmt->fetch();
    if (!$parent) {
        json_error('Selected parent menu was not found.', 422);
    }

    $current = $parent;
    $guard = 0;
    while ($menuId > 0 && $current && $current['parent_id'] !== null && $guard < 25) {
        if ((int) $current['parent_id'] === $menuId) {
            json_error('Circular menu hierarchy is not allowed.', 422);
        }
        $stmt->execute([':id' => (int) $current['parent_id']]);
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

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('sidebar-list.php', ACTION_VIEW);
    require_platform_user();

    if (isset($_GET['id'])) {
        $id = positive_id($_GET['id']);
        $stmt = db()->prepare(
            'SELECT m.*, p.menu_name AS parent_name
             FROM menus m LEFT JOIN menus p ON p.id = m.parent_id
             WHERE m.id = :id LIMIT 1'
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

    $stmt = db()->query(
        'SELECT m.*, p.menu_name AS parent_name
         FROM menus m LEFT JOIN menus p ON p.id = m.parent_id
         ORDER BY m.sort_order ASC, m.id ASC'
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
    require_fields($data, ['menu_name', 'menu_path', 'available_action_ids']);

    $parentId = validate_menu_parent(isset($data['parent_id']) ? $data['parent_id'] : null);
    $actions = validated_menu_actions($data['available_action_ids']);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO menus
             (parent_id, menu_name, menu_path, icon,
              available_action_ids, sort_order, status, created_at, updated_at)
             VALUES (:parent_id, :menu_name, :menu_path, :icon,
                     :actions, :sort_order, :status, NOW(), NOW())'
        );
        $stmt->execute([
            ':parent_id' => $parentId,
            ':menu_name' => trim((string) $data['menu_name']),
            ':menu_path' => trim((string) $data['menu_path']),
            ':icon' => isset($data['icon']) ? trim((string) $data['icon']) : null,
            ':actions' => $actions,
            ':sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            ':status' => isset($data['status']) ? normalize_status($data['status']) : 1,
        ]);
        $menuId = (int) $pdo->lastInsertId();

        /* The creator keeps access to the newly-created menu. */
        $stmt = $pdo->prepare(
            'INSERT INTO role_permissions
             (role_id, menu_id, action_ids, status, created_at, updated_at)
             VALUES (:role_id, :menu_id, :actions, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE action_ids = VALUES(action_ids), status = 1, updated_at = NOW()'
        );
        $stmt->execute([
            ':role_id' => (int) $user['role_id'],
            ':menu_id' => $menuId,
            ':actions' => $actions,
        ]);
        $pdo->commit();

        audit_log((int) $user['id'], ACTION_CREATE, [
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $menuId,
        ]);
        json_success('Sidebar menu created successfully.', ['menu_id' => $menuId], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Menu path already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('sidebar-list.php', ACTION_UPDATE);
    $user = require_platform_user();
    $data = request_data();
    require_fields($data, ['id', 'menu_name', 'menu_path', 'available_action_ids']);
    $menuId = positive_id($data['id']);

    $stmt = db()->prepare('SELECT * FROM menus WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $menuId]);
    $old = $stmt->fetch();
    if (!$old) {
        json_error('Menu was not found.', 404);
    }

    $parentId = validate_menu_parent(isset($data['parent_id']) ? $data['parent_id'] : null, $menuId);
    $actions = validated_menu_actions($data['available_action_ids']);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'UPDATE menus SET parent_id = :parent_id, menu_name = :menu_name,
             menu_path = :menu_path, icon = :icon,
             available_action_ids = :actions, sort_order = :sort_order,
             status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':parent_id' => $parentId,
            ':menu_name' => trim((string) $data['menu_name']),
            ':menu_path' => trim((string) $data['menu_path']),
            ':icon' => isset($data['icon']) ? trim((string) $data['icon']) : null,
            ':actions' => $actions,
            ':sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            ':status' => isset($data['status']) ? normalize_status($data['status']) : 1,
            ':id' => $menuId,
        ]);

        $allowed = normalize_csv_ids($actions);
        $permissionStmt = $pdo->prepare('SELECT id, action_ids, status FROM role_permissions WHERE menu_id = :menu_id');
        $permissionStmt->execute([':menu_id' => $menuId]);
        $updateStmt = $pdo->prepare(
            'UPDATE role_permissions SET action_ids = :actions, status = :status, updated_at = NOW() WHERE id = :id'
        );
        foreach ($permissionStmt->fetchAll() as $permission) {
            $clean = array_values(array_intersect(normalize_csv_ids($permission['action_ids']), $allowed));
            $updateStmt->execute([
                ':actions' => implode(',', $clean),
                ':status' => $clean === [] ? 0 : (int) $permission['status'],
                ':id' => (int) $permission['id'],
            ]);
        }

        // The Platform Owner must retain every currently available action for
        // system menus without relying on a later page request to repair it.
        if ((int) $user['role_type'] === 2 && (string) $user['role_name'] === 'Platform Owner') {
            $ownerPermission = $pdo->prepare(
                'INSERT INTO role_permissions (role_id, menu_id, action_ids, status, created_at, updated_at)
'
                . 'VALUES (:role_id, :menu_id, :actions, 1, NOW(), NOW())
'
                . 'ON DUPLICATE KEY UPDATE action_ids = VALUES(action_ids), status = 1, updated_at = NOW()'
            );
            $ownerPermission->execute([
                ':role_id' => (int) $user['role_id'],
                ':menu_id' => $menuId,
                ':actions' => $actions,
            ]);
        }
        $pdo->commit();

        audit_log((int) $user['id'], ACTION_UPDATE, [
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $menuId,
            'old_data' => $old,
        ]);
        json_success('Sidebar menu updated successfully.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

json_error('Method not allowed.', 405);
