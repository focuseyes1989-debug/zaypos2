<?php

declare(strict_types=1);

return [
    'name' => '202608070021_create_pos_register_reports',

    'up' => static function (\PDO $database): void {
        $permissions = [
            [
                'name' => 'View POS Register Reports',
                'code' => 'pos_reports.view',
                'description' =>
                    'View POS register and shift summary reports',
            ],
            [
                'name' => 'Print POS Shift Reports',
                'code' => 'pos_reports.print',
                'description' =>
                    'Print POS shift and register reports',
            ],
        ];

        $statement =
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

        foreach ($permissions as $permission) {
            $statement->execute([
                'name' =>
                    $permission['name'],

                'code' =>
                    $permission['code'],

                'module' =>
                    'pos_reports',

                'description' =>
                    $permission['description'],
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
               AND p.module = 'pos_reports'"
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
             CROSS JOIN permissions p
             WHERE r.code = 'manager'
               AND p.module = 'pos_reports'"
        );
    },
];