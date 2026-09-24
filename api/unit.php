<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';

function unit_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Unit management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row=$stmt->fetch();

    if(!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.',403);
    }

    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name']
    ];
}

function unit_text($value,string $field,string $label,int $max): string
{
    $value=trim((string)($value??''));

    if($value==='') {
        json_error($label.' is required.',422,[$field=>$label.' is required.']);
    }

    if(mb_strlen($value)>$max) {
        json_error($label.' is too long.',422,[$field=>$label.' must be within '.$max.' characters.']);
    }

    return $value;
}

function unit_record(int $branchId,int $id): array
{
    $stmt=db()->prepare(
        'SELECT id,branch_id,unit_name,short_name,status,created_by,created_at,updated_at
         FROM units
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':branch_id'=>$branchId]);
    $row=$stmt->fetch();

    if(!$row) {
        json_error('Unit was not found in your branch.',404);
    }

    $row['id']=(int)$row['id'];
    $row['branch_id']=(int)$row['branch_id'];
    $row['status']=(int)$row['status'];
    return $row;
}

function unit_unique(int $branchId,string $name,int $exclude=0): void
{
    $sql='SELECT id
          FROM units
          WHERE branch_id=:branch_id
            AND LOWER(unit_name)=LOWER(:name)';

    $params=[
        ':branch_id'=>$branchId,
        ':name'=>$name
    ];

    if($exclude>0){
        $sql.=' AND id<>:id';
        $params[':id']=$exclude;
    }

    $sql.=' LIMIT 1';
    $stmt=db()->prepare($sql);
    $stmt->execute($params);

    if($stmt->fetchColumn()) {
        json_error('Unit name already exists in your branch.',409,[
            'unit_name'=>'Use a unique unit name.'
        ]);
    }
}

function unit_status($value): int
{
    $status=(int)$value;

    if(!in_array($status,[1,2],true)) {
        json_error('Invalid Unit status.',422,[
            'status'=>'Status must be Active or Inactive.'
        ]);
    }

    return $status;
}

$method=request_method();

if($method==='GET'){
    $access=require_permission('unit-list.php',ACTION_VIEW);
    $user=$access['user'];
    $ctx=unit_tenant_context($user);
    $branchId=$ctx['branch_id'];

    if(isset($_GET['id'])) {
        json_success('Unit loaded.',[
            'unit'=>unit_record($branchId,positive_id($_GET['id'])),
            'allowed_actions'=>$access['actions']
        ]);
    }

    if(isset($_GET['datatable'])){
        $draw=max(1,(int)($_GET['draw']??1));
        $start=max(0,(int)($_GET['start']??0));
        $length=max(1,min(100000,(int)($_GET['length']??10)));
        $search=trim((string)($_GET['search']['value']??''));
        $status=$_GET['status']??'';

        $baseWhere=['branch_id=:branch_id'];
        $where=$baseWhere;
        $params=[':branch_id'=>$branchId];

        if($search!==''){
            $term='%'.$search.'%';
            $where[]='(unit_name LIKE :search_name OR short_name LIKE :search_short)';
            $params[':search_name']=$term;
            $params[':search_short']=$term;
        }

        if($status!==''){
            $where[]='status=:status';
            $params[':status']=unit_status($status);
        }

        $totalStmt=db()->prepare(
            'SELECT COUNT(*) FROM units WHERE '.implode(' AND ',$baseWhere)
        );
        $totalStmt->execute([':branch_id'=>$branchId]);
        $total=(int)$totalStmt->fetchColumn();

        $filteredStmt=db()->prepare(
            'SELECT COUNT(*) FROM units WHERE '.implode(' AND ',$where)
        );
        $filteredStmt->execute($params);
        $filtered=(int)$filteredStmt->fetchColumn();

        $overallStmt=db()->prepare(
            'SELECT COUNT(*) AS total_units,
                    COALESCE(SUM(CASE WHEN status=1 THEN 1 ELSE 0 END),0) AS active_units,
                    COALESCE(SUM(CASE WHEN status=2 THEN 1 ELSE 0 END),0) AS inactive_units
             FROM units
             WHERE branch_id=:branch_id'
        );
        $overallStmt->execute([':branch_id'=>$branchId]);
        $overall=$overallStmt->fetch(PDO::FETCH_ASSOC)?:[];

        $columns=['unit_name','short_name','status','id'];
        $index=(int)($_GET['order'][0]['column']??0);
        $direction=strtolower((string)($_GET['order'][0]['dir']??'asc'))==='desc'?'DESC':'ASC';
        $order=$columns[$index]??'unit_name';

        $sql='SELECT id,unit_name,short_name,status
              FROM units
              WHERE '.implode(' AND ',$where).'
              ORDER BY '.$order.' '.$direction.',id ASC
              LIMIT :start,:length';

        $stmt=db()->prepare($sql);

        foreach($params as $key=>$value){
            $stmt->bindValue(
                $key,
                $value,
                ($key===':branch_id'||$key===':status')?PDO::PARAM_INT:PDO::PARAM_STR
            );
        }

        $stmt->bindValue(':start',$start,PDO::PARAM_INT);
        $stmt->bindValue(':length',$length,PDO::PARAM_INT);
        $stmt->execute();

        $rows=$stmt->fetchAll();

        foreach($rows as &$row){
            $row['id']=(int)$row['id'];
            $row['status']=(int)$row['status'];
        }
        unset($row);

        json_success('Units loaded.',[
            'datatable'=>[
                'draw'=>$draw,
                'recordsTotal'=>$total,
                'recordsFiltered'=>$filtered,
                'data'=>$rows
            ],
            'summary'=>[
                'total_units'=>(int)($overall['total_units']??0),
                'active_units'=>(int)($overall['active_units']??0),
                'inactive_units'=>(int)($overall['inactive_units']??0),
                'matching_units'=>$filtered
            ],
            'allowed_actions'=>$access['actions']
        ]);
    }

    $active=isset($_GET['active'])&&(int)$_GET['active']===1;
    $sql='SELECT id,unit_name,short_name,status
          FROM units
          WHERE branch_id=:branch_id'.($active?' AND status=1':'').'
          ORDER BY unit_name';

    $stmt=db()->prepare($sql);
    $stmt->execute([':branch_id'=>$branchId]);

    json_success('Units loaded.',[
        'units'=>$stmt->fetchAll(),
        'allowed_actions'=>$access['actions']
    ]);
}

if($method==='POST'){
    $access=require_permission('unit-list.php',ACTION_CREATE);
    $user=$access['user'];
    $ctx=unit_tenant_context($user);
    $branchId=$ctx['branch_id'];
    $data=request_data();

    $name=unit_text($data['unit_name']??'','unit_name','Unit name',80);
    $short=unit_text($data['short_name']??'','short_name','Short name',20);

    unit_unique($branchId,$name);

    try{
        $stmt=db()->prepare(
            'INSERT INTO units
             (branch_id,unit_name,short_name,status,created_by,created_at,updated_at)
             VALUES(:branch_id,:unit_name,:short_name,1,:created_by,NOW(),NOW())'
        );

        $stmt->execute([
            ':branch_id'=>$branchId,
            ':unit_name'=>$name,
            ':short_name'=>$short,
            ':created_by'=>(int)$user['id']
        ]);

        $id=(int)db()->lastInsertId();

        audit_log((int)$user['id'],ACTION_CREATE,[
            'company_id'=>$ctx['company_id'],
            'branch_id'=>$branchId,
            'menu_id'=>(int)$access['menu']['id'],
            'record_id'=>$id
        ]);

        json_success('Unit created successfully.',[
            'unit'=>unit_record($branchId,$id)
        ],201);
    }catch(PDOException $e){
        if($e->getCode()==='23000'){
            json_error('Unit name already exists in your branch.',409,[
                'unit_name'=>'Use a unique unit name.'
            ]);
        }
        throw $e;
    }
}

if($method==='PUT'){
    $access=require_permission('unit-list.php',ACTION_UPDATE);
    $user=$access['user'];
    $ctx=unit_tenant_context($user);
    $branchId=$ctx['branch_id'];
    $data=request_data();

    require_fields($data,['id']);

    $id=positive_id($data['id']);
    $old=unit_record($branchId,$id);
    $name=unit_text($data['unit_name']??'','unit_name','Unit name',80);
    $short=unit_text($data['short_name']??'','short_name','Short name',20);

    unit_unique($branchId,$name,$id);

    db()->prepare(
        'UPDATE units
         SET unit_name=:unit_name,
             short_name=:short_name,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    )->execute([
        ':unit_name'=>$name,
        ':short_name'=>$short,
        ':id'=>$id,
        ':branch_id'=>$branchId
    ]);

    audit_log((int)$user['id'],ACTION_UPDATE,[
        'company_id'=>$ctx['company_id'],
        'branch_id'=>$branchId,
        'menu_id'=>(int)$access['menu']['id'],
        'record_id'=>$id,
        'old_data'=>$old
    ]);

    json_success('Unit updated successfully.',[
        'unit'=>unit_record($branchId,$id)
    ]);
}

if($method==='PATCH'){
    $data=request_data();
    require_fields($data,['id','status']);

    $status=unit_status($data['status']);

    $access=require_permission(
        'unit-list.php',
        $status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE
    );

    $user=$access['user'];
    $ctx=unit_tenant_context($user);
    $branchId=$ctx['branch_id'];
    $id=positive_id($data['id']);
    $old=unit_record($branchId,$id);

    db()->prepare(
        'UPDATE units
         SET status=:status,updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    )->execute([
        ':status'=>$status,
        ':id'=>$id,
        ':branch_id'=>$branchId
    ]);

    audit_log(
        (int)$user['id'],
        $status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE,
        [
            'company_id'=>$ctx['company_id'],
            'branch_id'=>$branchId,
            'menu_id'=>(int)$access['menu']['id'],
            'record_id'=>$id,
            'old_data'=>$old
        ]
    );

    json_success($status===1?'Unit activated.':'Unit deactivated.');
}

json_error('Method not allowed.',405);
