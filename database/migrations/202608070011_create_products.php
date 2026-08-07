<?php

declare(strict_types=1);

return [
    'name' => '202608070011_create_products',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,

    category_id BIGINT UNSIGNED NULL,
    brand_id BIGINT UNSIGNED NULL,
    base_unit_id BIGINT UNSIGNED NOT NULL,
    purchase_unit_id BIGINT UNSIGNED NULL,
    sale_unit_id BIGINT UNSIGNED NULL,
    tax_id BIGINT UNSIGNED NULL,

    name VARCHAR(190) NOT NULL,
    code VARCHAR(80) NOT NULL,
    sku VARCHAR(100) NULL,
    barcode VARCHAR(100) NULL,

    product_type VARCHAR(20) NOT NULL DEFAULT 'stock',

    purchase_price DECIMAL(18,4) NOT NULL DEFAULT 0,
    sale_price DECIMAL(18,4) NOT NULL DEFAULT 0,
    wholesale_price DECIMAL(18,4) NOT NULL DEFAULT 0,

    track_stock TINYINT(1) NOT NULL DEFAULT 1,
    allow_negative_stock TINYINT(1) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(18,4) NOT NULL DEFAULT 0,

    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_products_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_brand
        FOREIGN KEY (brand_id)
        REFERENCES brands(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_base_unit
        FOREIGN KEY (base_unit_id)
        REFERENCES units(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_products_purchase_unit
        FOREIGN KEY (purchase_unit_id)
        REFERENCES units(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_sale_unit
        FOREIGN KEY (sale_unit_id)
        REFERENCES units(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_tax
        FOREIGN KEY (tax_id)
        REFERENCES taxes(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_products_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_products_company_code (
        company_id,
        code
    ),

    UNIQUE KEY uq_products_company_sku (
        company_id,
        sku
    ),

    UNIQUE KEY uq_products_company_barcode (
        company_id,
        barcode
    ),

    KEY idx_products_company_name (
        company_id,
        name
    ),

    KEY idx_products_company_status (
        company_id,
        status
    ),

    KEY idx_products_company_type (
        company_id,
        product_type
    ),

    KEY idx_products_category (
        category_id
    ),

    KEY idx_products_brand (
        brand_id
    ),

    KEY idx_products_base_unit (
        base_unit_id
    ),

    KEY idx_products_tax (
        tax_id
    ),

    KEY idx_products_deleted_at (
        deleted_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL
        );

        $permissions = [
            [
                'name' => 'View Products',
                'code' => 'products.view',
                'description' => 'View product records',
            ],
            [
                'name' => 'Create Products',
                'code' => 'products.create',
                'description' => 'Create product records',
            ],
            [
                'name' => 'Update Products',
                'code' => 'products.update',
                'description' => 'Update product records',
            ],
            [
                'name' => 'Delete Products',
                'code' => 'products.delete',
                'description' => 'Soft-delete product records',
            ],
            [
                'name' => 'Restore Products',
                'code' => 'products.restore',
                'description' => 'Restore deleted product records',
            ],
        ];

        $permissionStatement = $database->prepare(
            'INSERT IGNORE INTO permissions (
                name,
                code,
                module,
                description,
                created_at
             ) VALUES (
                :name,
                :code,
                :module,
                :description,
                UTC_TIMESTAMP()
             )'
        );

        foreach ($permissions as $permission) {
            $permissionStatement->execute([
                'name' => $permission['name'],
                'code' => $permission['code'],
                'module' => 'products',
                'description' => $permission['description'],
            ]);
        }

        $database->exec(
            "INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
             )
             SELECT
                r.id,
                p.id,
                UTC_TIMESTAMP()
             FROM roles r
             CROSS JOIN permissions p
             WHERE r.code = 'super_admin'
               AND p.module = 'products'"
        );

        $database->exec(
            "INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
             )
             SELECT
                r.id,
                p.id,
                UTC_TIMESTAMP()
             FROM roles r
             INNER JOIN permissions p
                 ON p.code IN (
                     'products.view',
                     'products.create',
                     'products.update'
                 )
             WHERE r.code = 'manager'"
        );
    },
];