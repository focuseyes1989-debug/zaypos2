<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Repositories\ProductRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\WarehouseRepository;
use App\Repositories\WarehouseStockRepository;
use PDO;
use Throwable;

final class InventoryService
{
    public function __construct(
        private readonly WarehouseStockRepository $stockRepository =
            new WarehouseStockRepository(),
        private readonly StockMovementRepository $movementRepository =
            new StockMovementRepository(),
        private readonly ProductRepository $productRepository =
            new ProductRepository(),
        private readonly WarehouseRepository $warehouseRepository =
            new WarehouseRepository(),
        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<WarehouseStock>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginateStock(
        int $companyId,
        array $filters
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $warehouseId = $this->nullableId(
            $filters['warehouse_id'] ?? null
        );

        $productId = $this->nullableId(
            $filters['product_id'] ?? null
        );

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = (int) (
            $filters['per_page'] ?? 20
        );

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        return $this->stockRepository->paginate(
            $companyId,
            $warehouseId,
            $productId,
            $page,
            $perPage
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<StockMovement>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginateMovements(
        int $companyId,
        array $filters
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $warehouseId = $this->nullableId(
            $filters['warehouse_id'] ?? null
        );

        $productId = $this->nullableId(
            $filters['product_id'] ?? null
        );

        $movementType = strtolower(
            trim(
                (string) (
                    $filters['movement_type']
                    ?? ''
                )
            )
        );

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        if (
            $movementType !== ''
            && !in_array(
                $movementType,
                $this->movementTypes(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid stock movement type.'
            );
        }

        if (mb_strlen($search) > 190) {
            throw new ValidationException(
                'Search text must be 190 characters or fewer.'
            );
        }

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = (int) (
            $filters['per_page'] ?? 50
        );

        if ($perPage < 1) {
            $perPage = 50;
        }

        if ($perPage > 200) {
            $perPage = 200;
        }

        return $this->movementRepository->paginate(
            $companyId,
            $warehouseId,
            $productId,
            $movementType,
            $search,
            $page,
            $perPage
        );
    }

    public function stock(
        int $companyId,
        int $warehouseId,
        int $productId
    ): ?WarehouseStock {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        return $this->stockRepository->find(
            $companyId,
            $warehouseId,
            $productId
        );
    }

    /**
     * Create initial/opening stock.
     *
     * Opening stock is allowed only when the current
     * warehouse/product balance is zero.
     */
    public function openingStock(
        int $companyId,
        int $userId,
        int $warehouseId,
        int $productId,
        mixed $quantity,
        mixed $unitCost = 0,
        ?string $notes = null
    ): StockMovement {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $quantity = $this->positiveQuantity(
            $quantity,
            'Opening quantity'
        );

        $unitCost = $this->nonNegativeAmount(
            $unitCost,
            'Unit cost'
        );

        $notes = $this->nullableText(
            $notes,
            5000
        );

        $product = $this->requireStockProduct(
            $companyId,
            $productId
        );

        $warehouse = $this->requireWarehouse(
            $companyId,
            $warehouseId
        );

        $database = Database::connection();

        try {
            $this->beginTransaction($database);

            $stock = $this->stockRepository->findForUpdate(
                $companyId,
                $warehouseId,
                $productId
            );

            if (!$stock instanceof WarehouseStock) {
                $this->stockRepository->create(
                    $companyId,
                    $warehouseId,
                    $productId,
                    0.0,
                    0.0,
                    0.0
                );

                $stock = $this->stockRepository->findForUpdate(
                    $companyId,
                    $warehouseId,
                    $productId
                );
            }

            if (!$stock instanceof WarehouseStock) {
                throw new ValidationException(
                    'Warehouse stock record could not be initialized.'
                );
            }

            if (abs($stock->quantity()) > 0.00005) {
                throw new ValidationException(
                    'Opening stock can only be entered when the current quantity is zero.'
                );
            }

            $before = $stock->quantity();
            $after = $this->roundQuantity(
                $before + $quantity
            );

            $movementAt = $this->utcNow();

            $updatedStock =
                $this->stockRepository->updateBalance(
                    $companyId,
                    $warehouseId,
                    $productId,
                    $after,
                    $stock->reservedQuantity(),
                    $unitCost,
                    $movementAt
                );

            $movement =
                $this->movementRepository->create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'movement_type' => 'opening',
                    'quantity_in' => $quantity,
                    'quantity_out' => 0,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'unit_cost' => $unitCost,
                    'total_cost' => $this->roundMoney(
                        $quantity * $unitCost
                    ),
                    'reference_type' => 'opening',
                    'reference_id' => null,
                    'reference_number' => null,
                    'related_warehouse_id' => null,
                    'notes' => $notes,
                    'movement_at' => $movementAt,
                    'created_by' => $userId,
                ]);

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'inventory.opening',
            'stock_movement',
            $movement->id(),
            [
                'warehouse_id' => $warehouse->id(),
                'product_id' => $product->id(),
                'movement' => $movement->toArray(),
                'stock' => $updatedStock->toArray(),
            ]
        );

        return $movement;
    }

    /**
     * Manual stock adjustment.
     *
     * $direction:
     * - in
     * - out
     */
    public function adjust(
        int $companyId,
        int $userId,
        int $warehouseId,
        int $productId,
        string $direction,
        mixed $quantity,
        mixed $unitCost = 0,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): StockMovement {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $direction = strtolower(
            trim($direction)
        );

        if (
            !in_array(
                $direction,
                ['in', 'out'],
                true
            )
        ) {
            throw new ValidationException(
                'Adjustment direction must be in or out.'
            );
        }

        $quantity = $this->positiveQuantity(
            $quantity,
            'Adjustment quantity'
        );

        $unitCost = $this->nonNegativeAmount(
            $unitCost,
            'Unit cost'
        );

        $referenceNumber = $this->nullableString(
            $referenceNumber,
            100
        );

        $notes = $this->nullableText(
            $notes,
            5000
        );

        $product = $this->requireStockProduct(
            $companyId,
            $productId
        );

        $warehouse = $this->requireWarehouse(
            $companyId,
            $warehouseId
        );

        $database = Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $stock = $this->stockRepository->findForUpdate(
                $companyId,
                $warehouseId,
                $productId
            );

            if (!$stock instanceof WarehouseStock) {
                $this->stockRepository->create(
                    $companyId,
                    $warehouseId,
                    $productId
                );

                $stock = $this->stockRepository->findForUpdate(
                    $companyId,
                    $warehouseId,
                    $productId
                );
            }

            if (!$stock instanceof WarehouseStock) {
                throw new ValidationException(
                    'Warehouse stock record could not be initialized.'
                );
            }

            $before = $stock->quantity();

            if ($direction === 'in') {
                $after = $this->roundQuantity(
                    $before + $quantity
                );

                $newAverageCost =
                    $this->weightedAverageCost(
                        $before,
                        $stock->averageCost(),
                        $quantity,
                        $unitCost
                    );

                $movementType = 'adjustment_in';
                $quantityIn = $quantity;
                $quantityOut = 0.0;
                $movementCost = $unitCost;
            } else {
                $after = $this->roundQuantity(
                    $before - $quantity
                );

                $this->assertNegativeStockAllowed(
                    $product,
                    $warehouse,
                    $after
                );

                $newAverageCost =
                    $stock->averageCost();

                $movementType = 'adjustment_out';
                $quantityIn = 0.0;
                $quantityOut = $quantity;

                /*
                 * Outbound inventory uses the current
                 * weighted-average stock cost.
                 */
                $movementCost =
                    $stock->averageCost();
            }

            $movementAt = $this->utcNow();

            $updatedStock =
                $this->stockRepository->updateBalance(
                    $companyId,
                    $warehouseId,
                    $productId,
                    $after,
                    $stock->reservedQuantity(),
                    $newAverageCost,
                    $movementAt
                );

            $movement =
                $this->movementRepository->create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'movement_type' => $movementType,
                    'quantity_in' => $quantityIn,
                    'quantity_out' => $quantityOut,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'unit_cost' => $movementCost,
                    'total_cost' => $this->roundMoney(
                        $quantity * $movementCost
                    ),
                    'reference_type' => 'adjustment',
                    'reference_id' => null,
                    'reference_number' =>
                        $referenceNumber,
                    'related_warehouse_id' => null,
                    'notes' => $notes,
                    'movement_at' => $movementAt,
                    'created_by' => $userId,
                ]);

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'inventory.adjusted',
            'stock_movement',
            $movement->id(),
            [
                'direction' => $direction,
                'warehouse_id' => $warehouse->id(),
                'product_id' => $product->id(),
                'movement' => $movement->toArray(),
                'stock' => $updatedStock->toArray(),
            ]
        );

        return $movement;
    }

    /**
     * Transfer stock between two warehouses.
     *
     * Creates:
     * - transfer_out at source warehouse
     * - transfer_in at destination warehouse
     *
     * @return array{
     *     out: StockMovement,
     *     in: StockMovement
     * }
     */
    public function transfer(
        int $companyId,
        int $userId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $productId,
        mixed $quantity,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $this->validatePositiveId(
            $fromWarehouseId,
            'Source warehouse ID'
        );

        $this->validatePositiveId(
            $toWarehouseId,
            'Destination warehouse ID'
        );

        if ($fromWarehouseId === $toWarehouseId) {
            throw new ValidationException(
                'Source and destination warehouses must be different.'
            );
        }

        $quantity = $this->positiveQuantity(
            $quantity,
            'Transfer quantity'
        );

        $referenceNumber = $this->nullableString(
            $referenceNumber,
            100
        );

        $notes = $this->nullableText(
            $notes,
            5000
        );

        $product = $this->requireStockProduct(
            $companyId,
            $productId
        );

        $fromWarehouse = $this->requireWarehouse(
            $companyId,
            $fromWarehouseId
        );

        $toWarehouse = $this->requireWarehouse(
            $companyId,
            $toWarehouseId
        );

        $database = Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            /*
             * Lock in deterministic ID order to reduce
             * deadlock risk.
             */
            $firstWarehouseId = min(
                $fromWarehouseId,
                $toWarehouseId
            );

            $secondWarehouseId = max(
                $fromWarehouseId,
                $toWarehouseId
            );

            $firstStock =
                $this->stockRepository->findForUpdate(
                    $companyId,
                    $firstWarehouseId,
                    $productId
                );

            if (!$firstStock instanceof WarehouseStock) {
                $this->stockRepository->create(
                    $companyId,
                    $firstWarehouseId,
                    $productId
                );

                $firstStock =
                    $this->stockRepository->findForUpdate(
                        $companyId,
                        $firstWarehouseId,
                        $productId
                    );
            }

            $secondStock =
                $this->stockRepository->findForUpdate(
                    $companyId,
                    $secondWarehouseId,
                    $productId
                );

            if (!$secondStock instanceof WarehouseStock) {
                $this->stockRepository->create(
                    $companyId,
                    $secondWarehouseId,
                    $productId
                );

                $secondStock =
                    $this->stockRepository->findForUpdate(
                        $companyId,
                        $secondWarehouseId,
                        $productId
                    );
            }

            if (
                !$firstStock instanceof WarehouseStock
                || !$secondStock instanceof WarehouseStock
            ) {
                throw new ValidationException(
                    'Warehouse stock records could not be initialized.'
                );
            }

            $sourceStock =
                $fromWarehouseId === $firstWarehouseId
                    ? $firstStock
                    : $secondStock;

            $destinationStock =
                $toWarehouseId === $firstWarehouseId
                    ? $firstStock
                    : $secondStock;

            $sourceBefore =
                $sourceStock->quantity();

            $sourceAfter =
                $this->roundQuantity(
                    $sourceBefore - $quantity
                );

            $this->assertNegativeStockAllowed(
                $product,
                $fromWarehouse,
                $sourceAfter
            );

            $destinationBefore =
                $destinationStock->quantity();

            $destinationAfter =
                $this->roundQuantity(
                    $destinationBefore + $quantity
                );

            /*
             * Transfer stock keeps the source warehouse
             * weighted-average unit cost.
             */
            $transferUnitCost =
                $sourceStock->averageCost();

            $destinationAverageCost =
                $this->weightedAverageCost(
                    $destinationBefore,
                    $destinationStock->averageCost(),
                    $quantity,
                    $transferUnitCost
                );

            $movementAt = $this->utcNow();

            $sourceUpdated =
                $this->stockRepository->updateBalance(
                    $companyId,
                    $fromWarehouseId,
                    $productId,
                    $sourceAfter,
                    $sourceStock->reservedQuantity(),
                    $sourceStock->averageCost(),
                    $movementAt
                );

            $destinationUpdated =
                $this->stockRepository->updateBalance(
                    $companyId,
                    $toWarehouseId,
                    $productId,
                    $destinationAfter,
                    $destinationStock->reservedQuantity(),
                    $destinationAverageCost,
                    $movementAt
                );

            $totalCost = $this->roundMoney(
                $quantity * $transferUnitCost
            );

            $outMovement =
                $this->movementRepository->create([
                    'company_id' => $companyId,
                    'warehouse_id' => $fromWarehouseId,
                    'product_id' => $productId,
                    'movement_type' => 'transfer_out',
                    'quantity_in' => 0,
                    'quantity_out' => $quantity,
                    'quantity_before' => $sourceBefore,
                    'quantity_after' => $sourceAfter,
                    'unit_cost' => $transferUnitCost,
                    'total_cost' => $totalCost,
                    'reference_type' => 'transfer',
                    'reference_id' => null,
                    'reference_number' =>
                        $referenceNumber,
                    'related_warehouse_id' =>
                        $toWarehouseId,
                    'notes' => $notes,
                    'movement_at' => $movementAt,
                    'created_by' => $userId,
                ]);

            $inMovement =
                $this->movementRepository->create([
                    'company_id' => $companyId,
                    'warehouse_id' => $toWarehouseId,
                    'product_id' => $productId,
                    'movement_type' => 'transfer_in',
                    'quantity_in' => $quantity,
                    'quantity_out' => 0,
                    'quantity_before' =>
                        $destinationBefore,
                    'quantity_after' =>
                        $destinationAfter,
                    'unit_cost' =>
                        $transferUnitCost,
                    'total_cost' => $totalCost,
                    'reference_type' => 'transfer',
                    'reference_id' => null,
                    'reference_number' =>
                        $referenceNumber,
                    'related_warehouse_id' =>
                        $fromWarehouseId,
                    'notes' => $notes,
                    'movement_at' => $movementAt,
                    'created_by' => $userId,
                ]);

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'inventory.transferred',
            'stock_movement',
            $outMovement->id(),
            [
                'product_id' => $product->id(),
                'from_warehouse_id' =>
                    $fromWarehouse->id(),
                'to_warehouse_id' =>
                    $toWarehouse->id(),
                'quantity' => $quantity,
                'out_movement' =>
                    $outMovement->toArray(),
                'in_movement' =>
                    $inMovement->toArray(),
                'source_stock' =>
                    $sourceUpdated->toArray(),
                'destination_stock' =>
                    $destinationUpdated->toArray(),
            ]
        );

        return [
            'out' => $outMovement,
            'in' => $inMovement,
        ];
    }

    /**
     * @return list<StockMovement>
     */
    public function latestProductMovements(
        int $companyId,
        int $productId,
        int $limit = 50
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        return $this->movementRepository->latestForProduct(
            $companyId,
            $productId,
            $limit
        );
    }

    /**
     * @return list<StockMovement>
     */
    public function latestWarehouseMovements(
        int $companyId,
        int $warehouseId,
        int $limit = 100
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        return $this->movementRepository->latestForWarehouse(
            $companyId,
            $warehouseId,
            $limit
        );
    }

    /**
     * @return list<string>
     */
    public function movementTypes(): array
    {
        return [
            'opening',
            'purchase',
            'purchase_return',
            'sale',
            'sale_return',
            'adjustment_in',
            'adjustment_out',
            'transfer_in',
            'transfer_out',
        ];
    }

    private function requireStockProduct(
        int $companyId,
        int $productId
    ): Product {
        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        $product = $this->productRepository->find(
            $companyId,
            $productId
        );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Selected product was not found.'
            );
        }

        if (!$product->isActive()) {
            throw new ValidationException(
                'Selected product is inactive.'
            );
        }

        if ($product->isServiceProduct()) {
            throw new ValidationException(
                'Service products cannot have inventory.'
            );
        }

        if (!$product->tracksStock()) {
            throw new ValidationException(
                'Selected product does not track stock.'
            );
        }

        return $product;
    }

    private function requireWarehouse(
        int $companyId,
        int $warehouseId
    ): Warehouse {
        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        $warehouse = $this->warehouseRepository->find(
            $companyId,
            $warehouseId
        );

        if (!$warehouse instanceof Warehouse) {
            throw new ValidationException(
                'Selected warehouse was not found.'
            );
        }

        if (!$warehouse->isActive()) {
            throw new ValidationException(
                'Selected warehouse is inactive.'
            );
        }

        return $warehouse;
    }

    private function assertNegativeStockAllowed(
        Product $product,
        Warehouse $warehouse,
        float $quantityAfter
    ): void {
        if ($quantityAfter >= 0) {
            return;
        }

        /*
         * Negative stock is permitted only when both
         * product-level and warehouse-level settings
         * allow it.
         */
        if (
            $product->allowsNegativeStock()
            && $warehouse->allowsNegativeStock()
        ) {
            return;
        }

        throw new ValidationException(
            'Insufficient stock. Negative stock is not allowed for this product and warehouse.'
        );
    }

    private function weightedAverageCost(
        float $existingQuantity,
        float $existingAverageCost,
        float $incomingQuantity,
        float $incomingUnitCost
    ): float {
        /*
         * If existing quantity is zero or negative,
         * the new incoming cost becomes the average.
         */
        if ($existingQuantity <= 0) {
            return $this->roundMoney(
                $incomingUnitCost
            );
        }

        $newQuantity =
            $existingQuantity
            + $incomingQuantity;

        if ($newQuantity <= 0) {
            return 0.0;
        }

        $existingValue =
            $existingQuantity
            * $existingAverageCost;

        $incomingValue =
            $incomingQuantity
            * $incomingUnitCost;

        return $this->roundMoney(
            (
                $existingValue
                + $incomingValue
            ) / $newQuantity
        );
    }

    private function positiveQuantity(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $quantity = $this->roundQuantity(
            (float) $value
        );

        if (
            !is_finite($quantity)
            || $quantity <= 0
        ) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }

        if ($quantity > 99999999999999.9999) {
            throw new ValidationException(
                "{$field} is too large."
            );
        }

        return $quantity;
    }

    private function nonNegativeAmount(
        mixed $value,
        string $field
    ): float {
        if (
            $value === null
            || $value === ''
        ) {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $amount = $this->roundMoney(
            (float) $value
        );

        if (
            !is_finite($amount)
            || $amount < 0
        ) {
            throw new ValidationException(
                "{$field} must be zero or greater."
            );
        }

        if ($amount > 99999999999999.9999) {
            throw new ValidationException(
                "{$field} is too large."
            );
        }

        return $amount;
    }

    private function nullableId(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
            || $value === 0
            || $value === '0'
        ) {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new ValidationException(
                'Invalid related record ID.'
            );
        }

        $id = (int) $value;

        if ($id < 1) {
            throw new ValidationException(
                'Related record ID must be greater than zero.'
            );
        }

        return $id;
    }

    private function nullableString(
        ?string $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength) {
            throw new ValidationException(
                "Text must not exceed {$maximumLength} characters."
            );
        }

        return $value;
    }

    private function nullableText(
        ?string $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength) {
            throw new ValidationException(
                "Notes must not exceed {$maximumLength} characters."
            );
        }

        return $value;
    }

    private function validatePositiveId(
        int $value,
        string $field
    ): void {
        if ($value < 1) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }
    }

    private function roundQuantity(
        float $value
    ): float {
        return round(
            $value,
            4
        );
    }

    private function roundMoney(
        float $value
    ): float {
        return round(
            $value,
            4
        );
    }

    private function utcNow(): string
    {
        return gmdate(
            'Y-m-d H:i:s'
        );
    }

    private function beginTransaction(
        PDO $database
    ): void {
        if ($database->inTransaction()) {
            throw new ValidationException(
                'An inventory transaction is already active.'
            );
        }

        if (!$database->beginTransaction()) {
            throw new ValidationException(
                'Inventory transaction could not be started.'
            );
        }
    }

    private function rollbackIfNeeded(
        PDO $database
    ): void {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
    }
}