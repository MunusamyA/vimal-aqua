<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Role List'; ?>
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

<div class="page-head"><h1>Role List</h1><a class="btn btn-primary" id="addRole" href="role-form.php">Add Role</a></div>
<div class="card table-card"><table><thead><tr><th>Role</th><th>Category</th><th>Owner</th><th>Status</th><th>Actions</th></tr></thead><tbody id="rows"><tr><td colspan="5" class="empty">Loading...</td></tr></tbody></table></div>
<script>
(function(){"use strict";
var allowed=[],current={};
function has(id){return allowed.indexOf(Number(id))!==-1;}
function typeName(type){return {1:"Plan",2:"Platform Role",3:"Tenant Role"}[Number(type)]||"Unknown";}
function refreshIcons(){if(window.lucide)window.lucide.createIcons();}
async function load(){
    var body=document.getElementById("rows");
    try{
        var result=await App.api("api/roles.php");
        allowed=(result.data.allowed_actions||[]).map(Number); current=result.data.current_user||{};
        document.getElementById("addRole").style.display=has(2)?"inline-flex":"none";
        body.innerHTML="";
        (result.data.roles||[]).forEach(function(role){
            var row=document.createElement("tr");
            [role.role_name,typeName(role.role_type),role.company_name||"Platform"].forEach(function(value){var td=document.createElement("td");td.textContent=value;row.appendChild(td);});
            var status=document.createElement("td"); status.innerHTML='<span class="badge '+(Number(role.status)===1?'on':'off')+'">'+(Number(role.status)===1?'Active':'Inactive')+'</span>'; row.appendChild(status);
            var actions=document.createElement("td"); actions.className="table-action-icons";
            var platformManage=Number(current.role_type)===2&&[1,2].indexOf(Number(role.role_type))!==-1;
            var tenantManage=Number(current.role_type)!==2&&Number(role.role_type)===3&&Number(role.company_id)===Number(current.company_id);
            var canManage=has(3)&&(platformManage||tenantManage);
            if(canManage){
                actions.appendChild(App.createIconAction({href:role.edit_url,icon:"pencil",label:"Edit role"}));
                actions.appendChild(App.createIconAction({href:role.permission_url,icon:"key-round",label:"Permissions",tone:"primary"}));
            }
            if(canManage&&!role.is_current_role){
                actions.appendChild(App.createIconAction({icon:Number(role.status)===1?"circle-off":"circle-check",label:Number(role.status)===1?"Deactivate role":"Activate role",tone:Number(role.status)===1?"danger":"success",onClick:function(){changeStatus(role);}}));
            }
            if(!actions.children.length){var view=document.createElement("span");view.className="muted";view.textContent=Number(role.role_type)===1?"Read only":"View only";actions.appendChild(view);}
            row.appendChild(actions); body.appendChild(row);
        });
        if(!(result.data.roles||[]).length) body.innerHTML='<tr><td colspan="5" class="empty">No roles found.</td></tr>';
        refreshIcons();
    }catch(error){body.innerHTML='<tr><td colspan="5" class="empty">Unable to load roles.</td></tr>';App.showError(error,"Unable to load roles.");}
}
async function changeStatus(role){var next=Number(role.status)===1?0:1;if(!confirm((next?"Activate ":"Deactivate ")+'"'+role.role_name+'"?'))return;try{var result=await App.api("api/roles.php",{method:"PATCH",body:{ref:role.ref,status:next}});showToast(result.message,{type:"success",duration:3});load();}catch(error){App.showError(error);}}
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
