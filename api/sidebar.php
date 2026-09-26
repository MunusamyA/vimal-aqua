<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('Method not allowed.', 405);
}

$user = require_user();

/*
|--------------------------------------------------------------------------
| Sidebar company logo
|--------------------------------------------------------------------------
| Uses Settings -> General Settings -> Company Logo
| Branch users get the branch logo.
| If no branch-specific logo exists, app_setting() can inherit the
| platform/default value according to your existing settings logic.
|--------------------------------------------------------------------------
*/

$branchId = $user['branch_id'] === null
    ? null
    : (int) $user['branch_id'];

$companyLogoPath = trim(
    (string) app_setting(
        'company_logo',
        '',
        $branchId
    )
);

$companyLogoUrl = '';

if ($companyLogoPath !== '') {

    if (
        preg_match('~^(?:https?:)?//~i', $companyLogoPath)
        || strpos($companyLogoPath, 'data:') === 0
        || strpos($companyLogoPath, 'blob:') === 0
    ) {
        $companyLogoUrl = $companyLogoPath;

    } else {

        $uploadBaseUrl = rtrim(
            (string) env_value('UPLOAD_BASE_URL', ''),
            '/'
        );

        if (
            $uploadBaseUrl !== ''
            && strpos($companyLogoPath, 'uploads/') === 0
        ) {
            $companyLogoUrl =
                $uploadBaseUrl . '/'
                . ltrim(
                    substr(
                        $companyLogoPath,
                        strlen('uploads/')
                    ),
                    '/'
                );

        } else {

            $companyLogoUrl = ltrim(
                str_replace('\\', '/', $companyLogoPath),
                '/'
            );
        }
    }
}

json_success('Sidebar loaded.', [
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],

        'role_id' => (int) $user['role_id'],
        'role_name' => $user['role_name'],
        'role_type' => (int) $user['role_type'],

        'company_id' => $user['company_id'] === null
            ? null
            : (int) $user['company_id'],

        'company_name' => $user['company_name'],

        'branch_id' => $user['branch_id'] === null
            ? null
            : (int) $user['branch_id'],

        'branch_name' => $user['branch_name'],

        /*
        |--------------------------------------------------------------------------
        | Branding
        |--------------------------------------------------------------------------
        */
        'company_logo' => $companyLogoPath,
        'company_logo_url' => $companyLogoUrl,
    ],

    'menus' => sidebar_for_user($user),
]);