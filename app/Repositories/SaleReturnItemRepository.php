<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\SaleReturnItem;
use RuntimeException;

/**
 * @extends BaseRepository<SaleReturnItem>
 */
final class SaleReturnItemRepository extends BaseRepository
{
    protected string $table = 'sale_return_items';

    protected string $modelClass = SaleReturnItem::class;

    protected array $selectColumns = [
        'id',
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'unit_id',
        'tax_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'taxable_amount',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_total',
        'reason',
        'notes',
        'created_at',
        'updated_at',
    ];

    /**
     * @return list<SaleReturnItem>
     */
    public function byReturn(
        int $saleReturnId
    ): array {
        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_items`
             WHERE `sale_return_id` = :sale_return_id
             ORDER BY `id` ASC",
            [
                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        /** @var list<SaleReturnItem> $items */
        $items =
            $this->hydrateMany($rows);

        return $items;
    }

    public function find(
        int $saleReturnItemId
    ): ?SaleReturnItem {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_items`
             WHERE `id` = :sale_return_item_id
             LIMIT 1",
            [
                'sale_return_item_id' =>
                    $saleReturnItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item =
            $this->hydrate($row);

        return $item instanceof SaleReturnItem
            ? $item
            : null;
    }

    public function findForUpdate(
        int $saleReturnItemId
    ): ?SaleReturnItem {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_items`
             WHERE `id` = :sale_return_item_id
             LIMIT 1
             FOR UPDATE",
            [
                'sale_return_item_id' =>
                    $saleReturnItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item =
            $this->hydrate($row);

        return $item instanceof SaleReturnItem
            ? $item
            : null;
    }

    public function findByReturnAndSaleItem(
        int $saleReturnId,
        int $saleItemId
    ): ?SaleReturnItem {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_items`
             WHERE `sale_return_id` = :sale_return_id
               AND `sale_item_id` = :sale_item_id
             LIMIT 1",
            [
                'sale_return_id' =>
                    $saleReturnId,

                'sale_item_id' =>
                    $saleItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item =
            $this->hydrate($row);

        return $item instanceof SaleReturnItem
            ? $item
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data
    ): SaleReturnItem {
        $this->execute(
            'INSERT INTO `sale_return_items` (
                `sale_return_id`,
                `sale_item_id`,
                `product_id`,
                `unit_id`,
                `tax_id`,
                `quantity`,
                `unit_price`,
                `discount_amount`,
                `taxable_amount`,
                `tax_rate`,
                `tax_amount`,
                `line_subtotal`,
                `line_total`,
                `reason`,
                `notes`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :sale_return_id,
                :sale_item_id,
                :product_id,
                :unit_id,
                :tax_id,
                :quantity,
                :unit_price,
                :discount_amount,
                :taxable_amount,
                :tax_rate,
                :tax_amount,
                :line_subtotal,
                :line_total,
                :reason,
                :notes,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            [
                'sale_return_id' =>
                    $data['sale_return_id'],

                'sale_item_id' =>
                    $data['sale_item_id'],

                'product_id' =>
                    $data['product_id'],

                'unit_id' =>
                    $data['unit_id'],

                'tax_id' =>
                    $data['tax_id'],

                'quantity' =>
                    $data['quantity'],

                'unit_price' =>
                    $data['unit_price'],

                'discount_amount' =>
                    $data['discount_amount'],

                'taxable_amount' =>
                    $data['taxable_amount'],

                'tax_rate' =>
                    $data['tax_rate'],

                'tax_amount' =>
                    $data['tax_amount'],

                'line_subtotal' =>
                    $data['line_subtotal'],

                'line_total' =>
                    $data['line_total'],

                'reason' =>
                    $data['reason'],

                'notes' =>
                    $data['notes'],
            ]
        );

        $itemId =
            (int) $this->connection()
                ->lastInsertId();

        $item =
            $this->find($itemId);

        if (!$item instanceof SaleReturnItem) {
            throw new RuntimeException(
                'The sale return item was created but could not be reloaded.'
            );
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        int $saleReturnItemId,
        array $data
    ): ?SaleReturnItem {
        $this->execute(
            'UPDATE `sale_return_items`
             SET
                `sale_item_id` = :sale_item_id,
                `product_id` = :product_id,
                `unit_id` = :unit_id,
                `tax_id` = :tax_id,
                `quantity` = :quantity,
                `unit_price` = :unit_price,
                `discount_amount` = :discount_amount,
                `taxable_amount` = :taxable_amount,
                `tax_rate` = :tax_rate,
                `tax_amount` = :tax_amount,
                `line_subtotal` = :line_subtotal,
                `line_total` = :line_total,
                `reason` = :reason,
                `notes` = :notes,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_item_id',
            [
                'sale_item_id' =>
                    $data['sale_item_id'],

                'product_id' =>
                    $data['product_id'],

                'unit_id' =>
                    $data['unit_id'],

                'tax_id' =>
                    $data['tax_id'],

                'quantity' =>
                    $data['quantity'],

                'unit_price' =>
                    $data['unit_price'],

                'discount_amount' =>
                    $data['discount_amount'],

                'taxable_amount' =>
                    $data['taxable_amount'],

                'tax_rate' =>
                    $data['tax_rate'],

                'tax_amount' =>
                    $data['tax_amount'],

                'line_subtotal' =>
                    $data['line_subtotal'],

                'line_total' =>
                    $data['line_total'],

                'reason' =>
                    $data['reason'],

                'notes' =>
                    $data['notes'],

                'sale_return_item_id' =>
                    $saleReturnItemId,
            ]
        );

        return $this->find(
            $saleReturnItemId
        );
    }

    public function delete(
        int $saleReturnItemId
    ): bool {
        $statement = $this->execute(
            'DELETE FROM `sale_return_items`
             WHERE `id` = :sale_return_item_id',
            [
                'sale_return_item_id' =>
                    $saleReturnItemId,
            ]
        );

        return $statement->rowCount() > 0;
    }

    public function deleteByReturn(
        int $saleReturnId
    ): int {
        $statement = $this->execute(
            'DELETE FROM `sale_return_items`
             WHERE `sale_return_id` = :sale_return_id',
            [
                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        return $statement->rowCount();
    }

    public function countByReturn(
        int $saleReturnId
    ): int {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `sale_return_items`
             WHERE `sale_return_id` = :sale_return_id',
            [
                'sale_return_id' =>
                    $saleReturnId,
            ]
        );
    }

    /**
     * @return array{
     *     subtotal: float,
     *     discount_amount: float,
     *     tax_amount: float,
     *     grand_total: float
     * }
     */
    public function totalsByReturn(
        int $saleReturnId
    ): array {
        $row = $this->fetchOne(
            'SELECT
                COALESCE(
                    SUM(`line_subtotal`),
                    0
                ) AS subtotal,

                COALESCE(
                    SUM(`discount_amount`),
                    0
                ) AS discount_amount,

                COALESCE(
                    SUM(`tax_amount`),
                    0
                ) AS tax_amount,

                COALESCE(
                    SUM(`line_total`),
                    0
                ) AS grand_total

             FROM `sale_return_items`

             WHERE `sale_return_id`
                = :sale_return_id',
            [
                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        if ($row === null) {
            return [
                'subtotal' => 0.0,
                'discount_amount' => 0.0,
                'tax_amount' => 0.0,
                'grand_total' => 0.0,
            ];
        }

        return [
            'subtotal' =>
                (float) $row['subtotal'],

            'discount_amount' =>
                (float) $row[
                    'discount_amount'
                ],

            'tax_amount' =>
                (float) $row[
                    'tax_amount'
                ],

            'grand_total' =>
                (float) $row[
                    'grand_total'
                ],
        ];
    }

    /**
     * Sum returned quantity for a sale item from
     * active completed returns only.
     */
    public function completedReturnedQuantity(
        int $companyId,
        int $saleItemId
    ): float {
        return (float) $this->fetchValue(
            "SELECT COALESCE(
                SUM(sri.`quantity`),
                0
             )
             FROM `sale_return_items` sri
             INNER JOIN `sale_returns` sr
                 ON sr.`id`
                    = sri.`sale_return_id`
             WHERE sr.`company_id`
                    = :company_id
               AND sri.`sale_item_id`
                    = :sale_item_id
               AND sr.`status`
                    = 'completed'
               AND sr.`deleted_at`
                    IS NULL",
            [
                'company_id' =>
                    $companyId,

                'sale_item_id' =>
                    $saleItemId,
            ]
        );
    }

    /**
     * Same as completedReturnedQuantity(), but
     * excludes one return. Useful while editing or
     * validating the current return.
     */
    public function completedReturnedQuantityExcept(
        int $companyId,
        int $saleItemId,
        int $exceptSaleReturnId
    ): float {
        return (float) $this->fetchValue(
            "SELECT COALESCE(
                SUM(sri.`quantity`),
                0
             )
             FROM `sale_return_items` sri
             INNER JOIN `sale_returns` sr
                 ON sr.`id`
                    = sri.`sale_return_id`
             WHERE sr.`company_id`
                    = :company_id
               AND sri.`sale_item_id`
                    = :sale_item_id
               AND sr.`id`
                    <> :except_sale_return_id
               AND sr.`status`
                    = 'completed'
               AND sr.`deleted_at`
                    IS NULL",
            [
                'company_id' =>
                    $companyId,

                'sale_item_id' =>
                    $saleItemId,

                'except_sale_return_id' =>
                    $exceptSaleReturnId,
            ]
        );
    }
}