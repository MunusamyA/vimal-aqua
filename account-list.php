<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Account List';
$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
];
?>
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
<script src="assets/js/validation.js"></script>

<div class="page-head">
    <div>
        <h1>Account List</h1>
        <p>Manage Cash, Bank, UPI, Card and Other accounts.</p>
    </div>
    <button class="btn btn-primary" id="addButton" type="button">
        <i data-lucide="plus"></i>Add Account
    </button>
</div>

<div class="card table-card">
    <table id="accountTable" class="display data-table" style="width:100%">
        <thead>
        <tr>
            <th>Code</th>
            <th>Account</th>
            <th>Type</th>
            <th>Opening Balance</th>
            <th>Description</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
    </table>
</div>

<?php require __DIR__ . '/model/account-form.php'; ?>

<script>
(function ($, window, document) {
    "use strict";
    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var listActions = [];
    var actionCodes = { create: 2, update: 3, activate: 0, deactivate: 0 };
    var has = AppDataTable.has;
    var table = null;

    function esc(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function reload() {
        if (table) table.ajax.reload(null, false);
    }

    function openCreate() {
        if (!window.AppAccountForm) return;
        AppAccountForm.openCreate({
            apiUrl: "api/account.php",
            onSaved: reload
        });
    }

    function openEdit(id) {
        if (!window.AppAccountForm) return;
        AppAccountForm.openEdit(id, {
            apiUrl: "api/account.php",
            onSaved: reload
        });
    }

    async function changeStatus(id, status) {
        var message = status === 1 ? "Activate this account?" : "Deactivate this account?";
        if (!window.confirm(message)) return;

        try {
            var result = await App.api("api/account.php", {
                method: "PATCH",
                body: { id: id, status: status }
            });
            if (typeof window.showToast === "function") {
                showToast(result.message || "Account status updated.", { type: "success", duration: 2 });
            }
            reload();
        } catch (error) {
            App.showError(error, "Unable to update Account status.");
        }
    }

    document.getElementById("addButton").addEventListener("click", openCreate);

    table = AppDataTable.init("#accountTable", {
        serverSide: true,
        searching: true,
        searchDelay: 350,
        appSearchPlaceholder: "Search accounts...",
        appLoaderText: "Loading accounts...",
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [],
        scrollX: true,
        autoWidth: false,
        buttons: [
            { extend:"copyHtml5", text:"Copy", title:"Account List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5]} },
            { extend:"csvHtml5", text:"CSV", title:"Account List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5]} },
            { extend:"excelHtml5", text:"Excel", title:"Account List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5]} },
            { extend:"pdfHtml5", text:"PDF", title:"Account List", orientation:"landscape", pageSize:"A4", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5]} },
            { extend:"print", text:"Print", title:"Account List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5]} }
        ],
        ajax: function (data, callback) {
            var params = new URLSearchParams();
            params.set("datatable", "1");
            params.set("draw", data.draw);
            params.set("start", data.start);
            params.set("length", data.length);
            params.set("search[value]", data.search.value || "");
            if (data.order && data.order[0]) {
                params.set("order[0][column]", data.order[0].column);
                params.set("order[0][dir]", data.order[0].dir);
            }

            App.api("api/account.php?" + params.toString()).then(function (result) {
                listActions = (result.data.allowed_actions || []).map(Number);
                actionCodes = result.data.action_codes || actionCodes;

                document.getElementById("addButton").style.display = has(listActions, Number(actionCodes.create || 2)) ? "inline-flex" : "none";
                AppDataTable.applyExportPermissions(table, listActions);
                callback(result.data.datatable);
            }).catch(function (error) {
                App.showError(error, "Unable to load accounts.");
                callback({ draw:data.draw, recordsTotal:0, recordsFiltered:0, data:[] });
            });
        },
        columns: [
            { data:"account_code", defaultContent:"-" },
            { data:"account_name", defaultContent:"-" },
            { data:"account_type_label", defaultContent:"-" },
            {
                data:"opening_balance",
                className:"dt-right",
                render:function(value,type){
                    var amount = Number(value || 0);
                    if (type !== "display") return amount;
                    return "₹" + amount.toLocaleString("en-IN", {minimumFractionDigits:2, maximumFractionDigits:2});
                }
            },
            { data:"description", defaultContent:"-", render:function(value,type){ return type === "display" ? esc(value || "-") : (value || ""); } },
            {
                data:"status",
                render:function(value,type){
                    if(type !== "display") return Number(value);
                    return Number(value) === 1
                        ? '<span class="dt-status active">Active</span>'
                        : '<span class="dt-status inactive">Inactive</span>';
                }
            },
            {
                data:null,
                orderable:false,
                searchable:false,
                className:"table-action-icons",
                render:function(data,type,row){
                    if (type !== "display") return "";
                    var html = "";
                    if (has(listActions, Number(actionCodes.update || 3))) {
                        html += '<button type="button" class="action-button js-edit-account" data-id="' + Number(row.id) + '" title="Edit account" aria-label="Edit account"><i data-lucide="pencil"></i></button>';
                    }

                    if (Number(row.status) === 1 && has(listActions, Number(actionCodes.deactivate || 0))) {
                        html += '<button type="button" class="action-button js-account-status" data-id="' + Number(row.id) + '" data-status="2" title="Deactivate account" aria-label="Deactivate account"><i data-lucide="circle-off"></i></button>';
                    }
                    if (Number(row.status) === 2 && has(listActions, Number(actionCodes.activate || 0))) {
                        html += '<button type="button" class="action-button js-account-status" data-id="' + Number(row.id) + '" data-status="1" title="Activate account" aria-label="Activate account"><i data-lucide="circle-check"></i></button>';
                    }

                    return html || '<span class="muted">View only</span>';
                }
            }
        ],
        drawCallback: function () {
            if (window.lucide) window.lucide.createIcons();
        },
        language: {
            emptyTable:"No accounts found.",
            zeroRecords:"No matching accounts found.",
            processing:"Loading accounts..."
        }
    });

    $(document).on("click", ".js-edit-account", function () {
        openEdit(Number(this.getAttribute("data-id") || 0));
    });

    $(document).on("click", ".js-account-status", function () {
        changeStatus(
            Number(this.getAttribute("data-id") || 0),
            Number(this.getAttribute("data-status") || 2)
        );
    });

    if (window.lucide) window.lucide.createIcons();
})(window.jQuery, window, document);
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
