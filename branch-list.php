<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Branch List'; ?>
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

<div class="page-head"><h1>Branch List</h1><a class="btn btn-primary" id="addButton" href="branch-form.php">Add Branch</a></div>
<div class="card table-card"><table><thead><tr><th>Business</th><th>Branch</th><th>Code</th><th>Plan</th><th>Branch Admin</th><th>Status</th><th>Actions</th></tr></thead><tbody id="rows"><tr><td colspan="7" class="empty">Loading...</td></tr></tbody></table></div>
<script>
(function(){"use strict";
var allowed=[],current={};function has(id){return allowed.indexOf(Number(id))!==-1;}function icons(){if(window.lucide)window.lucide.createIcons();}
async function load(){var body=document.getElementById("rows");try{var result=await App.api("api/branches.php");allowed=(result.data.allowed_actions||[]).map(Number);current=result.data.current_user||{};document.getElementById("addButton").style.display=has(2)&&Number(current.role_type)===2?"inline-flex":"none";body.innerHTML="";(result.data.branches||[]).forEach(function(item){var row=document.createElement("tr");[item.company_name,item.branch_name,item.branch_code,item.plan_name,item.admin_name||"-"].forEach(function(value){var td=document.createElement("td");td.textContent=value;row.appendChild(td);});var status=document.createElement("td");status.innerHTML='<span class="badge '+(Number(item.status)===1?'on':'off')+'">'+(Number(item.status)===1?'Active':'Inactive')+'</span>';row.appendChild(status);var actions=document.createElement("td");actions.className="table-action-icons";if(has(3))actions.appendChild(App.createIconAction({href:"branch-form.php?id="+item.id,icon:"pencil",label:"Edit branch"}));if(has(3)&&Number(current.role_type)===2)actions.appendChild(App.createIconAction({icon:Number(item.status)===1?"circle-off":"circle-check",label:Number(item.status)===1?"Deactivate branch":"Activate branch",tone:Number(item.status)===1?"danger":"success",onClick:function(){changeStatus(item);}}));row.appendChild(actions);body.appendChild(row);});if(!(result.data.branches||[]).length)body.innerHTML='<tr><td colspan="7" class="empty">No branches found.</td></tr>';icons();}catch(error){body.innerHTML='<tr><td colspan="7" class="empty">Unable to load branches.</td></tr>';App.showError(error);}}
async function changeStatus(item){var next=Number(item.status)===1?0:1;if(!confirm((next?"Activate ":"Deactivate ")+'"'+item.branch_name+'"?'))return;try{var result=await App.api("api/branches.php",{method:"PATCH",body:{id:Number(item.id),status:next}});showToast(result.message,{type:"success",duration:3});load();}catch(error){App.showError(error);}}load();
})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
