<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_SAVE_DRAFT')) define('ACTION_SAVE_DRAFT', 10);
if (!defined('ACTION_POST')) define('ACTION_POST', 11);
if (!defined('ACTION_CANCEL')) define('ACTION_CANCEL', 14);

function purchase_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Purchase management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,
                b.company_id,
                b.branch_name,
                c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :branch_id
           AND b.status = 1
           AND c.status = 1
         LIMIT 1'
    );

    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function purchase_nullable($value, int $max = 0): ?string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') return null;

    if ($max > 0 && mb_strlen($value) > $max) {
        json_error('Entered value is too long.', 422);
    }

    return $value;
}

function purchase_decimal($value, string $field, string $label, int $places = 2, bool $allowNegative = false): float
{
    $text = trim((string)($value ?? ''));
    if ($text === '') $text = '0';

    $pattern = $allowNegative
        ? '/^-?(?:[0-9]+(?:\.[0-9]{1,' . $places . '})?|\.[0-9]{1,' . $places . '})$/'
        : '/^(?:[0-9]+(?:\.[0-9]{1,' . $places . '})?|\.[0-9]{1,' . $places . '})$/';

    if (!preg_match($pattern, $text)) {
        json_error('Purchase validation failed.', 422, [
            $field => 'Enter a valid ' . $label . '.',
        ]);
    }

    $number = round((float)$text, $places);

    if (!$allowNegative && $number < 0) {
        json_error('Purchase validation failed.', 422, [
            $field => $label . ' cannot be negative.',
        ]);
    }

    return $number;
}

function purchase_enum($value, string $field, string $label, array $allowed): int
{
    $number = (int)$value;
    if (!in_array($number, $allowed, true)) {
        json_error('Purchase validation failed.', 422, [
            $field => 'Select a valid ' . $label . '.',
        ]);
    }
    return $number;
}

function purchase_date($value, string $field = 'purchase_date', string $label = 'Purchase Date'): string
{
    $value = trim((string)($value ?? ''));
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        json_error('Purchase validation failed.', 422, [
            $field => 'Enter a valid ' . $label . '.',
        ]);
    }

    return $date->format('Y-m-d');
}

function purchase_optional_date($value, string $field, string $label): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    return purchase_date($value, $field, $label);
}

function purchase_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT purchase_no
         FROM purchases
         WHERE branch_id = :branch_id
           AND purchase_no REGEXP '^PUR[0-9]+$'
         ORDER BY CAST(SUBSTRING(purchase_no, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^PUR([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }

    return 'PUR' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function supplier_payment_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT payment_no
         FROM supplier_payments
         WHERE branch_id = :branch_id
           AND payment_no REGEXP '^SPY[0-9]+$'
         ORDER BY CAST(SUBSTRING(payment_no, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^SPY([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }

    return 'SPY' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function purchase_id_from_ref($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Purchase reference is required.', 422, ['ref' => 'Purchase reference is required.']);
    }

    try {
        $id = (int)decryptReference(trim($value), 'purchase');
    } catch (Throwable $exception) {
        json_error('Invalid Purchase reference.', 422, ['ref' => 'Invalid Purchase reference.']);
    }

    if ($id < 1) {
        json_error('Invalid Purchase reference.', 422);
    }

    return $id;
}

function purchase_assert_supplier(int $branchId, int $supplierId): void
{
    $stmt = db()->prepare(
        'SELECT id
         FROM suppliers
         WHERE id = :id
           AND branch_id = :branch_id
           AND status = 1
         LIMIT 1'
    );

    $stmt->execute([':id' => $supplierId, ':branch_id' => $branchId]);

    if (!$stmt->fetchColumn()) {
        json_error('Selected Supplier is invalid or inactive.', 422, [
            'supplier_id' => 'Select an active Supplier.',
        ]);
    }
}

function purchase_account_row(int $branchId, int $accountId): array
{
    $stmt = db()->prepare(
        'SELECT id, account_name, account_type, status
         FROM accounts
         WHERE id = :id
           AND branch_id = :branch_id
         LIMIT 1'
    );

    $stmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['status'] !== 1) {
        json_error('Selected payment Account is invalid or inactive.', 422, [
            'payment_details_json' => 'One or more Accounts are invalid.',
        ]);
    }

    return $row;
}

function purchase_paid_amount(int $purchaseId): float
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(a.amount), 0)
         FROM supplier_payment_allocations a
         INNER JOIN supplier_payments p
            ON p.id = a.supplier_payment_id
           AND p.status = 1
         WHERE a.purchase_id = :purchase_id'
    );

    $stmt->execute([':purchase_id' => $purchaseId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function purchase_payment_status(float $grandTotal, float $paidAmount): int
{
    if ($paidAmount <= 0.009) return 1;
    if ($paidAmount + 0.009 >= $grandTotal) return 3;
    return 2;
}

function purchase_options(int $branchId): array
{
    $supplierStmt = db()->prepare(
        'SELECT id, supplier_code, supplier_name
         FROM suppliers
         WHERE branch_id = :branch_id
           AND status = 1
         ORDER BY supplier_name ASC'
    );
    $supplierStmt->execute([':branch_id' => $branchId]);
    $suppliers = $supplierStmt->fetchAll();

    foreach ($suppliers as &$supplier) {
        $supplier['id'] = (int)$supplier['id'];
    }
    unset($supplier);

    $accountStmt = db()->prepare(
        'SELECT id, account_name, account_type
         FROM accounts
         WHERE branch_id = :branch_id
           AND status = 1
         ORDER BY account_name ASC'
    );
    $accountStmt->execute([':branch_id' => $branchId]);
    $accounts = $accountStmt->fetchAll();

    $accountLabels = [1 => 'Cash', 2 => 'Bank', 3 => 'UPI', 4 => 'Card', 5 => 'Other'];

    foreach ($accounts as &$account) {
        $account['id'] = (int)$account['id'];
        $account['account_type'] = (int)$account['account_type'];
        $account['account_type_label'] = $accountLabels[$account['account_type']] ?? 'Other';
    }
    unset($account);

    $productStmt = db()->prepare(
        'SELECT p.id AS product_id,
                p.product_code,
                p.product_name,
                p.product_type,
                p.purchase_price,
                p.gst_type,
                p.hsn_id,
                h.hsn_code,
                COALESCE(h.gst_rate, 0) AS gst_rate,
                COALESCE(h.cess_rate, 0) AS cess_rate,
                pu.id AS product_unit_id,
                pu.unit_id,
                pu.unit_type,
                pu.conversion_qty,
                u.unit_name,
                u.short_name
         FROM products p
         INNER JOIN product_units pu
            ON pu.product_id = p.id
           AND pu.status = 1
         INNER JOIN units u
            ON u.id = pu.unit_id
         LEFT JOIN hsn_master h
            ON h.id = p.hsn_id
           AND h.branch_id = p.branch_id
           AND h.status = 1
         WHERE p.branch_id = :branch_id
           AND p.status = 1
         ORDER BY p.product_name ASC, pu.unit_type ASC, pu.id ASC'
    );
    $productStmt->execute([':branch_id' => $branchId]);

    $products = [];

    foreach ($productStmt->fetchAll() as $row) {
        $productId = (int)$row['product_id'];

        if (!isset($products[$productId])) {
            $products[$productId] = [
                'id' => $productId,
                'product_code' => (string)$row['product_code'],
                'product_name' => (string)$row['product_name'],
                'product_type' => (int)$row['product_type'],
                'purchase_price' => (float)$row['purchase_price'],
                'gst_type' => (int)$row['gst_type'],
                'hsn_id' => $row['hsn_id'] === null ? null : (int)$row['hsn_id'],
                'hsn_code' => $row['hsn_code'],
                'gst_rate' => (float)$row['gst_rate'],
                'cess_rate' => (float)$row['cess_rate'],
                'tax_percentage' => round((float)$row['gst_rate'] + (float)$row['cess_rate'], 2),
                'units' => [],
            ];
        }

        $products[$productId]['units'][] = [
            'product_unit_id' => (int)$row['product_unit_id'],
            'unit_id' => (int)$row['unit_id'],
            'unit_type' => (int)$row['unit_type'],
            'conversion_qty' => (float)$row['conversion_qty'],
            'unit_name' => (string)$row['unit_name'],
            'short_name' => (string)$row['short_name'],
        ];
    }

    return [
        'suppliers' => $suppliers,
        'accounts' => $accounts,
        'products' => array_values($products),
    ];
}

function purchase_parse_items(array $data): array
{
    $raw = $data['items_json'] ?? '[]';
    $items = is_array($raw) ? $raw : json_decode((string)$raw, true);

    if (!is_array($items) || $items === []) {
        json_error('Purchase validation failed.', 422, ['items' => 'Add at least one Purchase Item.']);
    }

    return $items;
}

function purchase_load_product_bundle(int $branchId, int $productId): array
{
    $stmt = db()->prepare(
        'SELECT p.id AS product_id,
                p.product_name,
                p.gst_type,
                p.hsn_id,
                pu.id AS product_unit_id,
                pu.unit_type,
                pu.conversion_qty,
                u.unit_name,
                u.short_name,
                h.hsn_code,
                COALESCE(h.gst_rate, 0) AS gst_rate,
                COALESCE(h.cess_rate, 0) AS cess_rate
         FROM products p
         INNER JOIN product_units pu
            ON pu.product_id = p.id
           AND pu.status = 1
         INNER JOIN units u ON u.id = pu.unit_id
         LEFT JOIN hsn_master h
            ON h.id = p.hsn_id
           AND h.branch_id = p.branch_id
           AND h.status = 1
         WHERE p.id = :product_id
           AND p.branch_id = :branch_id
           AND p.status = 1
         ORDER BY pu.unit_type ASC, pu.id ASC'
    );
    $stmt->execute([':product_id'=>$productId, ':branch_id'=>$branchId]);
    $rows=$stmt->fetchAll();
    if(!$rows) json_error('Purchase Product is invalid or inactive.',422);

    $product=[
        'product_id'=>$productId,
        'product_name'=>(string)$rows[0]['product_name'],
        'gst_type'=>(int)$rows[0]['gst_type'],
        'hsn_id'=>$rows[0]['hsn_id']===null?null:(int)$rows[0]['hsn_id'],
        'hsn_code'=>$rows[0]['hsn_code'],
        'tax_percentage'=>round((float)$rows[0]['gst_rate']+(float)$rows[0]['cess_rate'],2),
        'primary'=>null,
        'secondary'=>null,
    ];

    foreach($rows as $row){
        $unit=[
            'product_unit_id'=>(int)$row['product_unit_id'],
            'unit_type'=>(int)$row['unit_type'],
            'conversion_qty'=>max(1,round((float)$row['conversion_qty'],4)),
            'unit_name'=>(string)$row['unit_name'],
            'short_name'=>(string)$row['short_name'],
        ];
        if((int)$row['unit_type']===1 && $product['primary']===null) $product['primary']=$unit;
        elseif((int)$row['unit_type']===2 && $product['secondary']===null) $product['secondary']=$unit;
    }

    if($product['primary']===null) json_error('Selected Product has no active Primary Unit.',422);
    return $product;
}

function purchase_calculate(array $data, int $branchId): array
{
    $items=purchase_parse_items($data);
    $calculated=[];
    $seenProducts=[];
    $subtotal=0.0;
    $itemDiscountTotal=0.0;
    $discountBase=0.0;

    foreach($items as $item){
        if(!is_array($item)) json_error('Purchase Item format is invalid.',422);
        $productId=(int)($item['product_id']??0);
        if($productId<1) json_error('Purchase Item Product is required.',422);
        if(isset($seenProducts[$productId])) json_error('The same Product cannot be added more than once.',422,['items'=>'Duplicate Product found. Use the Primary / Secondary Qty inputs in the same row.']);
        $seenProducts[$productId]=true;
        $source=purchase_load_product_bundle($branchId,$productId);

        $primaryQty=purchase_decimal($item['primary_qty']??0,'items','Primary Quantity',3,false);
        $secondaryQty=purchase_decimal($item['secondary_qty']??0,'items','Secondary Quantity',3,false);
        $freePrimaryQty=purchase_decimal($item['free_primary_qty']??0,'items','Free Primary Quantity',3,false);
        $freeSecondaryQty=purchase_decimal($item['free_secondary_qty']??0,'items','Free Secondary Quantity',3,false);
        if($source['secondary']===null){
            if($secondaryQty>0 || $freeSecondaryQty>0) json_error('Selected Product has no Secondary Unit.',422);
            $secondaryQty=0.0; $freeSecondaryQty=0.0;
        }
        if($primaryQty<=0 && $secondaryQty<=0 && $freePrimaryQty<=0 && $freeSecondaryQty<=0) json_error('Enter Primary Qty, Secondary Qty or Free Qty.',422);

        $primaryRate=purchase_decimal($item['primary_rate']??0,'items','Primary Rate',2,false);
        $discountType=purchase_enum($item['discount_type']??1,'items','Discount Type',[1,2,3]);
        $discountValue=purchase_decimal($item['discount_value']??0,'items','Discount',2,false);
        $primaryConversion=max(1,(float)$source['primary']['conversion_qty']);
        $secondaryConversion=$source['secondary']!==null?max(1,(float)$source['secondary']['conversion_qty']):0.0;
        $secondaryRate=$source['secondary']!==null?round($primaryRate*$secondaryConversion,2):0.0;

        $unitRows=[];
        $primaryGross=round($primaryQty*$primaryRate,2);
        $unitRows[]=[
            'product_id'=>$productId,
            'product_unit_id'=>(int)$source['primary']['product_unit_id'],
            'unit_type'=>1,
            'qty'=>$primaryQty,
            'free_qty'=>$freePrimaryQty,
            'conversion_qty'=>$primaryConversion,
            'base_qty'=>round($primaryQty*$primaryConversion,3),
            'free_base_qty'=>round($freePrimaryQty*$primaryConversion,3),
            'rate'=>$primaryRate,
            'gross_amount'=>$primaryGross,
            'discount_type'=>$discountType,
            'discount_value'=>$discountValue,
            'discount_amount'=>0.0,
            'after_item_discount'=>$primaryGross,
            'overall_discount_amount'=>0.0,
            'tax_percentage'=>(float)$source['tax_percentage'],
            'tax_amount'=>0.0,
            'other_charge_amount'=>0.0,
            'net_amount'=>0.0,
        ];

        $secondaryGross=0.0;
        if($source['secondary']!==null){
            $secondaryGross=round($secondaryQty*$secondaryRate,2);
            $unitRows[]=[
                'product_id'=>$productId,
                'product_unit_id'=>(int)$source['secondary']['product_unit_id'],
                'unit_type'=>2,
                'qty'=>$secondaryQty,
                'free_qty'=>$freeSecondaryQty,
                'conversion_qty'=>$secondaryConversion,
                'base_qty'=>round($secondaryQty*$secondaryConversion,3),
                'free_base_qty'=>round($freeSecondaryQty*$secondaryConversion,3),
                'rate'=>$secondaryRate,
                'gross_amount'=>$secondaryGross,
                'discount_type'=>$discountType,
                'discount_value'=>$discountValue,
                'discount_amount'=>0.0,
                'after_item_discount'=>$secondaryGross,
                'overall_discount_amount'=>0.0,
                'tax_percentage'=>(float)$source['tax_percentage'],
                'tax_amount'=>0.0,
                'other_charge_amount'=>0.0,
                'net_amount'=>0.0,
            ];
        }

        $gross=round($primaryGross+$secondaryGross,2);
        $discountAmount=0.0;
        if($discountType===2){
            if($discountValue>100) json_error('Item Discount Percentage cannot exceed 100%.',422);
            $discountAmount=round($gross*$discountValue/100,2);
        } elseif($discountType===3) $discountAmount=min($discountValue,$gross);

        $allocated=0.0;
        foreach($unitRows as $i=>&$u){
            $share=0.0;
            if($discountAmount>0 && $gross>0){
                if($i===count($unitRows)-1) $share=round($discountAmount-$allocated,2);
                else { $share=round($discountAmount*$u['gross_amount']/$gross,2); $allocated+=$share; }
            }
            $u['discount_amount']=$share;
            $u['after_item_discount']=round($u['gross_amount']-$share,2);
        }
        unset($u);

        $afterItemDiscount=round($gross-$discountAmount,2);
        $calculated[]=[
            'product_id'=>$productId,
            'product_name'=>$source['product_name'],
            'gst_type'=>(int)$source['gst_type'],
            'tax_percentage'=>(float)$source['tax_percentage'],
            'primary_qty'=>$primaryQty,
            'secondary_qty'=>$secondaryQty,
            'free_primary_qty'=>$freePrimaryQty,
            'free_secondary_qty'=>$freeSecondaryQty,
            'primary_rate'=>$primaryRate,
            'secondary_rate'=>$secondaryRate,
            'discount_type'=>$discountType,
            'discount_value'=>$discountValue,
            'gross_amount'=>$gross,
            'discount_amount'=>round($discountAmount,2),
            'after_item_discount'=>$afterItemDiscount,
            'overall_discount_amount'=>0.0,
            'tax_amount'=>0.0,
            'other_charge_amount'=>0.0,
            'net_amount'=>0.0,
            'unit_rows'=>$unitRows,
        ];
        $subtotal+=$gross;
        $itemDiscountTotal+=$discountAmount;
        $discountBase+=$afterItemDiscount;
    }

    $subtotal=round($subtotal,2);
    $itemDiscountTotal=round($itemDiscountTotal,2);
    $discountBase=round($discountBase,2);
    $overallType=purchase_enum($data['overall_discount_type']??1,'overall_discount_type','Overall Discount Type',[1,2,3]);
    $overallValue=purchase_decimal($data['overall_discount_value']??0,'overall_discount_value','Overall Discount Value',2,false);
    $overallAmount=0.0;
    if($overallType===2){ if($overallValue>100) json_error('Overall Discount Percentage cannot exceed 100%.',422,['overall_discount_value'=>'Percentage cannot exceed 100.']); $overallAmount=round($discountBase*$overallValue/100,2); }
    elseif($overallType===3) $overallAmount=min($overallValue,$discountBase);

    $allocatedOverall=0.0;
    foreach($calculated as $i=>&$p){
        $productShare=0.0;
        if($overallAmount>0 && $discountBase>0){
            if($i===count($calculated)-1) $productShare=round($overallAmount-$allocatedOverall,2);
            else { $productShare=round($overallAmount*$p['after_item_discount']/$discountBase,2); $allocatedOverall+=$productShare; }
        }
        $p['overall_discount_amount']=$productShare;
        $unitBase=array_sum(array_column($p['unit_rows'],'after_item_discount'));
        $allocatedUnit=0.0;
        foreach($p['unit_rows'] as $j=>&$u){
            $share=0.0;
            if($productShare>0 && $unitBase>0){
                if($j===count($p['unit_rows'])-1) $share=round($productShare-$allocatedUnit,2);
                else { $share=round($productShare*$u['after_item_discount']/$unitBase,2); $allocatedUnit+=$share; }
            }
            $u['overall_discount_amount']=$share;
            $line=round($u['after_item_discount']-$share,2);
            $taxRate=max(0,(float)$p['tax_percentage']);
            if((int)$p['gst_type']===1 && $taxRate>0){
                $taxable=round($line/(1+$taxRate/100),2);
                $u['tax_amount']=round($line-$taxable,2);
                $u['net_amount']=$line;
            } else {
                $u['tax_amount']=round($line*$taxRate/100,2);
                $u['net_amount']=round($line+$u['tax_amount'],2);
            }
        }
        unset($u);
        $p['tax_amount']=round(array_sum(array_column($p['unit_rows'],'tax_amount')),2);
        $p['net_amount']=round(array_sum(array_column($p['unit_rows'],'net_amount')),2);
    }
    unset($p);

    $otherCharges=purchase_decimal($data['other_charges']??0,'other_charges','Other Charges',2,false);
    $otherBase=0.0;
    foreach($calculated as $p) $otherBase+=max(0,$p['after_item_discount']-$p['overall_discount_amount']);
    $otherBase=round($otherBase,2);
    $allocatedOther=0.0;
    foreach($calculated as $i=>&$p){
        $productOther=0.0;
        if($otherCharges>0 && $otherBase>0){
            $rowBase=max(0,$p['after_item_discount']-$p['overall_discount_amount']);
            if($i===count($calculated)-1) $productOther=round($otherCharges-$allocatedOther,2);
            else { $productOther=round($otherCharges*$rowBase/$otherBase,2); $allocatedOther+=$productOther; }
        }
        $p['other_charge_amount']=$productOther;
        $unitBase=0.0;
        foreach($p['unit_rows'] as $u) $unitBase+=max(0,$u['after_item_discount']-$u['overall_discount_amount']);
        $allocatedUnit=0.0;
        foreach($p['unit_rows'] as $j=>&$u){
            $share=0.0;
            if($productOther>0 && $unitBase>0){
                $rowBase=max(0,$u['after_item_discount']-$u['overall_discount_amount']);
                if($j===count($p['unit_rows'])-1) $share=round($productOther-$allocatedUnit,2);
                else { $share=round($productOther*$rowBase/$unitBase,2); $allocatedUnit+=$share; }
            }
            $u['other_charge_amount']=$share;
            $u['net_amount']=round($u['net_amount']+$share,2);
        }
        unset($u);
        $p['net_amount']=round(array_sum(array_column($p['unit_rows'],'net_amount')),2);
    }
    unset($p);

    $flatItems=[];
    foreach($calculated as $p){
        foreach($p['unit_rows'] as $u){
            if((float)$u['qty']<=0 && (float)$u['free_qty']<=0) continue;
            $flatItems[]=$u;
        }
    }

    $taxAmount=round(array_sum(array_column($calculated,'tax_amount')),2);
    $beforeRound=round(array_sum(array_column($calculated,'net_amount')),2);
    $roundEnabled=(int)($data['round_off_enabled']??0)===1;
    $roundOff=$roundEnabled?round(round($beforeRound)-$beforeRound,2):0.0;
    $grandTotal=round($beforeRound+$roundOff,2);
    if($grandTotal<0) json_error('Grand Total cannot be negative.',422);

    return [
        'items'=>$calculated,
        'flat_items'=>$flatItems,
        'subtotal'=>$subtotal,
        'item_discount_total'=>$itemDiscountTotal,
        'overall_discount_type'=>$overallType,
        'overall_discount_value'=>$overallValue,
        'overall_discount_amount'=>round($overallAmount,2),
        'tax_amount'=>$taxAmount,
        'other_charges'=>$otherCharges,
        'before_round'=>$beforeRound,
        'round_off'=>$roundOff,
        'grand_total'=>$grandTotal,
    ];
}

function purchase_parse_payment_details(array $data, int $branchId): array
{
    $raw = $data['payment_details_json'] ?? '[]';
    $details = is_array($raw) ? $raw : json_decode((string)$raw, true);

    if (!is_array($details)) {
        json_error('Payment details format is invalid.', 422, [
            'payment_details_json' => 'Payment details format is invalid.',
        ]);
    }

    $allowedModes = [
        'cash' => 1,
        'upi' => 3,
        'bank' => 2,
        'cheque' => 2,
    ];

    $result = [];
    $total = 0.0;

    foreach ($details as $detail) {
        if (!is_array($detail)) continue;

        $mode = strtolower(trim((string)($detail['mode_key'] ?? '')));
        if (!isset($allowedModes[$mode])) continue;

        $amount = purchase_decimal($detail['amount'] ?? 0, 'payment_details_json', 'Payment Amount', 2, false);
        $accountId = (int)($detail['account_id'] ?? 0);
        $referenceNo = purchase_nullable($detail['reference_no'] ?? null, 100);
        $detailDate = purchase_optional_date($detail['detail_date'] ?? null, 'payment_details_json', 'Payment Date');

        if ($amount <= 0) {
            $zeroModeMap = ['cash'=>1,'upi'=>2,'bank'=>3,'cheque'=>4];
            $result[] = [
                'mode_key' => $mode,
                'payment_mode' => $zeroModeMap[$mode],
                'account_id' => $accountId > 0 ? $accountId : null,
                'amount' => 0.0,
                'reference_no' => $referenceNo,
                'detail_date' => $detailDate,
            ];
            continue;
        }

        if ($accountId < 1) {
            json_error('Purchase validation failed.', 422, [
                'payment_details_json' => 'Select Account for all entered Payment amounts.',
            ]);
        }

        $account = purchase_account_row($branchId, $accountId);
        $expectedType = $allowedModes[$mode];

        if ((int)$account['account_type'] !== $expectedType) {
            json_error('Purchase validation failed.', 422, [
                'payment_details_json' => 'Selected Account type does not match Payment Mode.',
            ]);
        }

        $paymentModeMap = ['cash'=>1,'upi'=>2,'bank'=>3,'cheque'=>4];
        $result[] = [
            'mode_key' => $mode,
            'payment_mode' => $paymentModeMap[$mode],
            'account_id' => $accountId,
            'amount' => $amount,
            'reference_no' => $referenceNo,
            'detail_date' => $detailDate,
        ];

        $total += $amount;
    }

    return [
        'details' => $result,
        'total' => round($total, 2),
    ];
}

function purchase_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT p.id,
                p.branch_id,
                p.purchase_no,
                p.purchase_date,
                p.supplier_id,
                s.supplier_code,
                s.supplier_name,
                p.supplier_invoice_no,
                p.subtotal,
                p.item_discount_total,
                p.overall_discount_type,
                p.overall_discount_value,
                p.overall_discount_amount,
                p.tax_amount,
                p.other_charges,
                p.round_off,
                p.grand_total,
                p.payment_status,
                p.status,
                p.created_by,
                p.created_at,
                p.updated_at
         FROM purchases p
         INNER JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.id = :id
           AND p.branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) json_error('Purchase was not found in your branch.', 404);

    $paidAmount = purchase_paid_amount($id);
    $row['payment_status'] = purchase_payment_status((float)$row['grand_total'], $paidAmount);
    $ref = encryptReference('purchase', $id);
    unset($row['id']);

    foreach (['branch_id','supplier_id','overall_discount_type','payment_status','status'] as $key) {
        $row[$key] = (int)$row[$key];
    }

    foreach (['subtotal','item_discount_total','overall_discount_value','overall_discount_amount','tax_amount','other_charges','round_off','grand_total'] as $key) {
        $row[$key] = (float)$row[$key];
    }

    $row['paid_amount'] = $paidAmount;
    $row['balance_amount'] = max(0, round((float)$row['grand_total'] - $paidAmount, 2));
    $row['ref'] = $ref;
    $row['edit_url'] = 'purchase-form.php?ref=' . $ref;
    $row['view_url'] = 'purchase-form.php?ref=' . $ref . '&view=1';

    $remarkStmt = db()->prepare(
        'SELECT sp.remarks
         FROM supplier_payment_allocations spa
         INNER JOIN supplier_payments sp
            ON sp.id = spa.supplier_payment_id
           AND sp.status = 1
         WHERE spa.purchase_id = :purchase_id
         ORDER BY sp.id DESC
         LIMIT 1'
    );
    $remarkStmt->execute([':purchase_id' => $id]);
    $row['payment_remarks'] = (string)($remarkStmt->fetchColumn() ?: '');

    return $row;
}

function purchase_items(int $purchaseId): array
{
    $stmt=db()->prepare(
        'SELECT pi.id,
                pu.product_id,
                pu.unit_type,
                pi.product_unit_id,
                p.product_name,
                p.gst_type,
                h.hsn_code,
                u.unit_name,
                u.short_name,
                pi.qty,
                pi.free_qty,
                pi.conversion_qty,
                pi.base_qty,
                pi.free_base_qty,
                pi.rate,
                pi.gross_amount,
                pi.discount_type,
                pi.discount_value,
                pi.discount_amount,
                pi.overall_discount_amount,
                pi.tax_percentage,
                pi.tax_amount,
                pi.other_charge_amount,
                pi.net_amount
         FROM purchase_items pi
         INNER JOIN product_units pu ON pu.id=pi.product_unit_id
         INNER JOIN products p ON p.id=pu.product_id
         INNER JOIN units u ON u.id=pu.unit_id
         LEFT JOIN hsn_master h ON h.id=p.hsn_id AND h.branch_id=p.branch_id
         WHERE pi.purchase_id=:purchase_id
         ORDER BY pi.id ASC'
    );
    $stmt->execute([':purchase_id'=>$purchaseId]);
    $rows=$stmt->fetchAll();
    foreach($rows as &$row){
        foreach(['id','product_id','unit_type','product_unit_id','gst_type','discount_type'] as $key) $row[$key]=(int)$row[$key];
        foreach(['qty','free_qty','conversion_qty','base_qty','free_base_qty','rate','gross_amount','discount_value','discount_amount','overall_discount_amount','tax_percentage','tax_amount','other_charge_amount','net_amount'] as $key) $row[$key]=(float)$row[$key];
    }
    unset($row);
    return $rows;
}

function purchase_grouped_items(int $purchaseId): array
{
    $rows=purchase_items($purchaseId);
    $grouped=[];
    foreach($rows as $row){
        $productId=(int)$row['product_id'];
        if(!isset($grouped[$productId])){
            $grouped[$productId]=[
                'product_id'=>$productId,
                'product_name'=>(string)$row['product_name'],
                'hsn_code'=>$row['hsn_code'],
                'gst_type'=>(int)$row['gst_type'],
                'tax_percentage'=>(float)$row['tax_percentage'],
                'primary_qty'=>0.0,
                'secondary_qty'=>0.0,
                'free_primary_qty'=>0.0,
                'free_secondary_qty'=>0.0,
                'primary_rate'=>0.0,
                'discount_type'=>(int)$row['discount_type'],
                'discount_value'=>(float)$row['discount_value'],
            ];
        }
        if((int)$row['unit_type']===1){
            $grouped[$productId]['primary_qty']=(float)$row['qty'];
            $grouped[$productId]['free_primary_qty']=(float)$row['free_qty'];
            $grouped[$productId]['primary_rate']=(float)$row['rate'];
        } elseif((int)$row['unit_type']===2){
            $grouped[$productId]['secondary_qty']=(float)$row['qty'];
            $grouped[$productId]['free_secondary_qty']=(float)$row['free_qty'];
            if($grouped[$productId]['primary_rate']<=0 && (float)$row['conversion_qty']>0){
                $grouped[$productId]['primary_rate']=round((float)$row['rate']/(float)$row['conversion_qty'],2);
            }
        }
    }
    return array_values($grouped);
}

function purchase_payment_details(int $purchaseId): array
{
    $hasDetailDate=false;
    $hasPaymentMode=false;
    try { $check=db()->query("SHOW COLUMNS FROM supplier_payment_details LIKE 'detail_date'"); $hasDetailDate=(bool)$check->fetch(); } catch(Throwable $exception) { $hasDetailDate=false; }
    try { $check=db()->query("SHOW COLUMNS FROM supplier_payment_details LIKE 'payment_mode'"); $hasPaymentMode=(bool)$check->fetch(); } catch(Throwable $exception) { $hasPaymentMode=false; }

    $sql='SELECT spd.account_id,a.account_type,spd.amount,spd.reference_no'.
        ($hasPaymentMode?',spd.payment_mode':',NULL AS payment_mode').
        ($hasDetailDate?',spd.detail_date':',NULL AS detail_date').
        ' FROM supplier_payment_allocations spa
          INNER JOIN supplier_payments sp ON sp.id=spa.supplier_payment_id AND sp.status=1
          INNER JOIN supplier_payment_details spd ON spd.supplier_payment_id=sp.id
          INNER JOIN accounts a ON a.id=spd.account_id
          WHERE spa.purchase_id=:purchase_id
          ORDER BY spd.id ASC';
    $stmt=db()->prepare($sql);
    $stmt->execute([':purchase_id'=>$purchaseId]);
    $rows=$stmt->fetchAll();
    $paymentModeMap=[1=>'cash',2=>'upi',3=>'bank',4=>'cheque'];
    $accountTypeMap=[1=>'cash',2=>'bank',3=>'upi'];
    $result=[];
    foreach($rows as $row){
        $modeKey=null;
        if($hasPaymentMode && $row['payment_mode']!==null) $modeKey=$paymentModeMap[(int)$row['payment_mode']]??null;
        if($modeKey===null) $modeKey=$accountTypeMap[(int)$row['account_type']]??'bank';
        $result[]=[
            'mode_key'=>$modeKey,
            'account_id'=>(int)$row['account_id'],
            'amount'=>(float)$row['amount'],
            'reference_no'=>(string)($row['reference_no']??''),
            'detail_date'=>$row['detail_date']?(string)$row['detail_date']:null,
        ];
    }
    return $result;
}

function purchase_payload(int $branchId, int $purchaseId): array
{
    return [
        'purchase' => purchase_record($branchId, $purchaseId),
        'items' => purchase_grouped_items($purchaseId),
        'payment_details' => purchase_payment_details($purchaseId),
    ];
}

function purchase_save_items(PDO $pdo, int $purchaseId, array $items): void
{
    $insert=$pdo->prepare(
        'INSERT INTO purchase_items
         (purchase_id,product_unit_id,qty,free_qty,conversion_qty,base_qty,free_base_qty,rate,gross_amount,discount_type,discount_value,discount_amount,overall_discount_amount,tax_percentage,tax_amount,other_charge_amount,net_amount)
         VALUES
         (:purchase_id,:product_unit_id,:qty,:free_qty,:conversion_qty,:base_qty,:free_base_qty,:rate,:gross_amount,:discount_type,:discount_value,:discount_amount,:overall_discount_amount,:tax_percentage,:tax_amount,:other_charge_amount,:net_amount)'
    );
    foreach($items as $item){
        $insert->execute([
            ':purchase_id'=>$purchaseId,
            ':product_unit_id'=>$item['product_unit_id'],
            ':qty'=>$item['qty'],
            ':free_qty'=>$item['free_qty'],
            ':conversion_qty'=>$item['conversion_qty'],
            ':base_qty'=>$item['base_qty'],
            ':free_base_qty'=>$item['free_base_qty'],
            ':rate'=>$item['rate'],
            ':gross_amount'=>$item['gross_amount'],
            ':discount_type'=>$item['discount_type'],
            ':discount_value'=>$item['discount_value'],
            ':discount_amount'=>$item['discount_amount'],
            ':overall_discount_amount'=>$item['overall_discount_amount'],
            ':tax_percentage'=>$item['tax_percentage'],
            ':tax_amount'=>$item['tax_amount'],
            ':other_charge_amount'=>$item['other_charge_amount'],
            ':net_amount'=>$item['net_amount'],
        ]);
    }
}

function purchase_post_stock(PDO $pdo, int $branchId, int $purchaseId, array $items, int $userId, string $purchaseDate): void
{
    $movementDate=$purchaseDate.' '.date('H:i:s');
    $grouped=[];
    foreach($items as $item){
        $productId=(int)$item['product_id'];
        if(!isset($grouped[$productId])) $grouped[$productId]=0.0;
        $grouped[$productId]+=(float)$item['base_qty']+(float)$item['free_base_qty'];
    }
    $insert=$pdo->prepare(
        'INSERT INTO stock_movements
         (branch_id,movement_date,product_id,movement_type,source_id,quantity_in,quantity_out,created_by,created_at)
         VALUES
         (:branch_id,:movement_date,:product_id,1,:source_id,:quantity_in,0,:created_by,NOW())'
    );
    foreach($grouped as $productId=>$quantity){
        $insert->execute([
            ':branch_id'=>$branchId,
            ':movement_date'=>$movementDate,
            ':product_id'=>$productId,
            ':source_id'=>$purchaseId,
            ':quantity_in'=>round($quantity,3),
            ':created_by'=>$userId,
        ]);
    }
}

function purchase_create_payment(PDO $pdo, int $branchId, int $purchaseId, int $supplierId, array $paymentRows, string $paymentDate, ?string $remarks, int $userId): int
{
    $totalAmount = 0.0;
    foreach ($paymentRows as $row) {
        $totalAmount += (float)$row['amount'];
    }
    $totalAmount = round($totalAmount, 2);

    if ($totalAmount <= 0) return 0;

    $paymentNo = supplier_payment_generate_no($branchId);

    $stmt = $pdo->prepare(
        'INSERT INTO supplier_payments
         (
            branch_id,
            payment_no,
            payment_date,
            supplier_id,
            amount,
            remarks,
            status,
            created_by,
            created_at
         )
         VALUES
         (
            :branch_id,
            :payment_no,
            :payment_date,
            :supplier_id,
            :amount,
            :remarks,
            1,
            :created_by,
            NOW()
         )'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':payment_no' => $paymentNo,
        ':payment_date' => $paymentDate,
        ':supplier_id' => $supplierId,
        ':amount' => $totalAmount,
        ':remarks' => $remarks,
        ':created_by' => $userId,
    ]);

    $paymentId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO supplier_payment_allocations
         (
            supplier_payment_id,
            purchase_id,
            amount
         )
         VALUES
         (
            :supplier_payment_id,
            :purchase_id,
            :amount
         )'
    )->execute([
        ':supplier_payment_id' => $paymentId,
        ':purchase_id' => $purchaseId,
        ':amount' => $totalAmount,
    ]);

    $hasDetailDate = false;
    $hasPaymentMode = false;

    try {
        $check = $pdo->query("SHOW COLUMNS FROM supplier_payment_details LIKE 'detail_date'");
        $hasDetailDate = (bool)$check->fetch();
    } catch (Throwable $exception) {
        $hasDetailDate = false;
    }

    try {
        $check = $pdo->query("SHOW COLUMNS FROM supplier_payment_details LIKE 'payment_mode'");
        $hasPaymentMode = (bool)$check->fetch();
    } catch (Throwable $exception) {
        $hasPaymentMode = false;
    }

    $detailColumns = 'supplier_payment_id, account_id, amount, reference_no';
    $detailValues = ':supplier_payment_id, :account_id, :amount, :reference_no';

    if ($hasPaymentMode) {
        $detailColumns .= ', payment_mode';
        $detailValues .= ', :payment_mode';
    }

    if ($hasDetailDate) {
        $detailColumns .= ', detail_date';
        $detailValues .= ', :detail_date';
    }

    $detailSql = 'INSERT INTO supplier_payment_details (' . $detailColumns . ') VALUES (' . $detailValues . ')';
    $detailInsert = $pdo->prepare($detailSql);

    $accountTxn = $pdo->prepare(
        'INSERT INTO account_transactions
         (
            branch_id,
            transaction_date,
            account_id,
            transaction_type,
            source_type,
            source_id,
            amount,
            remarks,
            created_by,
            created_at
         )
         VALUES
         (
            :branch_id,
            :transaction_date,
            :account_id,
            2,
            2,
            :source_id,
            :amount,
            :remarks,
            :created_by,
            NOW()
         )'
    );

    foreach ($paymentRows as $row) {
        $amount = round((float)$row['amount'], 2);
        if ($amount <= 0) continue;

        $params = [
            ':supplier_payment_id' => $paymentId,
            ':account_id' => (int)$row['account_id'],
            ':amount' => $amount,
            ':reference_no' => $row['reference_no'],
        ];

        if ($hasPaymentMode) {
            $params[':payment_mode'] = (int)$row['payment_mode'];
        }

        if ($hasDetailDate) {
            $params[':detail_date'] = $row['detail_date'];
        }

        $detailInsert->execute($params);

        $txnDate = $row['detail_date']
            ? $row['detail_date'] . ' ' . date('H:i:s')
            : $paymentDate . ' ' . date('H:i:s');

        $accountTxn->execute([
            ':branch_id' => $branchId,
            ':transaction_date' => $txnDate,
            ':account_id' => (int)$row['account_id'],
            ':source_id' => $paymentId,
            ':amount' => $amount,
            ':remarks' => $remarks,
            ':created_by' => $userId,
        ]);
    }

    return $paymentId;
}

function purchase_update_payment_status(PDO $pdo, int $purchaseId, float $grandTotal): void
{
    $paidAmount = purchase_paid_amount($purchaseId);
    $status = purchase_payment_status($grandTotal, $paidAmount);

    $pdo->prepare(
        'UPDATE purchases
         SET payment_status = :payment_status,
             updated_at = NOW()
         WHERE id = :id'
    )->execute([
        ':payment_status' => $status,
        ':id' => $purchaseId,
    ]);
}

function purchase_save(array $data, array $access, array $context, ?int $purchaseId = null): array
{
    $user = $access['user'];
    $branchId = (int)$context['branch_id'];
    $userId = (int)$user['id'];

    $intent = strtolower(trim((string)($data['intent'] ?? 'draft')));
    if (!in_array($intent, ['draft','post'], true)) {
        json_error('Invalid Purchase save action.', 422);
    }

    require_permission('purchase-list.php', $purchaseId === null ? ACTION_CREATE : ACTION_UPDATE);
    require_permission('purchase-list.php', $intent === 'post' ? ACTION_POST : ACTION_SAVE_DRAFT);

    $purchaseDate = purchase_date($data['purchase_date'] ?? '');
    $supplierId = (int)($data['supplier_id'] ?? 0);

    if ($supplierId < 1) {
        json_error('Purchase validation failed.', 422, ['supplier_id' => 'Select Supplier.']);
    }

    purchase_assert_supplier($branchId, $supplierId);

    $supplierInvoiceNo = purchase_nullable($data['supplier_invoice_no'] ?? null, 60);
    $paymentRemarks = purchase_nullable($data['payment_remarks'] ?? null, 255);

    $calculation = purchase_calculate($data, $branchId);
    $paymentData = purchase_parse_payment_details($data, $branchId);

    if ($intent === 'draft') {
        $paymentData = ['details' => [], 'total' => 0.0];
    }

    if ($intent === 'post' && $paymentData['total'] > $calculation['grand_total'] + 0.009) {
        json_error('Purchase validation failed.', 422, [
            'payment_details_json' => 'Split Paid Total cannot exceed Grand Total.',
        ]);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($purchaseId !== null) {
            $lock = $pdo->prepare(
                'SELECT status
                 FROM purchases
                 WHERE id = :id
                   AND branch_id = :branch_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $lock->execute([':id' => $purchaseId, ':branch_id' => $branchId]);
            $existingStatus = $lock->fetchColumn();

            if ($existingStatus === false) json_error('Purchase was not found.', 404);
            if ((int)$existingStatus !== 1) json_error('Only Draft Purchases can be edited or posted.', 409);
        }

        $status = $intent === 'post' ? 2 : 1;

        if ($purchaseId === null) {
            $purchaseNo = purchase_generate_no($branchId);

            $insert = $pdo->prepare(
                'INSERT INTO purchases
                 (
                    branch_id,
                    purchase_no,
                    purchase_date,
                    supplier_id,
                    supplier_invoice_no,
                    subtotal,
                    item_discount_total,
                    overall_discount_type,
                    overall_discount_value,
                    overall_discount_amount,
                    tax_amount,
                    other_charges,
                    round_off,
                    grand_total,
                    payment_status,
                    status,
                    created_by,
                    created_at,
                    updated_at
                 )
                 VALUES
                 (
                    :branch_id,
                    :purchase_no,
                    :purchase_date,
                    :supplier_id,
                    :supplier_invoice_no,
                    :subtotal,
                    :item_discount_total,
                    :overall_discount_type,
                    :overall_discount_value,
                    :overall_discount_amount,
                    :tax_amount,
                    :other_charges,
                    :round_off,
                    :grand_total,
                    1,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                 )'
            );

            $insert->execute([
                ':branch_id' => $branchId,
                ':purchase_no' => $purchaseNo,
                ':purchase_date' => $purchaseDate,
                ':supplier_id' => $supplierId,
                ':supplier_invoice_no' => $supplierInvoiceNo,
                ':subtotal' => $calculation['subtotal'],
                ':item_discount_total' => $calculation['item_discount_total'],
                ':overall_discount_type' => $calculation['overall_discount_type'],
                ':overall_discount_value' => $calculation['overall_discount_value'],
                ':overall_discount_amount' => $calculation['overall_discount_amount'],
                ':tax_amount' => $calculation['tax_amount'],
                ':other_charges' => $calculation['other_charges'],
                ':round_off' => $calculation['round_off'],
                ':grand_total' => $calculation['grand_total'],
                ':status' => $status,
                ':created_by' => $userId,
            ]);

            $purchaseId = (int)$pdo->lastInsertId();
        } else {
            $update = $pdo->prepare(
                'UPDATE purchases
                 SET purchase_date = :purchase_date,
                     supplier_id = :supplier_id,
                     supplier_invoice_no = :supplier_invoice_no,
                     subtotal = :subtotal,
                     item_discount_total = :item_discount_total,
                     overall_discount_type = :overall_discount_type,
                     overall_discount_value = :overall_discount_value,
                     overall_discount_amount = :overall_discount_amount,
                     tax_amount = :tax_amount,
                     other_charges = :other_charges,
                     round_off = :round_off,
                     grand_total = :grand_total,
                     payment_status = 1,
                     status = :status,
                     updated_at = NOW()
                 WHERE id = :id
                   AND branch_id = :branch_id'
            );

            $update->execute([
                ':purchase_date' => $purchaseDate,
                ':supplier_id' => $supplierId,
                ':supplier_invoice_no' => $supplierInvoiceNo,
                ':subtotal' => $calculation['subtotal'],
                ':item_discount_total' => $calculation['item_discount_total'],
                ':overall_discount_type' => $calculation['overall_discount_type'],
                ':overall_discount_value' => $calculation['overall_discount_value'],
                ':overall_discount_amount' => $calculation['overall_discount_amount'],
                ':tax_amount' => $calculation['tax_amount'],
                ':other_charges' => $calculation['other_charges'],
                ':round_off' => $calculation['round_off'],
                ':grand_total' => $calculation['grand_total'],
                ':status' => $status,
                ':id' => $purchaseId,
                ':branch_id' => $branchId,
            ]);

            $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = :purchase_id')
                ->execute([':purchase_id' => $purchaseId]);
        }

        purchase_save_items($pdo, $purchaseId, $calculation['flat_items']);

        if ($intent === 'post') {
            purchase_post_stock($pdo, $branchId, $purchaseId, $calculation['flat_items'], $userId, $purchaseDate);

            if ($paymentData['total'] > 0) {
                purchase_create_payment(
                    $pdo,
                    $branchId,
                    $purchaseId,
                    $supplierId,
                    $paymentData['details'],
                    $purchaseDate,
                    $paymentRemarks,
                    $userId
                );
            }

            purchase_update_payment_status($pdo, $purchaseId, $calculation['grand_total']);
        }

        audit_log(
            $userId,
            $intent === 'post' ? ACTION_POST : ACTION_SAVE_DRAFT,
            [
                'company_id' => (int)$context['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $purchaseId,
            ]
        );

        $pdo->commit();

        return [
            'purchase_id' => $purchaseId,
            'status' => $status,
        ];

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Purchase number or related transaction reference already exists.', 409);
        }

        throw $exception;
    }
}

function purchase_cancel(array $access, array $context, int $purchaseId): void
{
    $user = $access['user'];
    $branchId = (int)$context['branch_id'];
    $userId = (int)$user['id'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, status
             FROM purchases
             WHERE id = :id
               AND branch_id = :branch_id
             LIMIT 1
             FOR UPDATE'
        );

        $stmt->execute([':id' => $purchaseId, ':branch_id' => $branchId]);
        $purchase = $stmt->fetch();

        if (!$purchase) json_error('Purchase was not found.', 404);

        $status = (int)$purchase['status'];
        if ($status === 3) json_error('Purchase is already cancelled.', 409);

        $paid = purchase_paid_amount($purchaseId);
        if ($paid > 0.009) {
            json_error('This Purchase has Supplier Payment allocation. Cancel/reverse the payment first.', 409);
        }

        if ($status === 2) {
            $items = purchase_items($purchaseId);
            $required = [];

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                if (!isset($required[$productId])) $required[$productId] = 0.0;
                $required[$productId] += (float)$item['base_qty'] + (float)$item['free_base_qty'];
            }

            foreach ($required as $productId => $requiredQty) {
                $stockStmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(quantity_in - quantity_out), 0)
                     FROM stock_movements
                     WHERE branch_id = :branch_id
                       AND product_id = :product_id'
                );

                $stockStmt->execute([':branch_id' => $branchId, ':product_id' => $productId]);
                $available = round((float)$stockStmt->fetchColumn(), 3);

                if ($available + 0.0005 < round($requiredQty,3)) {
                    json_error('Purchase cannot be cancelled because some purchased stock has already been consumed or sold.', 409);
                }
            }

            $reverse = $pdo->prepare(
                'INSERT INTO stock_movements
                 (
                    branch_id,
                    movement_date,
                    product_id,
                    movement_type,
                    source_id,
                    quantity_in,
                    quantity_out,
                    created_by,
                    created_at
                 )
                 VALUES
                 (
                    :branch_id,
                    NOW(),
                    :product_id,
                    4,
                    :source_id,
                    0,
                    :quantity_out,
                    :created_by,
                    NOW()
                 )'
            );

            foreach ($required as $productId => $quantity) {
                $reverse->execute([
                    ':branch_id' => $branchId,
                    ':product_id' => $productId,
                    ':source_id' => $purchaseId,
                    ':quantity_out' => round($quantity,3),
                    ':created_by' => $userId,
                ]);
            }
        }

        $pdo->prepare(
            'UPDATE purchases
             SET status = 3,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        )->execute([':id' => $purchaseId, ':branch_id' => $branchId]);

        audit_log(
            $userId,
            ACTION_CANCEL,
            [
                'company_id' => (int)$context['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $purchaseId,
            ]
        );

        $pdo->commit();

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('purchase-list.php', ACTION_VIEW);
    $context = purchase_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $options = purchase_options($branchId);

    json_success('Purchase options loaded.', [
        'next_purchase_no' => purchase_generate_no($branchId),
        'suppliers' => $options['suppliers'],
        'products' => $options['products'],
        'accounts' => $options['accounts'],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('purchase-list.php', ACTION_VIEW);
    $context = purchase_context($access['user']);
    $purchaseId = purchase_id_from_ref($_GET['ref']);
    $payload = purchase_payload((int)$context['branch_id'], $purchaseId);
    $payload['allowed_actions'] = $access['actions'];
    json_success('Purchase loaded.', $payload);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('purchase-list.php', ACTION_VIEW);
    $context = purchase_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = max(1, (int)($_GET['draw'] ?? 1));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $statusFilter = (string)($_GET['status'] ?? '');

    $baseWhere = ['p.branch_id = :branch_id'];
    $where = $baseWhere;
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = '(p.purchase_no LIKE :search_purchase
              OR s.supplier_code LIKE :search_supplier_code
              OR s.supplier_name LIKE :search_supplier_name
              OR p.supplier_invoice_no LIKE :search_invoice)';

        $term = '%' . $search . '%';
        $params[':search_purchase'] = $term;
        $params[':search_supplier_code'] = $term;
        $params[':search_supplier_name'] = $term;
        $params[':search_invoice'] = $term;
    }

    if ($statusFilter !== '') {
        $status = purchase_enum($statusFilter, 'status', 'Status', [1,2,3]);
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    }

    $baseFrom = ' FROM purchases p INNER JOIN suppliers s ON s.id = p.supplier_id';

    $totalStmt = db()->prepare('SELECT COUNT(*)' . $baseFrom . ' WHERE ' . implode(' AND ', $baseWhere));
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare('SELECT COUNT(*)' . $baseFrom . ' WHERE ' . implode(' AND ', $where));
    $filteredStmt->execute($params);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $columns = [
        0 => 'p.purchase_no',
        1 => 'p.purchase_date',
        2 => 's.supplier_name',
        3 => 'p.supplier_invoice_no',
        4 => 'p.grand_total',
        5 => 'p.id',
        6 => 'p.id',
        7 => 'p.payment_status',
        8 => 'p.status',
        9 => 'p.id',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderColumn = $columns[$orderIndex] ?? 'p.purchase_date';

    $sql = 'SELECT p.id,
                   p.purchase_no,
                   p.purchase_date,
                   p.supplier_invoice_no,
                   p.grand_total,
                   p.status,
                   s.supplier_name,
                   COALESCE(
                     (
                       SELECT SUM(a.amount)
                       FROM supplier_payment_allocations a
                       INNER JOIN supplier_payments sp
                          ON sp.id = a.supplier_payment_id
                         AND sp.status = 1
                       WHERE a.purchase_id = p.id
                     ),
                     0
                   ) AS paid_amount' .
        $baseFrom .
        ' WHERE ' . implode(' AND ', $where) .
        ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ', p.id DESC
          LIMIT :start, :length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $type = ($key === ':branch_id' || $key === ':status') ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        $ref = encryptReference('purchase', $id);
        unset($row['id']);

        $row['grand_total'] = (float)$row['grand_total'];
        $row['paid_amount'] = (float)$row['paid_amount'];
        $row['balance_amount'] = max(0, round($row['grand_total'] - $row['paid_amount'], 2));
        $row['payment_status'] = purchase_payment_status($row['grand_total'], $row['paid_amount']);
        $row['status'] = (int)$row['status'];
        $row['ref'] = $ref;
        $row['edit_url'] = 'purchase-form.php?ref=' . $ref;
        $row['view_url'] = 'purchase-form.php?ref=' . $ref . '&view=1';
    }
    unset($row);

    json_success('Purchases loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('purchase-list.php', ACTION_CREATE);
    $context = purchase_context($access['user']);
    $result = purchase_save(request_data(), $access, $context, null);

    json_success(
        $result['status'] === 2 ? 'Purchase posted successfully.' : 'Purchase draft saved successfully.',
        purchase_payload((int)$context['branch_id'], (int)$result['purchase_id']),
        201
    );
}

if ($method === 'PUT') {
    $access = require_permission('purchase-list.php', ACTION_UPDATE);
    $context = purchase_context($access['user']);
    $data = request_data();
    $purchaseId = purchase_id_from_ref($data['ref'] ?? null);
    $result = purchase_save($data, $access, $context, $purchaseId);

    json_success(
        $result['status'] === 2 ? 'Purchase posted successfully.' : 'Purchase draft updated successfully.',
        purchase_payload((int)$context['branch_id'], $purchaseId)
    );
}

if ($method === 'PATCH') {
    $access = require_permission('purchase-list.php', ACTION_CANCEL);
    $context = purchase_context($access['user']);
    $data = request_data();

    if (($data['action'] ?? '') !== 'cancel') {
        json_error('Invalid Purchase action.', 422);
    }

    $purchaseId = purchase_id_from_ref($data['ref'] ?? null);
    purchase_cancel($access, $context, $purchaseId);
    json_success('Purchase cancelled successfully.');
}

json_error('Method not allowed.', 405);
