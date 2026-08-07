<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\SaleItem;
use RuntimeException;

/**
 * @extends BaseRepository<SaleItem>
 */
final class SaleItemRepository extends BaseRepository
{
    protected string $table = 'sale_items';

    protected string $modelClass = SaleItem::class;

    protected array $selectColumns = [
        'id',
        'sale_id',
        'product_id',
        'unit_id',
        'tax_id',
        'quantity',
        'fulfilled_quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'taxable_amount',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_total',
        'notes',
        'created_at',
        'updated_at',
    ];

    public function bySale(int $saleId): array
    {
        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `sale_items`
             WHERE `sale_id` = :sale_id
             ORDER BY `id` ASC",
            [
                'sale_id' => $saleId,
            ]
        );

        /** @var list<SaleItem> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    public function find(int $saleItemId): ?SaleItem
    {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_items`
             WHERE `id` = :sale_item_id
             LIMIT 1",
            [
                'sale_item_id' => $saleItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item = $this->hydrate($row);

        return $item instanceof SaleItem
            ? $item
            : null;
    }

    public function findForUpdate(
        int $saleItemId
    ): ?SaleItem {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_items`
             WHERE `id` = :sale_item_id
             LIMIT 1
             FOR UPDATE",
            [
                'sale_item_id' => $saleItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item = $this->hydrate($row);

        return $item instanceof SaleItem
            ? $item
            : null;
    }

    public function create(array $data): SaleItem
    {
        $this->execute(
            'INSERT INTO `sale_items` (
                `sale_id`,
                `product_id`,
                `unit_id`,
                `tax_id`,
                `quantity`,
                `fulfilled_quantity`,
                `unit_price`,
                `discount_type`,
                `discount_value`,
                `discount_amount`,
                `taxable_amount`,
                `tax_rate`,
                `tax_amount`,
                `line_subtotal`,
                `line_total`,
                `notes`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :sale_id,
                :product_id,
                :unit_id,
                :tax_id,
                :quantity,
                :fulfilled_quantity,
                :unit_price,
                :discount_type,
                :discount_value,
                :discount_amount,
                :taxable_amount,
                :tax_rate,
                :tax_amount,
                :line_subtotal,
                :line_total,
                :notes,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            [
                'sale_id' => $data['sale_id'],
                'product_id' => $data['product_id'],
                'unit_id' => $data['unit_id'],
                'tax_id' => $data['tax_id'],
                'quantity' => $data['quantity'],
                'fulfilled_quantity' =>
                    $data['fulfilled_quantity'],
                'unit_price' => $data['unit_price'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'discount_amount' => $data['discount_amount'],
                'taxable_amount' => $data['taxable_amount'],
                'tax_rate' => $data['tax_rate'],
                'tax_amount' => $data['tax_amount'],
                'line_subtotal' => $data['line_subtotal'],
                'line_total' => $data['line_total'],
                'notes' => $data['notes'],
            ]
        );

        $itemId =
            (int) $this->connection()->lastInsertId();

        $item = $this->find($itemId);

        if (!$item instanceof SaleItem) {
            throw new RuntimeException(
                'The sale item was created but could not be reloaded.'
            );
        }

        return $item;
    }

    public function update(
        int $saleItemId,
        array $data
    ): ?SaleItem {
        $this->execute(
            'UPDATE `sale_items`
             SET
                `product_id` = :product_id,
                `unit_id` = :unit_id,
                `tax_id` = :tax_id,
                `quantity` = :quantity,
                `unit_price` = :unit_price,
                `discount_type` = :discount_type,
                `discount_value` = :discount_value,
                `discount_amount` = :discount_amount,
                `taxable_amount` = :taxable_amount,
                `tax_rate` = :tax_rate,
                `tax_amount` = :tax_amount,
                `line_subtotal` = :line_subtotal,
                `line_total` = :line_total,
                `notes` = :notes,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_item_id',
            [
                'product_id' => $data['product_id'],
                'unit_id' => $data['unit_id'],
                'tax_id' => $data['tax_id'],
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'discount_amount' => $data['discount_amount'],
                'taxable_amount' => $data['taxable_amount'],
                'tax_rate' => $data['tax_rate'],
                'tax_amount' => $data['tax_amount'],
                'line_subtotal' => $data['line_subtotal'],
                'line_total' => $data['line_total'],
                'notes' => $data['notes'],
                'sale_item_id' => $saleItemId,
            ]
        );

        return $this->find($saleItemId);
    }

    public function updateFulfilledQuantity(
        int $saleItemId,
        float $fulfilledQuantity
    ): ?SaleItem {
        $this->execute(
            'UPDATE `sale_items`
             SET
                `fulfilled_quantity` = :fulfilled_quantity,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_item_id',
            [
                'fulfilled_quantity' => $fulfilledQuantity,
                'sale_item_id' => $saleItemId,
            ]
        );

        return $this->find($saleItemId);
    }

    public function delete(int $saleItemId): bool
    {
        $statement = $this->execute(
            'DELETE FROM `sale_items`
             WHERE `id` = :sale_item_id',
            [
                'sale_item_id' => $saleItemId,
            ]
        );

        return $statement->rowCount() > 0;
    }

    public function deleteBySale(int $saleId): int
    {
        $statement = $this->execute(
            'DELETE FROM `sale_items`
             WHERE `sale_id` = :sale_id',
            [
                'sale_id' => $saleId,
            ]
        );

        return $statement->rowCount();
    }

    public function countBySale(int $saleId): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `sale_items`
             WHERE `sale_id` = :sale_id',
            [
                'sale_id' => $saleId,
            ]
        );
    }

    public function totalsBySale(int $saleId): array
    {
        $row = $this->fetchOne(
            'SELECT
                COALESCE(SUM(`line_subtotal`), 0) AS subtotal,
                COALESCE(SUM(`discount_amount`), 0) AS discount_amount,
                COALESCE(SUM(`tax_amount`), 0) AS tax_amount,
                COALESCE(SUM(`line_total`), 0) AS grand_total
             FROM `sale_items`
             WHERE `sale_id` = :sale_id',
            [
                'sale_id' => $saleId,
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
            'subtotal' => (float) $row['subtotal'],
            'discount_amount' =>
                (float) $row['discount_amount'],
            'tax_amount' => (float) $row['tax_amount'],
            'grand_total' => (float) $row['grand_total'],
        ];
    }
}