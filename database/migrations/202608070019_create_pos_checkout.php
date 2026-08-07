<?php

declare(strict_types=1);

return [
    'name' => '202608070019_create_pos_checkout',

    'up' => static function (\PDO $database): void {
        /*
         * POS checkout currently reuses:
         *
         * - sales
         * - sale_items
         * - sale_payments
         * - warehouse_stocks
         * - stock_movements
         *
         * No POS transaction tables are required yet.
         *
         * This migration introduces only POS-specific
         * access permissions.
         */

        $permissions = [
            [
                'name' => 'Access POS',
                'code' => 'pos.access',
                'description' =>
                    'Access the POS checkout screen',
            ],
            [
                'name' => 'Process POS Sales',
                'code' => 'pos.checkout',
                'description' =>
                    'Create and complete sales from POS checkout',
            ],
        ];

        $statement = $database->prepare(
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
            $statement->execute([
                'name' =>
                    $permission['name'],

                'code' =>
                    $permission['code'],

                'module' =>
                    'pos',

                'description' =>
                    $permission['description'],
            ]);
        }

        /*
         * Super Admin receives all POS permissions.
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
               AND p.module = 'pos'"
        );

        /*
         * Manager can access and process POS sales.
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
             INNER JOIN permissions p
                 ON p.code IN (
                     'pos.access',
                     'pos.checkout'
                 )
             WHERE r.code = 'manager'"
        );
    },
];