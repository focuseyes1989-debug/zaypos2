<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\StockMovement;
use RuntimeException;

/**
 * @extends BaseRepository<StockMovement>
 */
final class StockMovementRepository extends BaseRepository
{
    protected string $table = 'stock_movements';

    protected string $modelClass = StockMovement::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'warehouse_id',
        'product_id',
        'movement_type',
        'quantity_in',
        'quantity_out',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'reference_number',
        'related_warehouse_id',
        'notes',
        'movement_at',
        'created_by',
        'created_at',
    ];

    public function find(
        int $companyId,
        int $movementId
    ): ?StockMovement {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `stock_movements`
             WHERE `company_id` = :company_id
               AND `id` = :movement_id
             LIMIT 1",
            [
                'company_id' => $companyId,
                'movement_id' => $movementId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $movement = $this->hydrate($row);

        return $movement instanceof StockMovement
            ? $movement
            : null;
    }

    /**
     * @return array{
     *     items: list<StockMovement>,
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
        string $movementType,
        string $search,
        int $page,
        int $perPage = 50
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

        if ($movementType !== '') {
            $conditions[] = '`movement_type` = :movement_type';
            $parameters['movement_type'] = $movementType;
        }

        if ($search !== '') {
            $conditions[] = '(
                `reference_number` LIKE :reference_number
                OR `reference_type` LIKE :reference_type
                OR `notes` LIKE :notes
            )';

            $searchValue = '%' . $search . '%';

            $parameters['reference_number'] = $searchValue;
            $parameters['reference_type'] = $searchValue;
            $parameters['notes'] = $searchValue;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `stock_movements`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `stock_movements`
             WHERE {$where}
             ORDER BY
                `movement_at` DESC,
                `id` DESC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<StockMovement> $items */
        $items = $this->hydrateMany($rows);

        return $this->paginationResult(
            $items,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * @return list<StockMovement>
     */
    public function latestForProduct(
        int $companyId,
        int $productId,
        int $limit = 50
    ): array {
        $limit = max(
            1,
            min(
                200,
                $limit
            )
        );

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `stock_movements`
             WHERE `company_id` = :company_id
               AND `product_id` = :product_id
             ORDER BY
                `movement_at` DESC,
                `id` DESC
             LIMIT :limit",
            [
                'company_id' => $companyId,
                'product_id' => $productId,
                'limit' => $limit,
            ]
        );

        /** @var list<StockMovement> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @return list<StockMovement>
     */
    public function latestForWarehouse(
        int $companyId,
        int $warehouseId,
        int $limit = 100
    ): array {
        $limit = max(
            1,
            min(
                500,
                $limit
            )
        );

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `stock_movements`
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
             ORDER BY
                `movement_at` DESC,
                `id` DESC
             LIMIT :limit",
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'limit' => $limit,
            ]
        );

        /** @var list<StockMovement> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @param array{
     *     company_id: int,
     *     warehouse_id: int,
     *     product_id: int,
     *     movement_type: string,
     *     quantity_in: float|int|string,
     *     quantity_out: float|int|string,
     *     quantity_before: float|int|string,
     *     quantity_after: float|int|string,
     *     unit_cost: float|int|string,
     *     total_cost: float|int|string,
     *     reference_type: string|null,
     *     reference_id: int|null,
     *     reference_number: string|null,
     *     related_warehouse_id: int|null,
     *     notes: string|null,
     *     movement_at: string,
     *     created_by: int|null
     * } $data
     */
    public function create(
        array $data
    ): StockMovement {
        $this->execute(
            'INSERT INTO `stock_movements` (
                `company_id`,
                `warehouse_id`,
                `product_id`,
                `movement_type`,
                `quantity_in`,
                `quantity_out`,
                `quantity_before`,
                `quantity_after`,
                `unit_cost`,
                `total_cost`,
                `reference_type`,
                `reference_id`,
                `reference_number`,
                `related_warehouse_id`,
                `notes`,
                `movement_at`,
                `created_by`,
                `created_at`
             ) VALUES (
                :company_id,
                :warehouse_id,
                :product_id,
                :movement_type,
                :quantity_in,
                :quantity_out,
                :quantity_before,
                :quantity_after,
                :unit_cost,
                :total_cost,
                :reference_type,
                :reference_id,
                :reference_number,
                :related_warehouse_id,
                :notes,
                :movement_at,
                :created_by,
                UTC_TIMESTAMP()
             )',
            [
                'company_id' => $data['company_id'],
                'warehouse_id' => $data['warehouse_id'],
                'product_id' => $data['product_id'],
                'movement_type' => $data['movement_type'],
                'quantity_in' => $data['quantity_in'],
                'quantity_out' => $data['quantity_out'],
                'quantity_before' => $data['quantity_before'],
                'quantity_after' => $data['quantity_after'],
                'unit_cost' => $data['unit_cost'],
                'total_cost' => $data['total_cost'],
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'reference_number' => $data['reference_number'],
                'related_warehouse_id' => $data['related_warehouse_id'],
                'notes' => $data['notes'],
                'movement_at' => $data['movement_at'],
                'created_by' => $data['created_by'],
            ]
        );

        $movementId = (int) $this->connection()->lastInsertId();

        $movement = $this->find(
            (int) $data['company_id'],
            $movementId
        );

        if (!$movement instanceof StockMovement) {
            throw new RuntimeException(
                'Stock movement was created but could not be reloaded.'
            );
        }

        return $movement;
    }
}