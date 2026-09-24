<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Role List';

$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css',
];

$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js',
];
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

    <?php foreach ($headStyles as $styleUrl): ?>
        <link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach ($headScripts as $scriptUrl): ?>
        <script src="<?php echo web_h($scriptUrl); ?>"></script>
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

<div class="page-head">
    <div>
        <h1>Role List</h1>
        <p>Manage Plans, Platform Roles and Tenant Roles.</p>
    </div>

    <a class="btn btn-primary" id="addRole" href="role-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Role
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue">
            <i data-lucide="users-round"></i>
        </div>
        <div>
            <div class="kpi-label">Total Roles</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green">
            <i data-lucide="circle-check-big"></i>
        </div>
        <div>
            <div class="kpi-label">Active Roles</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange">
            <i data-lucide="circle-off"></i>
        </div>
        <div>
            <div class="kpi-label">Inactive Roles</div>
            <div class="kpi-value" id="kpiInactive">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal">
            <i data-lucide="badge-check"></i>
        </div>
        <div>
            <div class="kpi-label">Plans</div>
            <div class="kpi-value" id="kpiPlans">0</div>
        </div>
    </div>
</div>

<div class="card table-card">

    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">

            <div class="field col-4">
                <label for="roleSearch">Search</label>
                <input
                    class="input"
                    id="roleSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Role or owner..."
                >
            </div>

            <div class="field col-4">
                <label for="categoryFilter">Category</label>
                <select class="select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="1">Plan</option>
                    <option value="2">Platform Role</option>
                    <option value="3">Tenant Role</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="statusFilter">Status</label>
                <select class="select" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

        </div>
    </div>

    <div class="app-table-wrap">
        <table id="roleTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Role</th>
                <th>Category</th>
                <th>Owner</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
    </div>

</div>

<script>
(function($,window,document){
    'use strict';

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()){
        return;
    }

    var allowed = [];
    var current = {};
    var table = null;
    var searchTimer = null;
    var has = AppDataTable.has;

    function esc(value){
        return String(value == null ? '' : value)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
    }

    function typeName(type){
        return {
            1:'Plan',
            2:'Platform Role',
            3:'Tenant Role'
        }[Number(type)] || 'Unknown';
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById('kpiTotal').textContent =
            Number(summary.total_roles || 0).toLocaleString('en-IN');

        document.getElementById('kpiActive').textContent =
            Number(summary.active_roles || 0).toLocaleString('en-IN');

        document.getElementById('kpiInactive').textContent =
            Number(summary.inactive_roles || 0).toLocaleString('en-IN');

        document.getElementById('kpiPlans').textContent =
            Number(summary.plan_roles || 0).toLocaleString('en-IN');
    }

    function params(data){
        var p = new URLSearchParams();

        p.set('datatable','1');
        p.set('draw',data.draw);
        p.set('start',data.start);
        p.set('length',data.length);
        p.set('search[value]',data.search.value || '');

        var category = document.getElementById('categoryFilter').value;
        var status = document.getElementById('statusFilter').value;

        if(category !== ''){
            p.set('role_type',category);
        }

        if(status !== ''){
            p.set('status',status);
        }

        if(data.order && data.order[0]){
            p.set('order[0][column]',data.order[0].column);
            p.set('order[0][dir]',data.order[0].dir);
        }

        return p;
    }

    function canManageRole(role){
        var platformManage =
            Number(current.role_type) === 2 &&
            [1,2].indexOf(Number(role.role_type)) !== -1;

        var tenantManage =
            Number(current.role_type) !== 2 &&
            Number(role.role_type) === 3 &&
            Number(role.company_id) === Number(current.company_id);

        return has(allowed,3) && (platformManage || tenantManage);
    }

    function actionHtml(role){
        var canManage = canManageRole(role);

        if(!canManage){
            return '<span class="muted">' +
                (Number(role.role_type) === 1 ? 'Read only' : 'View only') +
                '</span>';
        }

        var html = '';

        html +=
            '<a class="table-icon-action" href="' + esc(role.edit_url) + '"' +
            ' title="Edit role" aria-label="Edit role">' +
            '<i data-lucide="pencil"></i></a>';

        html +=
            '<a class="table-icon-action primary" href="' + esc(role.permission_url) + '"' +
            ' title="Permissions" aria-label="Permissions">' +
            '<i data-lucide="key-round"></i></a>';

        if(!role.is_current_role){
            html +=
                '<button type="button"' +
                ' class="table-icon-action ' + (Number(role.status) === 1 ? 'danger' : '') + ' js-role-status"' +
                ' data-ref="' + esc(role.ref) + '"' +
                ' data-name="' + esc(role.role_name) + '"' +
                ' data-status="' + Number(role.status) + '"' +
                ' title="' + (Number(role.status) === 1 ? 'Deactivate role' : 'Activate role') + '"' +
                ' aria-label="' + (Number(role.status) === 1 ? 'Deactivate role' : 'Activate role') + '">' +
                '<i data-lucide="' + (Number(role.status) === 1 ? 'circle-off' : 'circle-check') + '"></i>' +
                '</button>';
        }

        return html;
    }

    table = AppDataTable.init('#roleTable',{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:'Loading roles...',

        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],

        order:[[0,'asc']],
        scrollX:true,
        autoWidth:false,

        buttons:[
            {
                extend:'copyHtml5',
                text:'Copy',
                title:'Role List',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3]}
            },
            {
                extend:'csvHtml5',
                text:'CSV',
                title:'Role List',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3]}
            },
            {
                extend:'excelHtml5',
                text:'Excel',
                title:'Role List',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3]}
            },
            {
                extend:'pdfHtml5',
                text:'PDF',
                title:'Role List',
                orientation:'landscape',
                pageSize:'A4',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3]}
            },
            {
                extend:'print',
                text:'Print',
                title:'Role List',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3]}
            }
        ],

        ajax:function(data,callback){
            App.api('api/roles.php?' + params(data).toString())
                .then(function(result){
                    allowed = (result.data.allowed_actions || []).map(Number);
                    current = result.data.current_user || {};

                    document.getElementById('addRole').style.display =
                        has(allowed,2) ? 'inline-flex' : 'none';

                    setSummary(result.data.summary);

                    AppDataTable.applyExportPermissions(
                        table,
                        allowed
                    );

                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    document.getElementById('addRole').style.display = 'none';

                    App.showError(
                        error,
                        'Unable to load roles.'
                    );

                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },

        columns:[
            {
                data:'role_name',
                defaultContent:'-',
                render:function(value,type){
                    return type === 'display'
                        ? esc(value || '-')
                        : value;
                }
            },
            {
                data:'role_type',
                render:function(value,type){
                    var label = typeName(value);
                    return type === 'display'
                        ? esc(label)
                        : label;
                }
            },
            {
                data:null,
                render:function(data,type,row){
                    var owner = row.company_name || 'Platform';
                    return type === 'display'
                        ? esc(owner)
                        : owner;
                }
            },
            {
                data:'status',
                render:function(value,type){
                    if(type !== 'display'){
                        return Number(value);
                    }

                    return Number(value) === 1
                        ? '<span class="dt-status active">Active</span>'
                        : '<span class="dt-status inactive">Inactive</span>';
                }
            },
            {
                data:null,
                orderable:false,
                searchable:false,
                className:'table-action-icons',
                render:function(data,type,row){
                    return type === 'display'
                        ? actionHtml(row)
                        : '';
                }
            }
        ],

        language:{
            emptyTable:'No roles found.',
            zeroRecords:'No matching roles found.',
            processing:'Loading roles...'
        },

        drawCallback:function(){
            if(window.lucide){
                window.lucide.createIcons();
            }
        }
    });

    /*
     * Remove the automatic/default AppDataTable search row.
     * Only the custom Role Search field above is used.
     */
    (function removeDefaultSearch(){
        var element = document.getElementById('roleTable');
        var card = element ? element.closest('.table-card') : null;
        var row = card ? card.querySelector('.app-table-search-row') : null;

        if(row){
            row.remove();
        }
    })();

    document.getElementById('roleSearch').addEventListener('input',function(){
        var input = this;

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            if(table){
                table.search(input.value.trim()).draw();
            }
        },350);
    });

    ['categoryFilter','statusFilter'].forEach(function(id){
        document.getElementById(id).addEventListener('change',function(){
            if(table){
                table.ajax.reload(null,true);
            }
        });
    });

    $(document).on('click','.js-role-status',async function(){
        var button = this;
        var ref = button.getAttribute('data-ref') || '';
        var name = button.getAttribute('data-name') || 'Role';
        var currentStatus = Number(button.getAttribute('data-status') || 0);
        var nextStatus = currentStatus === 1 ? 0 : 1;

        if(!ref){
            return;
        }

        if(!window.confirm(
            (nextStatus ? 'Activate ' : 'Deactivate ') +
            '"' + name + '"?'
        )){
            return;
        }

        button.disabled = true;

        try{
            var result = await App.api('api/roles.php',{
                method:'PATCH',
                body:{
                    ref:ref,
                    status:nextStatus
                }
            });

            if(typeof window.showToast === 'function'){
                showToast(
                    result.message || 'Role status updated.',
                    {
                        type:'success',
                        duration:2
                    }
                );
            }

            table.ajax.reload(null,false);
        }catch(error){
            App.showError(
                error,
                'Unable to update Role status.'
            );

            button.disabled = false;
        }
    });

    if(window.lucide){
        window.lucide.createIcons();
    }

})(window.jQuery,window,document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>

</main>
</div>

<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>

</body>
</html>
