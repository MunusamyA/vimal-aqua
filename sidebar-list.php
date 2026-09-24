<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Sidebar Menus';

$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css'
];

$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js'
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
        <h1>Sidebar Menu List</h1>
        <p>Manage sidebar hierarchy, paths and available actions.</p>
    </div>

    <a class="btn btn-primary" id="addButton" href="sidebar-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Menu
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-icon blue"><i data-lucide="panel-left"></i></div>
        <div>
            <div class="kpi-label">Total Menus</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div>
        <div>
            <div class="kpi-label">Active Menus</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon orange"><i data-lucide="circle-off"></i></div>
        <div>
            <div class="kpi-label">Inactive Menus</div>
            <div class="kpi-value" id="kpiInactive">0</div>
        </div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon teal"><i data-lucide="layout-list"></i></div>
        <div>
            <div class="kpi-label">Main Menus</div>
            <div class="kpi-value" id="kpiMain">0</div>
        </div>
    </div>
</div>

<div class="card table-card">

    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">

            <div class="field col-4">
                <label for="menuSearch">Search</label>
                <input class="input" id="menuSearch" type="text" autocomplete="off"
                       placeholder="Menu, parent, path, actions...">
            </div>

            <div class="field col-4">
                <label for="parentFilter">Parent</label>
                <select class="select" id="parentFilter">
                    <option value="">All Parents</option>
                    <option value="main">Main Menu</option>
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
        <table id="menuTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Order</th>
                <th>Menu</th>
                <th>Parent</th>
                <th>Path</th>
                <th>Available actions</th>
                <th>Status</th>
                <th>Manage</th>
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

    var table = null;
    var allowed = [];
    var parentsLoaded = false;
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

    function setSummary(summary){
        summary = summary || {};

        document.getElementById('kpiTotal').textContent =
            Number(summary.total_menus || 0).toLocaleString('en-IN');

        document.getElementById('kpiActive').textContent =
            Number(summary.active_menus || 0).toLocaleString('en-IN');

        document.getElementById('kpiInactive').textContent =
            Number(summary.inactive_menus || 0).toLocaleString('en-IN');

        document.getElementById('kpiMain').textContent =
            Number(summary.main_menus || 0).toLocaleString('en-IN');
    }

    function fillParents(rows){
        if(parentsLoaded){
            return;
        }

        parentsLoaded = true;

        var select = document.getElementById('parentFilter');

        (rows || []).forEach(function(row){
            var option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = row.menu_name;
            select.appendChild(option);
        });
    }

    function params(data){
        var p = new URLSearchParams();

        p.set('datatable','1');
        p.set('draw',data.draw);
        p.set('start',data.start);
        p.set('length',data.length);
        p.set('search[value]',data.search.value || '');

        var parent = document.getElementById('parentFilter').value;
        var status = document.getElementById('statusFilter').value;

        if(parent !== ''){
            p.set('parent_filter',parent);
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

    table = AppDataTable.init('#menuTable',{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:'Loading menus...',

        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],

        order:[[0,'asc']],
        scrollX:true,
        autoWidth:false,

        buttons:[],

        ajax:function(data,callback){
            App.api('api/menus.php?' + params(data).toString())
                .then(function(result){
                    allowed = (result.data.allowed_actions || []).map(Number);

                    document.getElementById('addButton').style.display =
                        has(allowed,2)
                            ? 'inline-flex'
                            : 'none';

                    setSummary(result.data.summary);
                    fillParents(result.data.parents || []);

                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});

                    App.showError(
                        error,
                        'Unable to load menus.'
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
                data:'sort_order',
                className:'dt-body-right'
            },
            {
                data:'menu_name',
                defaultContent:'-',
                render:function(value,type){
                    return type === 'display'
                        ? esc(value || '-')
                        : value;
                }
            },
            {
                data:'parent_name',
                defaultContent:'Main menu',
                render:function(value,type){
                    var text = value || 'Main menu';

                    return type === 'display'
                        ? esc(text)
                        : text;
                }
            },
            {
                data:'menu_path',
                defaultContent:'-',
                render:function(value,type){
                    return type === 'display'
                        ? esc(value || '-')
                        : value;
                }
            },
            {
                data:'available_action_ids',
                defaultContent:'-',
                orderable:false,
                render:function(value,type){
                    return type === 'display'
                        ? esc(value || '-')
                        : value;
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
                    if(type !== 'display'){
                        return '';
                    }

                    if(!has(allowed,3)){
                        return '<span class="muted">View only</span>';
                    }

                    return App.iconActionHtml({
                        href:'sidebar-form.php?id=' + Number(row.id),
                        icon:'pencil',
                        label:'Edit menu'
                    });
                }
            }
        ],

        language:{
            emptyTable:'No menus found.',
            zeroRecords:'No matching menus found.',
            processing:'Loading menus...'
        },

        drawCallback:function(){
            if(window.lucide){
                window.lucide.createIcons();
            }
        }
    });

    (function removeDefaultSearchRow(){
        var tableElement = document.getElementById('menuTable');
        var card = tableElement
            ? tableElement.closest('.table-card')
            : null;

        var row = card
            ? card.querySelector('.app-table-search-row')
            : null;

        if(row){
            row.remove();
        }
    })();

    document.getElementById('menuSearch').addEventListener('input',function(){
        var input = this;

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(input.value.trim()).draw();
        },350);
    });

    ['parentFilter','statusFilter'].forEach(function(id){
        document.getElementById(id).addEventListener('change',function(){
            table.ajax.reload(null,true);
        });
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
