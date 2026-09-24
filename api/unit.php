<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';

function unit_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Unit management is available only for tenant users.', 403);
    }
    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);
    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row=$stmt->fetch();
    if(!$row) json_error('Your assigned tenant branch is invalid or inactive.',403);
    return ['branch_id'=>(int)$row['branch_id'],'company_id'=>(int)$row['company_id'],'branch_name'=>(string)$row['branch_name'],'company_name'=>(string)$row['company_name']];
}

function unit_text($value,string $field,string $label,int $max): string
{
    $value=trim((string)($value??''));
    if($value==='') json_error($label.' is required.',422,[$field=>$label.' is required.']);
    if(mb_strlen($value)>$max) json_error($label.' is too long.',422,[$field=>$label.' must be within '.$max.' characters.']);
    return $value;
}


function unit_record(int $branchId,int $id): array
{
    $stmt=db()->prepare('SELECT id,branch_id,unit_name,short_name,status,created_by,created_at,updated_at FROM units WHERE id=:id AND branch_id=:branch_id LIMIT 1');
    $stmt->execute([':id'=>$id,':branch_id'=>$branchId]);$row=$stmt->fetch();if(!$row)json_error('Unit was not found in your branch.',404);$row['id']=(int)$row['id'];$row['branch_id']=(int)$row['branch_id'];$row['status']=(int)$row['status'];return $row;
}
function unit_unique(int $branchId,string $name,int $exclude=0): void
{
    $sql='SELECT id FROM units WHERE branch_id=:branch_id AND LOWER(unit_name)=LOWER(:name)';$p=[':branch_id'=>$branchId,':name'=>$name];if($exclude>0){$sql.=' AND id<>:id';$p[':id']=$exclude;}$sql.=' LIMIT 1';$s=db()->prepare($sql);$s->execute($p);if($s->fetchColumn())json_error('Unit name already exists in your branch.',409,['unit_name'=>'Use a unique unit name.']);
}
function unit_status($value): int { $n=(int)$value;if(!in_array($n,[1,2],true))json_error('Invalid Unit status.',422,['status'=>'Status must be Active or Inactive.']);return $n; }
$method=request_method();
if($method==='GET'){
    $access=require_permission('unit-list.php',ACTION_VIEW);$user=$access['user'];$ctx=unit_tenant_context($user);$branchId=$ctx['branch_id'];
    if(isset($_GET['id'])) json_success('Unit loaded.',['unit'=>unit_record($branchId,positive_id($_GET['id'])),'allowed_actions'=>$access['actions']]);
    if(isset($_GET['datatable'])){
        $draw=max(1,(int)($_GET['draw']??1));$start=max(0,(int)($_GET['start']??0));$length=max(1,min(100000,(int)($_GET['length']??10)));$search=trim((string)($_GET['search']['value']??''));$status=$_GET['status']??'';$where=['branch_id=:branch_id'];$base=$where;$p=[':branch_id'=>$branchId];if($search!==''){$where[]='(unit_name LIKE :search OR short_name LIKE :search)';$p[':search']='%'.$search.'%';}if($status!==''){$where[]='status=:status';$p[':status']=unit_status($status);} $t=db()->prepare('SELECT COUNT(*) FROM units WHERE '.implode(' AND ',$base));$t->execute([':branch_id'=>$branchId]);$total=(int)$t->fetchColumn();$f=db()->prepare('SELECT COUNT(*) FROM units WHERE '.implode(' AND ',$where));$f->execute($p);$filtered=(int)$f->fetchColumn();$cols=['unit_name','short_name','status','id'];$idx=(int)($_GET['order'][0]['column']??0);$dir=strtolower((string)($_GET['order'][0]['dir']??'asc'))==='desc'?'DESC':'ASC';$order=$cols[$idx]??'unit_name';$sql='SELECT id,unit_name,short_name,status FROM units WHERE '.implode(' AND ',$where).' ORDER BY '.$order.' '.$dir.',id ASC LIMIT :start,:length';$s=db()->prepare($sql);foreach($p as $k=>$v)$s->bindValue($k,$v,($k===':branch_id'||$k===':status')?PDO::PARAM_INT:PDO::PARAM_STR);$s->bindValue(':start',$start,PDO::PARAM_INT);$s->bindValue(':length',$length,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();foreach($rows as &$r){$r['id']=(int)$r['id'];$r['status']=(int)$r['status'];}unset($r);json_success('Units loaded.',['datatable'=>['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows],'allowed_actions'=>$access['actions']]);
    }
    $active=isset($_GET['active'])&&(int)$_GET['active']===1;$sql='SELECT id,unit_name,short_name,status FROM units WHERE branch_id=:branch_id'.($active?' AND status=1':'').' ORDER BY unit_name';$s=db()->prepare($sql);$s->execute([':branch_id'=>$branchId]);json_success('Units loaded.',['units'=>$s->fetchAll(),'allowed_actions'=>$access['actions']]);
}
if($method==='POST'){$access=require_permission('unit-list.php',ACTION_CREATE);$user=$access['user'];$ctx=unit_tenant_context($user);$b=$ctx['branch_id'];$d=request_data();$name=unit_text($d['unit_name']??'','unit_name','Unit name',80);$short=unit_text($d['short_name']??'','short_name','Short name',20);unit_unique($b,$name);try{$s=db()->prepare('INSERT INTO units(branch_id,unit_name,short_name,status,created_by,created_at,updated_at) VALUES(:branch_id,:unit_name,:short_name,1,:created_by,NOW(),NOW())');$s->execute([':branch_id'=>$b,':unit_name'=>$name,':short_name'=>$short,':created_by'=>(int)$user['id']]);$id=(int)db()->lastInsertId();audit_log((int)$user['id'],ACTION_CREATE,['company_id'=>$ctx['company_id'],'branch_id'=>$b,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id]);json_success('Unit created successfully.',['unit'=>unit_record($b,$id)],201);}catch(PDOException $e){if($e->getCode()==='23000')json_error('Unit name already exists in your branch.',409,['unit_name'=>'Use a unique unit name.']);throw $e;}}
if($method==='PUT'){$access=require_permission('unit-list.php',ACTION_UPDATE);$user=$access['user'];$ctx=unit_tenant_context($user);$b=$ctx['branch_id'];$d=request_data();require_fields($d,['id']);$id=positive_id($d['id']);$old=unit_record($b,$id);$name=unit_text($d['unit_name']??'','unit_name','Unit name',80);$short=unit_text($d['short_name']??'','short_name','Short name',20);unit_unique($b,$name,$id);db()->prepare('UPDATE units SET unit_name=:unit_name,short_name=:short_name,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':unit_name'=>$name,':short_name'=>$short,':id'=>$id,':branch_id'=>$b]);audit_log((int)$user['id'],ACTION_UPDATE,['company_id'=>$ctx['company_id'],'branch_id'=>$b,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);json_success('Unit updated successfully.',['unit'=>unit_record($b,$id)]);}
if($method==='PATCH'){$d=request_data();require_fields($d,['id','status']);$status=unit_status($d['status']);$access=require_permission('unit-list.php',$status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE);$user=$access['user'];$ctx=unit_tenant_context($user);$b=$ctx['branch_id'];$id=positive_id($d['id']);$old=unit_record($b,$id);db()->prepare('UPDATE units SET status=:status,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':status'=>$status,':id'=>$id,':branch_id'=>$b]);audit_log((int)$user['id'],$status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE,['company_id'=>$ctx['company_id'],'branch_id'=>$b,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);json_success($status===1?'Unit activated.':'Unit deactivated.');}
json_error('Method not allowed.',405);
