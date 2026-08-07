<?php

declare(strict_types=1);

return [
    'name' => '202608070012_add_product_image',

    'up' => static function (\PDO $database): void {
        $database->exec(
            <<<'SQL'
ALTER TABLE products
    ADD COLUMN image_path VARCHAR(500) NULL
    AFTER barcode
SQL
        );
    },
];