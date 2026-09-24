<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('Method not allowed.', 405);
}

$user = require_user();
json_success('Sidebar loaded.', [
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role_id' => (int) $user['role_id'],
        'role_name' => $user['role_name'],
        'role_type' => (int) $user['role_type'],
        'company_id' => $user['company_id'] === null ? null : (int) $user['company_id'],
        'company_name' => $user['company_name'],
        'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
        'branch_name' => $user['branch_name'],
    ],
    'menus' => sidebar_for_user($user),
]);
