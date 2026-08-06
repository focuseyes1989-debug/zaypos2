<?php

declare(strict_types=1);

return [
    'name' => '202608070006_create_units',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS units (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(30) NOT NULL,
    symbol VARCHAR(20) NULL,
    description VARCHAR(500) NULL,
    decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_units_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_units_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_units_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_units_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_units_company_code (company_id, code),
    KEY idx_units_company_status (company_id, status),
    KEY idx_units_company_name (company_id, name),
    KEY idx_units_deleted_at (deleted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Units',
                'code' => 'units.view',
                'description' => 'View unit records',
            ],
            [
                'name' => 'Create Units',
                'code' => 'units.create',
                'description' => 'Create unit records',
            ],
            [
                'name' => 'Update Units',
                'code' => 'units.update',
                'description' => 'Update unit records',
            ],
            [
                'name' => 'Delete Units',
                'code' => 'units.delete',
                'description' => 'Soft-delete unit records',
            ],
            [
                'name' => 'Restore Units',
                'code' => 'units.restore',
                'description' => 'Restore deleted unit records',
            ],
        ];

        $permissionStatement = $database->prepare(
            'INSERT IGNORE INTO permissions
                (name, code, module, description, created_at)
             VALUES
                (:name, :code, :module, :description, UTC_TIMESTAMP())'
        );

        foreach ($permissions as $permission) {
            $permissionStatement->execute([
                'name' => $permission['name'],
                'code' => $permission['code'],
                'module' => 'units',
                'description' => $permission['description'],
            ]);
        }

        $database->exec(
            "INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             CROSS JOIN permissions p
             WHERE r.code = 'super_admin'
               AND p.module = 'units'"
        );

        $database->exec(
            "INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
             SELECT r.id, p.id, UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p
                 ON p.code IN (
                     'units.view',
                     'units.create',
                     'units.update'
                 )
             WHERE r.code = 'manager'"
        );

        $seedStatement = $database->prepare(
            'INSERT IGNORE INTO units (
                company_id,
                name,
                code,
                symbol,
                description,
                decimal_places,
                sort_order,
                status,
                created_at,
                updated_at
             )
             SELECT
                c.id,
                :name,
                :code,
                :symbol,
                :description,
                :decimal_places,
                :sort_order,
                :status,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             FROM companies c'
        );

        $units = [
            ['Piece', 'pcs', 'PCS', 'Individual item', 0, 10],
            ['Box', 'box', 'BOX', 'Box unit', 0, 20],
            ['Carton', 'ctn', 'CTN', 'Carton unit', 0, 30],
            ['Dozen', 'dozen', 'DOZ', 'Twelve items', 0, 40],
            ['Kilogram', 'kg', 'KG', 'Weight in kilograms', 3, 50],
            ['Gram', 'g', 'G', 'Weight in grams', 3, 60],
            ['Liter', 'l', 'L', 'Volume in liters', 3, 70],
            ['Milliliter', 'ml', 'ML', 'Volume in milliliters', 3, 80],
        ];

        foreach ($units as $unit) {
            $seedStatement->execute([
                'name' => $unit[0],
                'code' => $unit[1],
                'symbol' => $unit[2],
                'description' => $unit[3],
                'decimal_places' => $unit[4],
                'sort_order' => $unit[5],
                'status' => 'active',
            ]);
        }
    },
];