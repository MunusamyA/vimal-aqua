<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_CANCEL')) define('ACTION_CANCEL', 14);

const EXPENSE_PERMISSION_PATH = 'expense-list.php';

function expense_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Expense management is available only for tenant users.', 403);
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
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.',403);

    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
    ];
}

function expense_require_schema(): void
{
    foreach (['expense_names','expense_payment_details'] as $table) {
        $stmt=db()->query('SHOW TABLES LIKE '.db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error('Expense database update is required. Run expense-split-payment-migration.sql first. Missing table: '.$table.'.',500);
        }
    }
}

function expense_nullable($value,int $max=0): ?string
{
    $value=trim((string)($value??''));
    if ($value==='') return null;
    if ($max>0 && mb_strlen($value)>$max) json_error('Entered value is too long.',422);
    return $value;
}

function expense_valid_date($value,string $field,string $label,bool $required=true): ?string
{
    $value=trim((string)($value??''));
    if ($value==='') {
        if (!$required) return null;
        json_error($label.' is required.',422,[$field=>$label.' is required.']);
    }
    $d=DateTime::createFromFormat('Y-m-d',$value);
    $errors=DateTime::getLastErrors();
    if (!$d || ($errors && ((int)$errors['warning_count']>0 || (int)$errors['error_count']>0)) || $d->format('Y-m-d')!==$value) {
        json_error('Enter a valid '.$label.'.',422,[$field=>'Enter a valid '.$label.'.']);
    }
    return $value;
}

function expense_money($value,string $field,string $label,bool $required=true): float
{
    $raw=trim((string)($value??''));
    if ($raw==='') {
        if (!$required) return 0.0;
        json_error($label.' is required.',422,[$field=>$label.' is required.']);
    }
    $raw=str_replace(',','',$raw);
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/',$raw)) {
        json_error('Enter a valid '.$label.'.',422,[$field=>'Enter a valid '.$label.'.']);
    }
    return round((float)$raw,2);
}

function expense_id_from_ref($value): int
{
    if (!is_string($value) || trim($value)==='') json_error('Expense reference is required.',422,['ref'=>'Expense reference is required.']);
    try { $id=(int)decryptReference(trim($value),'expense'); }
    catch(Throwable $e){ json_error('Invalid Expense reference.',422,['ref'=>'Invalid Expense reference.']); }
    if ($id<1) json_error('Invalid Expense reference.',422);
    return $id;
}

function expense_account_id($value): int
{
    $id=(int)$value;
    if ($id<1) json_error('Select a Payment Account.',422,['payment_details_json'=>'Select an Account for every entered Payment Amount.']);
    return $id;
}

function expense_accounts(int $branchId): array
{
    $stmt=db()->prepare(
        'SELECT id,account_code,account_name,account_type
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name,id'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $labels=[1=>'Cash',2=>'Bank',3=>'UPI',4=>'Card',5=>'Other'];
    $rows=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $type=(int)$row['account_type'];
        $rows[]=[
            'id'=>(int)$row['id'],
            'account_code'=>(string)$row['account_code'],
            'account_name'=>(string)$row['account_name'],
            'account_type'=>$type,
            'account_type_label'=>$labels[$type]??'Other',
            'label'=>(string)$row['account_code'].' - '.(string)$row['account_name'],
        ];
    }
    return $rows;
}

function expense_assert_account(int $branchId,int $accountId): array
{
    $stmt=db()->prepare(
        'SELECT id,account_code,account_name,account_type
         FROM accounts
         WHERE id=:id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $stmt->execute([':id'=>$accountId,':branch_id'=>$branchId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) json_error('Selected Payment Account is unavailable.',422,['payment_details_json'=>'Select an active Payment Account.']);
    return $row;
}

function expense_names(int $branchId,?string $includeName=null): array
{
    $stmt=db()->prepare(
        'SELECT expense_name
         FROM expense_names
         WHERE branch_id=:branch_id AND status=1
         ORDER BY expense_name'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $names=array_values(array_filter(array_map(static fn($v)=>trim((string)$v),$stmt->fetchAll(PDO::FETCH_COLUMN))));
    if($includeName!==null && trim($includeName)!==''){
        $includeName=trim($includeName);
        $found=false;
        foreach($names as $name){if(mb_strtolower($name)===mb_strtolower($includeName)){$found=true;break;}}
        if(!$found)$names[]=$includeName;
        natcasesort($names);
        $names=array_values($names);
    }
    return array_map(static fn($name)=>['value'=>$name,'label'=>$name],$names);
}

function expense_resolve_name(PDO $pdo,int $branchId,int $userId,array $data): string
{
    $choice=trim((string)($data['expense_name']??''));
    $newName=trim((string)($data['new_expense_name']??''));
    $name=$choice==='__new__'?$newName:$choice;

    if($name==='') json_error('Expense Name is required.',422,['expense_name'=>'Select an Expense Name.']);
    if(mb_strlen($name)>150) json_error('Expense Name must be within 150 characters.',422,['expense_name'=>'Expense Name must be within 150 characters.']);

    if($choice!=='__new__'){
        $check=$pdo->prepare('SELECT id FROM expense_names WHERE branch_id=:branch_id AND expense_name=:expense_name AND status=1 LIMIT 1');
        $check->execute([':branch_id'=>$branchId,':expense_name'=>$name]);
        if(!$check->fetchColumn()) json_error('Selected Expense Name is unavailable.',422,['expense_name'=>'Select an active Expense Name or choose Add New Expense Name.']);
    }else{
        $stmt=$pdo->prepare(
            'INSERT INTO expense_names(branch_id,expense_name,status,created_by,created_at,updated_at)
             VALUES(:branch_id,:expense_name,1,:created_by,NOW(),NOW())
             ON DUPLICATE KEY UPDATE status=1,updated_at=NOW()'
        );
        $stmt->execute([':branch_id'=>$branchId,':expense_name'=>$name,':created_by'=>$userId]);
    }
    return $name;
}

function expense_parse_payments(int $branchId,array $data,float $expenseAmount,string $expenseDate): array
{
    $raw=$data['payment_details_json']??'[]';
    $rows=is_array($raw)?$raw:json_decode((string)$raw,true);
    if(!is_array($rows)) json_error('Payment details format is invalid.',422,['payment_details_json'=>'Payment details format is invalid.']);

    $expectedTypes=[1=>1,2=>3,3=>2,4=>2];
    $result=[];$total=0.0;

    foreach($rows as $r){
        if(!is_array($r))continue;
        $mode=(int)($r['payment_mode']??0);
        if(!isset($expectedTypes[$mode]))continue;
        $amount=expense_money($r['amount']??'','payment_details_json','Payment Amount',false);
        if($amount<=0)continue;

        $accountId=expense_account_id($r['account_id']??0);
        $account=expense_assert_account($branchId,$accountId);
        if((int)$account['account_type']!==$expectedTypes[$mode]){
            $modeLabel=[1=>'Cash',2=>'UPI',3=>'Bank',4=>'Cheque'][$mode]??'Payment';
            json_error('Selected Account type does not match '.$modeLabel.'.',422,['payment_details_json'=>'Select the correct Account for '.$modeLabel.'.']);
        }

        $reference=expense_nullable($r['reference_no']??null,100);
        $detailDate=expense_valid_date($r['detail_date']??$expenseDate,'payment_details_json','Payment Date',true);
        if($mode===4 && ($reference===null || $reference==='')){
            json_error('Cheque Number is required for Cheque payment.',422,['payment_details_json'=>'Enter Cheque Number.']);
        }

        $result[]=[
            'payment_mode'=>$mode,
            'account_id'=>$accountId,
            'amount'=>$amount,
            'reference_no'=>$reference,
            'detail_date'=>$detailDate,
        ];
        $total=round($total+$amount,2);
    }

    if(!$result) json_error('Enter at least one Payment Amount.',422,['payment_details_json'=>'Enter Cash, UPI, Bank or Cheque payment.']);
    if(abs($total-$expenseAmount)>0.009){
        json_error('Payment Total must equal Expense Amount.',422,[
            'payment_details_json'=>'Expense Amount is '.number_format($expenseAmount,2,'.','').' but Payment Total is '.number_format($total,2,'.','').'.'
        ]);
    }

    return ['rows'=>$result,'total'=>$total,'primary_account_id'=>(int)$result[0]['account_id']];
}

function expense_payment_details(int $expenseId): array
{
    $stmt=db()->prepare(
        'SELECT d.payment_mode,d.account_id,d.amount,d.reference_no,d.detail_date,
                a.account_code,a.account_name,a.account_type
         FROM expense_payment_details d
         INNER JOIN accounts a ON a.id=d.account_id
         WHERE d.expense_id=:expense_id
         ORDER BY d.id'
    );
    $stmt->execute([':expense_id'=>$expenseId]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['payment_mode']=(int)$row['payment_mode'];
        $row['account_id']=(int)$row['account_id'];
        $row['amount']=(float)$row['amount'];
        $row['account_type']=(int)$row['account_type'];
    }
    unset($row);
    return $rows;
}

function expense_record(int $branchId,int $id): array
{
    $stmt=db()->prepare(
        'SELECT id,branch_id,expense_date,expense_name,amount,account_id,remarks,status,created_by,created_at,updated_at
         FROM expenses
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':branch_id'=>$branchId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)json_error('Expense was not found in your branch.',404);

    $row['id']=(int)$row['id'];
    $row['amount']=(float)$row['amount'];
    $row['status']=(int)$row['status'];
    $row['ref']=encryptReference('expense',(int)$row['id']);
    $row['expense_no']='EXP'.str_pad((string)$row['id'],4,'0',STR_PAD_LEFT);
    $row['edit_url']='expense-form.php?ref='.rawurlencode($row['ref']);
    $row['payments']=expense_payment_details((int)$row['id']);
    unset($row['account_id']);
    return $row;
}

function expense_replace_financial_effects(PDO $pdo,int $branchId,int $expenseId,array $payments,string $expenseDate,string $expenseName,?string $remarks,int $userId): void
{
    $pdo->prepare('DELETE FROM account_transactions WHERE branch_id=:branch_id AND source_type=3 AND source_id=:source_id')
        ->execute([':branch_id'=>$branchId,':source_id'=>$expenseId]);
    $pdo->prepare('DELETE FROM expense_payment_details WHERE expense_id=:expense_id')
        ->execute([':expense_id'=>$expenseId]);

    $detail=$pdo->prepare(
        'INSERT INTO expense_payment_details(expense_id,payment_mode,account_id,amount,reference_no,detail_date,created_at,updated_at)
         VALUES(:expense_id,:payment_mode,:account_id,:amount,:reference_no,:detail_date,NOW(),NOW())'
    );
    $txn=$pdo->prepare(
        'INSERT INTO account_transactions
         (branch_id,transaction_date,account_id,transaction_type,source_type,source_id,amount,remarks,created_by,created_at)
         VALUES(:branch_id,:transaction_date,:account_id,2,3,:source_id,:amount,:remarks,:created_by,NOW())'
    );

    foreach($payments as $p){
        $detail->execute([
            ':expense_id'=>$expenseId,
            ':payment_mode'=>(int)$p['payment_mode'],
            ':account_id'=>(int)$p['account_id'],
            ':amount'=>(float)$p['amount'],
            ':reference_no'=>$p['reference_no'],
            ':detail_date'=>$p['detail_date'],
        ]);

        $modeLabel=[1=>'Cash',2=>'UPI',3=>'Bank',4=>'Cheque'][(int)$p['payment_mode']]??'Payment';
        $txnRemarks='Expense EXP'.str_pad((string)$expenseId,4,'0',STR_PAD_LEFT).' - '.$expenseName.' - '.$modeLabel;
        if($remarks)$txnRemarks.=' - '.$remarks;
        if(mb_strlen($txnRemarks)>255)$txnRemarks=mb_substr($txnRemarks,0,255);

        $txn->execute([
            ':branch_id'=>$branchId,
            ':transaction_date'=>(string)$p['detail_date'].' 00:00:00',
            ':account_id'=>(int)$p['account_id'],
            ':source_id'=>$expenseId,
            ':amount'=>(float)$p['amount'],
            ':remarks'=>$txnRemarks,
            ':created_by'=>$userId,
        ]);
    }
}

function expense_payment_mode_label(int $mode): string
{
    return [1=>'Cash',2=>'UPI',3=>'Bank',4=>'Cheque'][$mode]??'Payment';
}

$method=request_method();
expense_require_schema();

if($method==='GET' && isset($_GET['options'])){
    $access=require_permission(EXPENSE_PERMISSION_PATH,ACTION_VIEW);
    $ctx=expense_context($access['user']);
    json_success('Expense options loaded.',[
        'allowed_actions'=>$access['actions'],
        'today'=>date('Y-m-d'),
        'expense_names'=>expense_names((int)$ctx['branch_id']),
        'accounts'=>expense_accounts((int)$ctx['branch_id']),
    ]);
}

if($method==='GET' && isset($_GET['ref'])){
    $access=require_permission(EXPENSE_PERMISSION_PATH,ACTION_VIEW);
    $ctx=expense_context($access['user']);
    $record=expense_record((int)$ctx['branch_id'],expense_id_from_ref($_GET['ref']));
    json_success('Expense loaded.',[
        'expense'=>$record,
        'allowed_actions'=>$access['actions'],
        'expense_names'=>expense_names((int)$ctx['branch_id'],(string)$record['expense_name']),
        'accounts'=>expense_accounts((int)$ctx['branch_id']),
    ]);
}

if($method==='GET' && isset($_GET['datatable'])){
    $access=require_permission(EXPENSE_PERMISSION_PATH,ACTION_VIEW);
    $ctx=expense_context($access['user']);
    $branchId=(int)$ctx['branch_id'];

    $draw=max(0,(int)($_GET['draw']??0));
    $start=max(0,(int)($_GET['start']??0));
    $length=max(1,min(100000,(int)($_GET['length']??25)));
    $search=trim((string)($_GET['search']['value']??''));
    $dateFrom=trim((string)($_GET['date_from']??''));
    $dateTo=trim((string)($_GET['date_to']??''));
    $statusFilter=trim((string)($_GET['status']??''));
    $expenseName=trim((string)($_GET['expense_name']??''));
    $paymentMode=(int)($_GET['payment_mode']??0);
    $accountId=(int)($_GET['account_id']??0);

    $base=['e.branch_id=:branch_id'];
    $where=$base;
    $params=[':branch_id'=>$branchId];

    if($search!==''){
        $where[]='(e.expense_name LIKE :s1 OR e.remarks LIKE :s2 OR EXISTS (
            SELECT 1 FROM expense_payment_details sx
            INNER JOIN accounts ax ON ax.id=sx.account_id
            WHERE sx.expense_id=e.id AND (ax.account_name LIKE :s3 OR ax.account_code LIKE :s4 OR sx.reference_no LIKE :s5)
        ))';
        $term='%'.$search.'%';
        $params[':s1']=$term;$params[':s2']=$term;$params[':s3']=$term;$params[':s4']=$term;$params[':s5']=$term;
    }
    if($dateFrom!==''){$where[]='e.expense_date>=:date_from';$params[':date_from']=$dateFrom;}
    if($dateTo!==''){$where[]='e.expense_date<=:date_to';$params[':date_to']=$dateTo;}
    if($statusFilter!=='' && in_array((int)$statusFilter,[0,1],true)){$where[]='e.status=:status';$params[':status']=(int)$statusFilter;}
    if($expenseName!==''){$where[]='e.expense_name=:expense_name';$params[':expense_name']=$expenseName;}
    if(in_array($paymentMode,[1,2,3,4],true)){
        $where[]='EXISTS (SELECT 1 FROM expense_payment_details pm WHERE pm.expense_id=e.id AND pm.payment_mode=:payment_mode)';
        $params[':payment_mode']=$paymentMode;
    }
    if($accountId>0){
        $where[]='EXISTS (SELECT 1 FROM expense_payment_details pa WHERE pa.expense_id=e.id AND pa.account_id=:account_id)';
        $params[':account_id']=$accountId;
    }

    $totalStmt=db()->prepare('SELECT COUNT(*) FROM expenses e WHERE '.implode(' AND ',$base));
    $totalStmt->execute([':branch_id'=>$branchId]);
    $recordsTotal=(int)$totalStmt->fetchColumn();

    $countStmt=db()->prepare('SELECT COUNT(*) FROM expenses e WHERE '.implode(' AND ',$where));
    $countStmt->execute($params);
    $recordsFiltered=(int)$countStmt->fetchColumn();

    /*
     * Filtered statistics for Expense List KPI cards.
     * Cancelled expenses are kept separate because their linked
     * Money Out transactions are removed/reversed by the API.
     */
    $summaryStmt=db()->prepare(
        'SELECT
            COUNT(*) AS expense_count,
            COALESCE(SUM(CASE WHEN e.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN e.status=0 THEN 1 ELSE 0 END),0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN e.status=1 THEN e.amount ELSE 0 END),0) AS active_amount
         FROM expenses e
         WHERE '.implode(' AND ',$where)
    );
    $summaryStmt->execute($params);
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:[];

    $orderMap=[0=>'e.id',1=>'e.expense_date',2=>'e.expense_name',3=>'payment_modes',4=>'payment_accounts',5=>'e.amount',6=>'e.remarks',7=>'e.status'];
    $orderCol=(int)($_GET['order'][0]['column']??1);
    $orderDir=strtolower((string)($_GET['order'][0]['dir']??'desc'))==='asc'?'ASC':'DESC';
    $orderBy=$orderMap[$orderCol]??'e.expense_date';

    $sql='SELECT e.id,e.expense_date,e.expense_name,e.amount,e.remarks,e.status,
                 COALESCE(NULLIF(GROUP_CONCAT(DISTINCT CASE d.payment_mode WHEN 1 THEN \'Cash\' WHEN 2 THEN \'UPI\' WHEN 3 THEN \'Bank\' WHEN 4 THEN \'Cheque\' END ORDER BY d.payment_mode SEPARATOR \' + \'),\'\'),\'-\') AS payment_modes,
                 COALESCE(NULLIF(GROUP_CONCAT(DISTINCT a.account_name ORDER BY a.account_name SEPARATOR \' + \'),\'\'),\'-\') AS payment_accounts
          FROM expenses e
          LEFT JOIN expense_payment_details d ON d.expense_id=e.id
          LEFT JOIN accounts a ON a.id=d.account_id
          WHERE '.implode(' AND ',$where).'
          GROUP BY e.id,e.expense_date,e.expense_name,e.amount,e.remarks,e.status
          ORDER BY '.$orderBy.' '.$orderDir.',e.id DESC
          LIMIT '.(int)$start.','.(int)$length;
    $stmt=db()->prepare($sql);
    $stmt->execute($params);

    $rows=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $id=(int)$row['id'];
        $ref=encryptReference('expense',$id);
        $rows[]=[
            'expense_no'=>'EXP'.str_pad((string)$id,4,'0',STR_PAD_LEFT),
            'expense_date'=>(string)$row['expense_date'],
            'expense_name'=>(string)$row['expense_name'],
            'payment_modes'=>(string)$row['payment_modes'],
            'payment_accounts'=>(string)$row['payment_accounts'],
            'amount'=>(float)$row['amount'],
            'remarks'=>(string)($row['remarks']??''),
            'status'=>(int)$row['status'],
            'ref'=>$ref,
            'edit_url'=>'expense-form.php?ref='.rawurlencode($ref),
        ];
    }

    json_success('Expenses loaded.',[
        'summary'=>[
            'expense_count'=>(int)($summary['expense_count']??0),
            'active_count'=>(int)($summary['active_count']??0),
            'cancelled_count'=>(int)($summary['cancelled_count']??0),
            'active_amount'=>(float)($summary['active_amount']??0),
        ],
        'datatable'=>[
            'draw'=>$draw,
            'recordsTotal'=>$recordsTotal,
            'recordsFiltered'=>$recordsFiltered,
            'data'=>$rows,
        ],
        'allowed_actions'=>$access['actions'],
    ]);
}

if($method==='POST'){
    $data=request_data();
    $action=strtolower(trim((string)($data['action']??'save')));

    if($action==='cancel'){
        $access=require_permission(EXPENSE_PERMISSION_PATH,ACTION_CANCEL);
        $ctx=expense_context($access['user']);
        $branchId=(int)$ctx['branch_id'];
        $id=expense_id_from_ref($data['ref']??'');
        $old=expense_record($branchId,$id);
        if((int)$old['status']===0)json_success('Expense is already cancelled.');

        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE expenses SET status=0,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')
                ->execute([':id'=>$id,':branch_id'=>$branchId]);
            $pdo->prepare('DELETE FROM account_transactions WHERE branch_id=:branch_id AND source_type=3 AND source_id=:source_id')
                ->execute([':branch_id'=>$branchId,':source_id'=>$id]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

        audit_log((int)$access['user']['id'],ACTION_CANCEL,[
            'company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],
            'record_id'=>$id,'old_data'=>$old,'new_data'=>['status'=>0]
        ]);
        json_success('Expense cancelled successfully. Linked Money Out transactions were reversed.');
    }

    if($action!=='save')json_error('Unsupported Expense action.',404);
    $access=require_permission(EXPENSE_PERMISSION_PATH,ACTION_CREATE);
    $ctx=expense_context($access['user']);
    $branchId=(int)$ctx['branch_id'];$userId=(int)$access['user']['id'];
    $date=expense_valid_date($data['expense_date']??'','expense_date','Expense Date',true);
    $amount=expense_money($data['amount']??'','amount','Expense Amount',true);
    if($amount<=0)json_error('Expense Amount must be greater than zero.',422,['amount'=>'Expense Amount must be greater than zero.']);
    $remarks=expense_nullable($data['remarks']??null,255);

    $pdo=db();$pdo->beginTransaction();
    try{
        $name=expense_resolve_name($pdo,$branchId,$userId,$data);
        $payments=expense_parse_payments($branchId,$data,$amount,$date);
        $stmt=$pdo->prepare(
            'INSERT INTO expenses(branch_id,expense_date,expense_name,amount,account_id,remarks,status,created_by,created_at,updated_at)
             VALUES(:branch_id,:expense_date,:expense_name,:amount,:account_id,:remarks,1,:created_by,NOW(),NOW())'
        );
        $stmt->execute([
            ':branch_id'=>$branchId,':expense_date'=>$date,':expense_name'=>$name,':amount'=>$amount,
            ':account_id'=>$payments['primary_account_id'],':remarks'=>$remarks,':created_by'=>$userId,
        ]);
        $id=(int)$pdo->lastInsertId();
        expense_replace_financial_effects($pdo,$branchId,$id,$payments['rows'],$date,$name,$remarks,$userId);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

    audit_log($userId,ACTION_CREATE,[
        'company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,
        'new_data'=>['expense_date'=>$date,'expense_name'=>$name,'amount'=>$amount,'remarks'=>$remarks,'payments'=>$payments['rows']]
    ]);
    json_success('Expense saved successfully.',['expense'=>expense_record($branchId,$id)],201);
}

if($method==='PUT'){
    $access=require_permission(EXPENSE_PERMISSION_PATH,ACTION_UPDATE);
    $ctx=expense_context($access['user']);
    $branchId=(int)$ctx['branch_id'];$userId=(int)$access['user']['id'];
    $data=request_data();$id=expense_id_from_ref($data['ref']??'');$old=expense_record($branchId,$id);
    if((int)$old['status']!==1)json_error('Cancelled Expense cannot be edited.',422);

    $date=expense_valid_date($data['expense_date']??'','expense_date','Expense Date',true);
    $amount=expense_money($data['amount']??'','amount','Expense Amount',true);
    if($amount<=0)json_error('Expense Amount must be greater than zero.',422,['amount'=>'Expense Amount must be greater than zero.']);
    $remarks=expense_nullable($data['remarks']??null,255);

    $pdo=db();$pdo->beginTransaction();
    try{
        $name=expense_resolve_name($pdo,$branchId,$userId,$data);
        $payments=expense_parse_payments($branchId,$data,$amount,$date);
        $pdo->prepare(
            'UPDATE expenses SET expense_date=:expense_date,expense_name=:expense_name,amount=:amount,account_id=:account_id,remarks=:remarks,updated_at=NOW()
             WHERE id=:id AND branch_id=:branch_id AND status=1'
        )->execute([
            ':expense_date'=>$date,':expense_name'=>$name,':amount'=>$amount,':account_id'=>$payments['primary_account_id'],
            ':remarks'=>$remarks,':id'=>$id,':branch_id'=>$branchId,
        ]);
        expense_replace_financial_effects($pdo,$branchId,$id,$payments['rows'],$date,$name,$remarks,$userId);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

    audit_log($userId,ACTION_UPDATE,[
        'company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,
        'old_data'=>$old,'new_data'=>['expense_date'=>$date,'expense_name'=>$name,'amount'=>$amount,'remarks'=>$remarks,'payments'=>$payments['rows']]
    ]);
    json_success('Expense updated successfully.',['expense'=>expense_record($branchId,$id)]);
}

json_error('Method not allowed.',405);
