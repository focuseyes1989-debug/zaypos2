<?php

declare(strict_types=1);

return [
    'name' => '202608070022_add_pos_hold_sale',

    'up' => static function (\PDO $database): void {
        /*
         * -------------------------------------------------------------
         * POS Hold / Suspend Sale
         * -------------------------------------------------------------
         */

        $columns = [
            'is_held',
            'held_at',
            'held_by',
        ];

        $existingColumns = [];

        foreach ($columns as $column) {
            $statement =
                $database->query(
                    "SHOW COLUMNS
                     FROM sales
                     LIKE "
                    . $database->quote($column)
                );

            if (
                $statement !== false
                && $statement->fetch(
                    \PDO::FETCH_ASSOC
                ) !== false
            ) {
                $existingColumns[$column] = true;
            }
        }

        if (
            !isset(
                $existingColumns['is_held']
            )
        ) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD COLUMN is_held TINYINT(1) NOT NULL DEFAULT 0
        AFTER pos_shift_id
SQL
            );
        }

        if (
            !isset(
                $existingColumns['held_at']
            )
        ) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD COLUMN held_at DATETIME NULL
        AFTER is_held
SQL
            );
        }

        if (
            !isset(
                $existingColumns['held_by']
            )
        ) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD COLUMN held_by BIGINT UNSIGNED NULL
        AFTER held_at
SQL
            );
        }

        /*
         * -------------------------------------------------------------
         * held_by foreign key
         * -------------------------------------------------------------
         */

        $foreignKeyStatement =
            $database->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'sales'
                   AND CONSTRAINT_NAME = :constraint_name
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
            );

        $foreignKeyStatement->execute([
            'constraint_name' =>
                'fk_sales_held_by',
        ]);

        if (
            (int) $foreignKeyStatement
                ->fetchColumn() === 0
        ) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD CONSTRAINT fk_sales_held_by
        FOREIGN KEY (held_by)
        REFERENCES users(id)
        ON DELETE SET NULL
SQL
            );
        }

        /*
         * -------------------------------------------------------------
         * Index: company + held state + status
         * -------------------------------------------------------------
         */

        $indexStatement =
            $database->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'sales'
                   AND INDEX_NAME = :index_name"
            );

        $indexStatement->execute([
            'index_name' =>
                'idx_sales_pos_hold',
        ]);

        if (
            (int) $indexStatement
                ->fetchColumn() === 0
        ) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD INDEX idx_sales_pos_hold (
        company_id,
        is_held,
        status
    )
SQL
            );
        }

        /*
         * -------------------------------------------------------------
         * Index: held_by
         * -------------------------------------------------------------
         */

        $indexStatement->execute([
            'index_name' =>
                'idx_sales_held_by',
        ]);

        if (
            (int) $indexStatement
                ->fetchColumn() === 0
        ) {
            $database->exec(
                <<<'SQL'
ALTER TABLE sales
    ADD INDEX idx_sales_held_by (
        held_by
    )
SQL
            );
        }

        /*
         * -------------------------------------------------------------
         * POS Hold permissions
         * -------------------------------------------------------------
         */

        $permissions = [
            [
                'name' =>
                    'View Held POS Sales',

                'code' =>
                    'pos_hold.view',

                'description' =>
                    'View held and suspended POS sales',
            ],
            [
                'name' =>
                    'Hold POS Sales',

                'code' =>
                    'pos_hold.hold',

                'description' =>
                    'Hold active POS sales for later resume',
            ],
            [
                'name' =>
                    'Resume Held POS Sales',

                'code' =>
                    'pos_hold.resume',

                'description' =>
                    'Resume previously held POS sales',
            ],
            [
                'name' =>
                    'Cancel Held POS Sales',

                'code' =>
                    'pos_hold.cancel',

                'description' =>
                    'Cancel held POS sales',
            ],
        ];

        $permissionStatement =
            $database->prepare(
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

        foreach (
            $permissions
            as $permission
        ) {
            $permissionStatement->execute([
                'name' =>
                    $permission['name'],

                'code' =>
                    $permission['code'],

                'module' =>
                    'pos_hold',

                'description' =>
                    $permission[
                        'description'
                    ],
            ]);
        }

        /*
         * Super Admin permissions.
         */
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
               AND p.module = 'pos_hold'"
        );

        /*
         * Manager permissions.
         */
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
             WHERE r.code = 'manager'
               AND p.module = 'pos_hold'"
        );
    },
];