<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\WarehouseStock;
use RuntimeException;

/**
 * @extends BaseRepository<WarehouseStock>
 */
final class WarehouseStockRepository extends BaseRepository
{
    protected string $table = 'warehouse_stocks';

    protected string $modelClass = WarehouseStock::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'warehouse_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
        'last_movement_at',
        'created_at',
        'updated_at',
    ];

    public function find(
        int $companyId,
        int $warehouseId,
        int $productId
    ): ?WarehouseStock {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `warehouse_stocks`
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
               AND `product_id` = :product_id
             LIMIT 1",
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $stock = $this->hydrate($row);

        return $stock instanceof WarehouseStock
            ? $stock
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $warehouseId,
        int $productId
    ): ?WarehouseStock {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `warehouse_stocks`
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
               AND `product_id` = :product_id
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $stock = $this->hydrate($row);

        return $stock instanceof WarehouseStock
            ? $stock
            : null;
    }

    /**
     * @return list<WarehouseStock>
     */
    public function byWarehouse(
        int $companyId,
        int $warehouseId
    ): array {
        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `warehouse_stocks`
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
             ORDER BY `product_id` ASC",
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
            ]
        );

        /** @var list<WarehouseStock> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @return list<WarehouseStock>
     */
    public function byProduct(
        int $companyId,
        int $productId
    ): array {
        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `warehouse_stocks`
             WHERE `company_id` = :company_id
               AND `product_id` = :product_id
             ORDER BY `warehouse_id` ASC",
            [
                'company_id' => $companyId,
                'product_id' => $productId,
            ]
        );

        /** @var list<WarehouseStock> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @return array{
     *     items: list<WarehouseStock>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        ?int $warehouseId,
        ?int $productId,
        int $page,
        int $perPage = 20
    ): array {
        $pagination = $this->pagination(
            $page,
            $perPage
        );

        $conditions = [
            '`company_id` = :company_id',
        ];

        $parameters = [
            'company_id' => $companyId,
        ];

        if ($warehouseId !== null) {
            $conditions[] = '`warehouse_id` = :warehouse_id';
            $parameters['warehouse_id'] = $warehouseId;
        }

        if ($productId !== null) {
            $conditions[] = '`product_id` = :product_id';
            $parameters['product_id'] = $productId;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `warehouse_stocks`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `warehouse_stocks`
             WHERE {$where}
             ORDER BY
                `warehouse_id` ASC,
                `product_id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<WarehouseStock> $items */
        $items = $this->hydrateMany($rows);

        return $this->paginationResult(
            $items,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    public function create(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity = 0.0,
        float $reservedQuantity = 0.0,
        float $averageCost = 0.0
    ): WarehouseStock {
        $this->execute(
            'INSERT INTO `warehouse_stocks` (
                `company_id`,
                `warehouse_id`,
                `product_id`,
                `quantity`,
                `reserved_quantity`,
                `average_cost`,
                `last_movement_at`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :warehouse_id,
                :product_id,
                :quantity,
                :reserved_quantity,
                :average_cost,
                NULL,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'reserved_quantity' => $reservedQuantity,
                'average_cost' => $averageCost,
            ]
        );

        $stockId = (int) $this->connection()->lastInsertId();

        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `warehouse_stocks`
             WHERE `id` = :id
             LIMIT 1",
            [
                'id' => $stockId,
            ]
        );

        if ($row === null) {
            throw new RuntimeException(
                'Warehouse stock was created but could not be reloaded.'
            );
        }

        $stock = $this->hydrate($row);

        if (!$stock instanceof WarehouseStock) {
            throw new RuntimeException(
                'Warehouse stock could not be hydrated.'
            );
        }

        return $stock;
    }

    public function updateBalance(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $reservedQuantity,
        float $averageCost,
        ?string $lastMovementAt = null
    ): WarehouseStock {
        $this->execute(
            'UPDATE `warehouse_stocks`
             SET
                `quantity` = :quantity,
                `reserved_quantity` = :reserved_quantity,
                `average_cost` = :average_cost,
                `last_movement_at` = COALESCE(
                    :last_movement_at,
                    `last_movement_at`
                ),
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
               AND `product_id` = :product_id',
            [
                'quantity' => $quantity,
                'reserved_quantity' => $reservedQuantity,
                'average_cost' => $averageCost,
                'last_movement_at' => $lastMovementAt,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        $stock = $this->find(
            $companyId,
            $warehouseId,
            $productId
        );

        if (!$stock instanceof WarehouseStock) {
            throw new RuntimeException(
                'Warehouse stock could not be reloaded after update.'
            );
        }

        return $stock;
    }

    public function setReservedQuantity(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $reservedQuantity
    ): WarehouseStock {
        $this->execute(
            'UPDATE `warehouse_stocks`
             SET
                `reserved_quantity` = :reserved_quantity,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
               AND `product_id` = :product_id',
            [
                'reserved_quantity' => $reservedQuantity,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        $stock = $this->find(
            $companyId,
            $warehouseId,
            $productId
        );

        if (!$stock instanceof WarehouseStock) {
            throw new RuntimeException(
                'Warehouse stock could not be reloaded.'
            );
        }

        return $stock;
    }
}