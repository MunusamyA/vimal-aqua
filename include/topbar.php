<?php
declare(strict_types=1);

require_once __DIR__ . '/web-config.php';

$commonPageTitle = isset($pageTitle) && $pageTitle !== ''
    ? (string) $pageTitle
    : app_name();
?>
<header class="topbar">
    <div class="topbar-left">
        <button
            class="icon-button hover-primary"
            id="sidebarCollapseToggle"
            type="button"
            aria-label="Toggle sidebar"
            aria-expanded="true"
            title="Toggle sidebar"
        >
            <i data-lucide="menu"></i>
        </button>

        <div class="topbar-page-title">
            <strong><?php echo htmlspecialchars($commonPageTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
            <small id="appCompanyBranch"><?php echo web_h(app_name()); ?></small>
        </div>
    </div>

    <div class="topbar-actions">
        <button
            class="icon-button appearance-toggle"
            id="appearanceToggle"
            type="button"
            aria-label="Dark mode"
            title="Dark mode"
        >
            <i data-lucide="moon"></i>
        </button>

        <div class="top-dropdown notification-wrap" id="notificationWrap">
            <button
                class="icon-button"
                id="notificationButton"
                type="button"
                aria-label="Notifications"
                aria-expanded="false"
                title="Notifications"
            >
                <i data-lucide="bell"></i>
                <span class="badge-dot hidden" id="notificationBadge">0</span>
            </button>

            <div class="top-dropdown-menu notification-menu" id="notificationMenu">
                <div class="dropdown-head">
                    <div>
                        <strong>Notifications</strong>
                        <small>Recent system activity</small>
                    </div>
                    <button
                        class="dropdown-icon-button"
                        id="notificationRefresh"
                        type="button"
                        title="Refresh notifications"
                    >
                        <i data-lucide="refresh-cw"></i>
                    </button>
                </div>

                <div class="notification-list" id="notificationList">
                    <div class="dropdown-empty">Loading...</div>
                </div>
            </div>
        </div>

        <div class="top-dropdown profile-wrap" id="profileWrap">
            <button
                class="profile-button"
                id="profileButton"
                type="button"
                aria-expanded="false"
            >
                <span class="avatar" aria-hidden="true">
                    <i data-lucide="user-round"></i>
                </span>

                <span class="topbar-user-copy">
                    <strong id="appUserName">User</strong>
                    <small id="appRoleName"></small>
                </span>

                <i data-lucide="chevron-down" class="profile-chevron"></i>
            </button>

            <div class="top-dropdown-menu profile-menu" id="profileMenu">
                <div class="profile-menu-header">
                    <strong id="profileMenuName">User</strong>
                    <small id="profileMenuEmail"></small>
                </div>

                <a href="my-profile.php">
                    <i data-lucide="circle-user-round"></i>
                    <span>My Profile</span>
                </a>

                <div class="dropdown-separator"></div>

                <button
                    class="profile-menu-action danger"
                    id="appLogoutButton"
                    type="button"
                >
                    <i data-lucide="log-out"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </div>
</header>

<?php require __DIR__ . '/modal.php'; ?>
<script src="assets/js/appearance.js"></script>