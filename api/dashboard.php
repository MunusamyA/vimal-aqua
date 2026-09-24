<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('Method not allowed.', 405);
}

$access = require_permission('dashboard.php', ACTION_VIEW);
$user = $access['user'];
$pdo = db();
$isPlatform = (int) $user['role_type'] === 2;

function dashboard_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

if ($isPlatform) {
    $summary = [
        'businesses' => dashboard_count($pdo, 'SELECT COUNT(*) FROM companies WHERE status = 1'),
        'branches' => dashboard_count($pdo, 'SELECT COUNT(*) FROM branches WHERE status = 1'),
        'users' => dashboard_count($pdo, 'SELECT COUNT(*) FROM users WHERE status = 1'),
        'roles' => dashboard_count($pdo, 'SELECT COUNT(*) FROM roles WHERE status = 1'),
        'employees' => dashboard_count($pdo, 'SELECT COUNT(*) FROM employees WHERE status = 1'),
    ];
    $activitySql =
        'SELECT a.id, a.action_id, a.record_id, a.created_at,
                actor.name AS actor_name, m.menu_name, m.icon
         FROM audit_logs a
         INNER JOIN users actor ON actor.id = a.user_id
         LEFT JOIN menus m ON m.id = a.menu_id
         ORDER BY a.id DESC LIMIT 8';
    $activityStmt = $pdo->query($activitySql);
} else {
    $companyId = (int) $user['company_id'];
    $branchId = (int) $user['branch_id'];
    $summary = [
        'businesses' => $companyId > 0 ? 1 : 0,
        'branches' => dashboard_count($pdo, 'SELECT COUNT(*) FROM branches WHERE company_id = :company_id AND status = 1', [':company_id' => $companyId]),
        'users' => dashboard_count($pdo, 'SELECT COUNT(*) FROM users WHERE branch_id = :branch_id AND status = 1', [':branch_id' => $branchId]),
        'roles' => dashboard_count($pdo, 'SELECT COUNT(*) FROM roles WHERE (company_id = :company_id OR id = :plan_role_id) AND status = 1', [
            ':company_id' => $companyId,
            ':plan_role_id' => (int) ($user['branch_plan_role_id'] ?? 0),
        ]),
        'employees' => dashboard_count($pdo, 'SELECT COUNT(*) FROM employees WHERE branch_id = :branch_id AND status = 1', [':branch_id' => $branchId]),
    ];
    $activityStmt = $pdo->prepare(
        'SELECT a.id, a.action_id, a.record_id, a.created_at,
                actor.name AS actor_name, m.menu_name, m.icon
         FROM audit_logs a
         INNER JOIN users actor ON actor.id = a.user_id
         LEFT JOIN menus m ON m.id = a.menu_id
         WHERE a.branch_id = :branch_id
         ORDER BY a.id DESC LIMIT 8'
    );
    $activityStmt->execute([':branch_id' => $branchId]);
}

$labels = action_names(false);
$activity = [];
foreach ($activityStmt->fetchAll() as $row) {
    $actionId = (int) $row['action_id'];
    $activity[] = [
        'id' => (int) $row['id'],
        'message' => trim((string) ($row['actor_name'] ?? 'User')) . ' · ' .
            ($labels[$actionId] ?? 'Updated') . ' ' . trim((string) ($row['menu_name'] ?? 'System')),
        'detail' => $row['record_id'] === null ? '' : 'Record #' . (int) $row['record_id'],
        'icon' => $row['icon'] ?: 'activity',
        'created_at' => $row['created_at'],
    ];
}

json_success('Dashboard loaded.', [
    'summary' => $summary,
    'context' => [
        'name' => (string) $user['name'],
        'role_name' => (string) $user['role_name'],
        'company_name' => $user['company_name'] ?? null,
        'branch_name' => $user['branch_name'] ?? null,
        'role_type' => (int) $user['role_type'],
    ],
    'recent_activity' => $activity,
]);
