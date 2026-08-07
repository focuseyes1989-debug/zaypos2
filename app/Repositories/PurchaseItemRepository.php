<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\PurchaseItem;
use RuntimeException;

/**
 * @extends BaseRepository<PurchaseItem>
 */
final class PurchaseItemRepository extends BaseRepository
{
    protected string $table = 'purchase_items';

    protected string $modelClass = PurchaseItem::class;

    protected array $selectColumns = [
        'id',
        'purchase_id',
        'product_id',
        'unit_id',
        'tax_id',
        'quantity',
        'received_quantity',
        'unit_cost',
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

    /**
     * @return list<PurchaseItem>
     */
    public function byPurchase(
        int $purchaseId
    ): array {
        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_items`
             WHERE `purchase_id` = :purchase_id
             ORDER BY `id` ASC",
            [
                'purchase_id' => $purchaseId,
            ]
        );

        /** @var list<PurchaseItem> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    public function find(
        int $purchaseItemId
    ): ?PurchaseItem {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_items`
             WHERE `id` = :purchase_item_id
             LIMIT 1",
            [
                'purchase_item_id' => $purchaseItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item = $this->hydrate($row);

        return $item instanceof PurchaseItem
            ? $item
            : null;
    }

    public function findForUpdate(
        int $purchaseItemId
    ): ?PurchaseItem {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_items`
             WHERE `id` = :purchase_item_id
             LIMIT 1
             FOR UPDATE",
            [
                'purchase_item_id' => $purchaseItemId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $item = $this->hydrate($row);

        return $item instanceof PurchaseItem
            ? $item
            : null;
    }

    /**
     * @param array{
     *     purchase_id: int,
     *     product_id: int,
     *     unit_id: int,
     *     tax_id: int|null,
     *     quantity: float|int|string,
     *     received_quantity: float|int|string,
     *     unit_cost: float|int|string,
     *     discount_type: string|null,
     *     discount_value: float|int|string,
     *     discount_amount: float|int|string,
     *     taxable_amount: float|int|string,
     *     tax_rate: float|int|string,
     *     tax_amount: float|int|string,
     *     line_subtotal: float|int|string,
     *     line_total: float|int|string,
     *     notes: string|null
     * } $data
     */
    public function create(
        array $data
    ): PurchaseItem {
        $this->execute(
            'INSERT INTO `purchase_items` (
                `purchase_id`,
                `product_id`,
                `unit_id`,
                `tax_id`,
                `quantity`,
                `received_quantity`,
                `unit_cost`,
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
                :purchase_id,
                :product_id,
                :unit_id,
                :tax_id,
                :quantity,
                :received_quantity,
                :unit_cost,
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
                'purchase_id' => $data['purchase_id'],
                'product_id' => $data['product_id'],
                'unit_id' => $data['unit_id'],
                'tax_id' => $data['tax_id'],
                'quantity' => $data['quantity'],
                'received_quantity' => $data['received_quantity'],
                'unit_cost' => $data['unit_cost'],
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

        $itemId = (int) $this->connection()->lastInsertId();

        $item = $this->find(
            $itemId
        );

        if (!$item instanceof PurchaseItem) {
            throw new RuntimeException(
                'The purchase item was created but could not be reloaded.'
            );
        }

        return $item;
    }

    /**
     * @param array{
     *     product_id: int,
     *     unit_id: int,
     *     tax_id: int|null,
     *     quantity: float|int|string,
     *     unit_cost: float|int|string,
     *     discount_type: string|null,
     *     discount_value: float|int|string,
     *     discount_amount: float|int|string,
     *     taxable_amount: float|int|string,
     *     tax_rate: float|int|string,
     *     tax_amount: float|int|string,
     *     line_subtotal: float|int|string,
     *     line_total: float|int|string,
     *     notes: string|null
     * } $data
     */
    public function update(
        int $purchaseItemId,
        array $data
    ): ?PurchaseItem {
        $this->execute(
            'UPDATE `purchase_items`
             SET
                `product_id` = :product_id,
                `unit_id` = :unit_id,
                `tax_id` = :tax_id,
                `quantity` = :quantity,
                `unit_cost` = :unit_cost,
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
             WHERE `id` = :purchase_item_id',
            [
                'product_id' => $data['product_id'],
                'unit_id' => $data['unit_id'],
                'tax_id' => $data['tax_id'],
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'discount_amount' => $data['discount_amount'],
                'taxable_amount' => $data['taxable_amount'],
                'tax_rate' => $data['tax_rate'],
                'tax_amount' => $data['tax_amount'],
                'line_subtotal' => $data['line_subtotal'],
                'line_total' => $data['line_total'],
                'notes' => $data['notes'],
                'purchase_item_id' => $purchaseItemId,
            ]
        );

        return $this->find(
            $purchaseItemId
        );
    }

    public function updateReceivedQuantity(
        int $purchaseItemId,
        float $receivedQuantity
    ): ?PurchaseItem {
        $this->execute(
            'UPDATE `purchase_items`
             SET
                `received_quantity` = :received_quantity,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :purchase_item_id',
            [
                'received_quantity' => $receivedQuantity,
                'purchase_item_id' => $purchaseItemId,
            ]
        );

        return $this->find(
            $purchaseItemId
        );
    }

    public function delete(
        int $purchaseItemId
    ): bool {
        $affected = $this->execute(
            'DELETE FROM `purchase_items`
             WHERE `id` = :purchase_item_id',
            [
                'purchase_item_id' => $purchaseItemId,
            ]
        );

        return $affected > 0;
    }

    public function deleteByPurchase(
        int $purchaseId
    ): int {
        return $this->execute(
            'DELETE FROM `purchase_items`
             WHERE `purchase_id` = :purchase_id',
            [
                'purchase_id' => $purchaseId,
            ]
        );
    }

    public function countByPurchase(
        int $purchaseId
    ): int {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `purchase_items`
             WHERE `purchase_id` = :purchase_id',
            [
                'purchase_id' => $purchaseId,
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
    public function totalsByPurchase(
        int $purchaseId
    ): array {
        $row = $this->fetchOne(
            'SELECT
                COALESCE(SUM(`line_subtotal`), 0) AS subtotal,
                COALESCE(SUM(`discount_amount`), 0) AS discount_amount,
                COALESCE(SUM(`tax_amount`), 0) AS tax_amount,
                COALESCE(SUM(`line_total`), 0) AS grand_total
             FROM `purchase_items`
             WHERE `purchase_id` = :purchase_id',
            [
                'purchase_id' => $purchaseId,
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
            'discount_amount' => (float) $row['discount_amount'],
            'tax_amount' => (float) $row['tax_amount'],
            'grand_total' => (float) $row['grand_total'],
        ];
    }
}