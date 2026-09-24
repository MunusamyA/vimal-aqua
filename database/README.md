# Starter Kit Database

`starterkit_fresh.sql` is the clean install schema for the reusable framework.

It contains 11 core tables:

- `companies`
- `roles`
- `branches`
- `users`
- `permission_actions`
- `app_settings`
- `menus`
- `role_permissions`
- `employees` (example CRUD module)
- `email_settings`
- `audit_logs`

There is **no `user_sessions` table**. Authentication is stateless: the signed Bearer token carries a rolling Idle Expiry and fixed Absolute Login Expiry. The configurable minute values are stored in `app_settings`.

`email_settings` stores outgoing SMTP configuration. SMTP passwords are encrypted with `APP_ENCRYPTION_KEY` before they are written to MySQL and are never returned in API responses.

After a fresh import, open `platform-register.php`. The first Platform Owner registration initializes the Action Master, plan/platform roles, core menus (including Mail Configuration), and owner permissions.
