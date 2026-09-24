-- Optional upgrade for an existing Starter Kit v2 database.
-- Run after replacing the PHP files with v3.
-- Fresh installations should use starterkit_fresh.sql instead.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS user_sessions;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE email_settings
    MODIFY smtp_username VARCHAR(190) NULL,
    MODIFY smtp_password TEXT NULL COMMENT 'AES-256-GCM encrypted value; never returned by API',
    ADD COLUMN smtp_auth TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=No SMTP auth, 1=Use username/password' AFTER smtp_encryption,
    ADD COLUMN reply_to_email VARCHAR(190) NULL AFTER from_name,
    ADD COLUMN reply_to_name VARCHAR(150) NULL AFTER reply_to_email,
    ADD COLUMN timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 20 AFTER reply_to_name;

-- Security configuration is stateless. Existing legacy keys continue to work as fallback.
-- The Security Settings page will save these current keys when updated:
--   absolute_login_expiry_minutes
--   idle_expiry_minutes

-- Mail Configuration menu under Administration.
INSERT INTO menus
(parent_id, menu_name, menu_path, icon, available_action_ids, sort_order, status, created_at, updated_at)
SELECT p.id, 'Mail Configuration', 'mail-settings.php', 'mail-cog', '1,48', 19, 1, NOW(), NOW()
FROM menus p
WHERE p.menu_path = 'administration'
  AND NOT EXISTS (SELECT 1 FROM menus m WHERE m.menu_path = 'mail-settings.php')
LIMIT 1;

-- Built-in plan permissions. Tenant-created roles can be granted access from Role Permissions.
INSERT IGNORE INTO role_permissions (role_id, menu_id, action_ids, status, created_at, updated_at)
SELECT r.id, m.id,
       CASE WHEN r.role_name = 'Basic' THEN '1,48' ELSE m.available_action_ids END,
       1, NOW(), NOW()
FROM roles r
JOIN menus m ON m.menu_path = 'mail-settings.php'
WHERE r.company_id IS NULL AND r.role_type = 1 AND r.role_name IN ('Basic','Medium','Premium');

-- Platform Owner receives the complete action set for the new menu.
INSERT IGNORE INTO role_permissions (role_id, menu_id, action_ids, status, created_at, updated_at)
SELECT r.id, m.id, m.available_action_ids, 1, NOW(), NOW()
FROM roles r
JOIN menus m ON m.menu_path = 'mail-settings.php'
WHERE r.company_id IS NULL AND r.role_type = 2 AND r.role_name = 'Platform Owner';
