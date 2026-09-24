<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_ACTIVATE')) define('ACTION_ACTIVATE', 27);
if (!defined('ACTION_DEACTIVATE')) define('ACTION_DEACTIVATE', 28);
if (!defined('STOCK_MOVEMENT_OPENING')) define('STOCK_MOVEMENT_OPENING', 11);

function product_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Product management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, c.company_name
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

function product_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT product_code
         FROM products
         WHERE branch_id = :branch_id
           AND product_code REGEXP '^PRD[0-9]+$'
         ORDER BY CAST(SUBSTRING(product_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^PRD([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'PRD' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function product_id_from_ref($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Product reference is required.', 422, ['ref' => 'Product reference is required.']);
    }

    try {
        $id = (int)decryptReference(trim($value), 'product');
    } catch (Throwable $e) {
        json_error('Invalid Product reference.', 422, ['ref' => 'Invalid Product reference.']);
    }

    if ($id < 1) json_error('Invalid Product reference.', 422);
    return $id;
}

function product_enum($value, string $field, string $label, array $allowed): int
{
    $value = (int)$value;
    if (!in_array($value, $allowed, true)) {
        json_error('Product validation failed.', 422, [
            $field => 'Select a valid ' . $label . '.',
        ]);
    }
    return $value;
}

function product_decimal($value, string $field, string $label, int $places = 2, bool $positiveOnly = false): float
{
    $text = trim((string)($value ?? ''));
    if ($text === '') $text = '0';

    $pattern = $places === 4
        ? '/^(?:[0-9]+(?:\.[0-9]{1,4})?|\.[0-9]{1,4})$/'
        : '/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/';

    if (!preg_match($pattern, $text)) {
        json_error('Product validation failed.', 422, [
            $field => 'Enter a valid ' . $label . '.',
        ]);
    }

    $number = round((float)$text, $places);
    if ($positiveOnly && $number <= 0) {
        json_error('Product validation failed.', 422, [
            $field => $label . ' must be greater than zero.',
        ]);
    }
    if (!$positiveOnly && $number < 0) {
        json_error('Product validation failed.', 422, [
            $field => $label . ' cannot be negative.',
        ]);
    }

    return $number;
}

function product_quantity($value, string $field, string $label): float
{
    $text = trim((string)($value ?? ''));
    if ($text === '') $text = '0';

    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,3})?|\.[0-9]{1,3})$/', $text)) {
        json_error('Product validation failed.', 422, [
            $field => 'Enter a valid ' . $label . '.',
        ]);
    }

    $number = round((float)$text, 3);
    if ($number < 0) {
        json_error('Product validation failed.', 422, [
            $field => $label . ' cannot be negative.',
        ]);
    }
    return $number;
}

function product_status($value): int { return product_enum($value, 'status', 'Status', [1,2]); }
function product_type($value): int { return product_enum($value, 'product_type', 'Product Type', [1,2,3]); }
function product_container_type($value): int { return product_enum($value, 'container_type', 'Container Type', [1,2,3]); }
function product_sale_allowed($value): int { return product_enum($value, 'sale_allowed', 'Sales Allowed', [0,1]); }
function product_gst_type($value): int { return product_enum($value, 'gst_type', 'GST Type', [1,2]); }

function product_category_options(int $branchId, int $includeId = 0): array
{
    $sql = 'SELECT id, category_code, category_name, status
            FROM categories
            WHERE branch_id = :branch_id
              AND (status = 1';
    $params = [':branch_id' => $branchId];

    if ($includeId > 0) {
        $sql .= ' OR id = :include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ') ORDER BY category_name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);
    return $rows;
}

function product_subcategory_options(int $branchId, int $categoryId, int $includeId = 0): array
{
    if ($categoryId < 1) return [];

    $sql = 'SELECT s.id, s.category_id, s.subcategory_code, s.subcategory_name, s.status
            FROM subcategories s
            INNER JOIN categories c ON c.id = s.category_id
            WHERE s.category_id = :category_id
              AND c.branch_id = :branch_id
              AND (s.status = 1';
    $params = [':category_id' => $categoryId, ':branch_id' => $branchId];

    if ($includeId > 0) {
        $sql .= ' OR s.id = :include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ') ORDER BY s.subcategory_name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['category_id'] = (int)$row['category_id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);
    return $rows;
}

function product_unit_options(int $branchId, int $includePrimaryId = 0, int $includeSecondaryId = 0): array
{
    $includeIds = array_values(array_unique(array_filter([
        $includePrimaryId,
        $includeSecondaryId,
    ], static fn($id) => (int)$id > 0)));

    $sql = 'SELECT id, unit_name, short_name, status
            FROM units
            WHERE branch_id = :branch_id
              AND (status = 1';
    $params = [':branch_id' => $branchId];

    if ($includeIds) {
        $holders = [];
        foreach ($includeIds as $index => $id) {
            $key = ':unit_' . $index;
            $holders[] = $key;
            $params[$key] = (int)$id;
        }
        $sql .= ' OR id IN (' . implode(',', $holders) . ')';
    }

    $sql .= ') ORDER BY unit_name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);
    return $rows;
}

function product_hsn_options(int $branchId, int $includeId = 0): array
{
    $sql = 'SELECT id, hsn_code, description,
                   gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate, status
            FROM hsn_master
            WHERE branch_id = :branch_id
              AND (status = 1';
    $params = [':branch_id' => $branchId];

    if ($includeId > 0) {
        $sql .= ' OR id = :include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ') ORDER BY hsn_code ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        foreach (['gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        $row['status'] = (int)$row['status'];
    }
    unset($row);
    return $rows;
}

function product_price_level_options(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id, price_level_name, status
         FROM price_levels
         WHERE branch_id = :branch_id
           AND status = 1
         ORDER BY price_level_name ASC'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);
    return $rows;
}

function product_options(
    int $branchId,
    int $categoryId = 0,
    int $subcategoryId = 0,
    int $primaryUnitId = 0,
    int $secondaryUnitId = 0,
    int $hsnId = 0
): array {
    return [
        'categories' => product_category_options($branchId, $categoryId),
        'subcategories' => product_subcategory_options($branchId, $categoryId, $subcategoryId),
        'units' => product_unit_options($branchId, $primaryUnitId, $secondaryUnitId),
        'hsn_codes' => product_hsn_options($branchId, $hsnId),
        'price_levels' => product_price_level_options($branchId),
    ];
}

function product_assert_category(int $branchId, int $categoryId): void
{
    $stmt = db()->prepare(
        'SELECT id FROM categories
         WHERE id = :id AND branch_id = :branch_id AND status = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $categoryId, ':branch_id' => $branchId]);
    if (!$stmt->fetchColumn()) {
        json_error('Product validation failed.', 422, ['category_id' => 'Select an active Category.']);
    }
}

function product_assert_subcategory(int $branchId, int $categoryId, ?int $subcategoryId): void
{
    if (!$subcategoryId) return;

    $stmt = db()->prepare(
        'SELECT s.id
         FROM subcategories s
         INNER JOIN categories c ON c.id = s.category_id
         WHERE s.id = :id
           AND s.category_id = :category_id
           AND c.branch_id = :branch_id
           AND s.status = 1
           AND c.status = 1
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $subcategoryId,
        ':category_id' => $categoryId,
        ':branch_id' => $branchId,
    ]);
    if (!$stmt->fetchColumn()) {
        json_error('Product validation failed.', 422, [
            'subcategory_id' => 'Select an active Subcategory under the selected Category.',
        ]);
    }
}

function product_assert_unit(int $branchId, int $unitId, string $field, string $label): void
{
    $stmt = db()->prepare(
        'SELECT id FROM units
         WHERE id = :id AND branch_id = :branch_id AND status = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $unitId, ':branch_id' => $branchId]);
    if (!$stmt->fetchColumn()) {
        json_error('Product validation failed.', 422, [$field => 'Select an active ' . $label . '.']);
    }
}

function product_assert_hsn(int $branchId, ?int $hsnId): void
{
    if (!$hsnId) return;

    $stmt = db()->prepare(
        'SELECT id FROM hsn_master
         WHERE id = :id AND branch_id = :branch_id AND status = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $hsnId, ':branch_id' => $branchId]);
    if (!$stmt->fetchColumn()) {
        json_error('Product validation failed.', 422, ['hsn_id' => 'Select an active HSN.']);
    }
}

function product_validate(array $data, int $branchId): array
{
    $errors = [];

    $name = trim((string)($data['product_name'] ?? ''));
    if ($name === '') $errors['product_name'] = 'Product Name is required.';
    elseif (mb_strlen($name) > 150) $errors['product_name'] = 'Product Name must be within 150 characters.';

    $categoryId = (int)($data['category_id'] ?? 0);
    if ($categoryId < 1) $errors['category_id'] = 'Select Category.';

    $subcategoryRaw = $data['subcategory_id'] ?? null;
    $subcategoryId = ($subcategoryRaw === '' || $subcategoryRaw === null) ? null : (int)$subcategoryRaw;
    if ($subcategoryRaw !== '' && $subcategoryRaw !== null && $subcategoryId < 1) {
        $errors['subcategory_id'] = 'Select a valid Subcategory.';
    }

    $primaryUnitId = (int)($data['primary_unit_id'] ?? 0);
    if ($primaryUnitId < 1) $errors['primary_unit_id'] = 'Select Primary Unit.';

    $secondaryRaw = $data['secondary_unit_id'] ?? null;
    $secondaryUnitId = ($secondaryRaw === '' || $secondaryRaw === null) ? null : (int)$secondaryRaw;
    if ($secondaryRaw !== '' && $secondaryRaw !== null && $secondaryUnitId < 1) {
        $errors['secondary_unit_id'] = 'Select a valid Secondary Unit.';
    }

    if ($primaryUnitId > 0 && $secondaryUnitId && $primaryUnitId === $secondaryUnitId) {
        $errors['secondary_unit_id'] = 'Primary Unit and Secondary Unit must be different.';
    }

    $hsnRaw = $data['hsn_id'] ?? null;
    $hsnId = ($hsnRaw === '' || $hsnRaw === null) ? null : (int)$hsnRaw;
    if ($hsnRaw !== '' && $hsnRaw !== null && $hsnId < 1) {
        $errors['hsn_id'] = 'Select a valid HSN.';
    }

    if ($errors) json_error('Product validation failed.', 422, $errors);

    $conversion = 1.0;
    if ($secondaryUnitId) {
        $conversion = product_decimal(
            $data['conversion_qty'] ?? '',
            'conversion_qty',
            'Conversion Qty',
            4,
            true
        );
    }

    $clean = [
        'product_name' => $name,
        'product_type' => product_type($data['product_type'] ?? 2),
        'category_id' => $categoryId,
        'subcategory_id' => $subcategoryId,
        'hsn_id' => $hsnId,
        'gst_type' => product_gst_type($data['gst_type'] ?? 2),
        'purchase_price' => product_decimal(
            $data['purchase_price'] ?? 0,
            'purchase_price',
            'Base / Purchase Price',
            2,
            false
        ),
        'container_type' => product_container_type($data['container_type'] ?? 3),
        'sale_allowed' => product_sale_allowed($data['sale_allowed'] ?? 1),
        'status' => product_status($data['status'] ?? 1),
        'primary_unit_id' => $primaryUnitId,
        'secondary_unit_id' => $secondaryUnitId,
        'conversion_qty' => $conversion,
    ];

    product_assert_category($branchId, $clean['category_id']);
    product_assert_subcategory($branchId, $clean['category_id'], $clean['subcategory_id']);
    product_assert_unit($branchId, $clean['primary_unit_id'], 'primary_unit_id', 'Primary Unit');
    if ($clean['secondary_unit_id']) {
        product_assert_unit($branchId, $clean['secondary_unit_id'], 'secondary_unit_id', 'Secondary Unit');
    }
    product_assert_hsn($branchId, $clean['hsn_id']);

    return $clean;
}

function product_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.branch_id, p.product_code, p.product_name, p.product_type,
                p.category_id, p.subcategory_id, p.hsn_id, p.gst_type, p.purchase_price,
                p.container_type, p.sale_allowed, p.status,
                p.created_by, p.created_at, p.updated_at,
                c.category_code, c.category_name,
                s.subcategory_code, s.subcategory_name,
                h.hsn_code, h.description AS hsn_description,
                h.gst_rate, h.cgst_rate, h.sgst_rate, h.igst_rate, h.cess_rate
         FROM products p
         INNER JOIN categories c
            ON c.id = p.category_id
           AND c.branch_id = p.branch_id
         LEFT JOIN subcategories s
            ON s.id = p.subcategory_id
           AND s.category_id = p.category_id
         LEFT JOIN hsn_master h
            ON h.id = p.hsn_id
           AND h.branch_id = p.branch_id
         WHERE p.id = :id
           AND p.branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Product was not found in your branch.', 404);

    $ref = encryptReference('product', (int)$row['id']);
    unset($row['id']);

    foreach (['branch_id','product_type','category_id','gst_type','container_type','sale_allowed','status'] as $key) {
        $row[$key] = (int)$row[$key];
    }
    $row['subcategory_id'] = $row['subcategory_id'] === null ? null : (int)$row['subcategory_id'];
    $row['hsn_id'] = $row['hsn_id'] === null ? null : (int)$row['hsn_id'];
    $row['purchase_price'] = (float)$row['purchase_price'];
    foreach (['gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $key) {
        $row[$key] = $row[$key] === null ? 0.0 : (float)$row[$key];
    }
    $row['ref'] = $ref;
    $row['edit_url'] = 'product-form.php?ref=' . $ref;
    return $row;
}

function product_units(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT pu.id AS product_unit_id, pu.unit_id, pu.unit_type,
                pu.conversion_qty, pu.status, u.unit_name, u.short_name
         FROM product_units pu
         INNER JOIN units u ON u.id = pu.unit_id
         WHERE pu.product_id = :product_id
         ORDER BY pu.unit_type ASC, pu.id ASC'
    );
    $stmt->execute([':product_id' => $productId]);

    $result = ['primary' => null, 'secondary' => null];
    foreach ($stmt->fetchAll() as $row) {
        $item = [
            'product_unit_id' => (int)$row['product_unit_id'],
            'unit_id' => (int)$row['unit_id'],
            'unit_type' => (int)$row['unit_type'],
            'conversion_qty' => (float)$row['conversion_qty'],
            'status' => (int)$row['status'],
            'unit_name' => (string)$row['unit_name'],
            'short_name' => (string)$row['short_name'],
        ];

        if ((int)$row['unit_type'] === 1 && $result['primary'] === null) $result['primary'] = $item;
        if ((int)$row['unit_type'] === 2 && $result['secondary'] === null) $result['secondary'] = $item;
    }
    return $result;
}

function product_prices(array $units): array
{
    $result = ['primary' => [], 'secondary' => []];

    foreach (['primary','secondary'] as $kind) {
        if (!$units[$kind]) continue;

        $stmt = db()->prepare(
            'SELECT pp.price_level_id, pp.markup_type, pp.markup_value,
                    pp.selling_price, pp.status, pl.price_level_name
             FROM product_prices pp
             INNER JOIN price_levels pl ON pl.id = pp.price_level_id
             WHERE pp.product_unit_id = :product_unit_id
             ORDER BY pl.price_level_name ASC, pp.id ASC'
        );
        $stmt->execute([':product_unit_id' => (int)$units[$kind]['product_unit_id']]);

        foreach ($stmt->fetchAll() as $row) {
            $result[$kind][(string)(int)$row['price_level_id']] = [
                'price_level_id' => (int)$row['price_level_id'],
                'price_level_name' => (string)$row['price_level_name'],
                'markup_type' => (int)$row['markup_type'],
                'markup_value' => (float)$row['markup_value'],
                'selling_price' => (float)$row['selling_price'],
                'status' => (int)$row['status'],
            ];
        }
    }

    return $result;
}

function product_usage_locked(int $branchId, int $productId): bool
{
    $stmt = db()->prepare(
        'SELECT CASE WHEN
            EXISTS(SELECT 1 FROM stock_movements sm WHERE sm.branch_id = :b1 AND sm.product_id = :p1 LIMIT 1)
            OR EXISTS(SELECT 1 FROM line_supply_items lsi WHERE lsi.product_id = :p2 LIMIT 1)
            OR EXISTS(SELECT 1 FROM production_items pri WHERE pri.product_id = :p3 LIMIT 1)
            OR EXISTS(SELECT 1 FROM production_materials prm WHERE prm.product_id = :p4 LIMIT 1)
            OR EXISTS(SELECT 1 FROM sales_items si WHERE si.product_id = :p5 LIMIT 1)
            OR EXISTS(SELECT 1 FROM can_movements cm WHERE cm.branch_id = :b2 AND cm.product_id = :p6 LIMIT 1)
            OR EXISTS(SELECT 1 FROM can_stock_movements csm WHERE csm.branch_id = :b3 AND csm.product_id = :p7 LIMIT 1)
            OR EXISTS(SELECT 1 FROM vehicle_stock_movements vsm WHERE vsm.branch_id = :b4 AND vsm.product_id = :p8 LIMIT 1)
            OR EXISTS(
                SELECT 1 FROM product_units pu
                INNER JOIN purchase_items pi ON pi.product_unit_id = pu.id
                WHERE pu.product_id = :p9 LIMIT 1
            )
            OR EXISTS(
                SELECT 1 FROM product_units pu
                INNER JOIN customer_order_items coi ON coi.product_unit_id = pu.id
                WHERE pu.product_id = :p10 LIMIT 1
            )
            OR EXISTS(
                SELECT 1 FROM product_units pu
                INNER JOIN customer_product_prices cpp ON cpp.product_unit_id = pu.id
                WHERE pu.product_id = :p11 LIMIT 1
            )
        THEN 1 ELSE 0 END'
    );
    $stmt->execute([
        ':b1' => $branchId, ':p1' => $productId,
        ':p2' => $productId,
        ':p3' => $productId,
        ':p4' => $productId,
        ':p5' => $productId,
        ':b2' => $branchId, ':p6' => $productId,
        ':b3' => $branchId, ':p7' => $productId,
        ':b4' => $branchId, ':p8' => $productId,
        ':p9' => $productId,
        ':p10' => $productId,
        ':p11' => $productId,
    ]);
    return (int)$stmt->fetchColumn() === 1;
}

function product_opening_stock(int $branchId, int $productId, array $units): array
{
    $stmt = db()->prepare(
        'SELECT id, movement_date, quantity_in, quantity_out, created_at
         FROM stock_movements
         WHERE branch_id = :branch_id
           AND product_id = :product_id
           AND movement_type = :movement_type
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
        ':movement_type' => STOCK_MOVEMENT_OPENING,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return [
            'entered' => false,
            'base_qty' => 0.0,
            'primary_qty' => 0.0,
            'secondary_qty' => 0.0,
            'movement_date' => null,
        ];
    }

    $baseQty = round((float)$row['quantity_in'] - (float)$row['quantity_out'], 3);
    $primaryQty = 0.0;
    $secondaryQty = 0.0;
    $primaryConversion = $units['primary']
        ? max(0.0001, (float)$units['primary']['conversion_qty'])
        : 1.0;
    $secondaryConversion = $units['secondary']
        ? max(0.0001, (float)$units['secondary']['conversion_qty'])
        : 0.0;

    if ($units['secondary']) {
        $primaryQty = floor(($baseQty / $primaryConversion) + 0.0000001);
        $remainder = max(0.0, round($baseQty - ($primaryQty * $primaryConversion), 3));
        $secondaryQty = round($remainder / $secondaryConversion, 3);
    } else {
        $primaryQty = round($baseQty / $primaryConversion, 3);
    }

    return [
        'entered' => true,
        'base_qty' => $baseQty,
        'primary_qty' => (float)$primaryQty,
        'secondary_qty' => (float)$secondaryQty,
        'movement_date' => (string)$row['movement_date'],
    ];
}

function product_parse_opening_stock(array $data, ?int $secondaryUnitId, float $primaryConversion): array
{
    $primaryQty = product_quantity(
        $data['opening_primary_qty'] ?? 0,
        'opening_primary_qty',
        'Opening Primary Qty'
    );
    $secondaryQty = product_quantity(
        $data['opening_secondary_qty'] ?? 0,
        'opening_secondary_qty',
        'Opening Secondary Qty'
    );

    if (!$secondaryUnitId && $secondaryQty > 0) {
        json_error('Product validation failed.', 422, [
            'opening_secondary_qty' => 'Secondary opening quantity requires a Secondary Unit.',
        ]);
    }

    $baseQty = round(
        ($primaryQty * max(0.0001, $primaryConversion)) +
        ($secondaryUnitId ? $secondaryQty : 0.0),
        3
    );

    return [
        'primary_qty' => $primaryQty,
        'secondary_qty' => $secondaryQty,
        'base_qty' => $baseQty,
    ];
}

function product_insert_opening_stock(
    PDO $pdo,
    int $branchId,
    int $productId,
    float $baseQty,
    int $userId
): void {
    if ($baseQty <= 0) return;

    $check = $pdo->prepare(
        'SELECT id FROM stock_movements
         WHERE branch_id = :branch_id
           AND product_id = :product_id
           AND movement_type = :movement_type
         LIMIT 1
         FOR UPDATE'
    );
    $check->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
        ':movement_type' => STOCK_MOVEMENT_OPENING,
    ]);

    if ($check->fetchColumn()) {
        json_error('Opening Stock has already been entered for this Product.', 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO stock_movements
         (branch_id, movement_date, product_id, movement_type, source_id,
          quantity_in, quantity_out, created_by, created_at)
         VALUES
         (:branch_id, NOW(), :product_id, :movement_type, NULL,
          :quantity_in, 0, :created_by, NOW())'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
        ':movement_type' => STOCK_MOVEMENT_OPENING,
        ':quantity_in' => $baseQty,
        ':created_by' => $userId,
    ]);
}

function product_unit_model(array $units): string
{
    if (!$units['secondary']) return 'single';

    $primaryConversion = (float)($units['primary']['conversion_qty'] ?? 1.0);
    $secondaryConversion = (float)($units['secondary']['conversion_qty'] ?? 1.0);

    if (abs($primaryConversion - 1.0) <= 0.00005 && $secondaryConversion > 1.00005) {
        return 'legacy_secondary_larger';
    }
    return 'primary_larger';
}

function product_payload(int $branchId, int $id): array
{
    $product = product_record($branchId, $id);
    $units = product_units($id);
    $unitModel = product_unit_model($units);
    $relationshipConversion = 1.0;
    if ($units['secondary']) {
        $relationshipConversion = $unitModel === 'legacy_secondary_larger'
            ? (float)$units['secondary']['conversion_qty']
            : (float)$units['primary']['conversion_qty'];
    }

    return [
        'product' => $product,
        'units' => $units,
        'unit_model' => $unitModel,
        'relationship_conversion' => $relationshipConversion,
        'prices' => product_prices($units),
        'unit_usage_locked' => product_usage_locked($branchId, $id),
        'opening_stock' => product_opening_stock($branchId, $id, $units),
        'options' => product_options(
            $branchId,
            (int)$product['category_id'],
            (int)($product['subcategory_id'] ?? 0),
            $units['primary'] ? (int)$units['primary']['unit_id'] : 0,
            $units['secondary'] ? (int)$units['secondary']['unit_id'] : 0,
            (int)($product['hsn_id'] ?? 0)
        ),
    ];
}

function product_price_level_map(int $branchId): array
{
    $map = [];
    foreach (product_price_level_options($branchId) as $row) {
        $map[(int)$row['id']] = (string)$row['price_level_name'];
    }
    return $map;
}

function product_parse_prices(
    array $data,
    int $branchId,
    int $saleAllowed,
    float $primaryBase,
    ?float $secondaryBase,
    float $conversionQty,
    bool $secondaryIsLarger = false
): array {
    if ($saleAllowed !== 1) return ['primary' => [], 'secondary' => []];

    $levels = product_price_level_map($branchId);
    if (!$levels) {
        json_error('Product validation failed.', 422, [
            'pricing' => 'Create at least one active Price Level before enabling Sales Allowed.',
        ]);
    }

    $rawPrices = isset($data['prices']) && is_array($data['prices'])
        ? $data['prices']
        : [];

    /*
     * New Product form sends one common Price-Level configuration.
     * Old primary/secondary shaped payloads are also accepted so this
     * endpoint remains compatible with the previous Product form.
     */
    if (isset($rawPrices['common']) && is_array($rawPrices['common'])) {
        $source = $rawPrices['common'];
    } elseif (isset($rawPrices['primary']) && is_array($rawPrices['primary'])) {
        $source = $rawPrices['primary'];
    } else {
        $source = $rawPrices;
    }

    $result = ['primary' => [], 'secondary' => []];
    $conversionQty = $secondaryBase === null
        ? 1.0
        : max(0.0001, $conversionQty);

    foreach ($levels as $priceLevelId => $priceLevelName) {
        $row = $source[(string)$priceLevelId] ?? $source[$priceLevelId] ?? null;

        if (!is_array($row)) {
            json_error('Product validation failed.', 422, [
                'pricing' => 'Pricing is missing for ' . $priceLevelName . '.',
            ]);
        }

        $markupType = product_enum(
            $row['markup_type'] ?? 1,
            'pricing',
            'Markup Type',
            [1,2]
        );

        $markupValue = product_decimal(
            $row['markup_value'] ?? 0,
            'pricing',
            'Markup Value',
            2,
            false
        );

        $primarySellingPrice = $markupType === 1
            ? round($primaryBase + ($primaryBase * $markupValue / 100), 2)
            : round($primaryBase + $markupValue, 2);

        $result['primary'][$priceLevelId] = [
            'price_level_id' => $priceLevelId,
            'markup_type' => $markupType,
            'markup_value' => $markupValue,
            'selling_price' => $primarySellingPrice,
        ];

        if ($secondaryBase !== null) {
            /*
             * Vimal Aqua unit model:
             * 1 Primary (larger unit) = Conversion Qty x Secondary (base unit).
             * Example: 1 Box = 12 Pieces.
             */
            $secondaryMarkupValue = $markupType === 2
                ? round(
                    $secondaryIsLarger
                        ? $markupValue * $conversionQty
                        : $markupValue / $conversionQty,
                    2
                )
                : $markupValue;

            $secondarySellingPrice = round(
                $secondaryIsLarger
                    ? $primarySellingPrice * $conversionQty
                    : $primarySellingPrice / $conversionQty,
                2
            );

            $result['secondary'][$priceLevelId] = [
                'price_level_id' => $priceLevelId,
                'markup_type' => $markupType,
                'markup_value' => $secondaryMarkupValue,
                'selling_price' => $secondarySellingPrice,
            ];
        }
    }

    return $result;
}

function product_insert_unit(PDO $pdo, int $productId, int $unitId, int $unitType, float $conversionQty): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO product_units (product_id, unit_id, unit_type, conversion_qty, status)
         VALUES (:product_id, :unit_id, :unit_type, :conversion_qty, 1)'
    );
    $stmt->execute([
        ':product_id' => $productId,
        ':unit_id' => $unitId,
        ':unit_type' => $unitType,
        ':conversion_qty' => $conversionQty,
    ]);
    return (int)$pdo->lastInsertId();
}

function product_unit_usage_count(PDO $pdo, int $productUnitId): int
{
    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM purchase_items WHERE product_unit_id = :u1) +
            (SELECT COUNT(*) FROM sales_items WHERE product_unit_id = :u2) +
            (SELECT COUNT(*) FROM customer_order_items WHERE product_unit_id = :u3) +
            (SELECT COUNT(*) FROM customer_product_prices WHERE product_unit_id = :u4)
            AS usage_count'
    );
    $stmt->execute([
        ':u1' => $productUnitId,
        ':u2' => $productUnitId,
        ':u3' => $productUnitId,
        ':u4' => $productUnitId,
    ]);
    return (int)$stmt->fetchColumn();
}

function product_sync_unit(
    PDO $pdo,
    int $productId,
    int $unitType,
    ?int $requestedUnitId,
    float $conversionQty,
    string $label
): ?int {
    $stmt = $pdo->prepare(
        'SELECT id, unit_id, conversion_qty
         FROM product_units
         WHERE product_id = :product_id AND unit_type = :unit_type
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([':product_id' => $productId, ':unit_type' => $unitType]);
    $current = $stmt->fetch();

    if ($requestedUnitId === null) {
        if (!$current) return null;
        $currentId = (int)$current['id'];
        if (product_unit_usage_count($pdo, $currentId) > 0) {
            json_error($label . ' has already been used and cannot be removed.', 409);
        }
        $pdo->prepare('DELETE FROM product_units WHERE id = :id')->execute([':id' => $currentId]);
        return null;
    }

    if ($current && (int)$current['unit_id'] === $requestedUnitId) {
        $currentId = (int)$current['id'];
        $currentConversion = (float)$current['conversion_qty'];
        if (abs($currentConversion - $conversionQty) > 0.00005 && product_unit_usage_count($pdo, $currentId) > 0) {
            json_error($label . ' conversion has already been used and cannot be changed.', 409);
        }
        $pdo->prepare(
            'UPDATE product_units
             SET conversion_qty = :conversion_qty, status = 1
             WHERE id = :id'
        )->execute([
            ':conversion_qty' => $conversionQty,
            ':id' => $currentId,
        ]);
        return $currentId;
    }

    if ($current) {
        $currentId = (int)$current['id'];
        if (product_unit_usage_count($pdo, $currentId) > 0) {
            json_error($label . ' has already been used and cannot be changed.', 409);
        }
        $pdo->prepare('DELETE FROM product_units WHERE id = :id')->execute([':id' => $currentId]);
    }

    return product_insert_unit($pdo, $productId, $requestedUnitId, $unitType, $conversionQty);
}

function product_sync_prices(PDO $pdo, int $productUnitId, array $rows): void
{
    if (!$rows) return;

    $stmt = $pdo->prepare(
        'INSERT INTO product_prices
         (product_unit_id, price_level_id, markup_type, markup_value, selling_price, status)
         VALUES
         (:product_unit_id, :price_level_id, :markup_type, :markup_value, :selling_price, 1)
         ON DUPLICATE KEY UPDATE
            markup_type = VALUES(markup_type),
            markup_value = VALUES(markup_value),
            selling_price = VALUES(selling_price),
            status = 1'
    );

    foreach ($rows as $row) {
        $stmt->execute([
            ':product_unit_id' => $productUnitId,
            ':price_level_id' => (int)$row['price_level_id'],
            ':markup_type' => (int)$row['markup_type'],
            ':markup_value' => (float)$row['markup_value'],
            ':selling_price' => (float)$row['selling_price'],
        ]);
    }
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('product-list.php', ACTION_VIEW);
    $context = product_context($access['user']);
    $branchId = (int)$context['branch_id'];

    json_success('Product form options loaded.', [
        'next_product_code' => product_generate_code($branchId),
        'options' => product_options($branchId),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['subcategories'])) {
    $access = require_permission('product-list.php', ACTION_VIEW);
    $context = product_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $categoryId = positive_id($_GET['category_id'] ?? 0);
    $includeId = isset($_GET['include_subcategory_id']) ? (int)$_GET['include_subcategory_id'] : 0;

    json_success('Subcategories loaded.', [
        'subcategories' => product_subcategory_options($branchId, $categoryId, $includeId),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('product-list.php', ACTION_VIEW);
    $context = product_context($access['user']);
    $payload = product_payload((int)$context['branch_id'], product_id_from_ref($_GET['ref']));
    $payload['allowed_actions'] = $access['actions'];
    json_success('Product loaded.', $payload);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('product-list.php', ACTION_VIEW);
    $context = product_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = max(1, (int)($_GET['draw'] ?? 1));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $requestedLength = (int)($_GET['length'] ?? 10);
    $length = $requestedLength < 0 ? 100000 : max(1, min(100000, $requestedLength));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $statusFilter = (string)($_GET['status'] ?? '');
    $categoryFilter = trim((string)($_GET['category_id'] ?? ''));
    $productTypeFilter = trim((string)($_GET['product_type'] ?? ''));
    $saleAllowedFilter = trim((string)($_GET['sale_allowed'] ?? ''));

    $baseFrom =
        ' FROM products p
          INNER JOIN categories c ON c.id = p.category_id AND c.branch_id = p.branch_id
          LEFT JOIN subcategories s ON s.id = p.subcategory_id AND s.category_id = p.category_id
          LEFT JOIN hsn_master h ON h.id = p.hsn_id AND h.branch_id = p.branch_id
          LEFT JOIN (
             SELECT product_id, MIN(id) AS product_unit_id
             FROM product_units
             WHERE unit_type = 1
             GROUP BY product_id
          ) pick1 ON pick1.product_id = p.id
          LEFT JOIN product_units pu1 ON pu1.id = pick1.product_unit_id
          LEFT JOIN units u1 ON u1.id = pu1.unit_id
          LEFT JOIN (
             SELECT product_id, MIN(id) AS product_unit_id
             FROM product_units
             WHERE unit_type = 2
             GROUP BY product_id
          ) pick2 ON pick2.product_id = p.id
          LEFT JOIN product_units pu2 ON pu2.id = pick2.product_unit_id
          LEFT JOIN units u2 ON u2.id = pu2.unit_id';

    $baseWhere = ['p.branch_id = :branch_id'];
    $where = $baseWhere;
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = '(p.product_code LIKE :search_code
                     OR p.product_name LIKE :search_name
                     OR c.category_name LIKE :search_category
                     OR s.subcategory_name LIKE :search_subcategory
                     OR h.hsn_code LIKE :search_hsn
                     OR u1.unit_name LIKE :search_primary
                     OR u2.unit_name LIKE :search_secondary)';
        $term = '%' . $search . '%';
        $params[':search_code'] = $term;
        $params[':search_name'] = $term;
        $params[':search_category'] = $term;
        $params[':search_subcategory'] = $term;
        $params[':search_hsn'] = $term;
        $params[':search_primary'] = $term;
        $params[':search_secondary'] = $term;
    }

    if ($categoryFilter !== '') {
        $categoryId = (int)$categoryFilter;
        if ($categoryId < 1) {
            json_error('Invalid Category filter.', 422);
        }
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($productTypeFilter !== '') {
        $productType = (int)$productTypeFilter;
        if (!in_array($productType, [1,2,3], true)) {
            json_error('Invalid Product Type filter.', 422);
        }
        $where[] = 'p.product_type = :product_type';
        $params[':product_type'] = $productType;
    }

    if ($saleAllowedFilter !== '') {
        $saleAllowed = (int)$saleAllowedFilter;
        if (!in_array($saleAllowed, [0,1], true)) {
            json_error('Invalid Sales filter.', 422);
        }
        $where[] = 'p.sale_allowed = :sale_allowed';
        $params[':sale_allowed'] = $saleAllowed;
    }

    if ($statusFilter !== '') {
        $where[] = 'p.status = :status';
        $params[':status'] = product_status($statusFilter);
    }

    $totalStmt = db()->prepare('SELECT COUNT(DISTINCT p.id)' . $baseFrom . ' WHERE ' . implode(' AND ', $baseWhere));
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare('SELECT COUNT(DISTINCT p.id)' . $baseFrom . ' WHERE ' . implode(' AND ', $where));
    $filteredStmt->execute($params);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $columns = [
        0 => 'p.product_code',
        1 => 'p.product_name',
        2 => 'p.product_type',
        3 => 'c.category_name',
        4 => 'h.hsn_code',
        5 => 'p.sale_allowed',
        6 => 'p.purchase_price',
        7 => 'u1.unit_name',
        8 => 'u2.unit_name',
        9 => 'p.status',
        10 => 'p.id',
    ];
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderColumn = $columns[$orderIndex] ?? 'p.product_code';

    $sql =
        'SELECT p.id, p.product_code, p.product_name, p.product_type,
                p.sale_allowed, p.purchase_price, p.status,
                c.category_name, s.subcategory_name, h.hsn_code,
                u1.unit_name AS primary_unit_name, u1.short_name AS primary_short_name,
                u2.unit_name AS secondary_unit_name, u2.short_name AS secondary_short_name,
                pu1.conversion_qty AS primary_conversion_qty,
                CASE
                    WHEN pu2.id IS NULL THEN NULL
                    WHEN pu1.conversion_qty > 1.00005 THEN pu1.conversion_qty
                    ELSE pu2.conversion_qty
                END AS secondary_conversion_qty' .
        $baseFrom .
        ' WHERE ' . implode(' AND ', $where) .
        ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ', p.id ASC
          LIMIT :start, :length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $integerKeys = [':branch_id', ':status', ':category_id', ':product_type', ':sale_allowed'];
        $type = in_array($key, $integerKeys, true) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $ref = encryptReference('product', (int)$row['id']);
        unset($row['id']);
        $row['product_type'] = (int)$row['product_type'];
        $row['sale_allowed'] = (int)$row['sale_allowed'];
        $row['purchase_price'] = (float)$row['purchase_price'];
        $row['status'] = (int)$row['status'];
        $row['primary_conversion_qty'] = $row['primary_conversion_qty'] === null ? null : (float)$row['primary_conversion_qty'];
        $row['secondary_conversion_qty'] = $row['secondary_conversion_qty'] === null ? null : (float)$row['secondary_conversion_qty'];
        $row['ref'] = $ref;
        $row['edit_url'] = 'product-form.php?ref=' . $ref;
    }
    unset($row);

    json_success('Product DataTable loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET') {
    $access = require_permission('product-list.php', ACTION_VIEW);
    $context = product_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $sql =
        'SELECT id, product_code, product_name, product_type,
                category_id, subcategory_id, hsn_id, gst_type, purchase_price,
                container_type, sale_allowed, status
         FROM products
         WHERE branch_id = :branch_id';

    if (isset($_GET['active']) && (int)$_GET['active'] === 1) $sql .= ' AND status = 1';
    if (isset($_GET['sale_allowed']) && (int)$_GET['sale_allowed'] === 1) $sql .= ' AND sale_allowed = 1';
    $sql .= ' ORDER BY product_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        foreach (['id','product_type','category_id','gst_type','container_type','sale_allowed','status'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $row['subcategory_id'] = $row['subcategory_id'] === null ? null : (int)$row['subcategory_id'];
        $row['hsn_id'] = $row['hsn_id'] === null ? null : (int)$row['hsn_id'];
        $row['purchase_price'] = (float)$row['purchase_price'];
    }
    unset($row);

    json_success('Products loaded.', [
        'products' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('product-list.php', ACTION_CREATE);
    $user = $access['user'];
    $context = product_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();
    $clean = product_validate($data, $branchId);

    $primaryBase = $clean['purchase_price'];
    $primaryConversion = $clean['secondary_unit_id'] ? $clean['conversion_qty'] : 1.0;
    $secondaryBase = $clean['secondary_unit_id']
        ? round($clean['purchase_price'] / max(0.0001, $clean['conversion_qty']), 2)
        : null;
    $opening = product_parse_opening_stock($data, $clean['secondary_unit_id'], $primaryConversion);
    $prices = product_parse_prices(
        $data,
        $branchId,
        $clean['sale_allowed'],
        $primaryBase,
        $secondaryBase,
        $clean['conversion_qty'],
        false
    );

    $code = product_generate_code($branchId);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO products
             (branch_id, product_code, product_name, product_type,
              category_id, subcategory_id, hsn_id, gst_type, purchase_price,
              container_type, sale_allowed, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, :product_code, :product_name, :product_type,
              :category_id, :subcategory_id, :hsn_id, :gst_type, :purchase_price,
              :container_type, :sale_allowed, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':product_code' => $code,
            ':product_name' => $clean['product_name'],
            ':product_type' => $clean['product_type'],
            ':category_id' => $clean['category_id'],
            ':subcategory_id' => $clean['subcategory_id'],
            ':hsn_id' => $clean['hsn_id'],
            ':gst_type' => $clean['gst_type'],
            ':purchase_price' => $clean['purchase_price'],
            ':container_type' => $clean['container_type'],
            ':sale_allowed' => $clean['sale_allowed'],
            ':status' => $clean['status'],
            ':created_by' => (int)$user['id'],
        ]);

        $productId = (int)$pdo->lastInsertId();
        $primaryProductUnitId = product_insert_unit(
            $pdo,
            $productId,
            $clean['primary_unit_id'],
            1,
            $primaryConversion
        );
        $secondaryProductUnitId = null;

        if ($clean['secondary_unit_id']) {
            $secondaryProductUnitId = product_insert_unit(
                $pdo,
                $productId,
                $clean['secondary_unit_id'],
                2,
                1.0
            );
        }

        product_insert_opening_stock(
            $pdo,
            $branchId,
            $productId,
            $opening['base_qty'],
            (int)$user['id']
        );

        if ($clean['sale_allowed'] === 1) {
            product_sync_prices($pdo, $primaryProductUnitId, $prices['primary']);
            if ($secondaryProductUnitId) {
                product_sync_prices($pdo, $secondaryProductUnitId, $prices['secondary']);
            }
        }

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $productId,
        ]);

        $pdo->commit();
        json_success('Product created successfully.', product_payload($branchId, $productId), 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            json_error('Product or Unit configuration already exists.', 409);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    $access = require_permission('product-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = product_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();
    require_fields($data, ['ref' => 'Product reference is required.']);

    $productId = product_id_from_ref($data['ref']);
    $old = product_payload($branchId, $productId);
    $clean = product_validate($data, $branchId);

    $usageLocked = product_usage_locked($branchId, $productId);
    $oldUnits = $old['units'] ?? ['primary' => null, 'secondary' => null];
    $oldUnitModel = product_unit_model($oldUnits);
    $oldPrimaryUnitId = (int)($oldUnits['primary']['unit_id'] ?? 0);
    $oldSecondaryUnitId = (int)($oldUnits['secondary']['unit_id'] ?? 0);
    $requestedSecondaryId = (int)($clean['secondary_unit_id'] ?? 0);
    $unitIdsChanged =
        (int)$clean['primary_unit_id'] !== $oldPrimaryUnitId ||
        $requestedSecondaryId !== $oldSecondaryUnitId;
    $useLegacyDirection =
        $oldUnitModel === 'legacy_secondary_larger' &&
        !$unitIdsChanged;

    $oldRelationshipConversion = $oldUnitModel === 'legacy_secondary_larger'
        ? (float)($oldUnits['secondary']['conversion_qty'] ?? 1.0)
        : (float)($oldUnits['primary']['conversion_qty'] ?? 1.0);
    $requestedRelationshipConversion = $requestedSecondaryId > 0
        ? (float)$clean['conversion_qty']
        : 1.0;

    if ($usageLocked) {
        if (
            $unitIdsChanged ||
            abs($requestedRelationshipConversion - $oldRelationshipConversion) > 0.00005
        ) {
            json_error(
                'Unit Details cannot be changed because this Product has already been used in a transaction or stock movement.',
                409,
                ['units' => 'Primary Unit, Secondary Unit and Conversion Qty are locked after product usage.']
            );
        }
    }

    $primaryBase = $clean['purchase_price'];
    $primaryConversion = $clean['secondary_unit_id']
        ? ($useLegacyDirection ? 1.0 : $clean['conversion_qty'])
        : 1.0;
    $secondaryUnitConversion = $clean['secondary_unit_id']
        ? ($useLegacyDirection ? $clean['conversion_qty'] : 1.0)
        : 1.0;
    $secondaryBase = $clean['secondary_unit_id']
        ? round(
            $useLegacyDirection
                ? $clean['purchase_price'] * $clean['conversion_qty']
                : $clean['purchase_price'] / max(0.0001, $clean['conversion_qty']),
            2
        )
        : null;
    $opening = product_parse_opening_stock($data, $clean['secondary_unit_id'], $primaryConversion);
    $existingOpening = product_opening_stock($branchId, $productId, $oldUnits);

    if ($opening['base_qty'] > 0 && $useLegacyDirection) {
        json_error('Convert this Product to the new Primary-larger / Secondary-base unit model before entering Opening Stock.', 409);
    }
    if ($opening['base_qty'] > 0 && $existingOpening['entered']) {
        json_error('Opening Stock has already been entered for this Product and cannot be edited.', 409);
    }
    if ($opening['base_qty'] > 0 && $usageLocked && !$existingOpening['entered']) {
        json_error('Opening Stock cannot be added after this Product has already been used. Use Stock Adjustment instead.', 409);
    }
    $prices = product_parse_prices(
        $data,
        $branchId,
        $clean['sale_allowed'],
        $primaryBase,
        $secondaryBase,
        $clean['conversion_qty'],
        $useLegacyDirection
    );

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'UPDATE products
             SET product_name = :product_name,
                 product_type = :product_type,
                 category_id = :category_id,
                 subcategory_id = :subcategory_id,
                 hsn_id = :hsn_id,
                 gst_type = :gst_type,
                 purchase_price = :purchase_price,
                 container_type = :container_type,
                 sale_allowed = :sale_allowed,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id AND branch_id = :branch_id'
        )->execute([
            ':product_name' => $clean['product_name'],
            ':product_type' => $clean['product_type'],
            ':category_id' => $clean['category_id'],
            ':subcategory_id' => $clean['subcategory_id'],
            ':hsn_id' => $clean['hsn_id'],
            ':gst_type' => $clean['gst_type'],
            ':purchase_price' => $clean['purchase_price'],
            ':container_type' => $clean['container_type'],
            ':sale_allowed' => $clean['sale_allowed'],
            ':status' => $clean['status'],
            ':id' => $productId,
            ':branch_id' => $branchId,
        ]);

        $primaryProductUnitId = product_sync_unit(
            $pdo,
            $productId,
            1,
            $clean['primary_unit_id'],
            $primaryConversion,
            'Primary Unit'
        );

        $secondaryProductUnitId = product_sync_unit(
            $pdo,
            $productId,
            2,
            $clean['secondary_unit_id'],
            $secondaryUnitConversion,
            'Secondary Unit'
        );

        if (!$primaryProductUnitId) json_error('Primary Unit configuration is invalid.', 409);

        if ($opening['base_qty'] > 0) {
            product_insert_opening_stock(
                $pdo,
                $branchId,
                $productId,
                $opening['base_qty'],
                (int)$user['id']
            );
        }

        if ($clean['sale_allowed'] === 1) {
            product_sync_prices($pdo, $primaryProductUnitId, $prices['primary']);
            if ($secondaryProductUnitId) {
                product_sync_prices($pdo, $secondaryProductUnitId, $prices['secondary']);
            }
        }

        audit_log((int)$user['id'], ACTION_UPDATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $productId,
            'old_data' => $old,
        ]);

        $pdo->commit();
        json_success('Product updated successfully.', product_payload($branchId, $productId));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            json_error('Product Unit configuration already exists.', 409);
        }
        throw $e;
    }
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, [
        'ref' => 'Product reference is required.',
        'status' => 'Status is required.',
    ]);

    $status = product_status($data['status']);
    $action = $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;
    $access = require_permission('product-list.php', $action);
    $user = $access['user'];
    $context = product_context($user);
    $branchId = (int)$context['branch_id'];
    $productId = product_id_from_ref($data['ref']);
    $old = product_record($branchId, $productId);

    db()->prepare(
        'UPDATE products SET status = :status, updated_at = NOW()
         WHERE id = :id AND branch_id = :branch_id'
    )->execute([
        ':status' => $status,
        ':id' => $productId,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], $action, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $productId,
        'old_data' => $old,
    ]);

    json_success($status === 1 ? 'Product activated.' : 'Product deactivated.');
}

json_error('Method not allowed.', 405);
