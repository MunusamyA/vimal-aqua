<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';

function price_level_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Price Level management is available only for tenant users.', 403);
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

function price_level_text($value,string $field,string $label,int $max): string
{
    $value=trim((string)($value??''));
    if($value==='') json_error($label.' is required.',422,[$field=>$label.' is required.']);
    if(mb_strlen($value)>$max) json_error($label.' is too long.',422,[$field=>$label.' must be within '.$max.' characters.']);
    return $value;
}


function price_level_record(int $branchId,int $id): array{$s=db()->prepare('SELECT id,branch_id,price_level_name,status,created_by,created_at,updated_at FROM price_levels WHERE id=:id AND branch_id=:branch_id LIMIT 1');$s->execute([':id'=>$id,':branch_id'=>$branchId]);$r=$s->fetch();if(!$r)json_error('Price Level was not found in your branch.',404);$r['id']=(int)$r['id'];$r['branch_id']=(int)$r['branch_id'];$r['status']=(int)$r['status'];return $r;}
function price_level_unique(int $branchId,string $name,int $exclude=0): void{$sql='SELECT id FROM price_levels WHERE branch_id=:branch_id AND LOWER(price_level_name)=LOWER(:name)';$p=[':branch_id'=>$branchId,':name'=>$name];if($exclude>0){$sql.=' AND id<>:id';$p[':id']=$exclude;}$sql.=' LIMIT 1';$s=db()->prepare($sql);$s->execute($p);if($s->fetchColumn())json_error('Price Level name already exists in your branch.',409,['price_level_name'=>'Use a unique Price Level name.']);}
function price_level_status($v): int{$n=(int)$v;if(!in_array($n,[1,2],true))json_error('Invalid Price Level status.',422,['status'=>'Status must be Active or Inactive.']);return $n;}
$method=request_method();
if($method==='GET'){$access=require_permission('price-level-list.php',ACTION_VIEW);$u=$access['user'];$ctx=price_level_tenant_context($u);$b=$ctx['branch_id'];if(isset($_GET['id']))json_success('Price Level loaded.',['price_level'=>price_level_record($b,positive_id($_GET['id'])),'allowed_actions'=>$access['actions']]);if(isset($_GET['datatable'])){$draw=max(1,(int)($_GET['draw']??1));$start=max(0,(int)($_GET['start']??0));$length=max(1,min(100000,(int)($_GET['length']??10)));$search=trim((string)($_GET['search']['value']??''));$status=$_GET['status']??'';$where=['branch_id=:branch_id'];$base=$where;$p=[':branch_id'=>$b];if($search!==''){$where[]='price_level_name LIKE :search';$p[':search']='%'.$search.'%';}if($status!==''){$where[]='status=:status';$p[':status']=price_level_status($status);} $t=db()->prepare('SELECT COUNT(*) FROM price_levels WHERE '.implode(' AND ',$base));$t->execute([':branch_id'=>$b]);$total=(int)$t->fetchColumn();$f=db()->prepare('SELECT COUNT(*) FROM price_levels WHERE '.implode(' AND ',$where));$f->execute($p);$filtered=(int)$f->fetchColumn();$cols=['price_level_name','status','id'];$idx=(int)($_GET['order'][0]['column']??0);$dir=strtolower((string)($_GET['order'][0]['dir']??'asc'))==='desc'?'DESC':'ASC';$order=$cols[$idx]??'price_level_name';$sql='SELECT id,price_level_name,status FROM price_levels WHERE '.implode(' AND ',$where).' ORDER BY '.$order.' '.$dir.',id ASC LIMIT :start,:length';$s=db()->prepare($sql);foreach($p as $k=>$v)$s->bindValue($k,$v,($k===':branch_id'||$k===':status')?PDO::PARAM_INT:PDO::PARAM_STR);$s->bindValue(':start',$start,PDO::PARAM_INT);$s->bindValue(':length',$length,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();foreach($rows as &$r){$r['id']=(int)$r['id'];$r['status']=(int)$r['status'];}unset($r);json_success('Price Levels loaded.',['datatable'=>['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows],'allowed_actions'=>$access['actions']]);}$sql='SELECT id,price_level_name,status FROM price_levels WHERE branch_id=:branch_id'.((isset($_GET['active'])&&(int)$_GET['active']===1)?' AND status=1':'').' ORDER BY price_level_name';$s=db()->prepare($sql);$s->execute([':branch_id'=>$b]);json_success('Price Levels loaded.',['price_levels'=>$s->fetchAll(),'allowed_actions'=>$access['actions']]);}
if($method==='POST'){$access=require_permission('price-level-list.php',ACTION_CREATE);$u=$access['user'];$ctx=price_level_tenant_context($u);$b=$ctx['branch_id'];$d=request_data();$name=price_level_text($d['price_level_name']??'','price_level_name','Price Level name',100);price_level_unique($b,$name);try{$s=db()->prepare('INSERT INTO price_levels(branch_id,price_level_name,status,created_by,created_at,updated_at) VALUES(:branch_id,:name,1,:created_by,NOW(),NOW())');$s->execute([':branch_id'=>$b,':name'=>$name,':created_by'=>(int)$u['id']]);$id=(int)db()->lastInsertId();audit_log((int)$u['id'],ACTION_CREATE,['company_id'=>$ctx['company_id'],'branch_id'=>$b,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id]);json_success('Price Level created successfully.',['price_level'=>price_level_record($b,$id)],201);}catch(PDOException $e){if($e->getCode()==='23000')json_error('Price Level name already exists in your branch.',409,['price_level_name'=>'Use a unique Price Level name.']);throw $e;}}
if($method==='PUT'){$access=require_permission('price-level-list.php',ACTION_UPDATE);$u=$access['user'];$ctx=price_level_tenant_context($u);$b=$ctx['branch_id'];$d=request_data();require_fields($d,['id']);$id=positive_id($d['id']);$old=price_level_record($b,$id);$name=price_level_text($d['price_level_name']??'','price_level_name','Price Level name',100);price_level_unique($b,$name,$id);db()->prepare('UPDATE price_levels SET price_level_name=:name,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':name'=>$name,':id'=>$id,':branch_id'=>$b]);audit_log((int)$u['id'],ACTION_UPDATE,['company_id'=>$ctx['company_id'],'branch_id'=>$b,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);json_success('Price Level updated successfully.',['price_level'=>price_level_record($b,$id)]);}
if($method==='PATCH'){$d=request_data();require_fields($d,['id','status']);$status=price_level_status($d['status']);$access=require_permission('price-level-list.php',$status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE);$u=$access['user'];$ctx=price_level_tenant_context($u);$b=$ctx['branch_id'];$id=positive_id($d['id']);$old=price_level_record($b,$id);db()->prepare('UPDATE price_levels SET status=:status,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':status'=>$status,':id'=>$id,':branch_id'=>$b]);audit_log((int)$u['id'],$status===1?ACTION_ACTIVATE:ACTION_DEACTIVATE,['company_id'=>$ctx['company_id'],'branch_id'=>$b,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);json_success($status===1?'Price Level activated.':'Price Level deactivated.');}
json_error('Method not allowed.',405);
