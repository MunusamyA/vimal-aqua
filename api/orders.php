<?php
declare(strict_types=1);
require_once __DIR__ . '/_sales_common.php';

function order_record(int $branchId,int $id): array
{
    $stmt=db()->prepare(
        'SELECT s.*,c.customer_code,c.customer_name,l.line_name
         FROM sales s
         INNER JOIN customers c ON c.id=s.customer_id
         LEFT JOIN `lines` l ON l.id=s.line_id
         WHERE s.id=:id AND s.branch_id=:branch_id AND s.document_type=3 AND s.sale_type=2
         LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':branch_id'=>$branchId]);$row=$stmt->fetch();
    if(!$row)json_error('Customer Order was not found.',404);
    foreach(['id','branch_id','customer_id','line_id','sale_type','document_type','tax_mode','overall_discount_type','round_off_enabled','payment_status','delivery_status','status'] as $k)$row[$k]=$row[$k]===null?null:(int)$row[$k];
    foreach(['subtotal','item_discount_total','overall_discount_value','overall_discount_amount','tax_amount','other_charges','round_off','grand_total','paid_amount','balance_amount'] as $k)$row[$k]=(float)$row[$k];
    $row['ref']=encryptReference('order',(int)$row['id']);
    $row['edit_url']='order-form.php?ref='.rawurlencode($row['ref']);
    return $row;
}

function order_payload(int $branchId,int $id): array
{
    return ['order'=>order_record($branchId,$id),'items'=>aqua_sale_items_payload($id)];
}

function order_refresh_status(PDO $pdo,int $orderId): int
{
    $stmt=$pdo->prepare(
        'SELECT COUNT(*) AS item_count,
                SUM(CASE WHEN delivered_base_qty<=0.0005 THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN delivered_base_qty+0.0005>=ordered_base_qty THEN 1 ELSE 0 END) AS delivered_count
         FROM sales_items WHERE sale_id=:sale_id'
    );
    $stmt->execute([':sale_id'=>$orderId]);$r=$stmt->fetch();
    $count=(int)($r['item_count']??0);$pending=(int)($r['pending_count']??0);$delivered=(int)($r['delivered_count']??0);
    $status=$count===0?1:($delivered===$count?3:($pending===$count?1:2));
    $pdo->prepare('UPDATE sales SET delivery_status=:delivery_status,updated_at=NOW() WHERE id=:id')->execute([':delivery_status'=>$status,':id'=>$orderId]);
    return $status;
}

function order_save(array $data,array $access,array $context,?int $id=null): array
{
    $branchId=(int)$context['branch_id'];$userId=(int)$access['user']['id'];
    $intent=strtolower(trim((string)($data['intent']??'draft')));
    if(!in_array($intent,['draft','confirm'],true))json_error('Invalid Customer Order action.',422);
    aqua_require_action($access,$id===null?ACTION_CREATE:ACTION_UPDATE,'You do not have permission to save Customer Orders.');
    aqua_require_action($access,$intent==='confirm'?ACTION_POST:ACTION_SAVE_DRAFT,$intent==='confirm'?'You do not have permission to Confirm Customer Order.':'You do not have permission to Save Draft.');

    $date=aqua_date($data['sale_date']??'','order_date');
    $customerId=(int)($data['customer_id']??0);if($customerId<1)json_error('Select Customer.',422);
    $customer=aqua_customer($branchId,$customerId);
    if(!aqua_has_action((array)$access['actions'],ACTION_MANAGE_TAX_SETTINGS)){
        if($id!==null){
            $taxStmt=db()->prepare('SELECT tax_mode FROM sales WHERE id=:id AND branch_id=:branch_id AND document_type=3 AND sale_type=2 LIMIT 1');
            $taxStmt->execute([':id'=>$id,':branch_id'=>$branchId]);
            $savedTax=$taxStmt->fetchColumn();
            $data['tax_mode']=$savedTax===false?1:(int)$savedTax;
        }else{$data['tax_mode']=1;}
    }
    $calc=aqua_calculate_sale_items($data,$branchId,$customerId,$access,true);
    $remarks=aqua_nullable($data['remarks']??null,255);

    $pdo=db();$pdo->beginTransaction();
    try{
        $old=null;
        if($id!==null){
            $lock=$pdo->prepare('SELECT * FROM sales WHERE id=:id AND branch_id=:branch_id AND document_type=3 AND sale_type=2 FOR UPDATE');
            $lock->execute([':id'=>$id,':branch_id'=>$branchId]);$old=$lock->fetch();
            if(!$old)json_error('Customer Order was not found.',404);
            if((int)$old['status']===3 || (int)$old['delivery_status']===4)json_error('Cancelled Customer Order cannot be edited.',409);
            if((int)$old['delivery_status']===3)json_error('Delivered Customer Order is locked.',409);
            $deliveredStmt=$pdo->prepare('SELECT COALESCE(SUM(delivered_base_qty),0) FROM sales_items WHERE sale_id=:sale_id');$deliveredStmt->execute([':sale_id'=>$id]);
            if((float)$deliveredStmt->fetchColumn()>0.0005 && (int)$old['customer_id']!==$customerId){
                json_error('Customer cannot be changed after Order delivery has started.',409);
            }
        }

        $status=$intent==='confirm'?2:1;
        if($id===null){
            $orderNo=aqua_generate_no_locked($pdo,$branchId,'sales','sale_no','ORD');
            $stmt=$pdo->prepare(
                'INSERT INTO sales
                 (branch_id,sale_no,sale_date,customer_id,line_id,vehicle_id,line_supply_id,source_order_id,line_run_id,customer_order_id,
                  sale_type,document_type,tax_mode,subtotal,item_discount_total,overall_discount_type,overall_discount_value,overall_discount_amount,
                  tax_amount,other_charges,round_off,round_off_enabled,grand_total,paid_amount,balance_amount,payment_status,delivery_status,remarks,status,created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:sale_no,:sale_date,:customer_id,:line_id,NULL,NULL,NULL,NULL,NULL,
                  2,3,:tax_mode,:subtotal,:item_discount_total,:overall_discount_type,:overall_discount_value,:overall_discount_amount,
                  :tax_amount,:other_charges,:round_off,:round_off_enabled,:grand_total,0,0,1,1,:remarks,:status,:created_by,NOW(),NOW())'
            );
            $stmt->execute([
                ':branch_id'=>$branchId,':sale_no'=>$orderNo,':sale_date'=>$date,':customer_id'=>$customerId,':line_id'=>$customer['line_id'],
                ':tax_mode'=>$calc['tax_mode'],':subtotal'=>$calc['subtotal'],':item_discount_total'=>$calc['item_discount_total'],
                ':overall_discount_type'=>$calc['overall_discount_type'],':overall_discount_value'=>$calc['overall_discount_value'],
                ':overall_discount_amount'=>$calc['overall_discount_amount'],':tax_amount'=>$calc['tax_amount'],':other_charges'=>$calc['other_charges'],
                ':round_off'=>$calc['round_off'],':round_off_enabled'=>$calc['round_off_enabled'],':grand_total'=>$calc['grand_total'],
                ':remarks'=>$remarks,':status'=>$status,':created_by'=>$userId,
            ]);
            $id=(int)$pdo->lastInsertId();
        }else{
            $stmt=$pdo->prepare(
                'UPDATE sales SET sale_date=:sale_date,customer_id=:customer_id,line_id=:line_id,tax_mode=:tax_mode,
                    subtotal=:subtotal,item_discount_total=:item_discount_total,overall_discount_type=:overall_discount_type,
                    overall_discount_value=:overall_discount_value,overall_discount_amount=:overall_discount_amount,tax_amount=:tax_amount,
                    other_charges=:other_charges,round_off=:round_off,round_off_enabled=:round_off_enabled,grand_total=:grand_total,
                    remarks=:remarks,status=:status,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':sale_date'=>$date,':customer_id'=>$customerId,':line_id'=>$customer['line_id'],':tax_mode'=>$calc['tax_mode'],
                ':subtotal'=>$calc['subtotal'],':item_discount_total'=>$calc['item_discount_total'],':overall_discount_type'=>$calc['overall_discount_type'],
                ':overall_discount_value'=>$calc['overall_discount_value'],':overall_discount_amount'=>$calc['overall_discount_amount'],
                ':tax_amount'=>$calc['tax_amount'],':other_charges'=>$calc['other_charges'],':round_off'=>$calc['round_off'],
                ':round_off_enabled'=>$calc['round_off_enabled'],':grand_total'=>$calc['grand_total'],':remarks'=>$remarks,':status'=>$status,
                ':id'=>$id,':branch_id'=>$branchId,
            ]);
        }
        aqua_sync_sale_items($pdo,$id,$calc['items'],true);
        order_refresh_status($pdo,$id);
        $pdo->commit();
        return order_payload($branchId,$id);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

$method=request_method();

if($method==='GET' && isset($_GET['options'])){
    $access=require_permission('order-list.php',ACTION_VIEW);$context=aqua_sales_context($access['user']);$branchId=(int)$context['branch_id'];
    $customerId=isset($_GET['customer_id'])?(int)$_GET['customer_id']:null;
    json_success('Customer Order options loaded.',[
        'customers'=>aqua_customers($branchId),'products'=>aqua_sales_products($branchId,$customerId&&$customerId>0?$customerId:null),
        'allowed_actions'=>$access['actions'],'context'=>$context,
    ]);
}

if($method==='GET' && isset($_GET['datatable'])){
    $access=require_permission('order-list.php',ACTION_VIEW);$context=aqua_sales_context($access['user']);$branchId=(int)$context['branch_id'];
    $draw=(int)($_GET['draw']??1);$start=max(0,(int)($_GET['start']??0));$length=max(1,min(100,(int)($_GET['length']??10)));$search=trim((string)($_GET['search']['value']??''));
    $delivery=isset($_GET['delivery_status'])?(int)$_GET['delivery_status']:0;$where=['s.branch_id=:branch_id','s.document_type=3','s.sale_type=2'];$params=[':branch_id'=>$branchId];
    if(in_array($delivery,[1,2,3,4],true)){$where[]='s.delivery_status=:delivery';$params[':delivery']=$delivery;}
    if($search!==''){$where[]='(s.sale_no LIKE :q OR c.customer_name LIKE :q OR c.customer_code LIKE :q)';$params[':q']='%'.$search.'%';}
    $from=' FROM sales s INNER JOIN customers c ON c.id=s.customer_id INNER JOIN `lines` l ON l.id=s.line_id ';
    $cnt=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.implode(' AND ',$where));$cnt->execute($params);$filtered=(int)$cnt->fetchColumn();
    $tot=db()->prepare('SELECT COUNT(*) FROM sales WHERE branch_id=:branch_id AND document_type=3 AND sale_type=2');$tot->execute([':branch_id'=>$branchId]);$total=(int)$tot->fetchColumn();
    $sql='SELECT s.id,s.sale_no,s.sale_date,s.grand_total,s.delivery_status,s.status,c.customer_name,l.line_name,
                 COALESCE((SELECT SUM(si.ordered_base_qty) FROM sales_items si WHERE si.sale_id=s.id),0) AS ordered_qty,
                 COALESCE((SELECT SUM(si.delivered_base_qty) FROM sales_items si WHERE si.sale_id=s.id),0) AS delivered_qty'.$from.' WHERE '.implode(' AND ',$where).' ORDER BY s.sale_date DESC,s.id DESC LIMIT :start,:length';
    $stmt=db()->prepare($sql);foreach($params as $k=>$v)$stmt->bindValue($k,$v,in_array($k,[':branch_id',':delivery'],true)?PDO::PARAM_INT:PDO::PARAM_STR);$stmt->bindValue(':start',$start,PDO::PARAM_INT);$stmt->bindValue(':length',$length,PDO::PARAM_INT);$stmt->execute();
    $rows=[];foreach($stmt->fetchAll() as $r){$id=(int)$r['id'];$r['delivery_status']=(int)$r['delivery_status'];$r['status']=(int)$r['status'];$r['grand_total']=(float)$r['grand_total'];$r['ordered_qty']=(float)$r['ordered_qty'];$r['delivered_qty']=(float)$r['delivered_qty'];$r['pending_qty']=max(0,round($r['ordered_qty']-$r['delivered_qty'],3));$ref=encryptReference('order',$id);$r['ref']=$ref;$r['edit_url']='order-form.php?ref='.rawurlencode($ref);unset($r['id']);$rows[]=$r;}
    json_success('Customer Orders loaded.',['allowed_actions'=>$access['actions'],'datatable'=>['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows]]);
}

if($method==='GET' && isset($_GET['ref'])){
    $access=require_permission('order-list.php',ACTION_VIEW);$context=aqua_sales_context($access['user']);$id=aqua_ref_to_id($_GET['ref'],'order','Customer Order');
    $payload=order_payload((int)$context['branch_id'],$id);$payload['allowed_actions']=$access['actions'];$payload['context']=$context;
    json_success('Customer Order loaded.',$payload);
}

if($method==='POST'){
    $data=request_data();$id=null;if(!empty($data['ref']))$id=aqua_ref_to_id($data['ref'],'order','Customer Order');
    $access=require_permission('order-list.php',$id===null?ACTION_CREATE:ACTION_UPDATE);$context=aqua_sales_context($access['user']);$payload=order_save($data,$access,$context,$id);$payload['allowed_actions']=$access['actions'];
    json_success(($data['intent']??'draft')==='confirm'?'Customer Order confirmed.':'Customer Order draft saved.',$payload);
}

json_error('Unsupported Customer Order API request.',405);
