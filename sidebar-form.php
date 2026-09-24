<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Sidebar Menu Form'; ?>
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

<div class="page-head"><h1 id="title">Add Sidebar Menu</h1><a class="btn gray" href="sidebar-list.php">Menu List</a></div>
<div class="card form-card"><form id="menuForm" novalidate>
    <div class="card-header"><div><h2>Menu Details</h2><p>Configure the sidebar menu and its available numeric actions.</p></div></div>
    <div class="card-body"><div class="form-grid">
    <div class="field"><label>Menu name</label><input name="menu_name" required></div>
    <div class="field"><label>Parent menu</label><select name="parent_id" id="parentMenu"><option value="">Main menu</option></select></div>
    <div class="field"><label>Menu path / page</label><input name="menu_path" required placeholder="example-list.php"></div>
    <div class="field"><label>Icon name</label><input name="icon" placeholder="users"></div>
    <div class="field"><label>Sort order</label><input name="sort_order" type="number" value="0" data-validation="integer"></div>
    <div class="field"><label>Status</label><select name="status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <div class="field full"><label>Available actions</label><div class="buttons" id="actionChecks"></div></div>
</div></div>
    <div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="saveButton" type="submit">Save Menu</button><a class="btn gray" href="sidebar-list.php">Cancel</a></div></div>
</form></div>
<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";
    var form = document.getElementById("menuForm");
    var id = Number(new URLSearchParams(location.search).get("id") || 0);
    var actionMaster = [];

    function buildActions(selected) {
        selected = (selected || []).map(Number);
        var box = document.getElementById("actionChecks");
        box.innerHTML = "";
        var currentGroup = null;
        actionMaster.forEach(function (action) {
            if (currentGroup !== action.group_name) {
                currentGroup = action.group_name;
                var heading = document.createElement("div");
                heading.className = "muted";
                heading.style.cssText = "width:100%;font-weight:700;margin:8px 0 2px";
                heading.textContent = currentGroup;
                box.appendChild(heading);
            }
            var label = document.createElement("label");
            label.style.cssText = "display:inline-flex;align-items:center;gap:6px;padding:9px 11px;border:1px solid var(--line);border-radius:8px;margin:0";
            label.title = action.purpose || "";
            var input = document.createElement("input");
            input.style.cssText = "width:auto;height:auto";
            input.type = "checkbox";
            input.name = "actions";
            input.value = String(action.id);
            input.checked = selected.indexOf(Number(action.id)) !== -1;
            if (Number(action.id) === 1) {
                input.checked = true;
                input.disabled = true;
            }
            label.appendChild(input);
            label.appendChild(document.createTextNode(action.id + " - " + action.action_name));
            box.appendChild(label);
        });
    }

    async function load() {
        try {
            var list = await App.api("api/menus.php");
            actionMaster = list.data.actions || [];
            (list.data.menus || []).forEach(function (menu) {
                if (Number(menu.id) === id) return;
                var option = document.createElement("option");
                option.value = menu.id;
                option.textContent = menu.menu_name;
                document.getElementById("parentMenu").appendChild(option);
            });
            if (!id) {
                buildActions([1]);
                return;
            }
            document.getElementById("title").textContent = "Edit Sidebar Menu";
            var result = await App.api("api/menus.php?id=" + id);
            actionMaster = result.data.actions || actionMaster;
            var menu = result.data.menu;
            ["menu_name","menu_path","icon","sort_order","status","parent_id"].forEach(function (name) {
                if (form.elements[name]) form.elements[name].value = menu[name] === null ? "" : menu[name];
            });
            buildActions(String(menu.available_action_ids || "").split(",").filter(Boolean).map(Number));
        } catch (error) {
            App.showError(error, "Unable to load sidebar menu form.");
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        Validation.clearForm(form);
        if (!Validation.validateForm(form)) return;
        var actions = [1];
        Array.prototype.forEach.call(form.querySelectorAll('input[name="actions"]:checked'), function (input) {
            actions.push(Number(input.value));
        });
        actions = Array.from(new Set(actions)).sort(function (a, b) { return a - b; });
        var data = {
            id: id || undefined,
            menu_name: form.menu_name.value.trim(),
            parent_id: form.parent_id.value || null,
            menu_path: form.menu_path.value.trim(),
            icon: form.icon.value.trim(),
            sort_order: Number(form.sort_order.value || 0),
            status: Number(form.status.value),
            available_action_ids: actions
        };
        var button = document.getElementById("saveButton");
        button.disabled = true;
        try {
            var result = await App.api("api/menus.php", { method: id ? "PUT" : "POST", body: data });
            showToast(result.message, { type: "success", duration: 2 }); if (App.clearSidebarCache) App.clearSidebarCache();
            setTimeout(function () { location.href = "sidebar-list.php"; }, 650);
        } catch (error) {
            App.showError(error, "Unable to save sidebar menu.");
            button.disabled = false;
        }
    });

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
