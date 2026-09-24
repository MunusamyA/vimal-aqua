-- PHP ERP STARTER KIT v3 - FRESH CORE DATABASE
-- Core framework only: no companies, branches, users, employees or business transactions are inserted.
-- Includes reusable encrypted SMTP configuration.
-- After import, open platform-register.php to create the first Platform Owner and system definitions.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS email_settings;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS permission_actions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS companies;

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(150) NOT NULL,
    company_code VARCHAR(50) NOT NULL,
    email VARCHAR(190) NULL,
    mobile VARCHAR(20) NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_code (company_code),
    KEY idx_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NULL,
    role_name VARCHAR(100) NOT NULL,
    role_type TINYINT UNSIGNED NOT NULL COMMENT '1=Plan/Branch Admin, 2=Platform, 3=Tenant-created',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_roles_company (company_id),
    KEY idx_roles_type_status (role_type, status),
    CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES companies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE branches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_name VARCHAR(150) NOT NULL,
    branch_code VARCHAR(50) NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL COMMENT 'Current Basic/Medium/Premium plan role',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_branch_company_code (company_id, branch_code),
    KEY idx_branches_role (role_id),
    KEY idx_branches_status (status),
    CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_branches_plan_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL COMMENT 'Not unique',
    mobile VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    last_login_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_email (email),
    KEY idx_users_company (company_id),
    KEY idx_users_branch (branch_id),
    KEY idx_users_role (role_id),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE permission_actions (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_name VARCHAR(120) NOT NULL,
    purpose VARCHAR(255) NULL,
    group_id TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Numeric display group only; permission logic uses id',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permission_actions_name (action_name),
    KEY idx_permission_actions_group_sort (group_id, sort_order, id),
    KEY idx_permission_actions_status (status),
    KEY idx_permission_actions_created_by (created_by),
    KEY idx_permission_actions_updated_by (updated_by),
    CONSTRAINT fk_permission_actions_created_by FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_permission_actions_updated_by FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NULL COMMENT 'NULL means Platform default',
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_settings_branch_key (branch_id, setting_key),
    KEY idx_app_settings_branch (branch_id),
    KEY idx_app_settings_updated_by (updated_by),
    CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id),
    CONSTRAINT fk_app_settings_branch FOREIGN KEY (branch_id) REFERENCES branches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menus (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    menu_name VARCHAR(120) NOT NULL,
    menu_path VARCHAR(120) NOT NULL,
    icon VARCHAR(100) NULL,
    available_action_ids VARCHAR(1000) NOT NULL COMMENT 'Comma-separated numeric action IDs, e.g. 1,2,3,7,20',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_menus_path (menu_path),
    KEY idx_menus_parent (parent_id),
    KEY idx_menus_status_sort (status, sort_order),
    CONSTRAINT fk_menus_parent FOREIGN KEY (parent_id) REFERENCES menus (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    action_ids VARCHAR(1000) NOT NULL COMMENT 'Comma-separated numeric action IDs',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_menu (role_id, menu_id),
    KEY idx_role_permissions_status (status),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id),
    CONSTRAINT fk_role_permissions_menu FOREIGN KEY (menu_id) REFERENCES menus (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Login account linked to this employee',
    branch_id BIGINT UNSIGNED NOT NULL,
    employee_code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    mobile VARCHAR(20) NULL,
    pan VARCHAR(10) NULL,
    aadhaar VARCHAR(12) NULL,
    media_files LONGTEXT NULL COMMENT 'JSON array of uploaded image/video metadata',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_user (user_id),
    UNIQUE KEY uq_employee_branch_code (branch_id, employee_code),
    KEY idx_employees_status (status),
    KEY idx_employees_created_by (created_by),
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_employees_branch FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT fk_employees_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NULL COMMENT 'NULL means platform/default SMTP settings',
    smtp_host VARCHAR(190) NOT NULL,
    smtp_port INT UNSIGNED NOT NULL,
    smtp_encryption VARCHAR(20) NOT NULL DEFAULT 'tls' COMMENT 'tls, ssl or none',
    smtp_auth TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=No SMTP auth, 1=Use username/password',
    smtp_username VARCHAR(190) NULL,
    smtp_password TEXT NULL COMMENT 'AES-256-GCM encrypted value; never returned by API',
    from_email VARCHAR(190) NOT NULL,
    from_name VARCHAR(150) NOT NULL,
    reply_to_email VARCHAR(190) NULL,
    reply_to_name VARCHAR(150) NULL,
    timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_settings_branch_status (branch_id, status),
    CONSTRAINT fk_email_settings_branch FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT fk_email_settings_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NULL,
    action_id SMALLINT UNSIGNED NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    old_data LONGTEXT NULL,
    new_data LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_company (company_id),
    KEY idx_audit_branch (branch_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_menu (menu_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
