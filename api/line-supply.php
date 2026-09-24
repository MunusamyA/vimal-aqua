<?php
declare(strict_types=1);
require_once __DIR__ . '/_sales_common.php';


function line_supply_can_sale(array $user): bool
{
    $menu=menu_by_path('sales-list.php');
    if(!$menu) return false;
    $actions=effective_actions_for_menu($user,$menu);
    return aqua_has_action($actions,ACTION_CREATE) && aqua_has_action($actions,ACTION_POST);
}

function line_supply_record(int $branchId,int $id,array $access,array $context,bool $lock=false): array
{
    $scope=aqua_line_supply_scope_sql($access,$context,'ls');
    $sql='SELECT ls.*,v.vehicle_no,v.vehicle_name,l.line_code,l.line_name
          FROM line_supplies ls
          INNER JOIN vehicles v ON v.id=ls.vehicle_id AND v.branch_id=ls.branch_id
          INNER JOIN `lines` l ON l.id=ls.line_id AND l.branch_id=ls.branch_id
          WHERE ls.id=:id AND ls.branch_id=:branch_id'.$scope[0].' LIMIT 1'.($lock?' FOR UPDATE':'');
    $stmt=db()->prepare($sql);
    $stmt->execute([':id'=>$id,':branch_id'=>$branchId]+$scope[1]);
    $row=$stmt->fetch();
    if(!$row) json_error('Line Supply trip was not found.',404);
    foreach(['id','branch_id','vehicle_id','line_id','line_man_employee_id','status','created_by','returned_by','closed_by'] as $key){
        $row[$key]=$row[$key]===null?null:(int)$row[$key];
    }
    $row['ref']=encryptReference('line_supply',(int)$row['id']);
    $row['edit_url']='line-supply-form.php?ref='.rawurlencode($row['ref']);
    $row['sales_url']='sales-form.php';
    return $row;
}

function line_supply_items(int $branchId,int $supplyId,int $vehicleId,int $tripStatus=1): array
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
    $stmt->execute([':supply_id'=>$supplyId]);
    $rows=$stmt->fetchAll();
    foreach($rows as &$row){
        foreach(['id','line_supply_id','product_id','primary_product_unit_id','secondary_product_unit_id','container_type'] as $key){
            $row[$key]=$row[$key]===null?null:(int)$row[$key];
        }
        foreach(['primary_qty','secondary_qty','primary_conversion_qty','secondary_conversion_qty','loaded_base_qty','returned_base_qty','shortage_base_qty','empty_returned_to_plant_qty','empty_shortage_qty','damaged_returned_to_plant_qty','damaged_shortage_qty'] as $key){
            $row[$key]=(float)$row[$key];
        }
        if($tripStatus<4){
            $row['truck_stock']=aqua_vehicle_stock($branchId,$vehicleId,$supplyId,(int)$row['product_id']);
            $row['expected_return_qty']=$row['truck_stock'];
            $row['empty_truck_stock']=(int)$row['container_type']===1?aqua_can_stock($branchId,2,$vehicleId,(int)$row['product_id'],2,$supplyId):0.0;
            $row['damaged_truck_stock']=(int)$row['container_type']===1?aqua_can_stock($branchId,2,$vehicleId,(int)$row['product_id'],3,$supplyId):0.0;
            $row['empty_collected_qty']=$row['empty_truck_stock'];
            $row['damaged_collected_qty']=$row['damaged_truck_stock'];
        }else{
            $row['truck_stock']=0.0;
            $row['expected_return_qty']=round((float)$row['returned_base_qty']+(float)$row['shortage_base_qty'],3);
            $row['empty_collected_qty']=round((float)$row['empty_returned_to_plant_qty']+(float)$row['empty_shortage_qty'],3);
            $row['damaged_collected_qty']=round((float)$row['damaged_returned_to_plant_qty']+(float)$row['damaged_shortage_qty'],3);
            $row['empty_truck_stock']=0.0;
            $row['damaged_truck_stock']=0.0;
        }
    }
    unset($row);
    return $rows;
}

function line_supply_financial(int $supplyId): array
{
    $stmt=db()->prepare(
        'SELECT COALESCE(SUM(grand_total),0) total_sales,
                COALESCE(SUM(paid_amount),0) total_received,
                COALESCE(SUM(balance_amount),0) outstanding,
                COUNT(*) invoice_count
         FROM sales
         WHERE line_supply_id=:supply_id AND document_type=2 AND sale_type=3 AND status=2'
    );
    $stmt->execute([':supply_id'=>$supplyId]);
    $sum=$stmt->fetch();
    $modes=[1=>0.0,2=>0.0,3=>0.0,4=>0.0];
    $stmt=db()->prepare(
        'SELECT cpd.payment_mode,COALESCE(SUM(cpd.amount),0) amount
         FROM sales s
         INNER JOIN customer_payment_allocations cpa ON cpa.sale_id=s.id
         INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
         INNER JOIN customer_payment_details cpd ON cpd.customer_payment_id=cp.id
         WHERE s.line_supply_id=:supply_id AND s.document_type=2 AND s.sale_type=3 AND s.status=2
         GROUP BY cpd.payment_mode'
    );
    $stmt->execute([':supply_id'=>$supplyId]);
    foreach($stmt->fetchAll() as $row) $modes[(int)$row['payment_mode']]=(float)$row['amount'];
    return [
        'invoice_count'=>(int)$sum['invoice_count'],
        'total_sales'=>(float)$sum['total_sales'],
        'total_received'=>(float)$sum['total_received'],
        'outstanding'=>(float)$sum['outstanding'],
        'cash'=>$modes[1],'upi'=>$modes[2],'bank'=>$modes[3],'cheque'=>$modes[4],
    ];
}

function line_supply_payload(int $branchId,int $id,array $access,array $context): array
{
    $trip=line_supply_record($branchId,$id,$access,$context);
    return [
        'trip'=>$trip,
        'items'=>line_supply_items($branchId,$id,(int)$trip['vehicle_id'],(int)$trip['status']),
        'financial'=>line_supply_financial($id),
    ];
}

function line_supply_options(int $branchId): array
{
    $stmt=db()->prepare('SELECT id,vehicle_no,vehicle_name FROM vehicles WHERE branch_id=:branch_id AND status=1 ORDER BY vehicle_no');
    $stmt->execute([':branch_id'=>$branchId]);
    $vehicles=$stmt->fetchAll();
    foreach($vehicles as &$row) $row['id']=(int)$row['id'];
    unset($row);

    $stmt=db()->prepare('SELECT id,line_code,line_name FROM `lines` WHERE branch_id=:branch_id AND status=1 ORDER BY line_name');
    $stmt->execute([':branch_id'=>$branchId]);
    $lines=$stmt->fetchAll();
    foreach($lines as &$row) $row['id']=(int)$row['id'];
    unset($row);

    return ['vehicles'=>$vehicles,'lines'=>$lines];
}


function line_supply_return_options(int $branchId,array $access,array $context): array
{
    $scope=aqua_line_supply_scope_sql($access,$context,'ls');
    $where=['ls.branch_id=:branch_id','ls.status IN (2,3,4)'];
    $params=[':branch_id'=>$branchId]+$scope[1];
    if($scope[0]) $where[]=substr(trim($scope[0]),4);

    $stmt=db()->prepare(
        'SELECT ls.id,ls.supply_no,ls.supply_date,ls.status,ls.vehicle_id,ls.line_id,
                v.vehicle_no,v.vehicle_name,l.line_code,l.line_name
         FROM line_supplies ls
         INNER JOIN vehicles v ON v.id=ls.vehicle_id AND v.branch_id=ls.branch_id
         INNER JOIN `lines` l ON l.id=ls.line_id AND l.branch_id=ls.branch_id
         WHERE '.implode(' AND ',$where).'
         ORDER BY CASE WHEN ls.status IN (2,3) THEN 0 ELSE 1 END,ls.supply_date DESC,ls.id DESC'
    );
    $stmt->execute($params);
    $rows=[];
    foreach($stmt->fetchAll() as $row){
        foreach(['id','status','vehicle_id','line_id'] as $key) $row[$key]=(int)$row[$key];
        $row['ref']=encryptReference('line_supply',(int)$row['id']);
        unset($row['id']);
        $rows[]=$row;
    }
    return $rows;
}

function line_supply_parse_loading_items(array $data,int $branchId): array
{
    $raw=aqua_parse_json_rows($data['items_json']??'[]','Truck Loading Items');
    if(!$raw) json_error('Add at least one Product to Truck Loading.',422);
    $seen=[];$result=[];
    foreach($raw as $item){
        $productId=(int)($item['product_id']??0);
        if($productId<1) json_error('Product is required.',422);
        if(isset($seen[$productId])) json_error('The same Product cannot be loaded more than once.',422);
        $seen[$productId]=true;
        $product=aqua_product_bundle($branchId,$productId,null);
        $primary=aqua_decimal($item['primary_qty']??0,'items','Primary Qty',3,false);
        $secondary=aqua_decimal($item['secondary_qty']??0,'items','Secondary Qty',3,false);
        if($product['secondary_unit']===null && $secondary>0) json_error('Selected Product has no Secondary Unit.',422);
        if($primary<=0 && $secondary<=0) json_error('Enter loading quantity.',422);
        $primaryConv=max(1.0,(float)$product['primary_unit']['conversion_qty']);
        $secondaryConv=$product['secondary_unit']?max(1.0,(float)$product['secondary_unit']['conversion_qty']):0.0;
        $result[]=[
            'product'=>$product,
            'product_id'=>$productId,
            'primary_product_unit_id'=>(int)$product['primary_unit']['product_unit_id'],
            'secondary_product_unit_id'=>$product['secondary_unit']?(int)$product['secondary_unit']['product_unit_id']:null,
            'primary_qty'=>$primary,'secondary_qty'=>$secondary,
            'primary_conversion_qty'=>$primaryConv,'secondary_conversion_qty'=>$secondaryConv,
            'loaded_base_qty'=>round($primary*$primaryConv+$secondary*$secondaryConv,3),
        ];
    }
    return $result;
}

function line_supply_save_loading(array $data,array $access,array $context,?int $id=null): array
{
    $branchId=(int)$context['branch_id'];
    $userId=(int)$access['user']['id'];
    $intent=strtolower(trim((string)($data['intent']??'draft')));
    if(!in_array($intent,['draft','load'],true)) json_error('Invalid Truck Loading action.',422);
    aqua_require_action($access,$id===null?ACTION_CREATE:ACTION_UPDATE,'You do not have permission to save Truck Loading.');
    aqua_require_action($access,$intent==='load'?ACTION_POST:ACTION_SAVE_DRAFT,$intent==='load'?'You do not have permission to Post Truck Loading.':'You do not have permission to Save Draft.');

    $date=aqua_date($data['supply_date']??'','supply_date');
    $vehicleId=(int)($data['vehicle_id']??0);
    $lineId=(int)($data['line_id']??0);
    if($vehicleId<1||$lineId<1) json_error('Truck and Line are required.',422);

    $checks=[
        ['SELECT id FROM vehicles WHERE id=:id AND branch_id=:branch_id AND status=1',$vehicleId,'Truck'],
        ['SELECT id FROM `lines` WHERE id=:id AND branch_id=:branch_id AND status=1',$lineId,'Line'],
    ];
    foreach($checks as [$sql,$value,$label]){
        $stmt=db()->prepare($sql);$stmt->execute([':id'=>$value,':branch_id'=>$branchId]);
        if(!$stmt->fetchColumn()) json_error($label.' is invalid or inactive.',422);
    }

    $items=line_supply_parse_loading_items($data,$branchId);
    $remarks=aqua_nullable($data['remarks']??null,255);
    $employeeId=isset($context['employee']['id'])?(int)$context['employee']['id']:null;

    $pdo=db();$pdo->beginTransaction();
    try{
        if($id!==null){
            $old=line_supply_record($branchId,$id,$access,$context,true);
            if((int)$old['status']!==1) json_error('Posted Truck Loading cannot be edited.',409);
        }

        if($intent==='load'){
            $sql='SELECT id FROM line_supplies
                  WHERE branch_id=:branch_id AND vehicle_id=:vehicle_id AND status IN (2,3,4)';
            if($id!==null) $sql.=' AND id<>:id';
            $sql.=' LIMIT 1 FOR UPDATE';
            $stmt=$pdo->prepare($sql);
            $params=[':branch_id'=>$branchId,':vehicle_id'=>$vehicleId];
            if($id!==null) $params[':id']=$id;
            $stmt->execute($params);
            if($stmt->fetchColumn()) json_error('Selected Truck already has an active / returned Line Supply trip. Close it first.',409);
            foreach($items as $item){
                $available=aqua_plant_stock($branchId,(int)$item['product_id']);
                if((float)$item['loaded_base_qty']>$available+0.0005){
                    json_error($item['product']['product_name'].' Plant Stock is insufficient. Available: '.number_format($available,3,'.',''),409);
                }
            }
        }

        if($id===null){
            $no=aqua_generate_no_locked($pdo,$branchId,'line_supplies','supply_no','LS');
            $stmt=$pdo->prepare(
                'INSERT INTO line_supplies
                 (branch_id,supply_no,supply_date,vehicle_id,line_id,line_man_employee_id,remarks,status,created_by,created_at,updated_at)
                 VALUES(:branch_id,:supply_no,:supply_date,:vehicle_id,:line_id,:employee_id,:remarks,1,:created_by,NOW(),NOW())'
            );
            $stmt->execute([
                ':branch_id'=>$branchId,':supply_no'=>$no,':supply_date'=>$date,':vehicle_id'=>$vehicleId,
                ':line_id'=>$lineId,':employee_id'=>$employeeId,':remarks'=>$remarks,':created_by'=>$userId,
            ]);
            $id=(int)$pdo->lastInsertId();
        }else{
            $pdo->prepare(
                'UPDATE line_supplies
                 SET supply_date=:supply_date,vehicle_id=:vehicle_id,line_id=:line_id,
                     line_man_employee_id=:employee_id,remarks=:remarks,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':supply_date'=>$date,':vehicle_id'=>$vehicleId,':line_id'=>$lineId,':employee_id'=>$employeeId,
                ':remarks'=>$remarks,':id'=>$id,':branch_id'=>$branchId,
            ]);
            $pdo->prepare('DELETE FROM line_supply_items WHERE line_supply_id=:id')->execute([':id'=>$id]);
        }

        $insert=$pdo->prepare(
            'INSERT INTO line_supply_items
             (line_supply_id,product_id,primary_product_unit_id,secondary_product_unit_id,primary_qty,secondary_qty,
              primary_conversion_qty,secondary_conversion_qty,loaded_base_qty)
             VALUES(:supply_id,:product_id,:primary_unit,:secondary_unit,:primary_qty,:secondary_qty,:primary_conv,:secondary_conv,:loaded)'
        );
        foreach($items as $item){
            $insert->execute([
                ':supply_id'=>$id,':product_id'=>$item['product_id'],':primary_unit'=>$item['primary_product_unit_id'],
                ':secondary_unit'=>$item['secondary_product_unit_id'],':primary_qty'=>$item['primary_qty'],':secondary_qty'=>$item['secondary_qty'],
                ':primary_conv'=>$item['primary_conversion_qty'],':secondary_conv'=>$item['secondary_conversion_qty'],':loaded'=>$item['loaded_base_qty'],
            ]);
        }

        if($intent==='load'){
            $dateTime=$date.' '.date('H:i:s');
            foreach($items as $item){
                aqua_insert_stock_movement($pdo,$branchId,$dateTime,(int)$item['product_id'],9,$id,0,(float)$item['loaded_base_qty'],$userId);
                aqua_insert_vehicle_stock($pdo,$branchId,$dateTime,$vehicleId,$id,(int)$item['product_id'],1,null,(float)$item['loaded_base_qty'],0,$userId,'Truck Loading');
            }
            $pdo->prepare('UPDATE line_supplies SET status=2,started_at=NOW(),updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        }

        $pdo->commit();
        return line_supply_payload($branchId,$id,$access,$context);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function line_supply_submit_return(array $data,array $access,array $context): array
{
    aqua_require_action($access,ACTION_RETURN,'You do not have permission to submit Truck Return.');
    $branchId=(int)$context['branch_id'];
    $userId=(int)$access['user']['id'];
    $supplyId=aqua_ref_to_id($data['ref']??'','line_supply','Line Supply');
    $rows=aqua_parse_json_rows($data['return_items_json']??'[]','Truck Return Items');
    if(!$rows) json_error('Truck Return Items are required.',422);

    $pdo=db();$pdo->beginTransaction();
    try{
        $trip=line_supply_record($branchId,$supplyId,$access,$context,true);
        if(!in_array((int)$trip['status'],[2,3],true)) json_error('Only Loaded / In Route Truck can be returned.',409);
        $provided=[];
        foreach($rows as $row){
            $productId=(int)($row['product_id']??0);
            if($productId>0) $provided[$productId]=$row;
        }
        $items=line_supply_items($branchId,$supplyId,(int)$trip['vehicle_id'],(int)$trip['status']);
        $dateTime=date('Y-m-d H:i:s');
        $update=$pdo->prepare(
            'UPDATE line_supply_items SET
                returned_base_qty=:returned,shortage_base_qty=:shortage,
                empty_returned_to_plant_qty=:empty_return,empty_shortage_qty=:empty_short,
                damaged_returned_to_plant_qty=:damaged_return,damaged_shortage_qty=:damaged_short,
                return_reason=:reason
             WHERE id=:id AND line_supply_id=:supply_id'
        );

        foreach($items as $item){
            $productId=(int)$item['product_id'];
            if(!isset($provided[$productId])) json_error('Return quantity is missing for '.$item['product_name'].'.',422);
            $input=$provided[$productId];
            $expected=aqua_vehicle_stock($branchId,(int)$trip['vehicle_id'],$supplyId,$productId);
            $actual=aqua_decimal($input['actual_return_qty']??0,'return_items','Actual Return Qty',3,false);
            if($actual>$expected+0.0005) json_error('Actual Return cannot exceed Truck Stock for '.$item['product_name'].'.',422);
            $short=max(0,round($expected-$actual,3));

            $emptyAvailable=(int)$item['container_type']===1?aqua_can_stock($branchId,2,(int)$trip['vehicle_id'],$productId,2,$supplyId):0.0;
            $damagedAvailable=(int)$item['container_type']===1?aqua_can_stock($branchId,2,(int)$trip['vehicle_id'],$productId,3,$supplyId):0.0;
            $emptyActual=aqua_decimal($input['actual_empty_return_qty']??0,'return_items','Actual Empty Return Qty',3,false);
            $damagedActual=aqua_decimal($input['actual_damaged_return_qty']??0,'return_items','Actual Damaged Return Qty',3,false);
            if($emptyActual>$emptyAvailable+0.0005) json_error('Actual Empty Return cannot exceed collected Empty Cans for '.$item['product_name'].'.',422);
            if($damagedActual>$damagedAvailable+0.0005) json_error('Actual Damaged Return cannot exceed collected Damaged Cans for '.$item['product_name'].'.',422);
            $emptyShort=max(0,round($emptyAvailable-$emptyActual,3));
            $damagedShort=max(0,round($damagedAvailable-$damagedActual,3));
            $reason=aqua_nullable($input['reason']??null,255);
            if(($short>0.0005||$emptyShort>0.0005||$damagedShort>0.0005)&&$reason===null){
                json_error('Reason is required for Truck Return shortage / difference.',422);
            }

            if($actual>0){
                aqua_insert_vehicle_stock($pdo,$branchId,$dateTime,(int)$trip['vehicle_id'],$supplyId,$productId,3,null,0,$actual,$userId,'Truck Return To Plant');
                aqua_insert_stock_movement($pdo,$branchId,$dateTime,$productId,10,$supplyId,$actual,0,$userId);
            }
            if($short>0){
                aqua_insert_vehicle_stock($pdo,$branchId,$dateTime,(int)$trip['vehicle_id'],$supplyId,$productId,4,null,0,$short,$userId,'Truck Stock Shortage: '.($reason??''));
            }

            if($emptyActual>0){
                aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,2,(int)$trip['vehicle_id'],$supplyId,null,2,3,0,$emptyActual,$userId,'Empty Can Return To Plant');
                aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,1,null,$supplyId,null,2,2,$emptyActual,0,$userId,'Empty Can Received From Truck');
            }
            if($emptyShort>0){
                aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,2,(int)$trip['vehicle_id'],$supplyId,null,2,4,0,$emptyShort,$userId,'Empty Can Shortage: '.($reason??''));
            }

            if($damagedActual>0){
                aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,2,(int)$trip['vehicle_id'],$supplyId,null,3,3,0,$damagedActual,$userId,'Damaged Can Return To Plant');
                aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,1,null,$supplyId,null,3,2,$damagedActual,0,$userId,'Damaged Can Received From Truck');
            }
            if($damagedShort>0){
                aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,2,(int)$trip['vehicle_id'],$supplyId,null,3,4,0,$damagedShort,$userId,'Damaged Can Shortage: '.($reason??''));
            }

            $update->execute([
                ':returned'=>$actual,':shortage'=>$short,':empty_return'=>$emptyActual,':empty_short'=>$emptyShort,
                ':damaged_return'=>$damagedActual,':damaged_short'=>$damagedShort,':reason'=>$reason,
                ':id'=>(int)$item['id'],':supply_id'=>$supplyId,
            ]);
        }

        $remarks=aqua_nullable($data['return_remarks']??null,255);
        $pdo->prepare(
            'UPDATE line_supplies
             SET status=4,returned_at=NOW(),returned_by=:user_id,return_remarks=:remarks,updated_at=NOW()
             WHERE id=:id'
        )->execute([':user_id'=>$userId,':remarks'=>$remarks,':id'=>$supplyId]);
        $pdo->commit();
        return line_supply_payload($branchId,$supplyId,$access,$context);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function line_supply_close(array $data,array $access,array $context): array
{
    aqua_require_action($access,ACTION_FINALIZE,'You do not have permission to Close Truck Trip.');
    $branchId=(int)$context['branch_id'];
    $userId=(int)$access['user']['id'];
    $supplyId=aqua_ref_to_id($data['ref']??'','line_supply','Line Supply');
    $pdo=db();$pdo->beginTransaction();
    try{
        $trip=line_supply_record($branchId,$supplyId,$access,$context,true);
        if((int)$trip['status']!==4) json_error('Truck Return must be submitted before Closing.',409);
        $pdo->prepare('UPDATE line_supplies SET status=5,closed_at=NOW(),closed_by=:user_id,updated_at=NOW() WHERE id=:id')
            ->execute([':user_id'=>$userId,':id'=>$supplyId]);
        $pdo->commit();
        return line_supply_payload($branchId,$supplyId,$access,$context);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

$method=request_method();

if($method==='GET' && isset($_GET['return_options'])){
    $access=require_permission('line-supply-list.php',ACTION_VIEW);
    $context=aqua_sales_context($access['user']);
    $branchId=(int)$context['branch_id'];
    $rows=line_supply_return_options($branchId,$access,$context);
    json_success('Truck Return trips loaded.',[
        'trips'=>$rows,'allowed_actions'=>$access['actions'],'context'=>$context,
    ]);
}

if($method==='GET' && isset($_GET['options'])){
    $access=require_permission('line-supply-list.php',ACTION_VIEW);
    $context=aqua_sales_context($access['user']);
    $branchId=(int)$context['branch_id'];
    $options=line_supply_options($branchId);
    $products=aqua_sales_products($branchId,null);
    foreach($products as &$product) $product['plant_stock']=aqua_plant_stock($branchId,(int)$product['id']);
    unset($product);
    json_success('Line Supply options loaded.',[
        'vehicles'=>$options['vehicles'],'lines'=>$options['lines'],'products'=>$products,
        'allowed_actions'=>$access['actions'],'can_line_sale'=>line_supply_can_sale($access['user']),'context'=>$context,
    ]);
}

if($method==='GET' && isset($_GET['datatable'])){
    $access=require_permission('line-supply-list.php',ACTION_VIEW);
    $context=aqua_sales_context($access['user']);
    $branchId=(int)$context['branch_id'];
    $scope=aqua_line_supply_scope_sql($access,$context,'ls');
    $draw=(int)($_GET['draw']??1);$start=max(0,(int)($_GET['start']??0));$length=max(1,min(100,(int)($_GET['length']??10)));
    $search=trim((string)($_GET['search']['value']??''));$status=isset($_GET['status'])?(int)$_GET['status']:0;
    $where=['ls.branch_id=:branch_id'];$params=[':branch_id'=>$branchId]+$scope[1];
    if($scope[0])$where[]=substr(trim($scope[0]),4);
    if(in_array($status,[1,2,3,4,5,6],true)){$where[]='ls.status=:status';$params[':status']=$status;}
    if($search!==''){$where[]='(ls.supply_no LIKE :q OR v.vehicle_no LIKE :q OR l.line_name LIKE :q)';$params[':q']='%'.$search.'%';}
    $from=' FROM line_supplies ls INNER JOIN vehicles v ON v.id=ls.vehicle_id INNER JOIN `lines` l ON l.id=ls.line_id ';
    $cnt=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.implode(' AND ',$where));$cnt->execute($params);$filtered=(int)$cnt->fetchColumn();
    $tot=db()->prepare('SELECT COUNT(*)'.$from.' WHERE ls.branch_id=:branch_id'.($scope[0]?:''));$tot->execute([':branch_id'=>$branchId]+$scope[1]);$total=(int)$tot->fetchColumn();
    $sql='SELECT ls.id,ls.supply_no,ls.supply_date,ls.status,v.vehicle_no,l.line_name,
                 COALESCE((SELECT SUM(loaded_base_qty) FROM line_supply_items x WHERE x.line_supply_id=ls.id),0) AS loaded_qty,
                 COALESCE((SELECT SUM(si.base_qty) FROM sales s INNER JOIN sales_items si ON si.sale_id=s.id WHERE s.line_supply_id=ls.id AND s.document_type=2 AND s.sale_type=3 AND s.status=2),0) AS sold_qty'
         .$from.' WHERE '.implode(' AND ',$where).' ORDER BY ls.supply_date DESC,ls.id DESC LIMIT :start,:length';
    $stmt=db()->prepare($sql);
    foreach($params as $key=>$value)$stmt->bindValue($key,$value,in_array($key,[':branch_id',':status'],true)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stmt->bindValue(':start',$start,PDO::PARAM_INT);$stmt->bindValue(':length',$length,PDO::PARAM_INT);$stmt->execute();
    $rows=[];
    foreach($stmt->fetchAll() as $row){
        $id=(int)$row['id'];$row['status']=(int)$row['status'];$row['loaded_qty']=(float)$row['loaded_qty'];$row['sold_qty']=(float)$row['sold_qty'];
        $row['ref']=encryptReference('line_supply',$id);$row['edit_url']='line-supply-form.php?ref='.rawurlencode($row['ref']);$row['return_url']='line-return-form.php?ref='.rawurlencode($row['ref']);$row['sales_url']='sales-form.php';
        unset($row['id']);$rows[]=$row;
    }
    json_success('Line Supply list loaded.',[
        'allowed_actions'=>$access['actions'],'can_line_sale'=>line_supply_can_sale($access['user']),'context'=>$context,
        'datatable'=>['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows],
    ]);
}

if($method==='GET' && isset($_GET['ref'])){
    $access=require_permission('line-supply-list.php',ACTION_VIEW);
    $context=aqua_sales_context($access['user']);
    $id=aqua_ref_to_id($_GET['ref'],'line_supply','Line Supply');
    $payload=line_supply_payload((int)$context['branch_id'],$id,$access,$context);
    $payload['allowed_actions']=$access['actions'];$payload['can_line_sale']=line_supply_can_sale($access['user']);$payload['context']=$context;
    json_success('Line Supply loaded.',$payload);
}

if($method==='POST'){
    $data=request_data();
    $intent=strtolower(trim((string)($data['intent']??'draft')));
    $access=require_permission('line-supply-list.php',ACTION_VIEW);
    $context=aqua_sales_context($access['user']);
    if(in_array($intent,['draft','load'],true)){
        $id=null;if(!empty($data['ref']))$id=aqua_ref_to_id($data['ref'],'line_supply','Line Supply');
        $access=require_permission('line-supply-list.php',$id===null?ACTION_CREATE:ACTION_UPDATE);
        $context=aqua_sales_context($access['user']);
        $payload=line_supply_save_loading($data,$access,$context,$id);
        $message=$intent==='load'?'Truck Loading posted.':'Truck Loading draft saved.';
    }elseif($intent==='return'){
        $payload=line_supply_submit_return($data,$access,$context);$message='Truck Return submitted.';
    }elseif($intent==='close'){
        $payload=line_supply_close($data,$access,$context);$message='Truck Trip closed.';
    }else{
        json_error('Invalid Line Supply action.',422);
    }
    $payload['allowed_actions']=$access['actions'];$payload['can_line_sale']=line_supply_can_sale($access['user']);
    json_success($message,$payload);
}

json_error('Unsupported Line Supply API request.',405);
