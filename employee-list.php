<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Employee List';
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
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1>Employee List</h1>
        <p>Server-side employee search, filters, paging, sorting and export.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="employee-form.php">
        <i data-lucide="user-plus"></i>Add Employee
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="users"></i></div>
        <div>
            <div class="kpi-label">Total Employees</div>
            <div class="kpi-value" id="kpiTotalEmployees">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="user-check"></i></div>
        <div>
            <div class="kpi-label">Active Employees</div>
            <div class="kpi-value" id="kpiActiveEmployees">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="user-x"></i></div>
        <div>
            <div class="kpi-label">Inactive Employees</div>
            <div class="kpi-value" id="kpiInactiveEmployees">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="key-round"></i></div>
        <div>
            <div class="kpi-label">Login Accounts</div>
            <div class="kpi-value" id="kpiLoginAccounts">0</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">
            <div class="field col-3">
                <label for="employeeSearch">Search</label>
                <input
                    class="input"
                    id="employeeSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Code, employee, username, contact..."
                >
            </div>

            <div class="field col-3">
                <label for="branchFilter">Branch</label>
                <select class="select" id="branchFilter" data-placeholder="All Branches">
                    <option value="">All Branches</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="roleFilter">Role</label>
                <select class="select" id="roleFilter" data-placeholder="All Roles">
                    <option value="">All Roles</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter" data-placeholder="All Status">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="app-table-wrap">
        <table id="employeeTable" class="display data-table" style="width:100%">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Employee</th>
                    <th>Branch</th>
                    <th>Role</th>
                    <th>Username</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function ($, window, document) {
    'use strict';

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) {
        return;
    }

    var table = null;
    var searchTimer = null;
    var listActions = [];
    var formActions = [];
    var branches = [];
    var roles = [];
    var currentUser = {};
    var branchSelect = null;
    var roleSelect = null;
    var statusSelect = null;
    var has = AppDataTable.has;

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiTotalEmployees').textContent =
            Number(summary.total_employees || 0).toLocaleString('en-IN');

        document.getElementById('kpiActiveEmployees').textContent =
            Number(summary.active_employees || 0).toLocaleString('en-IN');

        document.getElementById('kpiInactiveEmployees').textContent =
            Number(summary.inactive_employees || 0).toLocaleString('en-IN');

        document.getElementById('kpiLoginAccounts').textContent =
            Number(summary.login_accounts || 0).toLocaleString('en-IN');
    }

    function selectedValue(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '') : '';
    }

    function roleItemsForBranch(branchId) {
        var selectedBranch = null;

        if (branchId !== '') {
            selectedBranch = branches.find(function (branch) {
                return String(branch.id) === String(branchId);
            }) || null;
        }

        return roles
            .filter(function (role) {
                if (!selectedBranch) return true;
                return Number(role.company_id) === Number(selectedBranch.company_id);
            })
            .map(function (role) {
                var prefix = Number(currentUser.role_type || 0) === 2 && role.company_name
                    ? role.company_name + ' — '
                    : '';

                return {
                    value: String(role.id),
                    text: prefix + role.role_name
                };
            });
    }

    function rebuildRoleFilter() {
        if (!roleSelect) return;
        roleSelect.setOptions(roleItemsForBranch(selectedValue('branchFilter')), '');
    }

    function reloadTable() {
        if (table) {
            table.ajax.reload(null, true);
        }
    }

    function requestParams(data) {
        var params = new URLSearchParams();

        params.set('datatable', '1');
        params.set('draw', data.draw);
        params.set('start', data.start);
        params.set('length', data.length);
        params.set('search[value]', data.search.value || '');

        var branchId = selectedValue('branchFilter');
        var roleId = selectedValue('roleFilter');
        var status = selectedValue('statusFilter');

        if (branchId !== '') params.set('branch_id', branchId);
        if (roleId !== '') params.set('role_id', roleId);
        if (status !== '') params.set('status', status);

        if (data.order && data.order[0]) {
            params.set('order[0][column]', data.order[0].column);
            params.set('order[0][dir]', data.order[0].dir);
        }

        return params;
    }

    function initTable() {
        table = AppDataTable.init('#employeeTable', {
            serverSide: true,
            searching: true,
            searchDelay: 350,
            appSearch: false,
            pageLength: 10,
            lengthMenu: [[10,25,50,100],[10,25,50,100]],
            order: [],
            scrollX: true,
            autoWidth: false,

            buttons: [
                {
                    extend: 'copyHtml5',
                    text: 'Copy',
                    title: 'Employee List',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: { columns: [0,1,2,3,4,5,6] }
                },
                {
                    extend: 'csvHtml5',
                    text: 'CSV',
                    title: 'Employee List',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: { columns: [0,1,2,3,4,5,6] }
                },
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: 'Employee List',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: { columns: [0,1,2,3,4,5,6] }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: 'Employee List',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: { columns: [0,1,2,3,4,5,6] }
                },
                {
                    extend: 'print',
                    text: 'Print',
                    title: 'Employee List',
                    action: AppDataTable.serverSideExportAction,
                    exportOptions: { columns: [0,1,2,3,4,5,6] }
                }
            ],

            ajax: function (data, callback) {
                App.api('api/employees.php?' + requestParams(data).toString())
                    .then(function (result) {
                        listActions = (result.data.list_actions || []).map(Number);
                        formActions = (result.data.form_actions || []).map(Number);

                        document.getElementById('addButton').style.display =
                            has(formActions, 2) ? 'inline-flex' : 'none';

                        setSummary(result.data.summary);

                        AppDataTable.applyExportPermissions(table, listActions);
                        callback(result.data.datatable);
                    })
                    .catch(function (error) {
                        setSummary({});
                        App.showError(error, 'Unable to load employees.');
                        callback({
                            draw: data.draw,
                            recordsTotal: 0,
                            recordsFiltered: 0,
                            data: []
                        });
                    });
            },

            columns: [
                { data: 'employee_code', defaultContent: '-' },
                { data: 'name', defaultContent: '-' },
                {
                    data: null,
                    render: function (data, type, row) {
                        return [row.company_name, row.branch_name]
                            .filter(Boolean)
                            .join(' — ') || '-';
                    }
                },
                { data: 'role_name', defaultContent: '-' },
                { data: 'username', defaultContent: '-' },
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row) {
                        return [row.email, row.mobile]
                            .filter(Boolean)
                            .join(' · ') || '-';
                    }
                },
                {
                    data: 'status',
                    render: function (value, type) {
                        if (type !== 'display') return Number(value);
                        return Number(value) === 1
                            ? '<span class="dt-status active">Active</span>'
                            : '<span class="dt-status inactive">Inactive</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'table-action-icons',
                    render: function (data, type, row) {
                        return has(formActions, 3)
                            ? App.iconActionHtml({
                                href: row.edit_url,
                                icon: 'pencil',
                                label: 'Edit employee'
                            })
                            : '<span class="muted">View only</span>';
                    }
                }
            ],

            language: {
                emptyTable: 'No employees found.',
                zeroRecords: 'No matching employees found.',
                processing: 'Loading employees...'
            },

            drawCallback: function () {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }
        });

        /* Remove only the automatically generated DataTable search row. */
        var card = document.getElementById('employeeTable').closest('.table-card');
        var duplicateSearch = card ? card.querySelector('.app-table-search-row') : null;
        if (duplicateSearch) {
            duplicateSearch.remove();
        }
    }

    async function boot() {
        try {
            var response = await App.api('api/employees.php?list_options=1');
            var data = response.data || {};

            branches = data.branches || [];
            roles = data.roles || [];
            currentUser = data.current_user || {};

            branchSelect = GlobalSelect.init(
                document.getElementById('branchFilter'),
                { placeholder: 'All Branches' }
            );

            var branchItems = branches.map(function (branch) {
                return {
                    value: String(branch.id),
                    text: [branch.company_name, branch.branch_name]
                        .filter(Boolean)
                        .join(' — ')
                };
            });

            var defaultBranch = Number(currentUser.role_type || 0) === 2
                ? ''
                : String(currentUser.branch_id || '');

            branchSelect.setOptions(branchItems, defaultBranch);
            document.getElementById('branchFilter').value = defaultBranch;

            roleSelect = GlobalSelect.init(
                document.getElementById('roleFilter'),
                { placeholder: 'All Roles' }
            );
            rebuildRoleFilter();

            statusSelect = GlobalSelect.init(
                document.getElementById('statusFilter'),
                { placeholder: 'All Status' }
            );

            initTable();
        } catch (error) {
            App.showError(error, 'Unable to load Employee List filters.');
        }
    }

    document.getElementById('employeeSearch').addEventListener('input', function () {
        var field = this;
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            if (table) {
                table.search(field.value.trim()).draw();
            }
        }, 350);
    });

    document.getElementById('branchFilter').addEventListener('change', function () {
        rebuildRoleFilter();
        reloadTable();
    });

    ['roleFilter', 'statusFilter'].forEach(function (id) {
        var element = document.getElementById(id);
        if (!element) return;

        element.addEventListener('change', function () {
            reloadTable();
        });
    });

    boot();
})(window.jQuery, window, document);
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
