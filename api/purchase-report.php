<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);

function purchase_report_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Purchase Report is available only for tenant users.', 403);
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
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
    ];
}

function purchase_report_ref_to_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value)==='') {
        json_error($label . ' reference is required.', 422);
    }

    try {
        $id=(int)decryptReference(trim($value),$purpose);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    if ($id<1) json_error('Invalid ' . $label . ' reference.', 422);
    return $id;
}

function purchase_report_date($value, string $label): ?string
{
    $value=trim((string)($value??''));
    if ($value==='') return null;

    $date=DateTime::createFromFormat('Y-m-d',$value);
    $errors=DateTime::getLastErrors();
    if (!$date || ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) || $date->format('Y-m-d')!==$value) {
        json_error('Enter a valid ' . $label . '.', 422);
    }
    return $value;
}

function purchase_report_has_column(string $table,string $column): bool
{
    try {
        $stmt=db()->query('SHOW COLUMNS FROM `' . str_replace('`','',$table) . '` LIKE ' . db()->quote($column));
        return $stmt && (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function purchase_report_suppliers(int $branchId): array
{
    $stmt=db()->prepare(
        'SELECT id,supplier_code,supplier_name
         FROM suppliers
         WHERE branch_id=:branch_id AND status=1
         ORDER BY supplier_name,supplier_code'
    );
    $stmt->execute([':branch_id'=>$branchId]);

    $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[]=[
            'id'=>(int)$row['id'],
            'supplier_code'=>(string)$row['supplier_code'],
            'supplier_name'=>(string)$row['supplier_name'],
            'ref'=>encryptReference('supplier',(int)$row['id']),
        ];
    }
    return $rows;
}

function purchase_report_payment_join(): string
{
    $discountExpr=purchase_report_has_column('supplier_payment_allocations','discount_amount')
        ? 'COALESCE(spa.discount_amount,0)'
        : '0';

    return ' LEFT JOIN (
                SELECT spa.purchase_id,
                       SUM(spa.amount) AS settled_amount,
                       SUM(' . $discountExpr . ') AS settlement_discount
                FROM supplier_payment_allocations spa
                INNER JOIN supplier_payments sp
                        ON sp.id=spa.supplier_payment_id
                       AND sp.status=1
                WHERE spa.purchase_id IS NOT NULL
                GROUP BY spa.purchase_id
              ) pay ON pay.purchase_id=p.id ';
}

function purchase_report_purchase_actions(array $user): array
{
    try {
        $menu=menu_by_path('purchase-list.php');
        if (!$menu) return [];
        return array_map('intval',effective_actions_for_menu($user,$menu));
    } catch (Throwable $e) {
        return [];
    }
}

$method=request_method();
if ($method!=='GET') json_error('Method not allowed.',405);

$access=require_permission('purchase-report.php',ACTION_VIEW);
$context=purchase_report_context($access['user']);
$branchId=(int)$context['branch_id'];

if (isset($_GET['options'])) {
    json_success('Purchase Report options loaded.',[
        'suppliers'=>purchase_report_suppliers($branchId),
        'allowed_actions'=>$access['actions'],
    ]);
}

if (!isset($_GET['datatable'])) {
    json_error('Invalid Purchase Report request.',422);
}

$draw=max(0,(int)($_GET['draw']??0));
$start=max(0,(int)($_GET['start']??0));
$length=max(1,min(100000,(int)($_GET['length']??25)));
$search=trim((string)($_GET['search']['value']??''));
$statusRaw=trim((string)($_GET['status']??''));
$paymentStatusRaw=trim((string)($_GET['payment_status']??''));
$dateFrom=purchase_report_date($_GET['date_from']??null,'From Date');
$dateTo=purchase_report_date($_GET['date_to']??null,'To Date');

if ($dateFrom!==null && $dateTo!==null && $dateFrom>$dateTo) {
    json_error('From Date cannot be after To Date.',422);
}

$where=['p.branch_id=:branch_id'];
$params=[':branch_id'=>$branchId];

if ($search!=='') {
    $like='%'.$search.'%';
    $where[]='(p.purchase_no LIKE :search_purchase
               OR p.supplier_invoice_no LIKE :search_invoice
               OR s.supplier_code LIKE :search_supplier_code
               OR s.supplier_name LIKE :search_supplier_name)';
    $params[':search_purchase']=$like;
    $params[':search_invoice']=$like;
    $params[':search_supplier_code']=$like;
    $params[':search_supplier_name']=$like;
}

if (!empty($_GET['supplier_ref'])) {
    $supplierId=purchase_report_ref_to_id($_GET['supplier_ref'],'supplier','Supplier');
    $where[]='p.supplier_id=:supplier_id';
    $params[':supplier_id']=$supplierId;
}

if ($statusRaw!=='') {
    $status=(int)$statusRaw;
    if (!in_array($status,[1,2,3],true)) json_error('Invalid Purchase Status.',422);
    $where[]='p.status=:status';
    $params[':status']=$status;
}

if ($dateFrom!==null) {
    $where[]='p.purchase_date>=:date_from';
    $params[':date_from']=$dateFrom;
}
if ($dateTo!==null) {
    $where[]='p.purchase_date<=:date_to';
    $params[':date_to']=$dateTo;
}

$settledExpr='COALESCE(pay.settled_amount,0)';
$discountExpr='COALESCE(pay.settlement_discount,0)';
$actualPaidExpr='GREATEST(0,' . $settledExpr . '-' . $discountExpr . ')';
$outstandingExpr='GREATEST(0,p.grand_total-' . $settledExpr . ')';

if ($paymentStatusRaw!=='') {
    $paymentStatus=(int)$paymentStatusRaw;
    if (!in_array($paymentStatus,[1,2,3],true)) json_error('Invalid Payment Status.',422);

    if ($paymentStatus===1) {
        $where[]=$settledExpr . '<=0.009';
    } elseif ($paymentStatus===2) {
        $where[]=$settledExpr . '>0.009 AND ' . $settledExpr . '+0.009<p.grand_total';
    } else {
        $where[]=$settledExpr . '+0.009>=p.grand_total';
    }
}

$from=' FROM purchases p
        INNER JOIN suppliers s
                ON s.id=p.supplier_id
               AND s.branch_id=p.branch_id ' . purchase_report_payment_join();

$totalStmt=db()->prepare('SELECT COUNT(*) FROM purchases p WHERE p.branch_id=:branch_id');
$totalStmt->execute([':branch_id'=>$branchId]);
$recordsTotal=(int)$totalStmt->fetchColumn();

$countStmt=db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ',$where));
$countStmt->execute($params);
$recordsFiltered=(int)$countStmt->fetchColumn();

$summarySql='SELECT COUNT(*) AS purchase_count,
                    COALESCE(SUM(p.grand_total),0) AS grand_total,
                    COALESCE(SUM(' . $actualPaidExpr . '),0) AS actual_paid,
                    COALESCE(SUM(' . $discountExpr . '),0) AS settlement_discount,
                    COALESCE(SUM(' . $outstandingExpr . '),0) AS outstanding
             ' . $from . '
             WHERE ' . implode(' AND ',$where);
$summaryStmt=db()->prepare($summarySql);
$summaryStmt->execute($params);
$summary=$summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$columns=[
    0=>'p.purchase_no',
    1=>'s.supplier_name',
    2=>'p.grand_total',
    3=>$actualPaidExpr,
    4=>$discountExpr,
    5=>$outstandingExpr,
    6=>$settledExpr,
    7=>'p.status',
    8=>'p.id',
    9=>'p.purchase_date',
    10=>'p.supplier_invoice_no',
    11=>'p.subtotal',
    12=>'(p.item_discount_total+p.overall_discount_amount)',
    13=>'p.tax_amount',
    14=>'p.other_charges',
    15=>'p.round_off',
];

$orderIndex=(int)($_GET['order'][0]['column']??1);
$orderDir=strtolower((string)($_GET['order'][0]['dir']??'desc'))==='asc'?'ASC':'DESC';
$orderColumn=$columns[$orderIndex]??'p.purchase_date';

$sql='SELECT p.id,
             p.purchase_no,
             p.purchase_date,
             p.supplier_invoice_no,
             p.subtotal,
             p.item_discount_total,
             p.overall_discount_amount,
             p.tax_amount,
             p.other_charges,
             p.round_off,
             p.grand_total,
             p.status,
             s.supplier_code,
             s.supplier_name,
             ' . $settledExpr . ' AS settled_amount,
             ' . $discountExpr . ' AS settlement_discount,
             ' . $actualPaidExpr . ' AS actual_paid,
             ' . $outstandingExpr . ' AS outstanding
      ' . $from . '
      WHERE ' . implode(' AND ',$where) . '
      ORDER BY ' . $orderColumn . ' ' . $orderDir . ',p.id DESC
      LIMIT :start,:length';

$stmt=db()->prepare($sql);
foreach ($params as $key=>$value) {
    $type=in_array($key,[':branch_id',':supplier_id',':status'],true)?PDO::PARAM_INT:PDO::PARAM_STR;
    $stmt->bindValue($key,$value,$type);
}
$stmt->bindValue(':start',$start,PDO::PARAM_INT);
$stmt->bindValue(':length',$length,PDO::PARAM_INT);
$stmt->execute();

$purchaseActions=purchase_report_purchase_actions($access['user']);
$canViewPurchase=in_array(ACTION_VIEW,$purchaseActions,true);

$rows=[];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id=(int)$row['id'];
    $grand=round((float)$row['grand_total'],2);
    $settled=round((float)$row['settled_amount'],2);
    $paymentStatus=1;
    if ($settled>0.009 && $settled+0.009<$grand) $paymentStatus=2;
    elseif ($grand<=0.009 || $settled+0.009>=$grand) $paymentStatus=3;

    $ref=encryptReference('purchase',$id);
    $rows[]=[
        'purchase_no'=>(string)$row['purchase_no'],
        'purchase_date'=>(string)$row['purchase_date'],
        'supplier_label'=>(string)$row['supplier_code'] . ' - ' . (string)$row['supplier_name'],
        'supplier_invoice_no'=>(string)($row['supplier_invoice_no']??''),
        'subtotal'=>(float)$row['subtotal'],
        'purchase_discount'=>round((float)$row['item_discount_total']+(float)$row['overall_discount_amount'],2),
        'tax_amount'=>(float)$row['tax_amount'],
        'other_charges'=>(float)$row['other_charges'],
        'round_off'=>(float)$row['round_off'],
        'grand_total'=>$grand,
        'actual_paid'=>(float)$row['actual_paid'],
        'settlement_discount'=>(float)$row['settlement_discount'],
        'settled_amount'=>$settled,
        'outstanding'=>(float)$row['outstanding'],
        'payment_status'=>$paymentStatus,
        'status'=>(int)$row['status'],
        'ref'=>$ref,
        'view_url'=>$canViewPurchase?'purchase-form.php?ref='.rawurlencode($ref).'&view=1':null,
    ];
}

json_success('Purchase Report loaded.',[
    'datatable'=>[
        'draw'=>$draw,
        'recordsTotal'=>$recordsTotal,
        'recordsFiltered'=>$recordsFiltered,
        'data'=>$rows,
    ],
    'summary'=>[
        'purchase_count'=>(int)($summary['purchase_count']??0),
        'grand_total'=>(float)($summary['grand_total']??0),
        'actual_paid'=>(float)($summary['actual_paid']??0),
        'settlement_discount'=>(float)($summary['settlement_discount']??0),
        'outstanding'=>(float)($summary['outstanding']??0),
    ],
    'allowed_actions'=>$access['actions'],
]);
