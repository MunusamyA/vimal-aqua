<?php
declare(strict_types=1);
require_once __DIR__ . '/web-config.php';
?>
<aside class="sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <div class="brand-mark" aria-hidden="true">
            <span class="brand-stem"></span>
        </div>

        <div class="app-brand-text">
            <div class="brand-title"><?php echo web_h(app_name()); ?></div>
            <div class="app-brand-subtitle"><?php echo web_h(app_subtitle()); ?></div>
        </div>
    </div>

    <nav class="sidebar-nav" id="appSidebarMenu">
        <div class="sidebar-loading">Loading permitted menus...</div>
    </nav>

    <div class="sidebar-mini-footer">
        <?php echo web_h(app_short_name()); ?> · V   0.0.1
    </div>
</aside>

<div class="sidebar-flyout" id="sidebarFlyout" hidden></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
