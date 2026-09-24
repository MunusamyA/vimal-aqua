<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Sidebar Menus'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach ((isset($headStyles) && is_array($headStyles) ? $headStyles : []) as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)$styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ((isset($headScripts) && is_array($headScripts) ? $headScripts : []) as $scriptUrl): ?>
    <script src="<?php echo htmlspecialchars((string)$scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
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
<script src="assets/js/datatable.js"></script>

<div class="page-head"><h1>Sidebar Menu List</h1><a class="btn btn-primary" id="addButton" href="sidebar-form.php">Add Menu</a></div>
<div class="card table-card"><table><thead><tr><th>Order</th><th>Menu</th><th>Parent</th><th>Path</th><th>Available actions</th><th>Status</th><th>Manage</th></tr></thead><tbody id="menuRows"><tr><td colspan="7" class="empty">Loading...</td></tr></tbody></table></div>
<script>
(function(){"use strict";
var allowed=[];function has(id){return allowed.indexOf(Number(id))!==-1;}function icons(){if(window.lucide)window.lucide.createIcons();}
async function load(){var body=document.getElementById("menuRows");try{var result=await App.api("api/menus.php");allowed=(result.data.allowed_actions||[]).map(Number);document.getElementById("addButton").style.display=has(2)?"inline-flex":"none";body.innerHTML="";(result.data.menus||[]).forEach(function(menu){var row=document.createElement("tr");[menu.sort_order,menu.menu_name,menu.parent_name||"Main menu",menu.menu_path,menu.available_action_ids].forEach(function(item){var cell=document.createElement("td");cell.textContent=item;row.appendChild(cell);});var status=document.createElement("td");status.innerHTML='<span class="badge '+(Number(menu.status)===1?'on':'off')+'">'+(Number(menu.status)===1?'Active':'Inactive')+'</span>';row.appendChild(status);var actions=document.createElement("td");actions.className="table-action-icons";if(has(3))actions.appendChild(App.createIconAction({href:"sidebar-form.php?id="+menu.id,icon:"pencil",label:"Edit menu"}));row.appendChild(actions);body.appendChild(row);});if(!(result.data.menus||[]).length)body.innerHTML='<tr><td colspan="7" class="empty">No menus found.</td></tr>';icons();}catch(error){body.innerHTML='<tr><td colspan="7" class="empty">Unable to load menus.</td></tr>';App.showError(error);}}
load();
})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
