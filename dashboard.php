<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
    <main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
        <section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>

<div class="page-head">
    <div><h1>Dashboard</h1><p>Reusable starter overview from the core platform tables.</p></div>
</div>

<div class="kpi-grid">
    <article class="card kpi-card"><span class="kpi-icon green"><i data-lucide="building-2"></i></span><div><div class="kpi-label">Businesses</div><div class="kpi-value" id="kpiBusinesses">0</div><div class="kpi-meta"><span>Active</span></div></div></article>
    <article class="card kpi-card"><span class="kpi-icon blue"><i data-lucide="map-pin"></i></span><div><div class="kpi-label">Branches</div><div class="kpi-value" id="kpiBranches">0</div><div class="kpi-meta"><span>Active</span></div></div></article>
    <article class="card kpi-card"><span class="kpi-icon teal"><i data-lucide="users"></i></span><div><div class="kpi-label">Users</div><div class="kpi-value" id="kpiUsers">0</div><div class="kpi-meta"><span>Active</span></div></div></article>
    <article class="card kpi-card"><span class="kpi-icon orange"><i data-lucide="shield-check"></i></span><div><div class="kpi-label">Roles</div><div class="kpi-value" id="kpiRoles">0</div><div class="kpi-meta"><span>Available</span></div></div></article>
</div>

<section class="dashboard-grid-main">
    <article class="card">
        <div class="card-header"><div><h2 class="card-title">Current Context</h2><p class="card-description">Scope is always derived from the authenticated user.</p></div></div>
        <div class="card-body">
            <div class="detail-grid">
                <div><span class="metric-label">User</span><strong id="contextUser">-</strong></div>
                <div><span class="metric-label">Role</span><strong id="contextRole">-</strong></div>
                <div><span class="metric-label">Business</span><strong id="contextBusiness">-</strong></div>
                <div><span class="metric-label">Branch</span><strong id="contextBranch">-</strong></div>
                <div><span class="metric-label">Employees</span><strong id="contextEmployees">0</strong></div>
            </div>
        </div>
    </article>

    <article class="card">
        <div class="card-header"><div><h2 class="card-title">Starter Kit</h2><p class="card-description">Core backend stays stable while the UI theme can be replaced.</p></div></div>
        <div class="card-body">
            <div class="activity-list">
                <div class="activity-item"><span class="activity-icon primary"><i data-lucide="key-round"></i></span><div class="activity-copy"><strong>Numeric permissions</strong><span>Menu actions are checked by numeric ID.</span></div></div>
                <div class="activity-item"><span class="activity-icon info"><i data-lucide="table-2"></i></span><div class="activity-copy"><strong>Reusable DataTable</strong><span>Search, export, loader and compact pagination.</span></div></div>
                <div class="activity-item"><span class="activity-icon accent"><i data-lucide="palette"></i></span><div class="activity-copy"><strong>Replaceable UI skin</strong><span>Sidebar, topbar, footer and CSS can change independently.</span></div></div>
            </div>
        </div>
    </article>
</section>

<article class="card">
    <div class="card-header"><div><h2 class="card-title">Recent Activity</h2><p class="card-description">Latest audit activity visible to the current login scope.</p></div></div>
    <div class="card-body"><div class="activity-list" id="recentActivity"><div class="empty">Loading...</div></div></div>
</article>

<script src="assets/js/dashboard.js"></script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
