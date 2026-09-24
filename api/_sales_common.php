<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_SAVE_DRAFT')) define('ACTION_SAVE_DRAFT', 10);
if (!defined('ACTION_POST')) define('ACTION_POST', 11);
if (!defined('ACTION_FINALIZE')) define('ACTION_FINALIZE', 12);
if (!defined('ACTION_CANCEL')) define('ACTION_CANCEL', 14);
if (!defined('ACTION_VERIFY')) define('ACTION_VERIFY', 22);
if (!defined('ACTION_APPROVE')) define('ACTION_APPROVE', 23);
if (!defined('ACTION_ASSIGN')) define('ACTION_ASSIGN', 25);
if (!defined('ACTION_RECEIVE_PAYMENT')) define('ACTION_RECEIVE_PAYMENT', 29);
if (!defined('ACTION_RETURN')) define('ACTION_RETURN', 32);
if (!defined('ACTION_APPLY_DISCOUNT')) define('ACTION_APPLY_DISCOUNT', 35);
if (!defined('ACTION_MANAGE_TAX_SETTINGS')) define('ACTION_MANAGE_TAX_SETTINGS', 49);

function aqua_sales_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('This module is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);

    $employeeStmt = db()->prepare(
        'SELECT id,employee_code,name
         FROM employees
         WHERE user_id=:user_id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $employeeStmt->execute([
        ':user_id'=>(int)($user['id'] ?? 0),
        ':branch_id'=>$branchId,
    ]);
    $employee = $employeeStmt->fetch() ?: null;

    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
        'employee'=>$employee ? [
            'id'=>(int)$employee['id'],
            'employee_code'=>(string)$employee['employee_code'],
            'name'=>(string)$employee['name'],
        ] : null,
    ];
}

function aqua_has_action(array $actions, int $id): bool
{
    foreach ($actions as $action) {
        if ((int)$action === $id) return true;
    }
    return false;
}

function aqua_require_action(array $access, int $id, string $message): void
{
    if (!aqua_has_action((array)($access['actions'] ?? []), $id)) {
        json_error($message, 403);
    }
}

function aqua_nullable($value, int $max=255): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

function aqua_date($value, string $field='date'): string
{
    $value = trim((string)$value);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) || $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field=>'Enter a valid date.']);
    }
    return $value;
}

function aqua_decimal($value, string $field, string $label, int $places=3, bool $required=false): float
{
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        if ($required) json_error($label . ' is required.', 422, [$field=>$label . ' is required.']);
        return 0.0;
    }
    $pattern = $places === 2
        ? '/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/'
        : '/^(?:[0-9]+(?:\.[0-9]{1,3})?|\.[0-9]{1,3})$/';
    if (!preg_match($pattern, $text)) {
        json_error('Enter a valid ' . $label . '.', 422, [$field=>'Enter a valid ' . $label . '.']);
    }
    return round((float)$text, $places);
}

function aqua_enum($value, string $field, string $label, array $allowed): int
{
    $number = (int)$value;
    if (!in_array($number, $allowed, true)) {
        json_error('Invalid ' . $label . '.', 422, [$field=>'Invalid ' . $label . '.']);
    }
    return $number;
}

function aqua_ref_to_id($value, string $scope, string $label): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error($label . ' reference is required.', 422);
    }
    try {
        $id = (int)decryptReference(trim($value), $scope);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }
    if ($id < 1) json_error('Invalid ' . $label . ' reference.', 422);
    return $id;
}

function aqua_generate_no_locked(PDO $pdo, int $branchId, string $table, string $column, string $prefix): string
{
    $allowed = [
        'sales'=>['sale_no'],
        'customer_payments'=>['payment_no'],
        'line_supplies'=>['supply_no'],
    ];
    if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
        throw new InvalidArgumentException('Invalid number source.');
    }

    $lock = $pdo->prepare('SELECT id FROM branches WHERE id=:branch_id FOR UPDATE');
    $lock->execute([':branch_id'=>$branchId]);
    if (!$lock->fetchColumn()) json_error('Branch was not found.',404);

    $start = strlen($prefix) + 1;
    $sql = 'SELECT `' . $column . '` FROM `' . $table . '`
            WHERE branch_id=:branch_id AND `' . $column . '` REGEXP :pattern
            ORDER BY CAST(SUBSTRING(`' . $column . '`,' . $start . ') AS UNSIGNED) DESC
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':branch_id'=>$branchId, ':pattern'=>'^' . preg_quote($prefix, '/') . '[0-9]+$']);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^' . preg_quote($prefix, '/') . '([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function aqua_customer(int $branchId, int $customerId, bool $active=true): array
{
    $sql = 'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.address,c.line_id,c.line_sequence,
                   c.price_level_id,c.credit_limit,c.opening_balance,c.opening_can_balance,c.status,
                   l.line_code,l.line_name,p.price_level_name
            FROM customers c
            INNER JOIN `lines` l ON l.id=c.line_id AND l.branch_id=c.branch_id
            INNER JOIN price_levels p ON p.id=c.price_level_id AND p.branch_id=c.branch_id
            WHERE c.id=:id AND c.branch_id=:branch_id';
    if ($active) $sql .= ' AND c.status=1';
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([':id'=>$customerId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Selected Customer is invalid or inactive.',422,['customer_id'=>'Select an active Customer.']);
    foreach (['id','line_id','price_level_id','status'] as $k) $row[$k]=(int)$row[$k];
    foreach (['credit_limit','opening_balance','opening_can_balance'] as $k) $row[$k]=(float)$row[$k];
    return $row;
}

function aqua_product_bundle(int $branchId, int $productId, ?int $customerId=null): array
{
    $stmt = db()->prepare(
        'SELECT p.id,p.product_code,p.product_name,p.product_type,p.container_type,p.sale_allowed,p.gst_type,p.hsn_id,p.status,
                h.hsn_code,COALESCE(h.gst_rate,0) AS gst_rate,COALESCE(h.cgst_rate,0) AS cgst_rate,
                COALESCE(h.sgst_rate,0) AS sgst_rate,COALESCE(h.igst_rate,0) AS igst_rate,COALESCE(h.cess_rate,0) AS cess_rate,
                pu.id AS product_unit_id,pu.unit_type,pu.conversion_qty,u.unit_name,u.short_name
         FROM products p
         INNER JOIN product_units pu ON pu.product_id=p.id AND pu.status=1
         INNER JOIN units u ON u.id=pu.unit_id
         LEFT JOIN hsn_master h ON h.id=p.hsn_id AND h.branch_id=p.branch_id AND h.status=1
         WHERE p.id=:product_id AND p.branch_id=:branch_id AND p.status=1 AND p.sale_allowed=1
         ORDER BY pu.unit_type ASC,pu.id ASC'
    );
    $stmt->execute([':product_id'=>$productId, ':branch_id'=>$branchId]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        json_error('Selected Product is invalid, inactive or not allowed for Sale.',422);
    }

    $first=$rows[0];
    $dbPrimary=null;
    $dbSecondary=null;

    foreach($rows as $row){
        $unit=[
            'product_unit_id'=>(int)$row['product_unit_id'],
            'source_unit_type'=>(int)$row['unit_type'],
            'conversion_qty'=>max(1.0,(float)$row['conversion_qty']),
            'unit_name'=>(string)$row['unit_name'],
            'short_name'=>(string)$row['short_name'],
        ];

        if((int)$row['unit_type']===1 && $dbPrimary===null){
            $dbPrimary=$unit;
        } elseif((int)$row['unit_type']===2 && $dbSecondary===null){
            $dbSecondary=$unit;
        }
    }

    if($dbPrimary===null){
        json_error('Selected Product has no active Primary Unit.',422);
    }

    /*
     * FINAL SALES UNIT STANDARD
     * -------------------------
     * Display/transaction Primary Unit = larger/main unit
     * Display/transaction Secondary Unit = smaller/base unit
     *
     * Old products may still be stored as:
     *   Primary = PCS (conversion 1)
     *   Secondary = Box (conversion 12)
     *
     * Do NOT alter historical master rows here. Instead normalize the unit
     * orientation for Sales by comparing conversion quantities.
     */
    $primary=$dbPrimary;
    $secondary=$dbSecondary;
    $legacySwapped=0;

    if(
        $dbSecondary!==null &&
        (float)$dbSecondary['conversion_qty'] > (float)$dbPrimary['conversion_qty']
    ){
        $primary=$dbSecondary;
        $secondary=$dbPrimary;
        $legacySwapped=1;
    }

    $primary['unit_type']=1;
    if($secondary!==null) $secondary['unit_type']=2;

    $bundle=[
        'id'=>(int)$first['id'],
        'product_code'=>(string)$first['product_code'],
        'product_name'=>(string)$first['product_name'],
        'product_type'=>(int)$first['product_type'],
        'container_type'=>(int)$first['container_type'],
        'gst_type'=>(int)$first['gst_type'],
        'hsn_id'=>$first['hsn_id']===null?null:(int)$first['hsn_id'],
        'hsn_code'=>$first['hsn_code'],
        'gst_rate'=>(float)$first['gst_rate'],
        'cgst_rate'=>(float)$first['cgst_rate'],
        'sgst_rate'=>(float)$first['sgst_rate'],
        'igst_rate'=>(float)$first['igst_rate'],
        'cess_rate'=>(float)$first['cess_rate'],
        'tax_percentage'=>round((float)$first['gst_rate']+(float)$first['cess_rate'],2),
        'primary_unit'=>$primary,
        'secondary_unit'=>$secondary,
        'primary_price'=>0.0,
        'secondary_price'=>0.0,
        'legacy_unit_swapped'=>$legacySwapped,
        'db_primary_unit_id'=>(int)$dbPrimary['product_unit_id'],
        'db_secondary_unit_id'=>$dbSecondary===null?null:(int)$dbSecondary['product_unit_id'],
    ];

    if($customerId !== null && $customerId > 0){
        $customer=aqua_customer($branchId,$customerId);
        $primaryUnitId=(int)$bundle['primary_unit']['product_unit_id'];

        // 1) Customer-specific Main/Primary Unit price.
        $priceStmt=db()->prepare(
            'SELECT cpp.selling_price
             FROM customer_product_prices cpp
             WHERE cpp.customer_id=:customer_id
               AND cpp.product_unit_id=:product_unit_id
               AND cpp.status=1
             LIMIT 1'
        );
        $priceStmt->execute([
            ':customer_id'=>$customerId,
            ':product_unit_id'=>$primaryUnitId
        ]);
        $price=$priceStmt->fetchColumn();

        // 2) Price-level Main/Primary Unit price.
        if($price===false){
            $priceStmt=db()->prepare(
                'SELECT pp.selling_price
                 FROM product_prices pp
                 WHERE pp.product_unit_id=:product_unit_id
                   AND pp.price_level_id=:price_level_id
                   AND pp.status=1
                 LIMIT 1'
            );
            $priceStmt->execute([
                ':product_unit_id'=>$primaryUnitId,
                ':price_level_id'=>(int)$customer['price_level_id']
            ]);
            $price=$priceStmt->fetchColumn();
        }

        /*
         * 3) Safe fallback:
         * If only the smaller/base-unit price exists, derive Main Unit price
         * using the conversion ratio.
         */
        if($price===false && $bundle['secondary_unit']!==null){
            $secondaryUnitId=(int)$bundle['secondary_unit']['product_unit_id'];

            $priceStmt=db()->prepare(
                'SELECT cpp.selling_price
                 FROM customer_product_prices cpp
                 WHERE cpp.customer_id=:customer_id
                   AND cpp.product_unit_id=:product_unit_id
                   AND cpp.status=1
                 LIMIT 1'
            );
            $priceStmt->execute([
                ':customer_id'=>$customerId,
                ':product_unit_id'=>$secondaryUnitId
            ]);
            $secondaryPrice=$priceStmt->fetchColumn();

            if($secondaryPrice===false){
                $priceStmt=db()->prepare(
                    'SELECT pp.selling_price
                     FROM product_prices pp
                     WHERE pp.product_unit_id=:product_unit_id
                       AND pp.price_level_id=:price_level_id
                       AND pp.status=1
                     LIMIT 1'
                );
                $priceStmt->execute([
                    ':product_unit_id'=>$secondaryUnitId,
                    ':price_level_id'=>(int)$customer['price_level_id']
                ]);
                $secondaryPrice=$priceStmt->fetchColumn();
            }

            if($secondaryPrice!==false){
                $price=round(
                    (float)$secondaryPrice *
                    (float)$bundle['primary_unit']['conversion_qty'] /
                    max(1.0,(float)$bundle['secondary_unit']['conversion_qty']),
                    2
                );
            }
        }

        $bundle['primary_price']=$price===false?0.0:round((float)$price,2);

        if($bundle['secondary_unit']!==null && $bundle['primary_price']>0){
            $bundle['secondary_price']=round(
                $bundle['primary_price'] *
                (float)$bundle['secondary_unit']['conversion_qty'] /
                max(1.0,(float)$bundle['primary_unit']['conversion_qty']),
                2
            );
        }
    }

    return $bundle;
}

function aqua_sales_products(int $branchId, ?int $customerId=null): array
{
    $stmt=db()->prepare(
        'SELECT id FROM products
         WHERE branch_id=:branch_id AND status=1 AND sale_allowed=1
         ORDER BY product_name ASC'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $result=[];
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id){
        $result[]=aqua_product_bundle($branchId,(int)$id,$customerId);
    }
    return $result;
}

function aqua_customers(int $branchId, ?int $lineId=null): array
{
    $sql='SELECT id,customer_code,customer_name,mobile,line_id,line_sequence,price_level_id
          FROM customers WHERE branch_id=:branch_id AND status=1';
    $params=[':branch_id'=>$branchId];
    if($lineId!==null && $lineId>0){
        $sql.=' AND line_id=:line_id';
        $params[':line_id']=$lineId;
    }
    $sql.=' ORDER BY line_sequence IS NULL,line_sequence,customer_name';
    $stmt=db()->prepare($sql); $stmt->execute($params);
    $rows=$stmt->fetchAll();
    foreach($rows as &$row){
        foreach(['id','line_id','price_level_id'] as $k) $row[$k]=(int)$row[$k];
        $row['line_sequence']=$row['line_sequence']===null?null:(int)$row['line_sequence'];
    }
    unset($row);
    return $rows;
}

function aqua_accounts(int $branchId): array
{
    $stmt=db()->prepare(
        'SELECT id,account_name,account_type
         FROM accounts WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $rows=$stmt->fetchAll();
    foreach($rows as &$row){$row['id']=(int)$row['id'];$row['account_type']=(int)$row['account_type'];}
    unset($row);
    return $rows;
}

function aqua_plant_stock(int $branchId, int $productId): float
{
    $stmt=db()->prepare(
        'SELECT COALESCE(SUM(quantity_in-quantity_out),0)
         FROM stock_movements WHERE branch_id=:branch_id AND product_id=:product_id'
    );
    $stmt->execute([':branch_id'=>$branchId, ':product_id'=>$productId]);
    return round((float)$stmt->fetchColumn(),3);
}

function aqua_vehicle_stock(int $branchId, int $vehicleId, int $supplyId, int $productId): float
{
    $stmt=db()->prepare(
        'SELECT COALESCE(SUM(quantity_in-quantity_out),0)
         FROM vehicle_stock_movements
         WHERE branch_id=:branch_id AND vehicle_id=:vehicle_id AND line_supply_id=:supply_id AND product_id=:product_id'
    );
    $stmt->execute([
        ':branch_id'=>$branchId, ':vehicle_id'=>$vehicleId, ':supply_id'=>$supplyId, ':product_id'=>$productId
    ]);
    return round((float)$stmt->fetchColumn(),3);
}

function aqua_can_stock(int $branchId, int $locationType, ?int $vehicleId, int $productId, int $state, ?int $supplyId=null): float
{
    $sql='SELECT COALESCE(SUM(quantity_in-quantity_out),0)
          FROM can_stock_movements
          WHERE branch_id=:branch_id AND location_type=:location_type AND product_id=:product_id AND can_state=:can_state';
    $params=[
        ':branch_id'=>$branchId, ':location_type'=>$locationType, ':product_id'=>$productId, ':can_state'=>$state
    ];
    if($locationType===2){
        $sql.=' AND vehicle_id=:vehicle_id';
        $params[':vehicle_id']=$vehicleId;
        if($supplyId!==null){$sql.=' AND line_supply_id=:supply_id';$params[':supply_id']=$supplyId;}
    }
    $stmt=db()->prepare($sql);$stmt->execute($params);
    return round((float)$stmt->fetchColumn(),3);
}

function aqua_customer_can_balance(int $branchId, int $customerId, int $productId): float
{
    /*
     * Product-wise returnable balance comes only from can_movements.
     *
     * New Customer Opening Stock is already inserted as:
     *   movement_type = 5
     *   remarks = Opening Customer Stock
     *
     * Therefore customers.opening_can_balance MUST NOT be added to each
     * individual product, otherwise the total opening quantity is duplicated
     * across every reusable product.
     */
    $stmt=db()->prepare(
        'SELECT COALESCE(SUM(CASE
             WHEN movement_type IN (1,5) THEN qty
             WHEN movement_type IN (2,3,4,6) THEN -qty
             ELSE 0 END),0)
         FROM can_movements
         WHERE branch_id=:branch_id
           AND customer_id=:customer_id
           AND product_id=:product_id'
    );

    $stmt->execute([
        ':branch_id'=>$branchId,
        ':customer_id'=>$customerId,
        ':product_id'=>$productId,
    ]);

    return round((float)$stmt->fetchColumn(),3);
}

function aqua_customer_legacy_opening_can_balance(int $branchId, int $customerId): float
{
    $customer=aqua_customer($branchId,$customerId,false);
    $opening=(float)$customer['opening_can_balance'];

    if($opening<=0.0005) return 0.0;

    /*
     * If product-wise opening movements exist, opening_can_balance is only
     * the backward-compatible total snapshot and must not be counted again.
     */
    $stmt=db()->prepare(
        "SELECT 1
         FROM can_movements
         WHERE branch_id=:branch_id
           AND customer_id=:customer_id
           AND movement_type=5
           AND sale_id IS NULL
           AND line_run_id IS NULL
           AND remarks='Opening Customer Stock'
         LIMIT 1"
    );

    $stmt->execute([
        ':branch_id'=>$branchId,
        ':customer_id'=>$customerId,
    ]);

    return $stmt->fetchColumn() ? 0.0 : round($opening,3);
}

function aqua_customer_outstanding(int $branchId, int $customerId): float
{
    $customer=aqua_customer($branchId,$customerId,false);
    $stmt=db()->prepare(
        'SELECT COALESCE(SUM(balance_amount),0)
         FROM sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=2 AND status=2'
    );
    $stmt->execute([':branch_id'=>$branchId, ':customer_id'=>$customerId]);
    return round((float)$customer['opening_balance']+(float)$stmt->fetchColumn(),2);
}

function aqua_parse_json_rows($raw, string $label): array
{
    $rows=is_array($raw)?$raw:json_decode((string)($raw ?? '[]'),true);
    if(!is_array($rows)) json_error($label . ' data is invalid.',422);
    return $rows;
}

function aqua_payment_rows(array $data, int $branchId, array $access): array
{
    $rows=aqua_parse_json_rows($data['payments_json'] ?? '[]','Payment');
    $result=[];$total=0.0;
    foreach($rows as $row){
        if(!is_array($row)) continue;
        $amount=aqua_decimal($row['amount']??0,'payments','Payment Amount',2,false);
        if($amount<=0) continue;
        aqua_require_action($access,ACTION_RECEIVE_PAYMENT,'You do not have permission to receive Customer Payments.');
        $mode=aqua_enum($row['payment_mode']??0,'payments','Payment Mode',[1,2,3,4]);
        $accountId=(int)($row['account_id']??0);
        if($accountId<1) json_error('Select an Account for each Payment row.',422);
        $stmt=db()->prepare('SELECT id,account_type FROM accounts WHERE id=:id AND branch_id=:branch_id AND status=1 LIMIT 1');
        $stmt->execute([':id'=>$accountId, ':branch_id'=>$branchId]);
        $account=$stmt->fetch();
        if(!$account) json_error('Selected Payment Account is invalid.',422);
        $requiredType=$mode===1?1:($mode===2?3:2);
        if((int)$account['account_type']!==$requiredType){
            json_error('Selected Account does not match the Payment Mode.',422);
        }
        $reference=aqua_nullable($row['reference_no']??null,100);
        if(in_array($mode,[2,3,4],true) && $reference===null){
            json_error('Reference No is required for UPI / Bank / Cheque.',422);
        }
        $detailDate=null;
        $detailDateRaw=trim((string)($row['detail_date']??''));
        if($detailDateRaw!==''){
            $detailDate=aqua_date($detailDateRaw,$mode===4?'cheque_date':'payment_date');
        } elseif($mode===4){
            json_error('Cheque Date is required.',422);
        }
        $result[]=[
            'payment_mode'=>$mode,'account_id'=>$accountId,'amount'=>$amount,
            'reference_no'=>$reference,'detail_date'=>$detailDate,
        ];
        $total+=$amount;
    }
    return ['rows'=>$result,'total'=>round($total,2)];
}

function aqua_payment_status(float $grandTotal, float $paid): int
{
    if($paid<=0.009) return 1;
    if($paid+0.009 >= $grandTotal) return 3;
    return 2;
}

function aqua_create_customer_payment(PDO $pdo, int $branchId, int $saleId, int $customerId, array $paymentRows, string $date, int $userId, ?string $remarks=null): float
{
    $total=0.0;foreach($paymentRows as $row)$total+=(float)$row['amount'];$total=round($total,2);
    if($total<=0) return 0.0;

    $paymentNo=aqua_generate_no_locked($pdo,$branchId,'customer_payments','payment_no','CR');
    $stmt=$pdo->prepare(
        'INSERT INTO customer_payments(branch_id,payment_no,payment_date,customer_id,amount,remarks,status,created_by,created_at)
         VALUES(:branch_id,:payment_no,:payment_date,:customer_id,:amount,:remarks,1,:created_by,NOW())'
    );
    $stmt->execute([
        ':branch_id'=>$branchId,':payment_no'=>$paymentNo,':payment_date'=>$date,':customer_id'=>$customerId,
        ':amount'=>$total,':remarks'=>$remarks,':created_by'=>$userId,
    ]);
    $paymentId=(int)$pdo->lastInsertId();

    $detail=$pdo->prepare(
        'INSERT INTO customer_payment_details(customer_payment_id,account_id,payment_mode,amount,reference_no,detail_date)
         VALUES(:payment_id,:account_id,:payment_mode,:amount,:reference_no,:detail_date)'
    );
    $accountTxn=$pdo->prepare(
        'INSERT INTO account_transactions(branch_id,transaction_date,account_id,transaction_type,source_type,source_id,amount,remarks,created_by,created_at)
         VALUES(:branch_id,:transaction_date,:account_id,1,1,:source_id,:amount,:remarks,:created_by,NOW())'
    );
    foreach($paymentRows as $row){
        $detail->execute([
            ':payment_id'=>$paymentId,':account_id'=>$row['account_id'],':payment_mode'=>$row['payment_mode'],
            ':amount'=>$row['amount'],':reference_no'=>$row['reference_no'],':detail_date'=>$row['detail_date'],
        ]);
        $accountTxn->execute([
            ':branch_id'=>$branchId,':transaction_date'=>$date,':account_id'=>$row['account_id'],
            ':source_id'=>$paymentId,':amount'=>$row['amount'],':remarks'=>'Customer payment '.$paymentNo,
            ':created_by'=>$userId,
        ]);
    }
    $alloc=$pdo->prepare(
        'INSERT INTO customer_payment_allocations(customer_payment_id,sale_id,amount)
         VALUES(:payment_id,:sale_id,:amount)'
    );
    $alloc->execute([':payment_id'=>$paymentId,':sale_id'=>$saleId,':amount'=>$total]);
    return $total;
}

function aqua_calculate_sale_items(
    array $data,
    int $branchId,
    int $customerId,
    array $access,
    bool $isOrder=false,
    bool $allowCanReturns=false
): array
{
    $items=aqua_parse_json_rows($data['items_json']??'[]','Sales Items');
    if(!$items) json_error('Add at least one Product.',422,['items'=>'Add at least one Product.']);
    $taxMode=aqua_enum($data['tax_mode']??1,'tax_mode','Tax Mode',[0,1]);
    $seen=[];$rows=[];$subtotal=0.0;$itemDiscountTotal=0.0;$discountBase=0.0;

    foreach($items as $item){
        if(!is_array($item)) json_error('Invalid Sales Item.',422);
        $productId=(int)($item['product_id']??0);
        if($productId<1) json_error('Product is required.',422);
        if(isset($seen[$productId])) json_error('The same Product cannot be added more than once.',422);
        $seen[$productId]=true;
        $product=aqua_product_bundle($branchId,$productId,$customerId);

        $primaryQty=aqua_decimal($item['primary_qty']??0,'items','Primary Qty',3,false);
        $secondaryQty=aqua_decimal($item['secondary_qty']??0,'items','Secondary Qty',3,false);
        if($product['secondary_unit']===null && $secondaryQty>0) json_error('Selected Product has no Secondary Unit.',422);
        if($primaryQty<=0 && $secondaryQty<=0) json_error('Enter Primary Qty or Secondary Qty.',422);

        $primaryConv=max(1.0,(float)$product['primary_unit']['conversion_qty']);
        $secondaryConv=$product['secondary_unit']?max(1.0,(float)$product['secondary_unit']['conversion_qty']):0.0;
        $baseQty=round($primaryQty*$primaryConv+$secondaryQty*$secondaryConv,3);

        $submittedRate=aqua_decimal($item['primary_rate']??$product['primary_price'],'items','Primary Rate',2,false);
        /* Rate editing is treated as an Update-level permission. Operational users
           without Update always use the customer/master price resolved by the API. */
        $rate=aqua_has_action((array)$access['actions'],ACTION_UPDATE)
            ? $submittedRate
            : round((float)$product['primary_price'],2);
        $discountType=aqua_enum($item['discount_type']??1,'items','Discount Type',[1,2,3]);
        $discountValue=aqua_decimal($item['discount_value']??0,'items','Discount',2,false);
        if(($discountType!==1 || $discountValue>0) && !aqua_has_action((array)$access['actions'],ACTION_APPLY_DISCOUNT)){
            json_error('You do not have permission to apply Discount.',403);
        }

        /*
         * Unit-aware rate calculation:
         * Secondary Rate =
         * Primary Rate x Secondary Conversion / Primary Conversion
         */
        $secondaryRate=$product['secondary_unit']
            ? round($rate*$secondaryConv/$primaryConv,2)
            : 0.0;

        $gross=round(
            $primaryQty*$rate +
            $secondaryQty*$secondaryRate,
            2
        );
        $discount=0.0;
        if($discountType===2) $discount=round($gross*min(100,$discountValue)/100,2);
        elseif($discountType===3) $discount=min($gross,round($discountValue,2));
        $afterItem=max(0,round($gross-$discount,2));

        $emptyReturn=aqua_decimal($item['empty_return_qty']??0,'items','Empty Return Qty',3,false);
        $damagedReturn=aqua_decimal($item['damaged_return_qty']??0,'items','Damaged Return Qty',3,false);
        $lostSettled=aqua_decimal($item['lost_settled_qty']??0,'items','Lost Settled Qty',3,false);
        if(!$allowCanReturns || (int)$product['container_type']!==1){
            $emptyReturn=0.0;$damagedReturn=0.0;$lostSettled=0.0;
        }

        $rows[]=[
            'item_id'=>(int)($item['item_id']??0),
            'source_order_item_id'=>(int)($item['source_order_item_id']??0) ?: null,
            'product'=>$product,
            'product_id'=>$productId,
            'product_unit_id'=>(int)$product['primary_unit']['product_unit_id'],
            'secondary_product_unit_id'=>$product['secondary_unit']?(int)$product['secondary_unit']['product_unit_id']:null,
            'primary_qty'=>$primaryQty,'secondary_qty'=>$secondaryQty,
            'conversion_qty'=>$primaryConv,'secondary_conversion_qty'=>$secondaryConv,
            'qty'=>$primaryQty,'base_qty'=>$baseQty,
            'rate'=>$rate,
            'secondary_rate'=>$secondaryRate,
            'gross_amount'=>$gross,
            'discount_type'=>$discountType,'discount_value'=>$discountValue,'discount_amount'=>$discount,
            'after_item_discount'=>$afterItem,'overall_discount_amount'=>0.0,
            'tax_type'=>(int)$product['gst_type'],'tax_percentage'=>$taxMode===1?(float)$product['tax_percentage']:0.0,
            'tax_amount'=>0.0,'net_amount'=>0.0,
            'empty_return_qty'=>$emptyReturn,'damaged_return_qty'=>$damagedReturn,'lost_settled_qty'=>$lostSettled,
            'ordered_base_qty'=>$isOrder?$baseQty:0.0,
        ];
        $subtotal+=$gross;$itemDiscountTotal+=$discount;$discountBase+=$afterItem;
    }

    $overallType=aqua_enum($data['overall_discount_type']??1,'overall_discount_type','Overall Discount Type',[1,2,3]);
    $overallValue=aqua_decimal($data['overall_discount_value']??0,'overall_discount_value','Overall Discount',2,false);
    if(($overallType!==1 || $overallValue>0) && !aqua_has_action((array)$access['actions'],ACTION_APPLY_DISCOUNT)){
        json_error('You do not have permission to apply Overall Discount.',403);
    }
    $overallAmount=0.0;
    if($overallType===2) $overallAmount=round($discountBase*min(100,$overallValue)/100,2);
    elseif($overallType===3) $overallAmount=min($discountBase,round($overallValue,2));

    $taxTotal=0.0;$netItems=0.0;$remaining=$overallAmount;
    foreach($rows as $index=>&$row){
        if($overallAmount>0 && $discountBase>0){
            $share=$index===array_key_last($rows)?$remaining:round($overallAmount*($row['after_item_discount']/$discountBase),2);
            $share=min($row['after_item_discount'],$share);
        }else{$share=0.0;}
        $remaining=round($remaining-$share,2);
        $row['overall_discount_amount']=$share;
        $amount=max(0,round($row['after_item_discount']-$share,2));
        $taxPct=$taxMode===1?(float)$row['tax_percentage']:0.0;
        if($taxPct>0){
            if((int)$row['tax_type']===1){
                $taxable=round($amount*100/(100+$taxPct),2);
                $tax=round($amount-$taxable,2);
                $net=$amount;
            }else{
                $tax=round($amount*$taxPct/100,2);
                $net=round($amount+$tax,2);
            }
        }else{$tax=0.0;$net=$amount;}
        $row['tax_amount']=$tax;$row['net_amount']=$net;
        $taxTotal+=$tax;$netItems+=$net;
    }
    unset($row);

    $otherCharges=aqua_decimal($data['other_charges']??0,'other_charges','Other Charges',2,false);
    $beforeRound=round($netItems+$otherCharges,2);
    $roundEnabled=(int)($data['round_off_enabled']??0)===1?1:0;
    $roundOff=$roundEnabled?round(round($beforeRound)-$beforeRound,2):0.0;
    $grand=round($beforeRound+$roundOff,2);

    return [
        'tax_mode'=>$taxMode,'items'=>$rows,'subtotal'=>round($subtotal,2),
        'item_discount_total'=>round($itemDiscountTotal,2),
        'overall_discount_type'=>$overallType,'overall_discount_value'=>$overallValue,
        'overall_discount_amount'=>round($overallAmount,2),'tax_amount'=>round($taxTotal,2),
        'other_charges'=>$otherCharges,'round_off_enabled'=>$roundEnabled,'round_off'=>$roundOff,
        'grand_total'=>$grand,
    ];
}

function aqua_sync_sale_items(PDO $pdo, int $saleId, array $items, bool $isOrder=false): void
{
    $existingStmt=$pdo->prepare('SELECT id,delivered_base_qty FROM sales_items WHERE sale_id=:sale_id');
    $existingStmt->execute([':sale_id'=>$saleId]);
    $existing=[];
    foreach($existingStmt->fetchAll() as $r)$existing[(int)$r['id']]=(float)$r['delivered_base_qty'];
    $kept=[];

    $update=$pdo->prepare(
        'UPDATE sales_items SET
            product_id=:product_id,product_unit_id=:product_unit_id,secondary_product_unit_id=:secondary_product_unit_id,
            source_order_item_id=:source_order_item_id,primary_qty=:primary_qty,secondary_qty=:secondary_qty,
            qty=:qty,conversion_qty=:conversion_qty,secondary_conversion_qty=:secondary_conversion_qty,base_qty=:base_qty,
            ordered_base_qty=:ordered_base_qty,delivery_status=:delivery_status,
            rate=:rate,gross_amount=:gross_amount,discount_type=:discount_type,discount_value=:discount_value,
            discount_amount=:discount_amount,overall_discount_amount=:overall_discount_amount,tax_type=:tax_type,
            tax_percentage=:tax_percentage,tax_amount=:tax_amount,net_amount=:net_amount,
            empty_return_qty=:empty_return_qty,damaged_return_qty=:damaged_return_qty,lost_settled_qty=:lost_settled_qty
         WHERE id=:id AND sale_id=:sale_id'
    );
    $insert=$pdo->prepare(
        'INSERT INTO sales_items
         (sale_id,product_id,product_unit_id,secondary_product_unit_id,source_order_item_id,
          primary_qty,secondary_qty,qty,conversion_qty,secondary_conversion_qty,base_qty,
          ordered_base_qty,delivered_base_qty,delivery_status,rate,gross_amount,discount_type,discount_value,
          discount_amount,overall_discount_amount,tax_type,tax_percentage,tax_amount,net_amount,
          empty_return_qty,damaged_return_qty,lost_settled_qty)
         VALUES
         (:sale_id,:product_id,:product_unit_id,:secondary_product_unit_id,:source_order_item_id,
          :primary_qty,:secondary_qty,:qty,:conversion_qty,:secondary_conversion_qty,:base_qty,
          :ordered_base_qty,0,:delivery_status,:rate,:gross_amount,:discount_type,:discount_value,
          :discount_amount,:overall_discount_amount,:tax_type,:tax_percentage,:tax_amount,:net_amount,
          :empty_return_qty,:damaged_return_qty,:lost_settled_qty)'
    );

    foreach($items as $row){
        $id=(int)($row['item_id']??0);
        $delivered=$id>0 && isset($existing[$id])?$existing[$id]:0.0;
        $ordered=$isOrder?(float)$row['ordered_base_qty']:0.0;
        if($isOrder && $ordered+0.0005<$delivered){
            json_error('Ordered Quantity cannot be reduced below already delivered quantity.',409);
        }
        $deliveryStatus=0;
        if($isOrder){
            $deliveryStatus=$delivered<=0.0005?1:($delivered+0.0005>=$ordered?3:2);
        }
        $params=[
            ':sale_id'=>$saleId,':product_id'=>$row['product_id'],':product_unit_id'=>$row['product_unit_id'],
            ':secondary_product_unit_id'=>$row['secondary_product_unit_id'],':source_order_item_id'=>$row['source_order_item_id'],
            ':primary_qty'=>$row['primary_qty'],':secondary_qty'=>$row['secondary_qty'],':qty'=>$row['qty'],
            ':conversion_qty'=>$row['conversion_qty'],':secondary_conversion_qty'=>$row['secondary_conversion_qty'],
            ':base_qty'=>$row['base_qty'],':ordered_base_qty'=>$ordered,':delivery_status'=>$deliveryStatus,
            ':rate'=>$row['rate'],':gross_amount'=>$row['gross_amount'],':discount_type'=>$row['discount_type'],
            ':discount_value'=>$row['discount_value'],':discount_amount'=>$row['discount_amount'],
            ':overall_discount_amount'=>$row['overall_discount_amount'],':tax_type'=>$row['tax_type'],
            ':tax_percentage'=>$row['tax_percentage'],':tax_amount'=>$row['tax_amount'],':net_amount'=>$row['net_amount'],
            ':empty_return_qty'=>$row['empty_return_qty'],':damaged_return_qty'=>$row['damaged_return_qty'],
            ':lost_settled_qty'=>$row['lost_settled_qty'],
        ];
        if($id>0 && array_key_exists($id,$existing)){
            $params[':id']=$id;$update->execute($params);$kept[$id]=true;
        }else{
            $insert->execute($params);$kept[(int)$pdo->lastInsertId()]=true;
        }
    }
    foreach($existing as $id=>$delivered){
        if(isset($kept[$id])) continue;
        if($isOrder && $delivered>0.0005) json_error('A delivered Order Item cannot be removed.',409);
        $del=$pdo->prepare('DELETE FROM sales_items WHERE id=:id AND sale_id=:sale_id');
        $del->execute([':id'=>$id,':sale_id'=>$saleId]);
    }
}

function aqua_insert_stock_movement(PDO $pdo, int $branchId, string $dateTime, int $productId, int $type, int $sourceId, float $in, float $out, int $userId): void
{
    $stmt=$pdo->prepare(
        'INSERT INTO stock_movements(branch_id,movement_date,product_id,movement_type,source_id,quantity_in,quantity_out,created_by,created_at)
         VALUES(:branch_id,:movement_date,:product_id,:movement_type,:source_id,:quantity_in,:quantity_out,:created_by,NOW())'
    );
    $stmt->execute([
        ':branch_id'=>$branchId,':movement_date'=>$dateTime,':product_id'=>$productId,':movement_type'=>$type,
        ':source_id'=>$sourceId,':quantity_in'=>round($in,3),':quantity_out'=>round($out,3),':created_by'=>$userId,
    ]);
}

function aqua_insert_vehicle_stock(PDO $pdo, int $branchId, string $dateTime, int $vehicleId, int $supplyId, int $productId, int $type, ?int $saleId, float $in, float $out, int $userId, ?string $remarks=null): void
{
    $stmt=$pdo->prepare(
        'INSERT INTO vehicle_stock_movements
         (branch_id,movement_date,vehicle_id,line_supply_id,product_id,movement_type,sale_id,quantity_in,quantity_out,remarks,created_by,created_at)
         VALUES(:branch_id,:movement_date,:vehicle_id,:supply_id,:product_id,:movement_type,:sale_id,:quantity_in,:quantity_out,:remarks,:created_by,NOW())'
    );
    $stmt->execute([
        ':branch_id'=>$branchId,':movement_date'=>$dateTime,':vehicle_id'=>$vehicleId,':supply_id'=>$supplyId,
        ':product_id'=>$productId,':movement_type'=>$type,':sale_id'=>$saleId,':quantity_in'=>round($in,3),
        ':quantity_out'=>round($out,3),':remarks'=>$remarks,':created_by'=>$userId,
    ]);
}

function aqua_insert_can_stock(PDO $pdo, int $branchId, string $dateTime, int $productId, int $locationType, ?int $vehicleId, ?int $supplyId, ?int $saleId, int $state, int $type, float $in, float $out, int $userId, ?string $remarks=null): void
{
    $stmt=$pdo->prepare(
        'INSERT INTO can_stock_movements
         (branch_id,movement_date,product_id,location_type,vehicle_id,line_supply_id,sale_id,can_state,movement_type,quantity_in,quantity_out,remarks,created_by,created_at)
         VALUES(:branch_id,:movement_date,:product_id,:location_type,:vehicle_id,:supply_id,:sale_id,:can_state,:movement_type,:quantity_in,:quantity_out,:remarks,:created_by,NOW())'
    );
    $stmt->execute([
        ':branch_id'=>$branchId,':movement_date'=>$dateTime,':product_id'=>$productId,':location_type'=>$locationType,
        ':vehicle_id'=>$vehicleId,':supply_id'=>$supplyId,':sale_id'=>$saleId,':can_state'=>$state,':movement_type'=>$type,
        ':quantity_in'=>round($in,3),':quantity_out'=>round($out,3),':remarks'=>$remarks,':created_by'=>$userId,
    ]);
}

function aqua_post_can_customer_movements(PDO $pdo, int $branchId, int $saleId, int $customerId, array $items, string $dateTime, int $userId, ?int $vehicleId=null, ?int $supplyId=null): void
{
    $can=$pdo->prepare(
        'INSERT INTO can_movements(branch_id,movement_date,customer_id,product_id,sale_id,line_run_id,movement_type,qty,remarks,created_by,created_at)
         VALUES(:branch_id,:movement_date,:customer_id,:product_id,:sale_id,NULL,:movement_type,:qty,:remarks,:created_by,NOW())'
    );
    foreach($items as $item){
        if((int)$item['product']['container_type']!==1) continue;
        $productId=(int)$item['product_id'];
        $moves=[
            [1,(float)$item['base_qty'],'Filled Delivered'],
            [2,(float)$item['empty_return_qty'],'Empty Returned'],
            [3,(float)$item['damaged_return_qty'],'Damaged Returned'],
            [4,(float)$item['lost_settled_qty'],'Lost Settled'],
        ];
        foreach($moves as [$type,$qty,$remarks]){
            if($qty<=0) continue;
            $can->execute([
                ':branch_id'=>$branchId,':movement_date'=>$dateTime,':customer_id'=>$customerId,':product_id'=>$productId,
                ':sale_id'=>$saleId,':movement_type'=>$type,':qty'=>round($qty,3),':remarks'=>$remarks,':created_by'=>$userId,
            ]);
        }
        if((float)$item['empty_return_qty']>0){
            aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,$vehicleId?2:1,$vehicleId,$supplyId,$saleId,2,1,(float)$item['empty_return_qty'],0,$userId,'Customer Empty Return');
        }
        if((float)$item['damaged_return_qty']>0){
            aqua_insert_can_stock($pdo,$branchId,$dateTime,$productId,$vehicleId?2:1,$vehicleId,$supplyId,$saleId,3,1,(float)$item['damaged_return_qty'],0,$userId,'Customer Damaged Return');
        }
    }
}

function aqua_sale_items_payload(int $saleId): array
{
    $stmt=db()->prepare(
        'SELECT si.id,si.product_id,p.product_code,p.product_name,p.container_type,p.gst_type,
                si.product_unit_id,si.secondary_product_unit_id,si.primary_qty,si.secondary_qty,
                si.conversion_qty,si.secondary_conversion_qty,si.base_qty,si.ordered_base_qty,si.delivered_base_qty,si.delivery_status,
                si.rate,si.gross_amount,si.discount_type,si.discount_value,si.discount_amount,si.overall_discount_amount,
                si.tax_type,si.tax_percentage,si.tax_amount,si.net_amount,si.empty_return_qty,si.damaged_return_qty,si.lost_settled_qty,
                si.source_order_item_id,
                up.unit_name AS primary_unit_name,up.short_name AS primary_short_name,
                us.unit_name AS secondary_unit_name,us.short_name AS secondary_short_name
         FROM sales_items si
         INNER JOIN products p ON p.id=si.product_id
         INNER JOIN product_units pup ON pup.id=si.product_unit_id
         INNER JOIN units up ON up.id=pup.unit_id
         LEFT JOIN product_units pus ON pus.id=si.secondary_product_unit_id
         LEFT JOIN units us ON us.id=pus.unit_id
         WHERE si.sale_id=:sale_id ORDER BY si.id'
    );
    $stmt->execute([':sale_id'=>$saleId]);
    $rows=$stmt->fetchAll();
    foreach($rows as &$r){
        foreach(['id','product_id','container_type','gst_type','product_unit_id','discount_type','tax_type','delivery_status'] as $k)$r[$k]=(int)$r[$k];
        $r['secondary_product_unit_id']=$r['secondary_product_unit_id']===null?null:(int)$r['secondary_product_unit_id'];
        $r['source_order_item_id']=$r['source_order_item_id']===null?null:(int)$r['source_order_item_id'];
        foreach(['primary_qty','secondary_qty','conversion_qty','secondary_conversion_qty','base_qty','ordered_base_qty','delivered_base_qty','rate','gross_amount','discount_value','discount_amount','overall_discount_amount','tax_percentage','tax_amount','net_amount','empty_return_qty','damaged_return_qty','lost_settled_qty'] as $k)$r[$k]=(float)$r[$k];
        $r['pending_base_qty']=max(0,round($r['ordered_base_qty']-$r['delivered_base_qty'],3));
    }
    unset($r);return $rows;
}

function aqua_payment_payload(int $saleId): array
{
    $stmt=db()->prepare(
        'SELECT cpd.account_id,cpd.payment_mode,cpd.amount,cpd.reference_no,
                COALESCE(cpd.detail_date,cp.payment_date) AS detail_date
         FROM customer_payment_allocations cpa
         INNER JOIN customer_payments cp ON cp.id=cpa.customer_payment_id AND cp.status=1
         INNER JOIN customer_payment_details cpd ON cpd.customer_payment_id=cp.id
         WHERE cpa.sale_id=:sale_id ORDER BY cpd.id'
    );
    $stmt->execute([':sale_id'=>$saleId]);$rows=$stmt->fetchAll();
    foreach($rows as &$r){$r['account_id']=(int)$r['account_id'];$r['payment_mode']=(int)$r['payment_mode'];$r['amount']=(float)$r['amount'];}
    unset($r);return $rows;
}


function aqua_active_line_supply(int $branchId, int $vehicleId, int $lineId, bool $lock=false): array
{
    $sql='SELECT ls.*,v.vehicle_no,v.vehicle_name,l.line_code,l.line_name
          FROM line_supplies ls
          INNER JOIN vehicles v ON v.id=ls.vehicle_id AND v.branch_id=ls.branch_id
          INNER JOIN `lines` l ON l.id=ls.line_id AND l.branch_id=ls.branch_id
          WHERE ls.branch_id=:branch_id AND ls.vehicle_id=:vehicle_id AND ls.line_id=:line_id
            AND ls.status IN (2,3)
          ORDER BY ls.id DESC';
    if($lock) $sql.=' FOR UPDATE';
    $stmt=db()->prepare($sql);
    $stmt->execute([':branch_id'=>$branchId,':vehicle_id'=>$vehicleId,':line_id'=>$lineId]);
    $rows=$stmt->fetchAll();
    if(!$rows){
        json_error('No active Truck Loading was found for the selected Truck and Line.',409);
    }
    if(count($rows)>1){
        json_error('More than one active Truck Loading exists for the selected Truck and Line. Close the old trip first.',409);
    }
    $row=$rows[0];
    foreach(['id','branch_id','vehicle_id','line_id','line_man_employee_id','status'] as $key){
        if(array_key_exists($key,$row)) $row[$key]=$row[$key]===null?null:(int)$row[$key];
    }
    $row['ref']=encryptReference('line_supply',(int)$row['id']);
    return $row;
}

function aqua_line_sale_products(int $branchId, int $vehicleId, int $lineId, ?int $customerId=null): array
{
    $trip=aqua_active_line_supply($branchId,$vehicleId,$lineId,false);
    $stmt=db()->prepare(
        'SELECT product_id FROM line_supply_items
         WHERE line_supply_id=:supply_id ORDER BY id'
    );
    $stmt->execute([':supply_id'=>(int)$trip['id']]);
    $products=[];
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $productId){
        $bundle=aqua_product_bundle($branchId,(int)$productId,$customerId);
        $bundle['truck_stock']=aqua_vehicle_stock($branchId,$vehicleId,(int)$trip['id'],(int)$productId);
        if($bundle['truck_stock']>0.0005) $products[]=$bundle;
    }
    return ['trip'=>$trip,'products'=>$products];
}

function aqua_pending_orders(int $branchId, int $customerId): array
{
    aqua_customer($branchId,$customerId);
    $stmt=db()->prepare(
        'SELECT s.id,s.sale_no,s.sale_date,s.tax_mode,s.grand_total,s.delivery_status
         FROM sales s
         WHERE s.branch_id=:branch_id AND s.customer_id=:customer_id
           AND s.document_type=3 AND s.status=2 AND s.delivery_status IN (1,2)
         ORDER BY s.sale_date,s.id'
    );
    $stmt->execute([':branch_id'=>$branchId,':customer_id'=>$customerId]);
    $orders=[];
    foreach($stmt->fetchAll() as $order){
        $orderId=(int)$order['id'];
        $items=aqua_sale_items_payload($orderId);
        $pending=[];
        foreach($items as $item){
            $pendingQty=max(0,round((float)$item['ordered_base_qty']-(float)$item['delivered_base_qty'],3));
            if($pendingQty<=0.0005) continue;
            $item['pending_base_qty']=$pendingQty;
            $pending[]=$item;
        }
        if(!$pending) continue;
        $orders[]=[
            'ref'=>encryptReference('sale',$orderId),
            'sale_no'=>(string)$order['sale_no'],
            'sale_date'=>(string)$order['sale_date'],
            'tax_mode'=>(int)$order['tax_mode'],
            'grand_total'=>(float)$order['grand_total'],
            'delivery_status'=>(int)$order['delivery_status'],
            'items'=>$pending,
        ];
    }
    return $orders;
}

function aqua_refresh_order_status(PDO $pdo, int $saleId): int
{
    $stmt=$pdo->prepare(
        'SELECT COUNT(*) AS item_count,
                SUM(CASE WHEN delivered_base_qty<=0.0005 THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN delivered_base_qty+0.0005>=ordered_base_qty THEN 1 ELSE 0 END) AS delivered_count
         FROM sales_items WHERE sale_id=:sale_id'
    );
    $stmt->execute([':sale_id'=>$saleId]);
    $row=$stmt->fetch();
    $count=(int)($row['item_count']??0);
    $pending=(int)($row['pending_count']??0);
    $delivered=(int)($row['delivered_count']??0);
    $status=$count===0?1:($delivered===$count?3:($pending===$count?1:2));
    $pdo->prepare('UPDATE sales SET delivery_status=:status,updated_at=NOW() WHERE id=:id')
        ->execute([':status'=>$status,':id'=>$saleId]);
    return $status;
}

function aqua_line_supply_scope_sql(array $access, array $context, string $alias='ls'): array
{
    /* Line Supply visibility is controlled by menu/action permissions.
       No Line Man selector or employee assignment is required. */
    return ['',[]];
}

