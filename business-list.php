<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Business List'; ?>
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

<div class="page-head"><h1>Business List</h1><a class="btn btn-primary" id="addButton" href="business-form.php">Add Business</a></div>
<div class="card table-card"><table><thead><tr><th>Business</th><th>Code</th><th>Contact</th><th>Branches</th><th>Status</th><th>Actions</th></tr></thead><tbody id="rows"><tr><td colspan="6" class="empty">Loading...</td></tr></tbody></table></div>
<script>
(function(){"use strict";
var allowed=[],current={};function has(id){return allowed.indexOf(Number(id))!==-1;}function icons(){if(window.lucide)window.lucide.createIcons();}
async function load(){var body=document.getElementById("rows");try{var result=await App.api("api/businesses.php");allowed=(result.data.allowed_actions||[]).map(Number);current=result.data.current_user||{};document.getElementById("addButton").style.display=has(2)&&Number(current.role_type)===2?"inline-flex":"none";body.innerHTML="";(result.data.businesses||[]).forEach(function(item){var row=document.createElement("tr");[item.company_name,item.company_code,[item.email,item.mobile].filter(Boolean).join(" · ")||"-",item.branch_count].forEach(function(value){var td=document.createElement("td");td.textContent=value;row.appendChild(td);});var status=document.createElement("td");status.innerHTML='<span class="badge '+(Number(item.status)===1?'on':'off')+'">'+(Number(item.status)===1?'Active':'Inactive')+'</span>';row.appendChild(status);var actions=document.createElement("td");actions.className="table-action-icons";if(has(3))actions.appendChild(App.createIconAction({href:"business-form.php?id="+item.id,icon:"pencil",label:"Edit business"}));if(has(3)&&Number(current.role_type)===2)actions.appendChild(App.createIconAction({icon:Number(item.status)===1?"circle-off":"circle-check",label:Number(item.status)===1?"Deactivate business":"Activate business",tone:Number(item.status)===1?"danger":"success",onClick:function(){changeStatus(item);}}));row.appendChild(actions);body.appendChild(row);});if(!(result.data.businesses||[]).length)body.innerHTML='<tr><td colspan="6" class="empty">No businesses found.</td></tr>';icons();}catch(error){body.innerHTML='<tr><td colspan="6" class="empty">Unable to load businesses.</td></tr>';App.showError(error);}}
async function changeStatus(item){var next=Number(item.status)===1?0:1;if(!confirm((next?"Activate ":"Deactivate ")+'"'+item.company_name+'"?'))return;try{var result=await App.api("api/businesses.php",{method:"PATCH",body:{id:Number(item.id),status:next}});showToast(result.message,{type:"success",duration:3});load();}catch(error){App.showError(error);}}load();
})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
