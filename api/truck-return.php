<?php
declare(strict_types=1);
require_once __DIR__ . '/_sales_common.php';

function truck_return_trip(int $branchId,int $id,array $access,array $context,bool $lock=false): array
{
    $scope=aqua_line_supply_scope_sql($access,$context,'ls');
    $sql='SELECT ls.*,v.vehicle_no,v.vehicle_name,l.line_code,l.line_name,e.employee_code,e.name AS line_man_name
          FROM line_supplies ls
          INNER JOIN vehicles v ON v.id=ls.vehicle_id AND v.branch_id=ls.branch_id
          INNER JOIN `lines` l ON l.id=ls.line_id AND l.branch_id=ls.branch_id
          INNER JOIN employees e ON e.id=ls.line_man_employee_id AND e.branch_id=ls.branch_id
          WHERE ls.id=:id AND ls.branch_id=:branch_id'.$scope[0].' LIMIT 1'.($lock?' FOR UPDATE':'');
    $stmt=db()->prepare($sql);$stmt->execute([':id'=>$id,':branch_id'=>$branchId]+$scope[1]);$row=$stmt->fetch();
    if(!$row)json_error('Line Supply trip was not found or is not assigned to you.',404);
    foreach(['id','branch_id','vehicle_id','line_id','line_man_employee_id','status','returned_by','closed_by'] as $k)$row[$k]=$row[$k]===null?null:(int)$row[$k];
    $row['ref']=encryptReference('line_supply',(int)$row['id']);return $row;
}

function truck_return_items(int $branchId,array $trip): array
{
    $stmt=db()->prepare(
        'SELECT lsi.*,p.product_code,p.product_name,p.container_type,
                up.unit_name AS primary_unit_name,up.short_name AS primary_short_name,
                us.unit_name AS secondary_unit_name,us.short_name AS secondary_short_name
         FROM line_supply_items lsi
         INNER JOIN products p ON p.id=lsi.product_id
         INNER JOIN product_units pup ON pup.id=lsi.primary_product_unit_id
         INNER JOIN units up ON up.id=pup.unit_id
         LEFT JOIN product_units pus ON pus.id=lsi.secondary_product_unit_id
         LEFT JOIN units us ON us.id=pus.unit_id
         WHERE lsi.line_supply_id=:supply_id ORDER BY lsi.id'
    );
    $stmt->execute([':supply_id'=>$trip['id']]);$rows=$stmt->fetchAll();
    foreach($rows as &$r){
        foreach(['id','product_id','container_type'] as $k)$r[$k]=(int)$r[$k];
        foreach(['loaded_base_qty','returned_base_qty','shortage_base_qty','empty_returned_to_plant_qty','empty_shortage_qty','damaged_returned_to_plant_qty','damaged_shortage_qty'] as $k)$r[$k]=(float)$r[$k];
        if((int)$trip['status']<4){
            $r['expected_return_qty']=aqua_vehicle_stock($branchId,(int)$trip['vehicle_id'],(int)$trip['id'],(int)$r['product_id']);
            $r['empty_collected_qty']=(int)$r['container_type']===1?aqua_can_stock($branchId,2,(int)$trip['vehicle_id'],(int)$r['product_id'],2,(int)$trip['id']):0.0;
            $r['damaged_collected_qty']=(int)$r['container_type']===1?aqua_can_stock($branchId,2,(int)$trip['vehicle_id'],(int)$r['product_id'],3,(int)$trip['id']):0.0;
        }else{
            $r['expected_return_qty']=round((float)$r['returned_base_qty']+(float)$r['shortage_base_qty'],3);
            $r['empty_collected_qty']=round((float)$r['empty_returned_to_plant_qty']+(float)$r['empty_shortage_qty'],3);
            $r['damaged_collected_qty']=round((float)$r['damaged_returned_to_plant_qty']+(float)$r['damaged_shortage_qty'],3);
        }
    }
    unset($r);return $rows;
}

function truck_return_financial(int $supplyId): array
{
    $stmt=db()->prepare(
        'SELECT COALESCE(SUM(grand_total),0) total_sales,COALESCE(SUM(paid_amount),0) total_received,COALESCE(SUM(balance_amount),0) outstanding,COUNT(*) invoice_count
         FROM sales WHERE line_supply_id=:supply_id AND document_type=2 AND status=2'
    );
    $stmt->execute([':supply_id'=>$supplyId]);$sum=$stmt->fetch();
    $modes=[1=>0.0,2=>0.0,3=>0.0,4=>0.0];
    $stmt=db()->prepare(
        'SELECT cpd.payment_mode,COALESCE(SUM(cpd.amount),0) amount
         FROM sales s
         INNER JOIN customer_payment_allocations cpa ON cpa.sale_id=s.id
         INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
         INNER JOIN customer_payment_details cpd ON cpd.customer_payment_id=cp.id
         WHERE s.line_supply_id=:supply_id AND s.document_type=2 AND s.status=2
         GROUP BY cpd.payment_mode'
    );
    $stmt->execute([':supply_id'=>$supplyId]);foreach($stmt->fetchAll() as $r){$modes[(int)$r['payment_mode']]=(float)$r['amount'];}
    return [
        'invoice_count'=>(int)$sum['invoice_count'],'total_sales'=>(float)$sum['total_sales'],'total_received'=>(float)$sum['total_received'],
        'outstanding'=>(float)$sum['outstanding'],'cash'=>$modes[1],'upi'=>$modes[2],'bank'=>$modes[3],'cheque'=>$modes[4],
    ];
}

function truck_return_payload(int $branchId,int $supplyId,array $access,array $context): array
{
    $trip=truck_return_trip($branchId,$supplyId,$access,$context);
    return ['trip'=>$trip,'items'=>truck_return_items($branchId,$trip),'financial'=>truck_return_financial($supplyId)];
}

function truck_return_submit(array $data,array $access,array $context): array
{
    $branchId=(int)$context['branch_id'];$userId=(int)$access['user']['id'];$supplyId=aqua_ref_to_id($data['ref']??'','line_supply','Line Supply');$intent=strtolower(trim((string)($data['intent']??'return')));
    if(!in_array($intent,['return','close'],true))json_error('Invalid Truck Return action.',422);
    if($intent==='return')aqua_require_action($access,ACTION_RETURN,'You do not have permission to submit Truck Return.');
    else aqua_require_action($access,ACTION_FINALIZE,'You do not have permission to Close Truck Trip.');

    $pdo=db();$pdo->beginTransaction();
    try{
        $trip=truck_return_trip($branchId,$supplyId,$access,$context,true);
        if($intent==='close'){
            if((int)$trip['status']!==4)json_error('Truck Return must be submitted before Closing.',409);
            $pdo->prepare('UPDATE line_supplies SET status=5,closed_at=NOW(),closed_by=:user_id,updated_at=NOW() WHERE id=:id')->execute([':user_id'=>$userId,':id'=>$supplyId]);
            $pdo->commit();return truck_return_payload($branchId,$supplyId,$access,$context);
        }
        if(!in_array((int)$trip['status'],[2,3],true))json_error('Only Loaded / In Route Truck can be returned.',409);

        $rows=aqua_parse_json_rows($data['items_json']??'[]','Truck Return Items');if(!$rows)json_error('Truck Return Items are required.',422);
        $provided=[];foreach($rows as $r){$pid=(int)($r['product_id']??0);if($pid>0)$provided[$pid]=$r;}
        $items=truck_return_items($branchId,$trip);$dt=date('Y-m-d H:i:s');
        $upd=$pdo->prepare('UPDATE line_supply_items SET returned_base_qty=:returned,shortage_base_qty=:shortage,empty_returned_to_plant_qty=:empty_return,empty_shortage_qty=:empty_short,damaged_returned_to_plant_qty=:damaged_return,damaged_shortage_qty=:damaged_short,return_reason=:reason WHERE id=:id AND line_supply_id=:supply_id');
        foreach($items as $item){
            $pid=(int)$item['product_id'];if(!isset($provided[$pid]))json_error('Return quantity is missing for '.$item['product_name'].'.',422);
            $input=$provided[$pid];$expected=aqua_vehicle_stock($branchId,(int)$trip['vehicle_id'],$supplyId,$pid);
            $actual=aqua_decimal($input['actual_return_qty']??0,'items','Actual Return Qty',3,false);
            if($actual>$expected+0.0005)json_error('Actual Return cannot exceed Truck Stock for '.$item['product_name'].'.',422);
            $short=max(0,round($expected-$actual,3));
            $emptyAvailable=(int)$item['container_type']===1?aqua_can_stock($branchId,2,(int)$trip['vehicle_id'],$pid,2,$supplyId):0.0;
            $damagedAvailable=(int)$item['container_type']===1?aqua_can_stock($branchId,2,(int)$trip['vehicle_id'],$pid,3,$supplyId):0.0;
            $emptyActual=aqua_decimal($input['actual_empty_return_qty']??0,'items','Actual Empty Return Qty',3,false);
            $damagedActual=aqua_decimal($input['actual_damaged_return_qty']??0,'items','Actual Damaged Return Qty',3,false);
            if($emptyActual>$emptyAvailable+0.0005)json_error('Actual Empty Return cannot exceed collected Empty Cans for '.$item['product_name'].'.',422);
            if($damagedActual>$damagedAvailable+0.0005)json_error('Actual Damaged Return cannot exceed collected Damaged Cans for '.$item['product_name'].'.',422);
            $emptyShort=max(0,round($emptyAvailable-$emptyActual,3));$damagedShort=max(0,round($damagedAvailable-$damagedActual,3));
            $reason=aqua_nullable($input['reason']??null,255);
            if(($short>0.0005||$emptyShort>0.0005||$damagedShort>0.0005)&&$reason===null)json_error('Reason is required for Truck Return shortage / difference.',422);

            if($actual>0){
                aqua_insert_vehicle_stock($pdo,$branchId,$dt,(int)$trip['vehicle_id'],$supplyId,$pid,3,null,0,$actual,$userId,'Truck Return To Plant');
                aqua_insert_stock_movement($pdo,$branchId,$dt,$pid,10,$supplyId,$actual,0,$userId);
            }
            if($short>0)aqua_insert_vehicle_stock($pdo,$branchId,$dt,(int)$trip['vehicle_id'],$supplyId,$pid,4,null,0,$short,$userId,'Truck Stock Shortage: '.($reason??''));

            if($emptyActual>0){
                aqua_insert_can_stock($pdo,$branchId,$dt,$pid,2,(int)$trip['vehicle_id'],$supplyId,null,2,3,0,$emptyActual,$userId,'Empty Can Return To Plant');
                aqua_insert_can_stock($pdo,$branchId,$dt,$pid,1,null,$supplyId,null,2,2,$emptyActual,0,$userId,'Empty Can Received From Truck');
            }
            if($emptyShort>0)aqua_insert_can_stock($pdo,$branchId,$dt,$pid,2,(int)$trip['vehicle_id'],$supplyId,null,2,4,0,$emptyShort,$userId,'Empty Can Shortage: '.($reason??''));

            if($damagedActual>0){
                aqua_insert_can_stock($pdo,$branchId,$dt,$pid,2,(int)$trip['vehicle_id'],$supplyId,null,3,3,0,$damagedActual,$userId,'Damaged Can Return To Plant');
                aqua_insert_can_stock($pdo,$branchId,$dt,$pid,1,null,$supplyId,null,3,2,$damagedActual,0,$userId,'Damaged Can Received From Truck');
            }
            if($damagedShort>0)aqua_insert_can_stock($pdo,$branchId,$dt,$pid,2,(int)$trip['vehicle_id'],$supplyId,null,3,4,0,$damagedShort,$userId,'Damaged Can Shortage: '.($reason??''));

            $upd->execute([':returned'=>$actual,':shortage'=>$short,':empty_return'=>$emptyActual,':empty_short'=>$emptyShort,':damaged_return'=>$damagedActual,':damaged_short'=>$damagedShort,':reason'=>$reason,':id'=>$item['id'],':supply_id'=>$supplyId]);
        }
        $remarks=aqua_nullable($data['return_remarks']??null,255);
        $pdo->prepare('UPDATE line_supplies SET status=4,returned_at=NOW(),returned_by=:user_id,return_remarks=:remarks,updated_at=NOW() WHERE id=:id')->execute([':user_id'=>$userId,':remarks'=>$remarks,':id'=>$supplyId]);
        $pdo->commit();return truck_return_payload($branchId,$supplyId,$access,$context);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

$method=request_method();
if($method==='GET' && isset($_GET['ref'])){
    $access=require_permission('line-supply-list.php',ACTION_VIEW);$context=aqua_sales_context($access['user']);$supplyId=aqua_ref_to_id($_GET['ref'],'line_supply','Line Supply');$payload=truck_return_payload((int)$context['branch_id'],$supplyId,$access,$context);$payload['allowed_actions']=$access['actions'];$payload['context']=$context;json_success('Truck Return loaded.',$payload);
}
if($method==='POST'){
    $access=require_permission('line-supply-list.php',ACTION_VIEW);$context=aqua_sales_context($access['user']);$payload=truck_return_submit(request_data(),$access,$context);$payload['allowed_actions']=$access['actions'];json_success('Truck Return updated.',$payload);
}
json_error('Unsupported Truck Return API request.',405);
