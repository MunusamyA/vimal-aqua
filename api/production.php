<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_CREATE')) define('ACTION_CREATE', 2);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_SAVE_DRAFT')) define('ACTION_SAVE_DRAFT', 10);
if (!defined('ACTION_POST')) define('ACTION_POST', 11);

function production_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Production management is available only for tenant users.', 403);
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

function production_nullable($value, int $max = 0): ?string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') return null;

    if ($max > 0 && mb_strlen($value) > $max) {
        json_error('Entered value is too long.', 422);
    }

    return $value;
}

function production_decimal($value, string $field, string $label, int $places = 3): float
{
    $text = trim((string)($value ?? ''));

    if ($text === '') $text = '0';

    $pattern =
        '/^(?:[0-9]+(?:\.[0-9]{1,' . $places . '})?|\.[0-9]{1,' . $places . '})$/';

    if (!preg_match($pattern, $text)) {
        json_error('Production validation failed.', 422, [
            $field => 'Enter a valid ' . $label . '.',
        ]);
    }

    $number = round((float)$text, $places);

    if ($number < 0) {
        json_error('Production validation failed.', 422, [
            $field => $label . ' cannot be negative.',
        ]);
    }

    return $number;
}

function production_date($value): string
{
    $value = trim((string)($value ?? ''));

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        ($errors !== false &&
            ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
    ) {
        json_error('Production validation failed.', 422, [
            'production_date' => 'Enter a valid Production Date.',
        ]);
    }

    return $date->format('Y-m-d');
}

function production_id_from_ref($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Production reference is required.',
            422,
            ['ref' => 'Production reference is required.']
        );
    }

    try {
        $id = (int)decryptReference(trim($value), 'production');
    } catch (Throwable $exception) {
        json_error(
            'Invalid Production reference.',
            422,
            ['ref' => 'Invalid Production reference.']
        );
    }

    if ($id < 1) {
        json_error('Invalid Production reference.', 422);
    }

    return $id;
}

function production_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT production_no
         FROM production
         WHERE branch_id = :branch_id
           AND production_no REGEXP '^PRD[0-9]+$'
         ORDER BY CAST(SUBSTRING(production_no, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^PRD([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }

    return 'PRD' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function production_stock(int $branchId, int $productId): float
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(quantity_in - quantity_out), 0)
         FROM stock_movements
         WHERE branch_id = :branch_id
           AND product_id = :product_id'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
    ]);

    return round((float)$stmt->fetchColumn(), 3);
}

function production_product_bundle(
    int $branchId,
    int $productId,
    bool $finished
): array {
    $typeWhere = $finished
        ? 'p.product_type = 2'
        : 'p.product_type IN (1,3)';

    $stmt = db()->prepare(
        'SELECT p.id AS product_id,
                p.product_code,
                p.product_name,
                p.product_type,
                pu.id AS product_unit_id,
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
         WHERE p.id = :product_id
           AND p.branch_id = :branch_id
           AND p.status = 1
           AND ' . $typeWhere . '
         ORDER BY pu.unit_type ASC, pu.id ASC'
    );

    $stmt->execute([
        ':product_id' => $productId,
        ':branch_id' => $branchId,
    ]);

    $rows = $stmt->fetchAll();

    if (!$rows) {
        json_error(
            $finished
                ? 'Selected Finished Product is invalid or inactive.'
                : 'Selected Material Product is invalid or inactive.',
            422
        );
    }

    $bundle = [
        'id' => $productId,
        'product_code' => (string)$rows[0]['product_code'],
        'product_name' => (string)$rows[0]['product_name'],
        'product_type' => (int)$rows[0]['product_type'],
        'primary_unit' => null,
        'secondary_unit' => null,
    ];

    foreach ($rows as $row) {
        $unit = [
            'product_unit_id' => (int)$row['product_unit_id'],
            'unit_type' => (int)$row['unit_type'],
            'conversion_qty' => max(1, round((float)$row['conversion_qty'], 4)),
            'unit_name' => (string)$row['unit_name'],
            'short_name' => (string)$row['short_name'],
        ];

        if ((int)$row['unit_type'] === 1 && $bundle['primary_unit'] === null) {
            $bundle['primary_unit'] = $unit;
        } elseif ((int)$row['unit_type'] === 2 && $bundle['secondary_unit'] === null) {
            $bundle['secondary_unit'] = $unit;
        }
    }

    if ($bundle['primary_unit'] === null) {
        json_error('Selected Product has no active Primary Unit.', 422);
    }

    return $bundle;
}

function production_options(int $branchId): array
{
    $finishedStmt = db()->prepare(
        'SELECT p.id,
                p.product_code,
                p.product_name,
                pu.id AS product_unit_id,
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
         WHERE p.branch_id = :branch_id
           AND p.status = 1
           AND p.product_type = 2
         ORDER BY p.product_name ASC,
                  pu.unit_type ASC,
                  pu.id ASC'
    );

    $finishedStmt->execute([':branch_id' => $branchId]);

    $finished = [];

    foreach ($finishedStmt->fetchAll() as $row) {
        $id = (int)$row['id'];

        if (!isset($finished[$id])) {
            $finished[$id] = [
                'id' => $id,
                'product_code' => (string)$row['product_code'],
                'product_name' => (string)$row['product_name'],
                'primary_unit' => null,
                'secondary_unit' => null,
            ];
        }

        $unit = [
            'product_unit_id' => (int)$row['product_unit_id'],
            'unit_type' => (int)$row['unit_type'],
            'conversion_qty' => max(1, round((float)$row['conversion_qty'], 4)),
            'unit_name' => (string)$row['unit_name'],
            'short_name' => (string)$row['short_name'],
        ];

        if ((int)$row['unit_type'] === 1 && $finished[$id]['primary_unit'] === null) {
            $finished[$id]['primary_unit'] = $unit;
        } elseif ((int)$row['unit_type'] === 2 && $finished[$id]['secondary_unit'] === null) {
            $finished[$id]['secondary_unit'] = $unit;
        }
    }

    $materialStmt = db()->prepare(
        'SELECT p.id,
                p.product_code,
                p.product_name,
                p.product_type,
                pu.id AS product_unit_id,
                pu.unit_type,
                pu.conversion_qty,
                u.unit_name,
                u.short_name,
                COALESCE(
                    (
                        SELECT SUM(sm.quantity_in - sm.quantity_out)
                        FROM stock_movements sm
                        WHERE sm.branch_id = p.branch_id
                          AND sm.product_id = p.id
                    ),
                    0
                ) AS available_stock
         FROM products p
         INNER JOIN product_units pu
            ON pu.product_id = p.id
           AND pu.status = 1
         INNER JOIN units u
            ON u.id = pu.unit_id
         WHERE p.branch_id = :branch_id
           AND p.status = 1
           AND p.product_type IN (1,3)
         ORDER BY p.product_name ASC,
                  pu.unit_type ASC,
                  pu.id ASC'
    );

    $materialStmt->execute([':branch_id' => $branchId]);

    $materials = [];

    foreach ($materialStmt->fetchAll() as $row) {
        $id = (int)$row['id'];

        if (!isset($materials[$id])) {
            $materials[$id] = [
                'id' => $id,
                'product_code' => (string)$row['product_code'],
                'product_name' => (string)$row['product_name'],
                'product_type' => (int)$row['product_type'],
                'available_stock' => round((float)$row['available_stock'], 3),
                'primary_unit' => null,
                'secondary_unit' => null,
            ];
        }

        $unit = [
            'product_unit_id' => (int)$row['product_unit_id'],
            'unit_type' => (int)$row['unit_type'],
            'conversion_qty' => max(1, round((float)$row['conversion_qty'], 4)),
            'unit_name' => (string)$row['unit_name'],
            'short_name' => (string)$row['short_name'],
        ];

        if ((int)$row['unit_type'] === 1 && $materials[$id]['primary_unit'] === null) {
            $materials[$id]['primary_unit'] = $unit;
        } elseif ((int)$row['unit_type'] === 2 && $materials[$id]['secondary_unit'] === null) {
            $materials[$id]['secondary_unit'] = $unit;
        }
    }

    foreach ($materials as $id => $materialRow) {
        if ($materialRow['primary_unit'] === null) {
            unset($materials[$id]);
        }
    }

    return [
        'finished_products' => array_values($finished),
        'material_products' => array_values($materials),
    ];
}

function production_parse_json_list(array $data, string $key, string $label): array
{
    $raw = $data[$key] ?? '[]';

    $rows = is_array($raw)
        ? $raw
        : json_decode((string)$raw, true);

    if (!is_array($rows) || $rows === []) {
        json_error(
            'Production validation failed.',
            422,
            [$key => 'Add at least one ' . $label . '.']
        );
    }

    return $rows;
}

function production_calculate(
    array $data,
    int $branchId,
    bool $validateStock
): array {
    $rawOutputs = production_parse_json_list(
        $data,
        'outputs_json',
        'Finished Product'
    );

    $outputs = [];
    $seenOutputs = [];

    foreach ($rawOutputs as $raw) {
        if (!is_array($raw)) {
            json_error('Finished Product format is invalid.', 422);
        }

        $productId = (int)($raw['product_id'] ?? 0);

        if ($productId < 1) {
            json_error('Finished Product is required.', 422);
        }

        if (isset($seenOutputs[$productId])) {
            json_error(
                'The same Finished Product cannot be added more than once.',
                422
            );
        }

        $seenOutputs[$productId] = true;

        $product = production_product_bundle(
            $branchId,
            $productId,
            true
        );

        $primaryQty = production_decimal(
            $raw['primary_qty'] ?? 0,
            'outputs',
            'Primary Quantity'
        );

        $secondaryQty = production_decimal(
            $raw['secondary_qty'] ?? 0,
            'outputs',
            'Secondary Quantity'
        );

        if ($product['secondary_unit'] === null && $secondaryQty > 0) {
            json_error(
                $product['product_name'] . ' has no Secondary Unit.',
                422
            );
        }

        $primaryConversion = max(
            1,
            (float)$product['primary_unit']['conversion_qty']
        );

        $secondaryConversion = $product['secondary_unit'] !== null
            ? max(1, (float)$product['secondary_unit']['conversion_qty'])
            : 0.0;

        $outputBaseQty = round(
            $primaryQty * $primaryConversion +
            $secondaryQty * $secondaryConversion,
            3
        );

        if ($outputBaseQty <= 0) {
            json_error(
                'Production Quantity must be greater than zero for ' .
                $product['product_name'] . '.',
                422
            );
        }

        $outputs[] = [
            'product_id' => $productId,
            'primary_product_unit_id' =>
                (int)$product['primary_unit']['product_unit_id'],
            'secondary_product_unit_id' =>
                $product['secondary_unit'] !== null
                    ? (int)$product['secondary_unit']['product_unit_id']
                    : null,
            'primary_qty' => $primaryQty,
            'secondary_qty' => $secondaryQty,
            'primary_conversion_qty' => $primaryConversion,
            'secondary_conversion_qty' => $secondaryConversion,
            'output_base_qty' => $outputBaseQty,
        ];
    }

    $rawMaterials = production_parse_json_list(
        $data,
        'materials_json',
        'Material'
    );

    $materials = [];
    $seenMaterials = [];

    foreach ($rawMaterials as $raw) {
        if (!is_array($raw)) {
            json_error('Material format is invalid.', 422);
        }

        $productId = (int)($raw['product_id'] ?? 0);

        if ($productId < 1) {
            json_error('Material Product is required.', 422);
        }

        if (isset($seenMaterials[$productId])) {
            json_error(
                'The same Material cannot be added more than once.',
                422
            );
        }

        $seenMaterials[$productId] = true;

        $material = production_product_bundle(
            $branchId,
            $productId,
            false
        );

        $primaryQty = production_decimal(
            $raw['primary_qty'] ?? ($raw['qty'] ?? 0),
            'materials',
            'Primary Quantity'
        );

        $secondaryQty = production_decimal(
            $raw['secondary_qty'] ?? 0,
            'materials',
            'Secondary Quantity'
        );

        if ($material['secondary_unit'] === null && $secondaryQty > 0) {
            json_error(
                $material['product_name'] . ' has no Secondary Unit.',
                422
            );
        }

        if ($primaryQty <= 0 && $secondaryQty <= 0) {
            json_error(
                'Material Quantity must be greater than zero.',
                422
            );
        }

        $primaryConversion = max(
            1,
            (float)$material['primary_unit']['conversion_qty']
        );

        $secondaryConversion = $material['secondary_unit'] !== null
            ? max(1, (float)$material['secondary_unit']['conversion_qty'])
            : 0.0;

        /*
         * Server-side re-calculation.
         * Never trust the live browser total.
         *
         * Example:
         * 5 Box x 12 + 3 Piece x 1 = 63 base Pieces.
         */
        $requiredBaseQty = round(
            ($primaryQty * $primaryConversion) +
            ($secondaryQty * $secondaryConversion),
            3
        );

        $available = production_stock(
            $branchId,
            $productId
        );

        if ($validateStock && $available + 0.0005 < $requiredBaseQty) {
            json_error(
                'Insufficient stock for ' .
                $material['product_name'] .
                '. Available: ' .
                number_format($available, 3, '.', '') .
                '. Required: ' .
                number_format($requiredBaseQty, 3, '.', '') .
                '.',
                409
            );
        }

        /*
         * No new database columns:
         * save Primary and Secondary as separate production_materials rows.
         */
        if ($primaryQty > 0) {
            $materials[] = [
                'product_id' => $productId,
                'product_unit_id' =>
                    (int)$material['primary_unit']['product_unit_id'],
                'unit_type' => 1,
                'qty' => $primaryQty,
                'conversion_qty' => $primaryConversion,
                'base_qty' => round(
                    $primaryQty * $primaryConversion,
                    3
                ),
                'available_stock' => $available,
            ];
        }

        if ($secondaryQty > 0 && $material['secondary_unit'] !== null) {
            $materials[] = [
                'product_id' => $productId,
                'product_unit_id' =>
                    (int)$material['secondary_unit']['product_unit_id'],
                'unit_type' => 2,
                'qty' => $secondaryQty,
                'conversion_qty' => $secondaryConversion,
                'base_qty' => round(
                    $secondaryQty * $secondaryConversion,
                    3
                ),
                'available_stock' => $available,
            ];
        }
    }

    return [
        'outputs' => $outputs,
        'materials' => $materials,
    ];
}

function production_record(int $branchId, int $productionId): array
{
    $stmt = db()->prepare(
        'SELECT pr.id,
                pr.branch_id,
                pr.production_no,
                pr.production_date,
                pr.remarks,
                pr.status,
                pr.created_by,
                pr.created_at,
                pr.updated_at
         FROM production pr
         WHERE pr.id = :id
           AND pr.branch_id = :branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $productionId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        json_error(
            'Production record was not found in your branch.',
            404
        );
    }

    $ref = encryptReference('production', $productionId);

    unset($row['id']);

    $row['branch_id'] = (int)$row['branch_id'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = $ref;
    $row['edit_url'] = 'production-form.php?ref=' . $ref;
    $row['view_url'] = 'production-form.php?ref=' . $ref . '&view=1';

    return $row;
}

function production_output_rows(int $productionId): array
{
    $stmt = db()->prepare(
        'SELECT pi.id,
                pi.product_id,
                p.product_name,
                pi.primary_product_unit_id,
                up.unit_name AS primary_unit_name,
                up.short_name AS primary_short_name,
                pi.secondary_product_unit_id,
                us.unit_name AS secondary_unit_name,
                us.short_name AS secondary_short_name,
                pi.primary_qty,
                pi.secondary_qty,
                pi.primary_conversion_qty,
                pi.secondary_conversion_qty,
                pi.output_base_qty
         FROM production_items pi
         INNER JOIN products p
            ON p.id = pi.product_id
         INNER JOIN product_units pup
            ON pup.id = pi.primary_product_unit_id
         INNER JOIN units up
            ON up.id = pup.unit_id
         LEFT JOIN product_units pus
            ON pus.id = pi.secondary_product_unit_id
         LEFT JOIN units us
            ON us.id = pus.unit_id
         WHERE pi.production_id = :production_id
         ORDER BY pi.id ASC'
    );

    $stmt->execute([
        ':production_id' => $productionId,
    ]);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        foreach ([
            'id',
            'product_id',
            'primary_product_unit_id',
        ] as $key) {
            $row[$key] = (int)$row[$key];
        }

        $row['secondary_product_unit_id'] =
            $row['secondary_product_unit_id'] === null
                ? null
                : (int)$row['secondary_product_unit_id'];

        foreach ([
            'primary_qty',
            'secondary_qty',
            'primary_conversion_qty',
            'secondary_conversion_qty',
            'output_base_qty',
        ] as $key) {
            $row[$key] = (float)$row[$key];
        }
    }
    unset($row);

    return $rows;
}

function production_material_rows(int $branchId, int $productionId): array
{
    $stmt = db()->prepare(
        'SELECT pm.id,
                pm.product_id,
                p.product_name,
                pm.product_unit_id,
                pu.unit_type,
                u.unit_name,
                u.short_name,
                pm.qty,
                pm.conversion_qty,
                pm.base_qty,
                COALESCE(
                    (
                        SELECT SUM(sm.quantity_in - sm.quantity_out)
                        FROM stock_movements sm
                        WHERE sm.branch_id = :branch_id_stock
                          AND sm.product_id = pm.product_id
                    ),
                    0
                ) AS available_stock
         FROM production_materials pm
         INNER JOIN products p
            ON p.id = pm.product_id
         INNER JOIN product_units pu
            ON pu.id = pm.product_unit_id
         INNER JOIN units u
            ON u.id = pu.unit_id
         WHERE pm.production_id = :production_id
         ORDER BY pm.product_id ASC, pu.unit_type ASC, pm.id ASC'
    );

    $stmt->execute([
        ':branch_id_stock' => $branchId,
        ':production_id' => $productionId,
    ]);

    $grouped = [];

    foreach ($stmt->fetchAll() as $row) {
        $productId = (int)$row['product_id'];
        $unitType = (int)$row['unit_type'];

        if (!isset($grouped[$productId])) {
            $grouped[$productId] = [
                'product_id' => $productId,
                'product_name' => (string)$row['product_name'],
                'available_stock' => round((float)$row['available_stock'], 3),

                'primary_product_unit_id' => null,
                'primary_unit_name' => null,
                'primary_short_name' => null,
                'primary_qty' => 0.0,
                'primary_conversion_qty' => 0.0,

                'secondary_product_unit_id' => null,
                'secondary_unit_name' => null,
                'secondary_short_name' => null,
                'secondary_qty' => 0.0,
                'secondary_conversion_qty' => 0.0,

                'base_qty' => 0.0,
            ];
        }

        $grouped[$productId]['base_qty'] = round(
            $grouped[$productId]['base_qty'] + (float)$row['base_qty'],
            3
        );

        if ($unitType === 1) {
            $grouped[$productId]['primary_product_unit_id'] =
                (int)$row['product_unit_id'];
            $grouped[$productId]['primary_unit_name'] =
                (string)$row['unit_name'];
            $grouped[$productId]['primary_short_name'] =
                (string)$row['short_name'];
            $grouped[$productId]['primary_qty'] = round(
                $grouped[$productId]['primary_qty'] + (float)$row['qty'],
                3
            );
            $grouped[$productId]['primary_conversion_qty'] =
                (float)$row['conversion_qty'];
        } elseif ($unitType === 2) {
            $grouped[$productId]['secondary_product_unit_id'] =
                (int)$row['product_unit_id'];
            $grouped[$productId]['secondary_unit_name'] =
                (string)$row['unit_name'];
            $grouped[$productId]['secondary_short_name'] =
                (string)$row['short_name'];
            $grouped[$productId]['secondary_qty'] = round(
                $grouped[$productId]['secondary_qty'] + (float)$row['qty'],
                3
            );
            $grouped[$productId]['secondary_conversion_qty'] =
                (float)$row['conversion_qty'];
        }
    }

    return array_values($grouped);
}

function production_payload(int $branchId, int $productionId): array
{
    return [
        'production' => production_record($branchId, $productionId),
        'outputs' => production_output_rows($productionId),
        'materials' => production_material_rows($branchId, $productionId),
    ];
}

function production_save_outputs(
    PDO $pdo,
    int $productionId,
    array $outputs
): void {
    $insert = $pdo->prepare(
        'INSERT INTO production_items
         (
            production_id,
            product_id,
            primary_product_unit_id,
            secondary_product_unit_id,
            primary_qty,
            secondary_qty,
            primary_conversion_qty,
            secondary_conversion_qty,
            output_base_qty
         )
         VALUES
         (
            :production_id,
            :product_id,
            :primary_product_unit_id,
            :secondary_product_unit_id,
            :primary_qty,
            :secondary_qty,
            :primary_conversion_qty,
            :secondary_conversion_qty,
            :output_base_qty
         )'
    );

    foreach ($outputs as $output) {
        $insert->execute([
            ':production_id' => $productionId,
            ':product_id' => $output['product_id'],
            ':primary_product_unit_id' => $output['primary_product_unit_id'],
            ':secondary_product_unit_id' => $output['secondary_product_unit_id'],
            ':primary_qty' => $output['primary_qty'],
            ':secondary_qty' => $output['secondary_qty'],
            ':primary_conversion_qty' => $output['primary_conversion_qty'],
            ':secondary_conversion_qty' => $output['secondary_conversion_qty'],
            ':output_base_qty' => $output['output_base_qty'],
        ]);
    }
}

function production_save_materials(
    PDO $pdo,
    int $productionId,
    array $materials
): void {
    $insert = $pdo->prepare(
        'INSERT INTO production_materials
         (
            production_id,
            product_id,
            product_unit_id,
            qty,
            conversion_qty,
            base_qty
         )
         VALUES
         (
            :production_id,
            :product_id,
            :product_unit_id,
            :qty,
            :conversion_qty,
            :base_qty
         )'
    );

    foreach ($materials as $material) {
        $insert->execute([
            ':production_id' => $productionId,
            ':product_id' => $material['product_id'],
            ':product_unit_id' => $material['product_unit_id'],
            ':qty' => $material['qty'],
            ':conversion_qty' => $material['conversion_qty'],
            ':base_qty' => $material['base_qty'],
        ]);
    }
}

function production_post_stock(
    PDO $pdo,
    int $branchId,
    int $productionId,
    array $outputs,
    array $materials,
    int $userId,
    string $productionDate
): void {
    $movementDate = $productionDate . ' ' . date('H:i:s');

    $consumeInsert = $pdo->prepare(
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
            :movement_date,
            :product_id,
            8,
            :source_id,
            0,
            :quantity_out,
            :created_by,
            NOW()
         )'
    );

    $materialTotals = [];

    foreach ($materials as $material) {
        $productId = (int)$material['product_id'];

        if (!isset($materialTotals[$productId])) {
            $materialTotals[$productId] = 0.0;
        }

        $materialTotals[$productId] = round(
            $materialTotals[$productId] + (float)$material['base_qty'],
            3
        );
    }

    foreach ($materialTotals as $productId => $baseQty) {
        $consumeInsert->execute([
            ':branch_id' => $branchId,
            ':movement_date' => $movementDate,
            ':product_id' => $productId,
            ':source_id' => $productionId,
            ':quantity_out' => $baseQty,
            ':created_by' => $userId,
        ]);
    }

    $outputInsert = $pdo->prepare(
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
            :movement_date,
            :product_id,
            7,
            :source_id,
            :quantity_in,
            0,
            :created_by,
            NOW()
         )'
    );

    foreach ($outputs as $output) {
        $outputInsert->execute([
            ':branch_id' => $branchId,
            ':movement_date' => $movementDate,
            ':product_id' => $output['product_id'],
            ':source_id' => $productionId,
            ':quantity_in' => $output['output_base_qty'],
            ':created_by' => $userId,
        ]);
    }
}

function production_save(
    array $data,
    array $access,
    array $context,
    ?int $productionId = null
): array {
    $user = $access['user'];

    $branchId = (int)$context['branch_id'];
    $userId = (int)$user['id'];

    $intent = strtolower(trim((string)($data['intent'] ?? 'draft')));

    if (!in_array($intent, ['draft','post'], true)) {
        json_error('Invalid Production save action.', 422);
    }

    require_permission(
        'production-list.php',
        $productionId === null ? ACTION_CREATE : ACTION_UPDATE
    );

    require_permission(
        'production-list.php',
        $intent === 'post' ? ACTION_POST : ACTION_SAVE_DRAFT
    );

    $productionDate = production_date($data['production_date'] ?? '');
    $remarks = production_nullable($data['remarks'] ?? null, 255);

    $calculation = production_calculate(
        $data,
        $branchId,
        $intent === 'post'
    );

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($productionId !== null) {
            $lock = $pdo->prepare(
                'SELECT status
                 FROM production
                 WHERE id = :id
                   AND branch_id = :branch_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $lock->execute([
                ':id' => $productionId,
                ':branch_id' => $branchId,
            ]);

            $existingStatus = $lock->fetchColumn();

            if ($existingStatus === false) {
                json_error('Production record was not found.', 404);
            }

            if ((int)$existingStatus !== 1) {
                json_error(
                    'Only Draft Production can be edited or posted.',
                    409
                );
            }
        }

        $status = $intent === 'post' ? 2 : 1;

        if ($productionId === null) {
            $productionNo = production_generate_no($branchId);

            $insert = $pdo->prepare(
                'INSERT INTO production
                 (
                    branch_id,
                    production_no,
                    production_date,
                    finished_product_id,
                    primary_qty,
                    secondary_qty,
                    output_base_qty,
                    remarks,
                    status,
                    created_by,
                    created_at,
                    updated_at
                 )
                 VALUES
                 (
                    :branch_id,
                    :production_no,
                    :production_date,
                    NULL,
                    0,
                    0,
                    0,
                    :remarks,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                 )'
            );

            $insert->execute([
                ':branch_id' => $branchId,
                ':production_no' => $productionNo,
                ':production_date' => $productionDate,
                ':remarks' => $remarks,
                ':status' => $status,
                ':created_by' => $userId,
            ]);

            $productionId = (int)$pdo->lastInsertId();

        } else {
            $update = $pdo->prepare(
                'UPDATE production
                 SET production_date = :production_date,
                     finished_product_id = NULL,
                     primary_qty = 0,
                     secondary_qty = 0,
                     output_base_qty = 0,
                     remarks = :remarks,
                     status = :status,
                     updated_at = NOW()
                 WHERE id = :id
                   AND branch_id = :branch_id'
            );

            $update->execute([
                ':production_date' => $productionDate,
                ':remarks' => $remarks,
                ':status' => $status,
                ':id' => $productionId,
                ':branch_id' => $branchId,
            ]);

            $pdo->prepare(
                'DELETE FROM production_items
                 WHERE production_id = :production_id'
            )->execute([
                ':production_id' => $productionId,
            ]);

            $pdo->prepare(
                'DELETE FROM production_materials
                 WHERE production_id = :production_id'
            )->execute([
                ':production_id' => $productionId,
            ]);
        }

        production_save_outputs(
            $pdo,
            $productionId,
            $calculation['outputs']
        );

        production_save_materials(
            $pdo,
            $productionId,
            $calculation['materials']
        );

        if ($intent === 'post') {
            production_post_stock(
                $pdo,
                $branchId,
                $productionId,
                $calculation['outputs'],
                $calculation['materials'],
                $userId,
                $productionDate
            );
        }

        audit_log(
            $userId,
            $intent === 'post' ? ACTION_POST : ACTION_SAVE_DRAFT,
            [
                'company_id' => (int)$context['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $productionId,
            ]
        );

        $pdo->commit();

        return [
            'production_id' => $productionId,
            'status' => $status,
        ];

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $exception instanceof PDOException &&
            $exception->getCode() === '23000'
        ) {
            json_error(
                'Production number or related reference already exists.',
                409
            );
        }

        throw $exception;
    }
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('production-list.php', ACTION_VIEW);
    $context = production_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $options = production_options($branchId);

    json_success(
        'Production options loaded.',
        [
            'next_production_no' => production_generate_no($branchId),
            'finished_products' => $options['finished_products'],
            'material_products' => $options['material_products'],
            'allowed_actions' => $access['actions'],
        ]
    );
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('production-list.php', ACTION_VIEW);
    $context = production_context($access['user']);
    $productionId = production_id_from_ref($_GET['ref']);

    $payload = production_payload(
        (int)$context['branch_id'],
        $productionId
    );

    $payload['allowed_actions'] = $access['actions'];

    json_success('Production loaded.', $payload);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('production-list.php', ACTION_VIEW);
    $context = production_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $draw = max(1, (int)($_GET['draw'] ?? 1));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $statusFilter = (string)($_GET['status'] ?? '');
    $finishedProductFilter = trim((string)($_GET['finished_product_id'] ?? ''));
    $dateFromRaw = trim((string)($_GET['date_from'] ?? ''));
    $dateToRaw = trim((string)($_GET['date_to'] ?? ''));
    $dateFrom = $dateFromRaw === '' ? null : production_date($dateFromRaw);
    $dateTo = $dateToRaw === '' ? null : production_date($dateToRaw);

    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
        json_error('From Date cannot be after To Date.', 422);
    }

    $baseWhere = ['pr.branch_id = :branch_id'];
    $where = $baseWhere;
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] =
            '(pr.production_no LIKE :search_no
              OR EXISTS (
                  SELECT 1
                  FROM production_items pis
                  INNER JOIN products ps ON ps.id = pis.product_id
                  WHERE pis.production_id = pr.id
                    AND (
                        ps.product_code LIKE :search_code
                        OR ps.product_name LIKE :search_name
                    )
              ))';

        $term = '%' . $search . '%';
        $params[':search_no'] = $term;
        $params[':search_code'] = $term;
        $params[':search_name'] = $term;
    }

    if ($statusFilter !== '') {
        $status = (int)$statusFilter;

        if (!in_array($status, [1,2], true)) {
            json_error('Invalid Production Status.', 422);
        }

        $where[] = 'pr.status = :status';
        $params[':status'] = $status;
    }

    if ($finishedProductFilter !== '') {
        $finishedProductId = positive_id($finishedProductFilter, 'finished_product_id');
        $where[] = 'EXISTS (
            SELECT 1
            FROM production_items pif
            WHERE pif.production_id = pr.id
              AND pif.product_id = :finished_product_id
        )';
        $params[':finished_product_id'] = $finishedProductId;
    }

    if ($dateFrom !== null) {
        $where[] = 'pr.production_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== null) {
        $where[] = 'pr.production_date <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $baseFrom = ' FROM production pr';

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)' .
        $baseFrom .
        ' WHERE ' .
        implode(' AND ', $baseWhere)
    );

    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare(
        'SELECT COUNT(*)' .
        $baseFrom .
        ' WHERE ' .
        implode(' AND ', $where)
    );

    $filteredStmt->execute($params);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $summarySql =
        'SELECT COUNT(*) AS total_production,
                COALESCE(SUM(CASE WHEN pr.status=1 THEN 1 ELSE 0 END),0) AS draft_count,
                COALESCE(SUM(CASE WHEN pr.status=2 THEN 1 ELSE 0 END),0) AS posted_count,
                COALESCE(SUM((SELECT COUNT(*) FROM production_items psi WHERE psi.production_id=pr.id)),0) AS output_items' .
        $baseFrom .
        ' WHERE ' . implode(' AND ', $where);

    $summaryStmt = db()->prepare($summarySql);
    foreach ($params as $key => $value) {
        $type = in_array($key, [':branch_id', ':status', ':finished_product_id'], true)
            ? PDO::PARAM_INT
            : PDO::PARAM_STR;
        $summaryStmt->bindValue($key, $value, $type);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        0 => 'pr.production_no',
        1 => 'pr.production_date',
        2 => 'pr.id',
        3 => 'pr.id',
        4 => 'pr.id',
        5 => 'pr.status',
        6 => 'pr.id',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDir =
        strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc'
            ? 'ASC'
            : 'DESC';

    $orderColumn = $columns[$orderIndex] ?? 'pr.production_date';

    $sql =
        'SELECT pr.id,
                pr.production_no,
                pr.production_date,
                pr.status,
                (
                    SELECT GROUP_CONCAT(p.product_name ORDER BY pi.id SEPARATOR ", ")
                    FROM production_items pi
                    INNER JOIN products p ON p.id = pi.product_id
                    WHERE pi.production_id = pr.id
                ) AS finished_products,
                (
                    SELECT COUNT(*)
                    FROM production_items pi
                    WHERE pi.production_id = pr.id
                ) AS output_count,
                (
                    SELECT COUNT(*)
                    FROM production_materials pm
                    WHERE pm.production_id = pr.id
                ) AS material_count' .
        $baseFrom .
        ' WHERE ' .
        implode(' AND ', $where) .
        ' ORDER BY ' .
        $orderColumn . ' ' . $orderDir .
        ', pr.id DESC
          LIMIT :start, :length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $type =
            in_array($key, [':branch_id', ':status', ':finished_product_id'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;

        $stmt->bindValue($key, $value, $type);
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        $ref = encryptReference('production', $id);

        unset($row['id']);

        $row['output_count'] = (int)$row['output_count'];
        $row['material_count'] = (int)$row['material_count'];
        $row['status'] = (int)$row['status'];
        $row['finished_products'] =
            (string)($row['finished_products'] ?? '');

        $row['ref'] = $ref;
        $row['edit_url'] = 'production-form.php?ref=' . $ref;
        $row['view_url'] = 'production-form.php?ref=' . $ref . '&view=1';
    }
    unset($row);

    json_success(
        'Production loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_production' => (int)($summary['total_production'] ?? 0),
                'draft_count' => (int)($summary['draft_count'] ?? 0),
                'posted_count' => (int)($summary['posted_count'] ?? 0),
                'output_items' => (int)($summary['output_items'] ?? 0),
            ],
            'allowed_actions' => $access['actions'],
        ]
    );
}

if ($method === 'POST') {
    $access = require_permission('production-list.php', ACTION_CREATE);
    $context = production_context($access['user']);

    $result = production_save(
        request_data(),
        $access,
        $context,
        null
    );

    json_success(
        $result['status'] === 2
            ? 'Production posted successfully.'
            : 'Production draft saved successfully.',
        production_payload(
            (int)$context['branch_id'],
            (int)$result['production_id']
        ),
        201
    );
}

if ($method === 'PUT') {
    $access = require_permission('production-list.php', ACTION_UPDATE);
    $context = production_context($access['user']);
    $data = request_data();

    $productionId = production_id_from_ref(
        $data['ref'] ?? null
    );

    $result = production_save(
        $data,
        $access,
        $context,
        $productionId
    );

    json_success(
        $result['status'] === 2
            ? 'Production posted successfully.'
            : 'Production draft updated successfully.',
        production_payload(
            (int)$context['branch_id'],
            $productionId
        )
    );
}

json_error('Method not allowed.', 405);
