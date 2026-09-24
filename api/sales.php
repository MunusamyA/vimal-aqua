<?php
declare(strict_types=1);
require_once __DIR__ . '/_sales_common.php';

const AQUA_SALE_MODE_QUOTATION = 1;
const AQUA_SALE_MODE_ORDER = 2;
const AQUA_SALE_MODE_DIRECT = 3;
const AQUA_SALE_MODE_LINE = 4;

function sales_mode_from_row(array $sale): int
{
    if ((int)$sale['document_type'] === 1) return AQUA_SALE_MODE_QUOTATION;
    if ((int)$sale['document_type'] === 3) return AQUA_SALE_MODE_ORDER;
    return (int)$sale['sale_type'] === 3 ? AQUA_SALE_MODE_LINE : AQUA_SALE_MODE_DIRECT;
}

function sales_document_type_for_mode(int $mode): int
{
    if ($mode === AQUA_SALE_MODE_QUOTATION) return 1;
    if ($mode === AQUA_SALE_MODE_ORDER) return 3;
    return 2;
}

function sales_sale_type_for_mode(int $mode): int
{
    if ($mode === AQUA_SALE_MODE_ORDER) return 2;
    if ($mode === AQUA_SALE_MODE_LINE) return 3;
    return 1;
}

function sales_prefix_for_mode(int $mode, int $taxMode): string
{
    if ($mode === AQUA_SALE_MODE_QUOTATION) return 'QTN';
    if ($mode === AQUA_SALE_MODE_ORDER) return 'ORD';
    return $taxMode === 0 ? 'NOG' : 'INV';
}

function sales_resolve_line_trip(int $branchId, int $vehicleId, int $requestedLineId, ?array $customer=null, bool $lock=false): array
{
    if ($vehicleId < 1) {
        json_error('Select Truck.', 422, ['vehicle_id'=>'Select Truck.']);
    }

    $customerLineId = (int)($customer['line_id'] ?? 0);
    if ($requestedLineId > 0) {
        if ($customerLineId > 0 && $customerLineId !== $requestedLineId) {
            json_error('Selected Customer does not belong to the selected Line.', 422, [
                'customer_id'=>'Select a Customer from the selected Line or clear Line for direct Customer selection.'
            ]);
        }
        return [
            'trip'=>aqua_active_line_supply($branchId, $vehicleId, $requestedLineId, $lock),
            'line_id'=>$requestedLineId,
            'line_was_selected'=>1,
        ];
    }

    /* Rare direct-Customer flow: Line is blank. Resolve the single active Truck Loading by Truck. */
    $sql = 'SELECT line_id FROM line_supplies
'
         . 'WHERE branch_id=:branch_id AND vehicle_id=:vehicle_id AND status IN (2,3)
'
         . 'ORDER BY id DESC';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id'=>$branchId, ':vehicle_id'=>$vehicleId]);
    $lineIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));

    if (!$lineIds) {
        json_error('No active Truck Loading was found for the selected Truck.', 409);
    }
    if (count($lineIds) > 1) {
        json_error('This Truck has more than one active Line. Select Line before billing.', 409, [
            'line_id'=>'Select Line for this Truck.'
        ]);
    }

    $resolvedLineId = (int)$lineIds[0];
    return [
        'trip'=>aqua_active_line_supply($branchId, $vehicleId, $resolvedLineId, $lock),
        'line_id'=>$resolvedLineId,
        'line_was_selected'=>0,
    ];
}

function sales_preview_no(int $branchId, int $mode, int $taxMode): string
{
    $prefix = sales_prefix_for_mode($mode, $taxMode);
    $startAt = strlen($prefix) + 1;
    $stmt = db()->prepare(
        'SELECT sale_no FROM sales
'
        . 'WHERE branch_id=:branch_id AND sale_no REGEXP :pattern
'
        . 'ORDER BY CAST(SUBSTRING(sale_no,' . (int)$startAt . ') AS UNSIGNED) DESC LIMIT 1'
    );
    $stmt->execute([
        ':branch_id'=>$branchId,
        ':pattern'=>'^' . preg_quote($prefix, '/') . '[0-9]+$',
    ]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^' . preg_quote($prefix, '/') . '([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function sales_allocated_paid(PDO $pdo, int $saleId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(cpa.amount),0)
'
        . 'FROM customer_payment_allocations cpa
'
        . 'INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
'
        . 'WHERE cpa.sale_id=:sale_id'
    );
    $stmt->execute([':sale_id'=>$saleId]);
    return round((float)$stmt->fetchColumn(), 2);
}


function sales_payment_edit_state(PDO $pdo, int $saleId): array
{
    $stmt = $pdo->prepare(
        'SELECT cp.id AS payment_id,cp.payment_no,cp.payment_date,cp.customer_id,cp.amount AS payment_amount,
                cpa.amount AS allocation_amount,
                (SELECT COUNT(*) FROM customer_payment_allocations ca WHERE ca.customer_payment_id=cp.id) AS allocation_count
         FROM customer_payment_allocations cpa
         INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
         WHERE cpa.sale_id=:sale_id
         ORDER BY cp.id'
    );
    $stmt->execute([':sale_id'=>$saleId]);
    $editableIds=[];$fixedPaid=0.0;
    foreach($stmt->fetchAll() as $row){
        $paymentId=(int)$row['payment_id'];
        $allocation=(float)$row['allocation_amount'];
        $paymentAmount=(float)$row['payment_amount'];
        $exclusive=((int)$row['allocation_count']===1) && abs($allocation-$paymentAmount)<=0.009;
        if($exclusive){$editableIds[$paymentId]=$paymentId;}
        else{$fixedPaid=round($fixedPaid+$allocation,2);}
    }

    $byMode=[];
    if($editableIds){
        $placeholders=implode(',',array_fill(0,count($editableIds),'?'));
        $detail=$pdo->prepare(
            'SELECT customer_payment_id,account_id,payment_mode,amount,reference_no,detail_date
             FROM customer_payment_details
             WHERE customer_payment_id IN ('.$placeholders.')
             ORDER BY id'
        );
        $detail->execute(array_values($editableIds));
        foreach($detail->fetchAll() as $row){
            $mode=(int)$row['payment_mode'];
            if($mode<1||$mode>4) continue;
            if(!isset($byMode[$mode])){
                $byMode[$mode]=[
                    'payment_mode'=>$mode,'account_id'=>(int)$row['account_id'],'amount'=>0.0,
                    'reference_no'=>$row['reference_no'],'detail_date'=>$row['detail_date'],
                ];
            }
            $byMode[$mode]['amount']=round((float)$byMode[$mode]['amount']+(float)$row['amount'],2);
            $byMode[$mode]['account_id']=(int)$row['account_id'];
            if(trim((string)($row['reference_no']??''))!=='') $byMode[$mode]['reference_no']=(string)$row['reference_no'];
            if(trim((string)($row['detail_date']??''))!=='') $byMode[$mode]['detail_date']=(string)$row['detail_date'];
        }
    }
    ksort($byMode);
    return ['rows'=>array_values($byMode),'fixed_paid'=>round($fixedPaid,2),'payment_ids'=>array_values($editableIds)];
}

function sales_normalize_payment_rows(array $rows): array
{
    $map=[];
    foreach($rows as $row){
        if(!is_array($row)) continue;
        $mode=(int)($row['payment_mode']??0);
        $amount=round((float)($row['amount']??0),2);
        if($mode<1||$mode>4||$amount<=0.009) continue;
        if(!isset($map[$mode])){
            $map[$mode]=[
                'payment_mode'=>$mode,
                'account_id'=>(int)($row['account_id']??0),
                'amount'=>0.0,
                'reference_no'=>trim((string)($row['reference_no']??'')),
                'detail_date'=>trim((string)($row['detail_date']??'')),
            ];
        }
        $map[$mode]['amount']=round($map[$mode]['amount']+$amount,2);
        $map[$mode]['account_id']=(int)($row['account_id']??0);
        $ref=trim((string)($row['reference_no']??''));
        $date=trim((string)($row['detail_date']??''));
        if($ref!=='')$map[$mode]['reference_no']=$ref;
        if($date!=='')$map[$mode]['detail_date']=$date;
    }
    ksort($map);
    return array_values($map);
}

function sales_payment_rows_equal(array $a, array $b): bool
{
    return json_encode(sales_normalize_payment_rows($a),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        ===json_encode(sales_normalize_payment_rows($b),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function sales_reverse_editable_payments(PDO $pdo, int $branchId, int $saleId, array $paymentIds, string $date, int $userId): void
{
    if(!$paymentIds) return;
    $lock=$pdo->prepare('SELECT id,payment_no,status,amount FROM customer_payments WHERE id=:id AND branch_id=:branch_id FOR UPDATE');
    $alloc=$pdo->prepare('SELECT sale_id,amount FROM customer_payment_allocations WHERE customer_payment_id=:payment_id ORDER BY id FOR UPDATE');
    $details=$pdo->prepare('SELECT account_id,amount FROM customer_payment_details WHERE customer_payment_id=:payment_id ORDER BY id');
    $reverse=$pdo->prepare(
        'INSERT INTO account_transactions(branch_id,transaction_date,account_id,transaction_type,source_type,source_id,amount,remarks,created_by,created_at)
         VALUES(:branch_id,:transaction_date,:account_id,2,5,:source_id,:amount,:remarks,:created_by,NOW())'
    );
    $cancel=$pdo->prepare('UPDATE customer_payments SET status=2 WHERE id=:id AND branch_id=:branch_id AND status=1');

    foreach($paymentIds as $paymentId){
        $paymentId=(int)$paymentId;
        if($paymentId<1) continue;
        $lock->execute([':id'=>$paymentId,':branch_id'=>$branchId]);
        $payment=$lock->fetch();
        if(!$payment || (int)$payment['status']!==1) continue;

        $alloc->execute([':payment_id'=>$paymentId]);
        $allocRows=$alloc->fetchAll();
        if(count($allocRows)!==1 || (int)$allocRows[0]['sale_id']!==$saleId || abs((float)$allocRows[0]['amount']-(float)$payment['amount'])>0.009){
            json_error('This payment is shared with another document and cannot be replaced automatically.',409);
        }

        $details->execute([':payment_id'=>$paymentId]);
        foreach($details->fetchAll() as $detail){
            $amount=round((float)$detail['amount'],2);
            if($amount<=0.009) continue;
            $reverse->execute([
                ':branch_id'=>$branchId,':transaction_date'=>$date,':account_id'=>(int)$detail['account_id'],
                ':source_id'=>$paymentId,':amount'=>$amount,
                ':remarks'=>'Payment edit reversal '.(string)$payment['payment_no'],':created_by'=>$userId,
            ]);
        }
        $cancel->execute([':id'=>$paymentId,':branch_id'=>$branchId]);
    }
}

function sales_transfer_order_advance(PDO $pdo, int $orderId, int $invoiceId, float $amount): float
{
    $remaining = round(max(0, $amount), 2);
    if ($remaining <= 0.009) return 0.0;

    $stmt = $pdo->prepare(
        'SELECT cpa.id,cpa.customer_payment_id,cpa.amount
'
        . 'FROM customer_payment_allocations cpa
'
        . 'INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
'
        . 'WHERE cpa.sale_id=:sale_id AND cpa.amount>0
'
        . 'ORDER BY cpa.id FOR UPDATE'
    );
    $stmt->execute([':sale_id'=>$orderId]);
    $moved = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        if ($remaining <= 0.009) break;
        $available = round((float)$row['amount'], 2);
        if ($available <= 0.009) continue;
        $take = min($available, $remaining);
        if ($take + 0.009 >= $available) {
            $pdo->prepare('UPDATE customer_payment_allocations SET sale_id=:invoice_id WHERE id=:id')
                ->execute([':invoice_id'=>$invoiceId, ':id'=>(int)$row['id']]);
        } else {
            $pdo->prepare('UPDATE customer_payment_allocations SET amount=:amount WHERE id=:id')
                ->execute([':amount'=>round($available-$take,2), ':id'=>(int)$row['id']]);
            $pdo->prepare(
                'INSERT INTO customer_payment_allocations(customer_payment_id,sale_id,amount) VALUES(:payment_id,:sale_id,:amount)'
            )->execute([
                ':payment_id'=>(int)$row['customer_payment_id'],
                ':sale_id'=>$invoiceId,
                ':amount'=>round($take,2),
            ]);
        }
        $moved = round($moved + $take, 2);
        $remaining = round($remaining - $take, 2);
    }
    return $moved;
}

function sales_refresh_payment_header(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare('SELECT grand_total,document_type FROM sales WHERE id=:id LIMIT 1');
    $stmt->execute([':id'=>$saleId]);
    $sale = $stmt->fetch();
    if (!$sale) return;
    $paid = sales_allocated_paid($pdo, $saleId);
    $grand = (float)$sale['grand_total'];
    $balance = max(0, round($grand-$paid,2));
    $status = aqua_payment_status($grand, $paid);
    $pdo->prepare('UPDATE sales SET paid_amount=:paid,balance_amount=:balance,payment_status=:status,updated_at=NOW() WHERE id=:id')
        ->execute([':paid'=>$paid, ':balance'=>$balance, ':status'=>$status, ':id'=>$saleId]);
}

function sales_line_mode_access(array $user): bool
{
    try {
        if (function_exists('menu_by_path') && function_exists('effective_actions_for_menu')) {
            $menu = menu_by_path('line-supply-list.php');
            if (!$menu) return false;
            $actions = effective_actions_for_menu($user, $menu);
            return aqua_has_action((array)$actions, ACTION_VIEW);
        }

        $roleId = (int)($user['role_id'] ?? 0);
        if ($roleId < 1) return false;
        $stmt = db()->prepare(
            'SELECT rp.action_ids
             FROM role_permissions rp
             INNER JOIN menus m ON m.id=rp.menu_id AND m.status=1
             WHERE rp.role_id=:role_id AND rp.status=1 AND m.menu_path=:path
             LIMIT 1'
        );
        $stmt->execute([':role_id'=>$roleId, ':path'=>'line-supply-list.php']);
        $ids = trim((string)($stmt->fetchColumn() ?: ''));
        if ($ids === '') return false;
        return in_array(1, array_map('intval', array_filter(explode(',', $ids), 'strlen')), true);
    } catch (Throwable $e) {
        return false;
    }
}

function sales_allowed_modes(array $access): array
{
    $actions = (array)($access['actions'] ?? []);
    $user = (array)($access['user'] ?? []);
    $modes = [];

    if (aqua_has_action($actions, ACTION_SAVE_DRAFT)) {
        $modes[] = AQUA_SALE_MODE_QUOTATION;
    }

    /* Update is deliberately used as the commercial-edit permission for
       Customer Orders / Direct Invoice. A Line Man can therefore be given
       Create + Post + Receive Payment without Update and will only get the
       operational Line Supply Sale mode. */
    if (aqua_has_action($actions, ACTION_UPDATE) && aqua_has_action($actions, ACTION_POST)) {
        $modes[] = AQUA_SALE_MODE_ORDER;
        $modes[] = AQUA_SALE_MODE_DIRECT;
    }

    if (aqua_has_action($actions, ACTION_POST) && sales_line_mode_access($user)) {
        $modes[] = AQUA_SALE_MODE_LINE;
    }

    return array_values(array_unique($modes));
}

function sales_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT s.*,c.customer_code,c.customer_name,l.line_name,l.line_code,v.vehicle_no,v.vehicle_name,
                ls.supply_no
         FROM sales s
         INNER JOIN customers c ON c.id=s.customer_id
         LEFT JOIN `lines` l ON l.id=s.line_id
         LEFT JOIN vehicles v ON v.id=s.vehicle_id
         LEFT JOIN line_supplies ls ON ls.id=s.line_supply_id
         WHERE s.id=:id AND s.branch_id=:branch_id AND s.document_type IN (1,2,3)
         LIMIT 1'
    );
    $stmt->execute([':id'=>$id, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Sales document was not found.', 404);

    foreach (['id','branch_id','customer_id','line_id','vehicle_id','line_supply_id','source_order_id','sale_type','document_type','tax_mode','overall_discount_type','round_off_enabled','payment_status','delivery_status','status'] as $key) {
        if (array_key_exists($key, $row)) $row[$key] = $row[$key] === null ? null : (int)$row[$key];
    }
    foreach (['subtotal','item_discount_total','overall_discount_value','overall_discount_amount','tax_amount','other_charges','round_off','grand_total','paid_amount','balance_amount'] as $key) {
        $row[$key] = (float)$row[$key];
    }
    $row['mode'] = sales_mode_from_row($row);
    $row['ref'] = encryptReference('sale', (int)$row['id']);
    $row['source_order_ref'] = $row['source_order_id'] ? encryptReference('sale', (int)$row['source_order_id']) : null;
    $row['edit_url'] = 'sales-form.php?ref=' . rawurlencode($row['ref']);
    return $row;
}

function sales_payload(int $branchId, int $id): array
{
    $paymentState=sales_payment_edit_state(db(),$id);
    return [
        'sale'=>sales_record($branchId, $id),
        'items'=>aqua_sale_items_payload($id),
        'payments'=>$paymentState['rows'],
        'fixed_paid_amount'=>$paymentState['fixed_paid'],
    ];
}

function sales_master_options(int $branchId, ?int $customerId=null): array
{
    $products = aqua_sales_products($branchId, $customerId);
    foreach ($products as &$product) {
        $product['plant_stock'] = aqua_plant_stock($branchId, (int)$product['id']);
        $product['truck_stock'] = 0.0;
    }
    unset($product);

    $vehiclesStmt = db()->prepare(
        'SELECT id,vehicle_no,vehicle_name FROM vehicles
         WHERE branch_id=:branch_id AND status=1 ORDER BY vehicle_no'
    );
    $vehiclesStmt->execute([':branch_id'=>$branchId]);
    $vehicles = $vehiclesStmt->fetchAll();
    foreach ($vehicles as &$vehicle) $vehicle['id'] = (int)$vehicle['id'];
    unset($vehicle);

    $linesStmt = db()->prepare(
        'SELECT id,line_code,line_name FROM `lines`
         WHERE branch_id=:branch_id AND status=1 ORDER BY line_name'
    );
    $linesStmt->execute([':branch_id'=>$branchId]);
    $lines = $linesStmt->fetchAll();
    foreach ($lines as &$line) $line['id'] = (int)$line['id'];
    unset($line);

    return [
        'customers'=>aqua_customers($branchId),
        'accounts'=>aqua_accounts($branchId),
        'products'=>$products,
        'vehicles'=>$vehicles,
        'lines'=>$lines,
    ];
}

function sales_order_lock(PDO $pdo, int $branchId, int $customerId, int $orderId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM sales
         WHERE id=:id AND branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=3 AND status=2 AND delivery_status IN (1,2)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([':id'=>$orderId, ':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    $order = $stmt->fetch();
    if (!$order) json_error('Selected Customer Order is no longer pending.', 409);

    $items = $pdo->prepare(
        'SELECT id,product_id,ordered_base_qty,delivered_base_qty,delivery_status
         FROM sales_items WHERE sale_id=:sale_id FOR UPDATE'
    );
    $items->execute([':sale_id'=>$orderId]);
    $map = [];
    foreach ($items->fetchAll() as $item) {
        $map[(int)$item['id']] = [
            'id'=>(int)$item['id'],
            'product_id'=>(int)$item['product_id'],
            'ordered'=>(float)$item['ordered_base_qty'],
            'delivered'=>(float)$item['delivered_base_qty'],
        ];
    }
    return ['header'=>$order, 'items'=>$map];
}

function sales_validate_and_apply_order_delivery(PDO $pdo, ?array $orderLock, array $items): void
{
    if ($orderLock === null) {
        foreach ($items as $item) {
            if (!empty($item['source_order_item_id'])) {
                json_error('Remove the Order link or select the source Customer Order.', 422);
            }
        }
        return;
    }

    $used = false;
    foreach ($items as $item) {
        $sourceId = (int)($item['source_order_item_id'] ?? 0);
        if ($sourceId < 1) continue;
        $used = true;
        if (!isset($orderLock['items'][$sourceId])) json_error('Selected Order Item is invalid.', 409);
        $source = $orderLock['items'][$sourceId];
        if ((int)$source['product_id'] !== (int)$item['product_id']) json_error('Order Product does not match the delivered Product.', 409);
        $pending = max(0, round((float)$source['ordered'] - (float)$source['delivered'], 3));
        if ((float)$item['base_qty'] > $pending + 0.0005) {
            json_error($item['product']['product_name'] . ' delivery exceeds Order pending quantity of ' . number_format($pending, 3, '.', '') . '.', 409);
        }
    }
    if (!$used) json_error('Selected Customer Order has no delivered item in this Invoice.', 422);

    $update = $pdo->prepare(
        'UPDATE sales_items
         SET delivered_base_qty=:delivered,
             delivery_status=:delivery_status
         WHERE id=:id'
    );
    foreach ($items as $item) {
        $sourceId = (int)($item['source_order_item_id'] ?? 0);
        if ($sourceId < 1) continue;
        $source = $orderLock['items'][$sourceId];
        $delivered = round((float)$source['delivered'] + (float)$item['base_qty'], 3);
        $status = $delivered + 0.0005 >= (float)$source['ordered'] ? 3 : 2;
        $update->execute([':delivered'=>$delivered, ':delivery_status'=>$status, ':id'=>$sourceId]);
    }
    aqua_refresh_order_status($pdo, (int)$orderLock['header']['id']);
}

function sales_invoice_effect_items(PDO $pdo, int $saleId, bool $lock=false): array
{
    $sql = 'SELECT si.id,si.product_id,si.base_qty,si.empty_return_qty,si.damaged_return_qty,si.lost_settled_qty,
                   si.source_order_item_id,p.container_type
            FROM sales_items si
            INNER JOIN products p ON p.id=si.product_id
            WHERE si.sale_id=:sale_id
            ORDER BY si.id';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sale_id'=>$saleId]);
    return $stmt->fetchAll();
}

function sales_reverse_posted_invoice_effects(PDO $pdo, int $branchId, array $sale, int $userId): void
{
    if ((int)$sale['document_type'] !== 2 || (int)$sale['status'] !== 2) return;

    $saleId = (int)$sale['id'];
    $mode = sales_mode_from_row($sale);
    $vehicleId = (int)($sale['vehicle_id'] ?? 0);
    $supplyId = (int)($sale['line_supply_id'] ?? 0);
    $dateTime = date('Y-m-d H:i:s');
    $items = sales_invoice_effect_items($pdo, $saleId, true);

    if ($mode === AQUA_SALE_MODE_LINE && ($vehicleId < 1 || $supplyId < 1)) {
        json_error('Posted Line Sale is missing its Truck Loading reference and cannot be edited safely.', 409);
    }

    $canMove = $pdo->prepare(
        'INSERT INTO can_movements
         (branch_id,movement_date,customer_id,product_id,sale_id,line_run_id,movement_type,qty,remarks,created_by,created_at)
         VALUES(:branch_id,:movement_date,:customer_id,:product_id,:sale_id,NULL,:movement_type,:qty,:remarks,:created_by,NOW())'
    );
    $orderItem = $pdo->prepare(
        'SELECT id,sale_id,ordered_base_qty,delivered_base_qty
         FROM sales_items WHERE id=:id LIMIT 1 FOR UPDATE'
    );
    $orderUpdate = $pdo->prepare(
        'UPDATE sales_items SET delivered_base_qty=:delivered,delivery_status=:delivery_status WHERE id=:id'
    );
    $affectedOrders = [];

    foreach ($items as $item) {
        $productId = (int)$item['product_id'];
        $baseQty = round((float)$item['base_qty'], 3);
        if ($baseQty > 0.0005) {
            if ($mode === AQUA_SALE_MODE_LINE) {
                aqua_insert_vehicle_stock(
                    $pdo, $branchId, $dateTime, $vehicleId, $supplyId, $productId,
                    5, $saleId, $baseQty, 0, $userId, 'Invoice Edit Reversal'
                );
            } else {
                aqua_insert_stock_movement($pdo, $branchId, $dateTime, $productId, 5, $saleId, $baseQty, 0, $userId);
            }
        }

        if ((int)$item['container_type'] === 1) {
            $reverseCustomerMoves = [
                [6, $baseQty, 'Invoice Edit Reversal - Filled Delivered'],
                [5, round((float)$item['empty_return_qty'],3), 'Invoice Edit Reversal - Empty Returned'],
                [5, round((float)$item['damaged_return_qty'],3), 'Invoice Edit Reversal - Damaged Returned'],
                [5, round((float)$item['lost_settled_qty'],3), 'Invoice Edit Reversal - Lost Settled'],
            ];
            foreach ($reverseCustomerMoves as [$type,$qty,$remarks]) {
                if ($qty <= 0.0005) continue;
                $canMove->execute([
                    ':branch_id'=>$branchId, ':movement_date'=>$dateTime,
                    ':customer_id'=>(int)$sale['customer_id'], ':product_id'=>$productId,
                    ':sale_id'=>$saleId, ':movement_type'=>$type, ':qty'=>$qty,
                    ':remarks'=>$remarks, ':created_by'=>$userId,
                ]);
            }

            $locationType = $mode === AQUA_SALE_MODE_LINE ? 2 : 1;
            $emptyQty = round((float)$item['empty_return_qty'],3);
            if ($emptyQty > 0.0005) {
                aqua_insert_can_stock(
                    $pdo,$branchId,$dateTime,$productId,$locationType,
                    $mode===AQUA_SALE_MODE_LINE?$vehicleId:null,
                    $mode===AQUA_SALE_MODE_LINE?$supplyId:null,
                    $saleId,2,6,0,$emptyQty,$userId,'Invoice Edit Reversal - Empty Return'
                );
            }
            $damagedQty = round((float)$item['damaged_return_qty'],3);
            if ($damagedQty > 0.0005) {
                aqua_insert_can_stock(
                    $pdo,$branchId,$dateTime,$productId,$locationType,
                    $mode===AQUA_SALE_MODE_LINE?$vehicleId:null,
                    $mode===AQUA_SALE_MODE_LINE?$supplyId:null,
                    $saleId,3,6,0,$damagedQty,$userId,'Invoice Edit Reversal - Damaged Return'
                );
            }
        }

        $sourceOrderItemId = (int)($item['source_order_item_id'] ?? 0);
        if ($sourceOrderItemId > 0 && $baseQty > 0.0005) {
            $orderItem->execute([':id'=>$sourceOrderItemId]);
            $source = $orderItem->fetch();
            if ($source) {
                $ordered = (float)$source['ordered_base_qty'];
                $delivered = max(0, round((float)$source['delivered_base_qty'] - $baseQty, 3));
                $deliveryStatus = $delivered <= 0.0005 ? 1 : ($delivered + 0.0005 >= $ordered ? 3 : 2);
                $orderUpdate->execute([
                    ':delivered'=>$delivered, ':delivery_status'=>$deliveryStatus,
                    ':id'=>$sourceOrderItemId,
                ]);
                $affectedOrders[(int)$source['sale_id']] = true;
            }
        }
    }

    foreach (array_keys($affectedOrders) as $orderId) {
        aqua_refresh_order_status($pdo, (int)$orderId);
    }
}

function sales_save(array $data, array $access, array $context, ?int $id=null): array
{
    $branchId = (int)$context['branch_id'];
    $userId = (int)$access['user']['id'];
    $mode = aqua_enum($data['mode'] ?? 0, 'mode', 'Sale Type', [1,2,3,4]);
    $allowedModes = sales_allowed_modes($access);
    if (!in_array($mode, $allowedModes, true)) {
        json_error('You do not have permission for the selected Sale Type.', 403);
    }

    aqua_require_action($access, $id === null ? ACTION_CREATE : ACTION_UPDATE, 'You do not have permission to save this Sales document.');
    if ($mode === AQUA_SALE_MODE_QUOTATION) {
        aqua_require_action($access, ACTION_SAVE_DRAFT, 'You do not have permission to Save Quotation.');
    } else {
        aqua_require_action($access, ACTION_POST, 'You do not have permission to Post this Sales document.');
    }
    if ($mode === AQUA_SALE_MODE_LINE) {
        /* Backend enforcement: the user must also be allowed into Line Supply. */
        require_permission('line-supply-list.php', ACTION_VIEW);
    }

    $requestedDate = trim((string)($data['sale_date'] ?? ''));
    if ($requestedDate !== '') {
        $date = aqua_date($requestedDate, 'sale_date');
    } elseif ($id !== null) {
        $dateStmt = db()->prepare('SELECT sale_date FROM sales WHERE id=:id AND branch_id=:branch_id LIMIT 1');
        $dateStmt->execute([':id'=>$id, ':branch_id'=>$branchId]);
        $savedDate = $dateStmt->fetchColumn();
        $date = $savedDate ? (string)$savedDate : date('Y-m-d');
    } else {
        $date = date('Y-m-d');
    }
    $customerId = (int)($data['customer_id'] ?? 0);
    if ($customerId < 1) json_error('Select Customer.', 422, ['customer_id'=>'Select Customer.']);
    $customer = aqua_customer($branchId, $customerId);

    if (!aqua_has_action((array)$access['actions'], ACTION_MANAGE_TAX_SETTINGS)) {
        if ($id !== null) {
            $stmt = db()->prepare('SELECT tax_mode FROM sales WHERE id=:id AND branch_id=:branch_id LIMIT 1');
            $stmt->execute([':id'=>$id, ':branch_id'=>$branchId]);
            $saved = $stmt->fetchColumn();
            $data['tax_mode'] = $saved === false ? 1 : (int)$saved;
        } else {
            $data['tax_mode'] = 1;
        }
    }

    if (!aqua_has_action((array)$access['actions'], ACTION_UPDATE)) {
        $data['other_charges'] = 0;
        $data['round_off_enabled'] = 0;
    }

    $isOrder = $mode === AQUA_SALE_MODE_ORDER;
    $isInvoice = in_array($mode, [AQUA_SALE_MODE_DIRECT, AQUA_SALE_MODE_LINE], true);
    $acceptsPayment = $isOrder || $isInvoice;
    $calc = aqua_calculate_sale_items($data, $branchId, $customerId, $access, $isOrder, $isInvoice);
    $payments = aqua_payment_rows($data, $branchId, $access);
    if (!$acceptsPayment && $payments['total'] > 0) json_error('Quotation cannot receive payment.', 422);

    $vehicleId = null;
    $lineId = null;
    if ($mode === AQUA_SALE_MODE_LINE) {
        $vehicleId = (int)($data['vehicle_id'] ?? 0);
        $lineId = (int)($data['line_id'] ?? 0); // optional; resolved from active Truck Loading when blank
        if ($vehicleId < 1) json_error('Select Truck.', 422, ['vehicle_id'=>'Select Truck.']);
        if ($lineId > 0 && (int)($customer['line_id'] ?? 0) > 0 && (int)$customer['line_id'] !== $lineId) {
            json_error('Selected Customer does not belong to the selected Line.', 422, [
                'customer_id'=>'Select a Customer from the selected Line or clear Line for direct Customer selection.'
            ]);
        }
    }

    $sourceOrderId = null;
    if (!empty($data['source_order_ref'])) {
        $sourceOrderId = aqua_ref_to_id($data['source_order_ref'], 'sale', 'Customer Order');
    }
    if (!$isInvoice && $sourceOrderId !== null) json_error('Source Order can be selected only for Sales Invoice.', 422);

    $remarks = aqua_nullable($data['remarks'] ?? null, 255);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $old = null;
        $oldMode = 0;
        $oldTaxMode = 1;
        $oldSaleNo = '';
        $paymentEditState = ['rows'=>[],'fixed_paid'=>0.0,'payment_ids'=>[]];
        $replaceEditablePayments = $id === null && (float)$payments['total'] > 0.009;
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=:id AND branch_id=:branch_id FOR UPDATE');
            $stmt->execute([':id'=>$id, ':branch_id'=>$branchId]);
            $old = $stmt->fetch();
            if (!$old) json_error('Sales document was not found.', 404);
            $oldMode = sales_mode_from_row($old);
            $oldTaxMode = (int)$old['tax_mode'];
            $oldSaleNo = (string)$old['sale_no'];
            if ((int)$old['status'] === 3 || (int)$old['delivery_status'] === 4) json_error('Cancelled document cannot be edited.', 409);

            /* Whole-page edit: a posted Invoice may change Sale Type when the user
               has permission. Existing posted stock/can/order effects are reversed
               below and the edited document is posted again in the selected mode. */

            if ((int)$old['document_type'] === 3) {
                $deliveredStmt = $pdo->prepare('SELECT COALESCE(SUM(delivered_base_qty),0) FROM sales_items WHERE sale_id=:sale_id');
                $deliveredStmt->execute([':sale_id'=>$id]);
                $delivered = (float)$deliveredStmt->fetchColumn();
                if ($delivered > 0.0005 && $mode !== AQUA_SALE_MODE_ORDER) json_error('Customer Order with delivery history cannot be converted.', 409);
                if ($delivered > 0.0005 && (int)$old['customer_id'] !== $customerId) json_error('Customer cannot be changed after Order delivery starts.', 409);
            }

            /* Existing document payments are editable. Exclusive payments are
               compared with the submitted rows and replaced only when something changed. */
            $paymentEditState = sales_payment_edit_state($pdo, $id);
            if ($mode === AQUA_SALE_MODE_QUOTATION && (float)$paymentEditState['fixed_paid'] > 0.009) {
                json_error('This document has a shared payment allocation. Remove or reallocate that payment before converting it to Quotation.', 409);
            }
            $replaceEditablePayments = !sales_payment_rows_equal($payments['rows'], $paymentEditState['rows'])
                || (int)$old['customer_id'] !== $customerId
                || $mode !== $oldMode;
        }

        /* Reverse the current posted Invoice effects first. All reversal rows and
           the edited re-post happen inside this same DB transaction, so a failure
           rolls everything back and the original Invoice remains effective. */
        if ($old !== null && (int)$old['document_type'] === 2 && (int)$old['status'] === 2) {
            sales_reverse_posted_invoice_effects($pdo, $branchId, $old, $userId);
        }

        $trip = null;
        if ($mode === AQUA_SALE_MODE_LINE) {
            $resolvedLine = sales_resolve_line_trip($branchId, (int)$vehicleId, (int)$lineId, $customer, true);
            $trip = $resolvedLine['trip'];
            $lineId = (int)$resolvedLine['line_id'];
        }

        $orderLock = null;
        if ($sourceOrderId !== null) {
            $orderLock = sales_order_lock($pdo, $branchId, $customerId, $sourceOrderId);
        }

        if ($isInvoice) {
            foreach ($calc['items'] as $item) {
                if ($mode === AQUA_SALE_MODE_DIRECT) {
                    $available = aqua_plant_stock($branchId, (int)$item['product_id']);
                    if ((float)$item['base_qty'] > $available + 0.0005) {
                        json_error(
                            $item['product']['product_name'] .
                            ' Plant Stock is insufficient. Available: ' .
                            number_format($available,3,'.','') .
                            ', Required: ' .
                            number_format((float)$item['base_qty'],3,'.',''),
                            409
                        );
                    }
                } else {
                    $exists = $pdo->prepare('SELECT id FROM line_supply_items WHERE line_supply_id=:supply_id AND product_id=:product_id LIMIT 1');
                    $exists->execute([':supply_id'=>(int)$trip['id'], ':product_id'=>(int)$item['product_id']]);
                    if (!$exists->fetchColumn()) json_error($item['product']['product_name'] . ' is not loaded in the selected Truck.', 409);
                    $available = aqua_vehicle_stock($branchId, (int)$vehicleId, (int)$trip['id'], (int)$item['product_id']);
                    if ((float)$item['base_qty'] > $available + 0.0005) {
                        json_error(
                            $item['product']['product_name'] .
                            ' Truck Stock is insufficient. Available: ' .
                            number_format($available,3,'.','') .
                            ', Required: ' .
                            number_format((float)$item['base_qty'],3,'.',''),
                            409
                        );
                    }
                }
            }
        }

        /* Validate pending Order quantity before any stock posting. */
        if ($isInvoice) {
            if ($orderLock !== null) {
                foreach ($calc['items'] as $item) {
                    $sourceId = (int)($item['source_order_item_id'] ?? 0);
                    if ($sourceId < 1) continue;
                    if (!isset($orderLock['items'][$sourceId])) json_error('Selected Order Item is invalid.',409);
                    $source = $orderLock['items'][$sourceId];
                    if ((int)$source['product_id'] !== (int)$item['product_id']) json_error('Order Product does not match delivery Product.',409);
                    $pending = max(0,round((float)$source['ordered']-(float)$source['delivered'],3));
                    if ((float)$item['base_qty'] > $pending + 0.0005) json_error($item['product']['product_name'].' delivery exceeds pending Order quantity.',409);
                }
            } else {
                foreach ($calc['items'] as $item) {
                    if (!empty($item['source_order_item_id'])) json_error('Select the source Customer Order.',422);
                }
            }
        }

        $documentType = sales_document_type_for_mode($mode);
        $saleType = sales_sale_type_for_mode($mode);
        $status = $mode === AQUA_SALE_MODE_QUOTATION ? 1 : 2;
        $deliveryStatus = $mode === AQUA_SALE_MODE_ORDER ? 1 : 0;

        /* Payment rows submitted by the form are now the desired editable payment
           values, not "new payment only" values. Shared allocations stay fixed. */
        $fixedPaid = $id !== null ? (float)$paymentEditState['fixed_paid'] : 0.0;
        $sourceOrderAdvance = 0.0;
        if ($isInvoice && $sourceOrderId !== null && ($id === null || $sourceOrderId !== $id)) {
            $sourceOrderAdvance = sales_allocated_paid($pdo, $sourceOrderId);
        }
        $advanceApplied = $isInvoice ? min($sourceOrderAdvance, (float)$calc['grand_total']) : 0.0;
        $basePaid = $acceptsPayment ? round($fixedPaid + $advanceApplied, 2) : 0.0;
        $desiredEditablePaid = $acceptsPayment ? (float)$payments['total'] : 0.0;
        if ($acceptsPayment && $basePaid + $desiredEditablePaid > (float)$calc['grand_total'] + 0.009) {
            json_error('Received amount is greater than the Invoice / Order total.', 422);
        }
        $paid = $acceptsPayment ? round($basePaid + $desiredEditablePaid, 2) : 0.0;
        $balance = $acceptsPayment ? max(0, round((float)$calc['grand_total'] - $paid, 2)) : 0.0;
        $paymentStatus = $acceptsPayment ? aqua_payment_status((float)$calc['grand_total'], $paid) : 1;
        $prefix = sales_prefix_for_mode($mode, (int)$calc['tax_mode']);

        if ($id !== null && $replaceEditablePayments) {
            sales_reverse_editable_payments($pdo,$branchId,$id,(array)$paymentEditState['payment_ids'],$date,$userId);
        }

        if ($id === null) {
            $saleNo = aqua_generate_no_locked($pdo, $branchId, 'sales', 'sale_no', $prefix);
            $stmt = $pdo->prepare(
                'INSERT INTO sales
                 (branch_id,sale_no,sale_date,customer_id,line_id,vehicle_id,line_supply_id,source_order_id,line_run_id,customer_order_id,
                  sale_type,document_type,tax_mode,subtotal,item_discount_total,overall_discount_type,overall_discount_value,
                  overall_discount_amount,tax_amount,other_charges,round_off,round_off_enabled,grand_total,paid_amount,balance_amount,
                  payment_status,delivery_status,remarks,status,created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:sale_no,:sale_date,:customer_id,:line_id,:vehicle_id,:line_supply_id,:source_order_id,NULL,NULL,
                  :sale_type,:document_type,:tax_mode,:subtotal,:item_discount_total,:overall_discount_type,:overall_discount_value,
                  :overall_discount_amount,:tax_amount,:other_charges,:round_off,:round_off_enabled,:grand_total,:paid_amount,:balance_amount,
                  :payment_status,:delivery_status,:remarks,:status,:created_by,NOW(),NOW())'
            );
            $stmt->execute([
                ':branch_id'=>$branchId, ':sale_no'=>$saleNo, ':sale_date'=>$date, ':customer_id'=>$customerId,
                ':line_id'=>$mode===AQUA_SALE_MODE_LINE?$lineId:($mode===AQUA_SALE_MODE_ORDER?(int)$customer['line_id']:null),
                ':vehicle_id'=>$mode===AQUA_SALE_MODE_LINE?$vehicleId:null,
                ':line_supply_id'=>$trip?(int)$trip['id']:null,
                ':source_order_id'=>$sourceOrderId,
                ':sale_type'=>$saleType, ':document_type'=>$documentType, ':tax_mode'=>$calc['tax_mode'],
                ':subtotal'=>$calc['subtotal'], ':item_discount_total'=>$calc['item_discount_total'],
                ':overall_discount_type'=>$calc['overall_discount_type'], ':overall_discount_value'=>$calc['overall_discount_value'],
                ':overall_discount_amount'=>$calc['overall_discount_amount'], ':tax_amount'=>$calc['tax_amount'],
                ':other_charges'=>$calc['other_charges'], ':round_off'=>$calc['round_off'], ':round_off_enabled'=>$calc['round_off_enabled'],
                ':grand_total'=>$calc['grand_total'], ':paid_amount'=>$paid, ':balance_amount'=>$balance,
                ':payment_status'=>$paymentStatus, ':delivery_status'=>$deliveryStatus, ':remarks'=>$remarks,
                ':status'=>$status, ':created_by'=>$userId,
            ]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $numberMustChange = $oldMode !== $mode || sales_prefix_for_mode($oldMode, $oldTaxMode) !== $prefix;
            $saleNo = $numberMustChange
                ? aqua_generate_no_locked($pdo, $branchId, 'sales', 'sale_no', $prefix)
                : $oldSaleNo;
            $stmt = $pdo->prepare(
                'UPDATE sales SET
                    sale_no=:sale_no,sale_date=:sale_date,customer_id=:customer_id,line_id=:line_id,
                    vehicle_id=:vehicle_id,line_supply_id=:line_supply_id,source_order_id=:source_order_id,
                    sale_type=:sale_type,document_type=:document_type,tax_mode=:tax_mode,
                    subtotal=:subtotal,item_discount_total=:item_discount_total,overall_discount_type=:overall_discount_type,
                    overall_discount_value=:overall_discount_value,overall_discount_amount=:overall_discount_amount,
                    tax_amount=:tax_amount,other_charges=:other_charges,round_off=:round_off,round_off_enabled=:round_off_enabled,
                    grand_total=:grand_total,paid_amount=:paid_amount,balance_amount=:balance_amount,payment_status=:payment_status,
                    delivery_status=:delivery_status,remarks=:remarks,status=:status,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':sale_no'=>$saleNo, ':sale_date'=>$date, ':customer_id'=>$customerId,
                ':line_id'=>$mode===AQUA_SALE_MODE_LINE?$lineId:($mode===AQUA_SALE_MODE_ORDER?(int)$customer['line_id']:null),
                ':vehicle_id'=>$mode===AQUA_SALE_MODE_LINE?$vehicleId:null,
                ':line_supply_id'=>$trip?(int)$trip['id']:null,
                ':source_order_id'=>$sourceOrderId,
                ':sale_type'=>$saleType, ':document_type'=>$documentType, ':tax_mode'=>$calc['tax_mode'],
                ':subtotal'=>$calc['subtotal'], ':item_discount_total'=>$calc['item_discount_total'],
                ':overall_discount_type'=>$calc['overall_discount_type'], ':overall_discount_value'=>$calc['overall_discount_value'],
                ':overall_discount_amount'=>$calc['overall_discount_amount'], ':tax_amount'=>$calc['tax_amount'],
                ':other_charges'=>$calc['other_charges'], ':round_off'=>$calc['round_off'], ':round_off_enabled'=>$calc['round_off_enabled'],
                ':grand_total'=>$calc['grand_total'], ':paid_amount'=>$paid, ':balance_amount'=>$balance,
                ':payment_status'=>$paymentStatus, ':delivery_status'=>$deliveryStatus, ':remarks'=>$remarks,
                ':status'=>$status, ':id'=>$id, ':branch_id'=>$branchId,
            ]);
        }

        /* Existing item ids are updated in place. Quotation -> Invoice does not
           delete/reinsert the item rows unless the user truly adds/removes a row. */
        aqua_sync_sale_items($pdo, $id, $calc['items'], $mode === AQUA_SALE_MODE_ORDER);
        if ($mode === AQUA_SALE_MODE_ORDER) aqua_refresh_order_status($pdo, $id);

        if ($isOrder && $replaceEditablePayments && $payments['total'] > 0) {
            aqua_create_customer_payment($pdo, $branchId, $id, $customerId, $payments['rows'], $date, $userId, $remarks);
            sales_refresh_payment_header($pdo, $id);
        }

        if ($isInvoice) {
            if ($advanceApplied > 0 && $sourceOrderId !== null && $sourceOrderId !== $id) {
                $moved = sales_transfer_order_advance($pdo, $sourceOrderId, $id, $advanceApplied);
                if ($moved + 0.009 < $advanceApplied) {
                    json_error('Customer Order advance changed while posting. Please reload and try again.', 409);
                }
                sales_refresh_payment_header($pdo, $sourceOrderId);
            }
            $dateTime = $date . ' ' . date('H:i:s');
            foreach ($calc['items'] as $item) {
                if ($mode === AQUA_SALE_MODE_DIRECT) {
                    aqua_insert_stock_movement($pdo, $branchId, $dateTime, (int)$item['product_id'], 2, $id, 0, (float)$item['base_qty'], $userId);
                } else {
                    aqua_insert_vehicle_stock($pdo, $branchId, $dateTime, (int)$vehicleId, (int)$trip['id'], (int)$item['product_id'], 2, $id, 0, (float)$item['base_qty'], $userId, 'Customer Sale');
                }
            }

            aqua_post_can_customer_movements(
                $pdo, $branchId, $id, $customerId, $calc['items'], $dateTime, $userId,
                $mode===AQUA_SALE_MODE_LINE?(int)$vehicleId:null,
                $mode===AQUA_SALE_MODE_LINE?(int)$trip['id']:null
            );

            if ($replaceEditablePayments && $payments['total'] > 0) {
                aqua_create_customer_payment($pdo, $branchId, $id, $customerId, $payments['rows'], $date, $userId, $remarks);
            }
            sales_refresh_payment_header($pdo, $id);

            if ($orderLock !== null) {
                sales_validate_and_apply_order_delivery($pdo, $orderLock, $calc['items']);
            }

            if ($mode === AQUA_SALE_MODE_LINE && (int)$trip['status'] === 2) {
                $pdo->prepare('UPDATE line_supplies SET status=3,started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=:id')
                    ->execute([':id'=>(int)$trip['id']]);
            }
        }

        $pdo->commit();
        return sales_payload($branchId, $id);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$method = request_method();

if ($method === 'GET' && isset($_GET['next_no'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $mode = aqua_enum($_GET['mode'] ?? 0, 'mode', 'Sale Type', [1,2,3,4]);
    if (!in_array($mode, sales_allowed_modes($access), true)) {
        json_error('You do not have permission for the selected Sale Type.', 403);
    }
    $taxMode = aqua_enum($_GET['tax_mode'] ?? 1, 'tax_mode', 'Tax Mode', [0,1]);
    json_success('Document number preview loaded.', [
        'sale_no'=>sales_preview_no((int)$context['branch_id'], $mode, $taxMode),
    ]);
}

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
    $options = sales_master_options($branchId, $customerId && $customerId > 0 ? $customerId : null);
    json_success('Sales options loaded.', $options + [
        'allowed_actions'=>$access['actions'],
        'allowed_modes'=>sales_allowed_modes($access),
        'context'=>$context,
    ]);
}

if ($method === 'GET' && isset($_GET['product_context'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $customerId = (int)($_GET['customer_id'] ?? 0);
    $productId = (int)($_GET['product_id'] ?? 0);
    $mode = (int)($_GET['mode'] ?? AQUA_SALE_MODE_DIRECT);

    if ($customerId < 1) {
        json_error('Select Customer before Product.',422,[
            'customer_id'=>'Select Customer before Product.'
        ]);
    }

    if ($productId < 1) {
        json_error('Select Product.',422,[
            'product_id'=>'Select Product.'
        ]);
    }

    $customer = aqua_customer($branchId,$customerId);
    $product = aqua_product_bundle($branchId,$productId,$customerId);
    $product['plant_stock'] = aqua_plant_stock($branchId,$productId);
    $product['truck_stock'] = 0.0;
    $trip = null;
    $resolvedLineId = null;

    if ($mode === AQUA_SALE_MODE_LINE) {
        require_permission('line-supply-list.php', ACTION_VIEW);

        $vehicleId = (int)($_GET['vehicle_id'] ?? 0);
        $requestedLineId = (int)($_GET['line_id'] ?? 0);

        $resolved = sales_resolve_line_trip(
            $branchId,
            $vehicleId,
            $requestedLineId,
            $customer,
            false
        );

        $trip = $resolved['trip'];
        $resolvedLineId = (int)$resolved['line_id'];

        $product['truck_stock'] = aqua_vehicle_stock(
            $branchId,
            $vehicleId,
            (int)$trip['id'],
            $productId
        );
    }

    $product['customer_can_balance'] =
        (int)$product['container_type'] === 1
            ? aqua_customer_can_balance($branchId,$customerId,$productId)
            : 0.0;

    json_success('Sales Product context loaded.',[
        'product'=>$product,
        'trip'=>$trip,
        'resolved_line_id'=>$resolvedLineId,
    ]);
}

if ($method === 'GET' && isset($_GET['line_context'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    require_permission('line-supply-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $vehicleId = (int)($_GET['vehicle_id'] ?? 0);
    $requestedLineId = (int)($_GET['line_id'] ?? 0);
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($vehicleId < 1) json_error('Select Truck.', 422, ['vehicle_id'=>'Select Truck.']);

    /* Customer is optional while choosing Truck. It is still mandatory when the Sale is saved. */
    $customer = $customerId > 0 ? aqua_customer($branchId, $customerId) : null;
    $resolved = sales_resolve_line_trip($branchId, $vehicleId, $requestedLineId, $customer, false);
    $resolvedLineId = (int)$resolved['line_id'];
    $bundle = aqua_line_sale_products($branchId, $vehicleId, $resolvedLineId, $customerId > 0 ? $customerId : null);

    json_success('Line Supply Sale context loaded.', [
        'trip'=>$bundle['trip'],
        'products'=>$bundle['products'],
        /* If Line was explicitly selected, keep the Customer list filtered.
           If Line was blank, preserve the rare direct-Customer flow with all Customers. */
        'customers'=>$requestedLineId > 0 ? aqua_customers($branchId, $resolvedLineId) : aqua_customers($branchId),
        'resolved_line_id'=>$resolvedLineId,
    ]);
}

if ($method === 'GET' && isset($_GET['pending_orders'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId < 1) json_error('Select Customer.', 422);
    $orders = aqua_pending_orders((int)$context['branch_id'], $customerId);
    foreach ($orders as &$order) {
        $orderId = aqua_ref_to_id($order['ref'], 'sale', 'Customer Order');
        $order['advance_paid'] = sales_allocated_paid(db(), $orderId);
    }
    unset($order);
    json_success('Pending Customer Orders loaded.', ['orders'=>$orders]);
}

if ($method === 'GET' && isset($_GET['customer_summary'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $customerId = (int)($_GET['customer_id'] ?? 0);
    aqua_customer($branchId, $customerId);
    $reusable = [];
    $returnableTotal = 0.0;

    $stmt = db()->prepare(
        'SELECT id,product_code,product_name
         FROM products
         WHERE branch_id=:branch_id
           AND container_type=1
         ORDER BY product_name'
    );

    $stmt->execute([':branch_id'=>$branchId]);

    foreach ($stmt->fetchAll() as $product) {
        $balance = aqua_customer_can_balance(
            $branchId,
            $customerId,
            (int)$product['id']
        );

        $reusable[] = [
            'product_id'=>(int)$product['id'],
            'product_code'=>(string)$product['product_code'],
            'product_name'=>(string)$product['product_name'],
            'can_balance'=>$balance,
        ];

        $returnableTotal = round($returnableTotal + $balance, 3);
    }

    /*
     * Older customers may have only customers.opening_can_balance without a
     * product-wise opening movement. Keep that quantity visible as unallocated
     * instead of incorrectly assigning it to every reusable product.
     */
    $legacyOpening = aqua_customer_legacy_opening_can_balance(
        $branchId,
        $customerId
    );

    $returnableTotal = round($returnableTotal + $legacyOpening, 3);

    json_success('Customer summary loaded.', [
        'outstanding'=>aqua_customer_outstanding($branchId, $customerId),
        'returnable_can_total'=>$returnableTotal,
        'legacy_unallocated_opening_can'=>$legacyOpening,
        'reusable_balances'=>$reusable,
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $draw = (int)($_GET['draw'] ?? 1);
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $doc = isset($_GET['document_type']) ? (int)$_GET['document_type'] : 0;
    $status = isset($_GET['status']) ? (int)$_GET['status'] : 0;
    $delivery = isset($_GET['delivery_status']) ? (int)$_GET['delivery_status'] : 0;

    $where = ['s.branch_id=:branch_id','s.document_type IN (1,2,3)'];
    $params = [':branch_id'=>$branchId];
    if (in_array($doc,[1,2,3],true)) { $where[]='s.document_type=:doc'; $params[':doc']=$doc; }
    if (in_array($status,[1,2,3],true)) { $where[]='s.status=:status'; $params[':status']=$status; }
    if (in_array($delivery,[1,2,3,4],true)) { $where[]='s.document_type=3 AND s.delivery_status=:delivery'; $params[':delivery']=$delivery; }
    if ($search !== '') {
        $where[]='(s.sale_no LIKE :q OR c.customer_name LIKE :q OR c.customer_code LIKE :q OR l.line_name LIKE :q OR v.vehicle_no LIKE :q)';
        $params[':q']='%'.$search.'%';
    }

    $from=' FROM sales s
            INNER JOIN customers c ON c.id=s.customer_id
            LEFT JOIN vehicles v ON v.id=s.vehicle_id
            LEFT JOIN `lines` l ON l.id=s.line_id ';
    $cnt=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.implode(' AND ',$where));
    $cnt->execute($params); $filtered=(int)$cnt->fetchColumn();
    $tot=db()->prepare('SELECT COUNT(*) FROM sales WHERE branch_id=:branch_id AND document_type IN (1,2,3)');
    $tot->execute([':branch_id'=>$branchId]); $total=(int)$tot->fetchColumn();

    $sql='SELECT s.id,s.sale_no,s.sale_date,s.document_type,s.sale_type,s.tax_mode,s.grand_total,s.paid_amount,s.balance_amount,
                 s.payment_status,s.delivery_status,s.status,c.customer_name,v.vehicle_no,l.line_name,
                 COALESCE((SELECT SUM(si.ordered_base_qty) FROM sales_items si WHERE si.sale_id=s.id),0) AS ordered_qty,
                 COALESCE((SELECT SUM(si.delivered_base_qty) FROM sales_items si WHERE si.sale_id=s.id),0) AS delivered_qty'
         .$from.' WHERE '.implode(' AND ',$where).' ORDER BY s.sale_date DESC,s.id DESC LIMIT :start,:length';
    $stmt=db()->prepare($sql);
    foreach($params as $key=>$value) {
        $stmt->bindValue($key,$value,in_array($key,[':branch_id',':doc',':status',':delivery'],true)?PDO::PARAM_INT:PDO::PARAM_STR);
    }
    $stmt->bindValue(':start',$start,PDO::PARAM_INT);
    $stmt->bindValue(':length',$length,PDO::PARAM_INT);
    $stmt->execute();

    $rows=[];
    foreach($stmt->fetchAll() as $row){
        $id=(int)$row['id'];
        foreach(['document_type','sale_type','tax_mode','payment_status','delivery_status','status'] as $key) $row[$key]=(int)$row[$key];
        foreach(['grand_total','paid_amount','balance_amount','ordered_qty','delivered_qty'] as $key) $row[$key]=(float)$row[$key];
        $row['pending_qty']=max(0,round($row['ordered_qty']-$row['delivered_qty'],3));
        $row['mode']=sales_mode_from_row($row);
        $row['ref']=encryptReference('sale',$id);
        $row['edit_url']='sales-form.php?ref='.rawurlencode($row['ref']);
        unset($row['id']);
        $rows[]=$row;
    }
    json_success('Sales list loaded.', [
        'allowed_actions'=>$access['actions'],
        'allowed_modes'=>sales_allowed_modes($access),
        'datatable'=>['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = aqua_sales_context($access['user']);
    $id = aqua_ref_to_id($_GET['ref'], 'sale', 'Sales');
    $payload = sales_payload((int)$context['branch_id'], $id);
    $payload['allowed_actions']=$access['actions'];
    $payload['allowed_modes']=sales_allowed_modes($access);
    $payload['context']=$context;
    json_success('Sales document loaded.', $payload);
}

if ($method === 'POST') {
    $data = request_data();
    $id = null;
    if (!empty($data['ref'])) $id = aqua_ref_to_id($data['ref'], 'sale', 'Sales');
    $access = require_permission('sales-list.php', $id===null ? ACTION_CREATE : ACTION_UPDATE);
    $context = aqua_sales_context($access['user']);
    $payload = sales_save($data,$access,$context,$id);
    $payload['allowed_actions']=$access['actions'];
    $payload['allowed_modes']=sales_allowed_modes($access);
    $mode=(int)($data['mode']??0);
    $message=$id!==null
        ? ($mode===AQUA_SALE_MODE_QUOTATION?'Quotation updated.':($mode===AQUA_SALE_MODE_ORDER?'Customer Order updated.':'Sales Invoice updated.'))
        : ($mode===AQUA_SALE_MODE_QUOTATION?'Quotation saved.':($mode===AQUA_SALE_MODE_ORDER?'Customer Order saved.':'Sales Invoice posted.'));
    json_success($message,$payload);
}

json_error('Unsupported Sales API request.',405);
