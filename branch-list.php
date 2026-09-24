<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Branch List';

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
<?php foreach ($headStyles as $url): ?><link rel="stylesheet" href="<?php echo web_h($url); ?>"><?php endforeach; ?>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<?php foreach ($headScripts as $url): ?><script src="<?php echo web_h($url); ?>"></script><?php endforeach; ?>
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
        <h1>Branch List</h1>
        <p>Manage branches, plans and Branch Admins.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="branch-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Branch
    </a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="kpi-icon blue"><i data-lucide="git-branch"></i></div><div><div class="kpi-label">Total Branches</div><div class="kpi-value" id="kpiTotal">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon green"><i data-lucide="circle-check-big"></i></div><div><div class="kpi-label">Active Branches</div><div class="kpi-value" id="kpiActive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon orange"><i data-lucide="circle-off"></i></div><div><div class="kpi-label">Inactive Branches</div><div class="kpi-value" id="kpiInactive">0</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon teal"><i data-lucide="building-2"></i></div><div><div class="kpi-label">Businesses</div><div class="kpi-value" id="kpiBusinesses">0</div></div></div>
</div>

<div class="card table-card">
    <div class="card-header" style="display:block;">
        <div class="form-row" style="width:100%;">
            <div class="field col-3">
                <label for="branchSearch">Search</label>
                <input class="input" id="branchSearch" type="text" autocomplete="off" placeholder="Branch, code, admin...">
            </div>

            <div class="field col-3">
                <label for="businessFilter">Business</label>
                <select class="select" id="businessFilter">
                    <option value="">All Businesses</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="planFilter">Plan</label>
                <select class="select" id="planFilter">
                    <option value="">All Plans</option>
                </select>
            </div>

            <div class="field col-3">
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
        <table id="branchTable" class="display data-table" style="width:100%">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Branch</th>
                    <th>Code</th>
                    <th>Plan</th>
                    <th>Branch Admin</th>
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

if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;

var table=null;
var allowed=[];
var current={};
var searchTimer=null;
var has=AppDataTable.has;

function escapeHtml(value){
    return String(value===null||value===undefined?'':value)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

function setSummary(summary){
    summary=summary||{};
    document.getElementById('kpiTotal').textContent=Number(summary.total_branches||0).toLocaleString('en-IN');
    document.getElementById('kpiActive').textContent=Number(summary.active_branches||0).toLocaleString('en-IN');
    document.getElementById('kpiInactive').textContent=Number(summary.inactive_branches||0).toLocaleString('en-IN');
    document.getElementById('kpiBusinesses').textContent=Number(summary.business_count||0).toLocaleString('en-IN');
}

function fillOptions(select,rows,valueKey,textFunction){
    var currentValue=select.value;
    while(select.options.length>1)select.remove(1);

    rows.forEach(function(row){
        var option=document.createElement('option');
        option.value=String(row[valueKey]);
        option.textContent=textFunction(row);
        select.appendChild(option);
    });

    if(currentValue!=='')select.value=currentValue;
}

function params(data){
    var p=new URLSearchParams();
    p.set('datatable','1');
    p.set('draw',data.draw);
    p.set('start',data.start);
    p.set('length',data.length);
    p.set('search[value]',data.search.value||'');

    var company=document.getElementById('businessFilter').value;
    var plan=document.getElementById('planFilter').value;
    var status=document.getElementById('statusFilter').value;

    if(company!=='')p.set('company_id',company);
    if(plan!=='')p.set('plan_role_id',plan);
    if(status!=='')p.set('status',status);

    if(data.order&&data.order[0]){
        p.set('order[0][column]',data.order[0].column);
        p.set('order[0][dir]',data.order[0].dir);
    }

    return p;
}

function init(){
    table=AppDataTable.init('#branchTable',{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,'asc']],
        scrollX:true,
        autoWidth:false,
        buttons:[],
        ajax:function(data,callback){
            App.api('api/branches.php?'+params(data).toString())
                .then(function(result){
                    allowed=(result.data.allowed_actions||[]).map(Number);
                    current=result.data.current_user||{};
                    document.getElementById('addButton').style.display=
                        has(allowed,2)&&Number(current.role_type)===2?'inline-flex':'none';
                    setSummary(result.data.summary);
                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,'Unable to load branches.');
                    callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                });
        },
        columns:[
            {data:'company_name',defaultContent:'-',render:function(v,t){return t==='display'?escapeHtml(v||'-'):v;}},
            {data:'branch_name',defaultContent:'-',render:function(v,t){return t==='display'?escapeHtml(v||'-'):v;}},
            {data:'branch_code',defaultContent:'-',render:function(v,t){return t==='display'?escapeHtml(v||'-'):v;}},
            {data:'plan_name',defaultContent:'-',render:function(v,t){return t==='display'?escapeHtml(v||'-'):v;}},
            {data:'admin_name',defaultContent:'-',render:function(v,t){return t==='display'?escapeHtml(v||'-'):v;}},
            {data:'status',render:function(v,t){
                if(t!=='display')return Number(v);
                return Number(v)===1
                    ?'<span class="dt-status active">Active</span>'
                    :'<span class="dt-status inactive">Inactive</span>';
            }},
            {data:null,orderable:false,searchable:false,className:'table-action-icons',render:function(d,t,row){
                if(t!=='display')return '';
                var html='';

                if(has(allowed,3)){
                    html+=App.iconActionHtml({
                        href:'branch-form.php?id='+Number(row.id),
                        icon:'pencil',
                        label:'Edit branch'
                    });
                }

                if(has(allowed,3)&&Number(current.role_type)===2){
                    html+='<button type="button" class="table-icon-action '+(Number(row.status)===1?'danger':'')+
                        ' js-branch-status" data-id="'+Number(row.id)+'" data-name="'+escapeHtml(row.branch_name)+
                        '" data-status="'+Number(row.status)+'" title="'+(Number(row.status)===1?'Deactivate':'Activate')+
                        ' Branch"><i data-lucide="'+(Number(row.status)===1?'circle-off':'circle-check')+'"></i></button>';
                }

                return html||'<span class="muted">View only</span>';
            }}
        ],
        language:{
            emptyTable:'No branches found.',
            zeroRecords:'No matching branches found.',
            processing:'Loading branches...'
        },
        drawCallback:function(){
            if(window.lucide)window.lucide.createIcons();
        }
    });

    var card=document.getElementById('branchTable').closest('.table-card');
    var defaultSearch=card?card.querySelector('.app-table-search-row'):null;
    if(defaultSearch)defaultSearch.remove();
}

async function boot(){
    try{
        var result=await App.api('api/branches.php?options=1');
        var data=result.data||{};

        fillOptions(
            document.getElementById('businessFilter'),
            data.companies||[],
            'id',
            function(row){
                return (row.company_code?row.company_code+' - ':'')+row.company_name;
            }
        );

        fillOptions(
            document.getElementById('planFilter'),
            data.plans||[],
            'id',
            function(row){
                return row.role_name;
            }
        );

        init();
    }catch(error){
        App.showError(error,'Unable to load Branch filters.');
    }
}

document.getElementById('branchSearch').addEventListener('input',function(){
    var field=this;
    clearTimeout(searchTimer);
    searchTimer=setTimeout(function(){
        if(table)table.search(field.value.trim()).draw();
    },350);
});

['businessFilter','planFilter','statusFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change',function(){
        if(table)table.ajax.reload(null,true);
    });
});

document.addEventListener('click',function(event){
    var button=event.target.closest('.js-branch-status');
    if(!button)return;

    var currentStatus=Number(button.getAttribute('data-status')||0);
    var next=currentStatus===1?0:1;
    var name=button.getAttribute('data-name')||'Branch';

    if(!confirm((next?'Activate ':'Deactivate ')+'"'+name+'"?'))return;

    button.disabled=true;

    App.api('api/branches.php',{
        method:'PATCH',
        body:{
            id:Number(button.getAttribute('data-id')||0),
            status:next
        }
    }).then(function(result){
        if(window.showToast)showToast(result.message||'Status updated successfully.',{type:'success',duration:2});
        table.ajax.reload(null,false);
    }).catch(function(error){
        App.showError(error,'Unable to update Branch status.');
    }).finally(function(){
        button.disabled=false;
    });
});

boot();
})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
