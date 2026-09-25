<?php
declare(strict_types=1);

require_once __DIR__ . '/_sales_common.php';

function dash_scalar(string $sql,array $params=[]): float {
    $stmt=db()->prepare($sql);$stmt->execute($params);return (float)$stmt->fetchColumn();
}
function dash_int(string $sql,array $params=[]): int {
    $stmt=db()->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn();
}
function dash_money(float $v): float { return round($v,2); }
function dash_qty(float $v): float { return round($v,3); }

function dash_date_series(int $days): array {
    $rows=[];$start=(new DateTimeImmutable('today'))->modify('-'.max(0,$days-1).' days');
    for($i=0;$i<$days;$i++){
        $d=$start->modify('+'.$i.' days');
        $rows[$d->format('Y-m-d')]=['date'=>$d->format('Y-m-d'),'label'=>$d->format('d M'),'sales'=>0.0,'purchase'=>0.0,'stock_in'=>0.0,'stock_out'=>0.0];
    }
    return $rows;
}

function dash_month_series(int $months): array {
    $rows=[];$start=(new DateTimeImmutable('first day of this month'))->modify('-'.max(0,$months-1).' months');
    for($i=0;$i<$months;$i++){
        $d=$start->modify('+'.$i.' months');$key=$d->format('Y-m');
        $rows[$key]=['month'=>$key,'label'=>$d->format('M Y'),'sales'=>0.0,'purchase'=>0.0];
    }
    return $rows;
}

function dash_daily_sales_purchase(int $branchId,int $days=7): array {
    $series=dash_date_series($days);$from=array_key_first($series);$to=array_key_last($series);
    $stmt=db()->prepare('SELECT sale_date d,COALESCE(SUM(grand_total),0) amount FROM sales WHERE branch_id=:b AND document_type=2 AND status=2 AND sale_date BETWEEN :f AND :t GROUP BY sale_date');
    $stmt->execute([':b'=>$branchId,':f'=>$from,':t'=>$to]);
    foreach($stmt->fetchAll() as $r){$k=(string)$r['d'];if(isset($series[$k]))$series[$k]['sales']=dash_money((float)$r['amount']);}
    $stmt=db()->prepare('SELECT purchase_date d,COALESCE(SUM(grand_total),0) amount FROM purchases WHERE branch_id=:b AND status=2 AND purchase_date BETWEEN :f AND :t GROUP BY purchase_date');
    $stmt->execute([':b'=>$branchId,':f'=>$from,':t'=>$to]);
    foreach($stmt->fetchAll() as $r){$k=(string)$r['d'];if(isset($series[$k]))$series[$k]['purchase']=dash_money((float)$r['amount']);}
    return array_values($series);
}

function dash_monthly_sales_purchase(int $branchId,int $months=6): array {
    $series=dash_month_series($months);$from=array_key_first($series).'-01';$to=(new DateTimeImmutable('first day of next month'))->format('Y-m-d');
    $stmt=db()->prepare("SELECT DATE_FORMAT(sale_date,'%Y-%m') ym,COALESCE(SUM(grand_total),0) amount FROM sales WHERE branch_id=:b AND document_type=2 AND status=2 AND sale_date>=:f AND sale_date<:t GROUP BY DATE_FORMAT(sale_date,'%Y-%m')");
    $stmt->execute([':b'=>$branchId,':f'=>$from,':t'=>$to]);foreach($stmt->fetchAll() as $r){$k=(string)$r['ym'];if(isset($series[$k]))$series[$k]['sales']=dash_money((float)$r['amount']);}
    $stmt=db()->prepare("SELECT DATE_FORMAT(purchase_date,'%Y-%m') ym,COALESCE(SUM(grand_total),0) amount FROM purchases WHERE branch_id=:b AND status=2 AND purchase_date>=:f AND purchase_date<:t GROUP BY DATE_FORMAT(purchase_date,'%Y-%m')");
    $stmt->execute([':b'=>$branchId,':f'=>$from,':t'=>$to]);foreach($stmt->fetchAll() as $r){$k=(string)$r['ym'];if(isset($series[$k]))$series[$k]['purchase']=dash_money((float)$r['amount']);}
    return array_values($series);
}

function dash_stock_flow(int $branchId,int $days=7): array {
    $series=dash_date_series($days);$from=array_key_first($series).' 00:00:00';$to=(new DateTimeImmutable(array_key_last($series)))->modify('+1 day')->format('Y-m-d').' 00:00:00';
    $stmt=db()->prepare('SELECT DATE(movement_date) d,COALESCE(SUM(quantity_in),0) stock_in,COALESCE(SUM(quantity_out),0) stock_out FROM stock_movements WHERE branch_id=:b AND movement_date>=:f AND movement_date<:t GROUP BY DATE(movement_date)');
    $stmt->execute([':b'=>$branchId,':f'=>$from,':t'=>$to]);
    foreach($stmt->fetchAll() as $r){$k=(string)$r['d'];if(isset($series[$k])){$series[$k]['stock_in']=dash_qty((float)$r['stock_in']);$series[$k]['stock_out']=dash_qty((float)$r['stock_out']);}}
    return array_values($series);
}

function dash_top_products(int $branchId): array {
    $stmt=db()->prepare('SELECT p.product_code,p.product_name,COALESCE(SUM(si.base_qty),0) sold_base_qty,COALESCE(SUM(si.net_amount),0) sales_amount FROM sales s INNER JOIN sales_items si ON si.sale_id=s.id INNER JOIN products p ON p.id=si.product_id WHERE s.branch_id=:b AND s.document_type=2 AND s.status=2 AND s.sale_date>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY p.id,p.product_code,p.product_name ORDER BY sold_base_qty DESC,sales_amount DESC LIMIT 5');
    $stmt->execute([':b'=>$branchId]);$rows=[];
    foreach($stmt->fetchAll() as $r)$rows[]=['product_code'=>(string)$r['product_code'],'product_name'=>(string)$r['product_name'],'sold_base_qty'=>dash_qty((float)$r['sold_base_qty']),'sales_amount'=>dash_money((float)$r['sales_amount'])];
    return $rows;
}

function dash_sales_mix(int $branchId): array {
    $stmt=db()->prepare('SELECT tax_mode,COUNT(*) invoice_count,COALESCE(SUM(grand_total),0) amount FROM sales WHERE branch_id=:b AND document_type=2 AND status=2 AND sale_date>=DATE_FORMAT(CURDATE(),"%Y-%m-01") GROUP BY tax_mode');
    $stmt->execute([':b'=>$branchId]);$out=['gst'=>['count'=>0,'amount'=>0.0],'non_gst'=>['count'=>0,'amount'=>0.0]];
    foreach($stmt->fetchAll() as $r){$k=(int)$r['tax_mode']===1?'gst':'non_gst';$out[$k]=['count'=>(int)$r['invoice_count'],'amount'=>dash_money((float)$r['amount'])];}
    return $out;
}

function dash_customer_outstanding(int $branchId): float {
    $opening=dash_scalar('SELECT COALESCE(SUM(opening_balance),0) FROM customers WHERE branch_id=:b AND status=1',[':b'=>$branchId]);
    $sales=dash_scalar('SELECT COALESCE(SUM(balance_amount),0) FROM sales WHERE branch_id=:b AND document_type=2 AND status=2',[':b'=>$branchId]);
    return dash_money($opening+$sales);
}

function dash_supplier_outstanding(int $branchId): float {
    $stmt=db()->prepare('SELECT COALESCE((SELECT SUM(opening_balance) FROM suppliers WHERE branch_id=:b1 AND status=1),0)-COALESCE((SELECT SUM(spa.amount) FROM supplier_payment_allocations spa INNER JOIN supplier_payments sp ON sp.id=spa.supplier_payment_id AND sp.status=1 INNER JOIN suppliers s ON s.id=sp.supplier_id AND s.branch_id=:b2 WHERE spa.allocation_type=2),0)+COALESCE((SELECT SUM(grand_total) FROM purchases WHERE branch_id=:b3 AND status=2),0)-COALESCE((SELECT SUM(spa.amount) FROM supplier_payment_allocations spa INNER JOIN supplier_payments sp ON sp.id=spa.supplier_payment_id AND sp.status=1 INNER JOIN purchases p ON p.id=spa.purchase_id AND p.branch_id=:b4 AND p.status=2 WHERE spa.allocation_type=1),0)');
    $stmt->execute([':b1'=>$branchId,':b2'=>$branchId,':b3'=>$branchId,':b4'=>$branchId]);return dash_money(max(0,(float)$stmt->fetchColumn()));
}

function dash_account_balance(int $branchId): float {
    return dash_money(dash_scalar('SELECT COALESCE(SUM(CASE WHEN transaction_type=1 THEN amount WHEN transaction_type=2 THEN -amount ELSE 0 END),0) FROM account_transactions WHERE branch_id=:b',[':b'=>$branchId]));
}

function dash_pending_orders(int $branchId): int {
    return dash_int('SELECT COUNT(*) FROM (SELECT s.id,COALESCE(SUM(si.ordered_base_qty),0) ordered_qty,COALESCE(SUM(si.delivered_base_qty),0) delivered_qty FROM sales s LEFT JOIN sales_items si ON si.sale_id=s.id WHERE s.branch_id=:b AND s.document_type=3 AND s.status=2 GROUP BY s.id HAVING ordered_qty-delivered_qty>0.0005) x',[':b'=>$branchId]);
}

function dash_recent_activity(int $branchId): array {
    $rows=[];$sources=[
        ['type'=>'sale','title'=>'Sales Invoice','sql'=>'SELECT id,sale_no ref_no,sale_date d,grand_total amount,created_at FROM sales WHERE branch_id=:b AND document_type=2 AND status=2 ORDER BY created_at DESC LIMIT 5'],
        ['type'=>'purchase','title'=>'Purchase','sql'=>'SELECT id,purchase_no ref_no,purchase_date d,grand_total amount,created_at FROM purchases WHERE branch_id=:b AND status=2 ORDER BY created_at DESC LIMIT 5'],
        ['type'=>'production','title'=>'Production','sql'=>'SELECT id,production_no ref_no,production_date d,0 amount,created_at FROM production WHERE branch_id=:b AND status=2 ORDER BY created_at DESC LIMIT 4'],
        ['type'=>'expense','title'=>'Expense','sql'=>'SELECT id,expense_name ref_no,expense_date d,amount,created_at FROM expenses WHERE branch_id=:b AND status=1 ORDER BY created_at DESC LIMIT 4'],
    ];
    foreach($sources as $cfg){$stmt=db()->prepare($cfg['sql']);$stmt->execute([':b'=>$branchId]);foreach($stmt->fetchAll() as $r){$id=(int)$r['id'];$url='';if($cfg['type']==='sale')$url='sales-form.php?ref='.rawurlencode(encryptReference('sale',$id));elseif($cfg['type']==='purchase')$url='purchase-form.php?ref='.rawurlencode(encryptReference('purchase',$id));elseif($cfg['type']==='production')$url='production-form.php?ref='.rawurlencode(encryptReference('production',$id));$rows[]=['type'=>$cfg['type'],'title'=>$cfg['title'],'ref_no'=>(string)$r['ref_no'],'date'=>(string)$r['d'],'amount'=>dash_money((float)$r['amount']),'created_at'=>(string)$r['created_at'],'url'=>$url];}}
    usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return array_slice($rows,0,10);
}

function dash_recent_trips(int $branchId): array {
    $stmt=db()->prepare('SELECT ls.id,ls.supply_no,ls.supply_date,ls.status,v.vehicle_no,l.line_name,COALESCE((SELECT SUM(x.loaded_base_qty) FROM line_supply_items x WHERE x.line_supply_id=ls.id),0) loaded_qty,COALESCE((SELECT SUM(si.base_qty) FROM sales s INNER JOIN sales_items si ON si.sale_id=s.id WHERE s.line_supply_id=ls.id AND s.document_type=2 AND s.sale_type=3 AND s.status=2),0) sold_qty FROM line_supplies ls INNER JOIN vehicles v ON v.id=ls.vehicle_id INNER JOIN `lines` l ON l.id=ls.line_id WHERE ls.branch_id=:b AND ls.status IN (2,3,4) ORDER BY ls.updated_at DESC,ls.id DESC LIMIT 6');
    $stmt->execute([':b'=>$branchId]);$labels=[2=>'Loaded',3=>'In Route',4=>'Returned'];$rows=[];
    foreach($stmt->fetchAll() as $r){$id=(int)$r['id'];$rows[]=['supply_no'=>(string)$r['supply_no'],'supply_date'=>(string)$r['supply_date'],'status'=>(int)$r['status'],'status_label'=>$labels[(int)$r['status']]??'Trip','vehicle_no'=>(string)$r['vehicle_no'],'line_name'=>(string)$r['line_name'],'loaded_qty'=>dash_qty((float)$r['loaded_qty']),'sold_qty'=>dash_qty((float)$r['sold_qty']),'balance_qty'=>dash_qty(max(0,(float)$r['loaded_qty']-(float)$r['sold_qty'])),'url'=>'line-supply-form.php?ref='.rawurlencode(encryptReference('line_supply',$id)),'return_url'=>'line-return-form.php?ref='.rawurlencode(encryptReference('line_supply',$id))];}
    return $rows;
}

function dash_outstanding_customers(int $branchId): array {
    $stmt=db()->prepare('SELECT c.customer_code,c.customer_name,c.opening_balance+COALESCE((SELECT SUM(s.balance_amount) FROM sales s WHERE s.customer_id=c.id AND s.branch_id=c.branch_id AND s.document_type=2 AND s.status=2),0) outstanding FROM customers c WHERE c.branch_id=:b AND c.status=1 HAVING outstanding>0.005 ORDER BY outstanding DESC,c.customer_name LIMIT 6');
    $stmt->execute([':b'=>$branchId]);$rows=[];foreach($stmt->fetchAll() as $r)$rows[]=['customer_code'=>(string)$r['customer_code'],'customer_name'=>(string)$r['customer_name'],'outstanding'=>dash_money((float)$r['outstanding'])];return $rows;
}

function dash_stock_warnings(int $branchId): array {
    $stmt=db()->prepare('SELECT p.product_code,p.product_name,COALESCE(SUM(sm.quantity_in-sm.quantity_out),0) stock_qty FROM products p LEFT JOIN stock_movements sm ON sm.product_id=p.id AND sm.branch_id=p.branch_id WHERE p.branch_id=:b AND p.status=1 GROUP BY p.id,p.product_code,p.product_name HAVING stock_qty<=0.0005 ORDER BY stock_qty ASC,p.product_name LIMIT 6');
    $stmt->execute([':b'=>$branchId]);$rows=[];foreach($stmt->fetchAll() as $r)$rows[]=['product_code'=>(string)$r['product_code'],'product_name'=>(string)$r['product_name'],'stock_qty'=>dash_qty((float)$r['stock_qty'])];return $rows;
}

$access=require_permission('dashboard.php',ACTION_VIEW);
$context=aqua_sales_context($access['user']);$branchId=(int)$context['branch_id'];$today=date('Y-m-d');$monthStart=date('Y-m-01');

$kpis=[
    'today_sales'=>dash_money(dash_scalar('SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE branch_id=:b AND document_type=2 AND status=2 AND sale_date=:d',[':b'=>$branchId,':d'=>$today])),
    'today_purchase'=>dash_money(dash_scalar('SELECT COALESCE(SUM(grand_total),0) FROM purchases WHERE branch_id=:b AND status=2 AND purchase_date=:d',[':b'=>$branchId,':d'=>$today])),
    'today_collection'=>dash_money(dash_scalar('SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE branch_id=:b AND status=1 AND payment_date=:d',[':b'=>$branchId,':d'=>$today])),
    'today_expense'=>dash_money(dash_scalar('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE branch_id=:b AND status=1 AND expense_date=:d',[':b'=>$branchId,':d'=>$today])),
    'customer_outstanding'=>dash_customer_outstanding($branchId),
    'supplier_outstanding'=>dash_supplier_outstanding($branchId),
    'account_balance'=>dash_account_balance($branchId),
    'plant_stock_base'=>dash_qty(dash_scalar('SELECT COALESCE(SUM(quantity_in-quantity_out),0) FROM stock_movements WHERE branch_id=:b',[':b'=>$branchId])),
    'month_sales'=>dash_money(dash_scalar('SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE branch_id=:b AND document_type=2 AND status=2 AND sale_date>=:d',[':b'=>$branchId,':d'=>$monthStart])),
    'month_purchase'=>dash_money(dash_scalar('SELECT COALESCE(SUM(grand_total),0) FROM purchases WHERE branch_id=:b AND status=2 AND purchase_date>=:d',[':b'=>$branchId,':d'=>$monthStart])),
];

$counts=[
    'customers'=>dash_int('SELECT COUNT(*) FROM customers WHERE branch_id=:b AND status=1',[':b'=>$branchId]),
    'suppliers'=>dash_int('SELECT COUNT(*) FROM suppliers WHERE branch_id=:b AND status=1',[':b'=>$branchId]),
    'products'=>dash_int('SELECT COUNT(*) FROM products WHERE branch_id=:b AND status=1',[':b'=>$branchId]),
    'pending_orders'=>dash_pending_orders($branchId),
    'active_trips'=>dash_int('SELECT COUNT(*) FROM line_supplies WHERE branch_id=:b AND status IN (2,3)',[':b'=>$branchId]),
    'returned_pending_close'=>dash_int('SELECT COUNT(*) FROM line_supplies WHERE branch_id=:b AND status=4',[':b'=>$branchId]),
];

json_success('Dashboard loaded.',[
    'context'=>$context,
    'user'=>['id'=>(int)$access['user']['id'],'name'=>(string)($access['user']['name']??''),'username'=>(string)($access['user']['username']??'')],
    'kpis'=>$kpis,
    'counts'=>$counts,
    'charts'=>[
        'daily_sales_purchase'=>dash_daily_sales_purchase($branchId,7),
        'monthly_sales_purchase'=>dash_monthly_sales_purchase($branchId,6),
        'stock_flow'=>dash_stock_flow($branchId,7),
        'top_products'=>dash_top_products($branchId),
        'sales_mix'=>dash_sales_mix($branchId),
    ],
    'recent_activity'=>dash_recent_activity($branchId),
    'active_trips'=>dash_recent_trips($branchId),
    'top_outstanding_customers'=>dash_outstanding_customers($branchId),
    'stock_warnings'=>dash_stock_warnings($branchId),
    'allowed_actions'=>$access['actions'],
    'generated_at'=>date('Y-m-d H:i:s'),
]);
