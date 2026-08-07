<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleItemRepository;
use App\Repositories\SaleRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Repositories\WarehouseRepository;
use App\Repositories\WarehouseStockRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SaleService
{
    public function __construct(
        private readonly SaleRepository $saleRepository =
            new SaleRepository(),

        private readonly SaleItemRepository $itemRepository =
            new SaleItemRepository(),

        private readonly CustomerRepository $customerRepository =
            new CustomerRepository(),

        private readonly WarehouseRepository $warehouseRepository =
            new WarehouseRepository(),

        private readonly ProductRepository $productRepository =
            new ProductRepository(),

        private readonly UnitRepository $unitRepository =
            new UnitRepository(),

        private readonly TaxRepository $taxRepository =
            new TaxRepository(),

        private readonly WarehouseStockRepository $stockRepository =
            new WarehouseStockRepository(),

        private readonly StockMovementRepository $movementRepository =
            new StockMovementRepository(),

        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Sale>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        array $filters
    ): array {
        $this->validatePositiveId($companyId, 'Company ID');

        $search = trim((string) ($filters['search'] ?? ''));

        $status = strtolower(
            trim((string) ($filters['status'] ?? ''))
        );

        $paymentStatus = strtolower(
            trim((string) ($filters['payment_status'] ?? ''))
        );

        $customerId = $this->nullableId(
            $filters['customer_id'] ?? null
        );

        $warehouseId = $this->nullableId(
            $filters['warehouse_id'] ?? null
        );

        $page = max(1, (int) ($filters['page'] ?? 1));

        $perPage = (int) ($filters['per_page'] ?? 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $onlyDeleted = filter_var(
            $filters['deleted'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (mb_strlen($search) > 190) {
            throw new ValidationException(
                'Search text must be 190 characters or fewer.'
            );
        }

        if (
            $status !== ''
            && !in_array(
                $status,
                ['draft', 'completed', 'cancelled'],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid sale status filter.'
            );
        }

        if (
            $paymentStatus !== ''
            && !in_array(
                $paymentStatus,
                ['unpaid', 'partial', 'paid'],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment status filter.'
            );
        }

        return $this->saleRepository->paginate(
            $companyId,
            $search,
            $status,
            $paymentStatus,
            $customerId,
            $warehouseId,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $saleId,
        bool $includeDeleted = false
    ): Sale {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($saleId, 'Sale ID');

        $sale = $this->saleRepository->find(
            $companyId,
            $saleId,
            $includeDeleted
        );

        if (!$sale instanceof Sale) {
            throw new ValidationException(
                'Sale not found.'
            );
        }

        return $sale;
    }

    /**
     * @return list<SaleItem>
     */
    public function items(
        int $companyId,
        int $saleId
    ): array {
        $this->find(
            $companyId,
            $saleId,
            true
        );

        return $this->itemRepository->bySale(
            $saleId
        );
    }

    /**
     * @return array{
     *     sale: Sale,
     *     items: list<SaleItem>
     * }
     */
    public function detail(
        int $companyId,
        int $saleId,
        bool $includeDeleted = false
    ): array {
        $sale = $this->find(
            $companyId,
            $saleId,
            $includeDeleted
        );

        return [
            'sale' => $sale,
            'items' => $this->itemRepository->bySale(
                $saleId
            ),
        ];
    }

    /**
     * Create an empty draft sale.
     *
     * Customer is optional so walk-in sales are supported.
     *
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): Sale {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($userId, 'User ID');

        $customerId = $this->nullableId(
            $input['customer_id'] ?? null
        );

        $warehouseId = $this->requiredId(
            $input['warehouse_id'] ?? null,
            'Warehouse'
        );

        if ($customerId !== null) {
            $this->requireCustomer(
                $companyId,
                $customerId
            );
        }

        $this->requireWarehouse(
            $companyId,
            $warehouseId
        );

        $saleNumber = trim(
            (string) ($input['sale_number'] ?? '')
        );

        if ($saleNumber === '') {
            $saleNumber = $this->generateSaleNumber(
                $companyId
            );
        }

        $this->validateSaleNumber(
            $companyId,
            $saleNumber
        );

        $customerReference = $this->nullableString(
            $input['customer_reference'] ?? null,
            100
        );

        $saleDate = $this->dateValue(
            $input['sale_date'] ?? gmdate('Y-m-d'),
            'Sale date'
        );

        $dueDate = $this->nullableDateValue(
            $input['due_date'] ?? null,
            'Due date'
        );

        if (
            $dueDate !== null
            && $dueDate < $saleDate
        ) {
            throw new ValidationException(
                'Due date must not be earlier than sale date.'
            );
        }

        $shippingAmount = $this->nonNegativeAmount(
            $input['shipping_amount'] ?? 0,
            'Shipping amount'
        );

        $otherAmount = $this->nonNegativeAmount(
            $input['other_amount'] ?? 0,
            'Other amount'
        );

        $paidAmount = $this->nonNegativeAmount(
            $input['paid_amount'] ?? 0,
            'Paid amount'
        );

        /*
         * A new draft starts without item rows, therefore the
         * current total consists only of header-level charges.
         */
        $grandTotal = $this->money(
            $shippingAmount + $otherAmount
        );

        if (
            $paidAmount
            > $grandTotal + 0.00005
        ) {
            throw new ValidationException(
                'Paid amount must not exceed grand total.'
            );
        }

        $balanceDue = $this->money(
            max(0, $grandTotal - $paidAmount)
        );

        $paymentStatus = $this->paymentStatus(
            $paidAmount,
            $grandTotal
        );

        $notes = $this->nullableText(
            $input['notes'] ?? null,
            5000
        );

        $sale = $this->saleRepository->create([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId,
            'sale_number' => $saleNumber,
            'customer_reference' => $customerReference,
            'sale_date' => $saleDate,
            'due_date' => $dueDate,
            'status' => 'draft',
            'payment_status' => $paymentStatus,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => $shippingAmount,
            'other_amount' => $otherAmount,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'notes' => $notes,
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'sales.created',
            'sale',
            $sale->id(),
            [
                'sale' => $sale->toArray(),
            ]
        );

        return $sale;
    }

    /**
     * Update a draft sale header.
     *
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $saleId,
        int $userId,
        array $input
    ): Sale {
        $this->validatePositiveId($userId, 'User ID');

        $existing = $this->find(
            $companyId,
            $saleId
        );

        if (!$existing->isDraft()) {
            throw new ValidationException(
                'Only draft sales can be updated.'
            );
        }

        $customerId = array_key_exists(
            'customer_id',
            $input
        )
            ? $this->nullableId($input['customer_id'])
            : $existing->customerId();

        $warehouseId = $this->requiredId(
            $input['warehouse_id']
                ?? $existing->warehouseId(),
            'Warehouse'
        );

        if ($customerId !== null) {
            $this->requireCustomer(
                $companyId,
                $customerId
            );
        }

        $this->requireWarehouse(
            $companyId,
            $warehouseId
        );

        $customerReference = $this->nullableString(
            $input['customer_reference']
                ?? $existing->customerReference(),
            100
        );

        $saleDate = $this->dateValue(
            $input['sale_date']
                ?? $existing
                    ->saleDate()
                    ->format('Y-m-d'),
            'Sale date'
        );

        $dueDate = $this->nullableDateValue(
            $input['due_date']
                ?? $existing
                    ->dueDate()
                    ?->format('Y-m-d'),
            'Due date'
        );

        if (
            $dueDate !== null
            && $dueDate < $saleDate
        ) {
            throw new ValidationException(
                'Due date must not be earlier than sale date.'
            );
        }

        $shippingAmount = $this->nonNegativeAmount(
            $input['shipping_amount']
                ?? $existing->shippingAmount(),
            'Shipping amount'
        );

        $otherAmount = $this->nonNegativeAmount(
            $input['other_amount']
                ?? $existing->otherAmount(),
            'Other amount'
        );

        $paidAmount = $this->nonNegativeAmount(
            $input['paid_amount']
                ?? $existing->paidAmount(),
            'Paid amount'
        );

        $notes = $this->nullableText(
            $input['notes']
                ?? $existing->notes(),
            5000
        );

        $totals = $this->itemRepository->totalsBySale(
            $saleId
        );

        $grandTotal = $this->money(
            $totals['grand_total']
            + $shippingAmount
            + $otherAmount
        );

        if (
            $paidAmount
            > $grandTotal + 0.00005
        ) {
            throw new ValidationException(
                'Paid amount must not exceed grand total.'
            );
        }

        $balanceDue = $this->money(
            max(0, $grandTotal - $paidAmount)
        );

        $paymentStatus = $this->paymentStatus(
            $paidAmount,
            $grandTotal
        );

        $updated = $this->saleRepository->updateDraft(
            $companyId,
            $saleId,
            [
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'customer_reference' => $customerReference,
                'sale_date' => $saleDate,
                'due_date' => $dueDate,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals[
                    'discount_amount'
                ],
                'tax_amount' => $totals['tax_amount'],
                'shipping_amount' => $shippingAmount,
                'other_amount' => $otherAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'notes' => $notes,
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Sale) {
            throw new ValidationException(
                'Sale could not be updated.'
            );
        }

        $this->audit->record(
            'sales.updated',
            'sale',
            $saleId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addItem(
        int $companyId,
        int $saleId,
        int $userId,
        array $input
    ): SaleItem {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId
        );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Items can only be added to draft sales.'
            );
        }

        $data = $this->validateItem(
            $companyId,
            $input
        );

        $item = $this->itemRepository->create([
            'sale_id' => $saleId,
            'product_id' => $data['product_id'],
            'unit_id' => $data['unit_id'],
            'tax_id' => $data['tax_id'],
            'quantity' => $data['quantity'],
            'fulfilled_quantity' => 0,
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
        ]);

        $this->recalculateTotals(
            $companyId,
            $saleId,
            $userId
        );

        $this->audit->record(
            'sales.item_created',
            'sale',
            $saleId,
            [
                'item' => $item->toArray(),
            ]
        );

        return $item;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateItem(
        int $companyId,
        int $saleId,
        int $itemId,
        int $userId,
        array $input
    ): SaleItem {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId
        );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Items can only be updated on draft sales.'
            );
        }

        $existing = $this->itemRepository->find(
            $itemId
        );

        if (
            !$existing instanceof SaleItem
            || $existing->saleId() !== $saleId
        ) {
            throw new ValidationException(
                'Sale item not found.'
            );
        }

        $data = $this->validateItem(
            $companyId,
            $input
        );

        $updated = $this->itemRepository->update(
            $itemId,
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
            ]
        );

        if (!$updated instanceof SaleItem) {
            throw new ValidationException(
                'Sale item could not be updated.'
            );
        }

        $this->recalculateTotals(
            $companyId,
            $saleId,
            $userId
        );

        $this->audit->record(
            'sales.item_updated',
            'sale',
            $saleId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function deleteItem(
        int $companyId,
        int $saleId,
        int $itemId,
        int $userId
    ): void {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId
        );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Items can only be removed from draft sales.'
            );
        }

        $item = $this->itemRepository->find(
            $itemId
        );

        if (
            !$item instanceof SaleItem
            || $item->saleId() !== $saleId
        ) {
            throw new ValidationException(
                'Sale item not found.'
            );
        }

        if (!$this->itemRepository->delete($itemId)) {
            throw new ValidationException(
                'Sale item could not be deleted.'
            );
        }

        $this->recalculateTotals(
            $companyId,
            $saleId,
            $userId
        );

        $this->audit->record(
            'sales.item_deleted',
            'sale',
            $saleId,
            [
                'item' => $item->toArray(),
            ]
        );
    }

    /**
     * Complete a draft sale and post stock OUT.
     *
     * The sale header, fulfilled quantities, warehouse stock
     * and stock movements are committed atomically.
     */
    public function complete(
        int $companyId,
        int $saleId,
        int $userId
    ): Sale {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($saleId, 'Sale ID');
        $this->validatePositiveId($userId, 'User ID');

        $database = Database::connection();

        try {
            $this->beginTransaction($database);

            $sale = $this->saleRepository->findForUpdate(
                $companyId,
                $saleId
            );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Sale not found.'
                );
            }

            if (!$sale->isDraft()) {
                throw new ValidationException(
                    'Only draft sales can be completed.'
                );
            }

            $warehouse = $this->requireWarehouse(
                $companyId,
                $sale->warehouseId()
            );

            $warehouseData = $warehouse->toArray();

            $items = $this->itemRepository->bySale(
                $saleId
            );

            if ($items === []) {
                throw new ValidationException(
                    'Sale must contain at least one item before completion.'
                );
            }

            $movementAt = gmdate('Y-m-d H:i:s');

            foreach ($items as $item) {
                $quantityToFulfil = $this->quantity(
                    $item->remainingQuantity()
                );

                if ($quantityToFulfil <= 0) {
                    continue;
                }

                $product = $this->requireProduct(
                    $companyId,
                    $item->productId()
                );

                $productData = $product->toArray();

                $tracksStock =
                    ($productData['product_type'] ?? '') === 'stock'
                    && !empty($productData['track_stock']);

                if ($tracksStock) {
                    $stock = $this->stockRepository->findForUpdate(
                        $companyId,
                        $sale->warehouseId(),
                        (int) $product->id()
                    );

                    $allowNegative =
                        !empty($productData['allow_negative_stock'])
                        || !empty(
                            $warehouseData['allow_negative_stock']
                        );

                    if (!$stock instanceof WarehouseStock) {
                        if (!$allowNegative) {
                            throw new ValidationException(
                                'Insufficient stock for product: '
                                . (string) (
                                    $productData['name']
                                    ?? ('#' . $product->id())
                                )
                                . '.'
                            );
                        }

                        $this->stockRepository->create(
                            $companyId,
                            $sale->warehouseId(),
                            (int) $product->id(),
                            0,
                            0,
                            0
                        );

                        $stock =
                            $this->stockRepository
                                ->findForUpdate(
                                    $companyId,
                                    $sale->warehouseId(),
                                    (int) $product->id()
                                );
                    }

                    if (!$stock instanceof WarehouseStock) {
                        throw new ValidationException(
                            'Warehouse stock could not be loaded.'
                        );
                    }

                    $before = $this->quantity(
                        $stock->quantity()
                    );

                    $after = $this->quantity(
                        $before - $quantityToFulfil
                    );

                    if (
                        $after < -0.00005
                        && !$allowNegative
                    ) {
                        throw new ValidationException(
                            'Insufficient stock for product: '
                            . (string) (
                                $productData['name']
                                ?? ('#' . $product->id())
                            )
                            . '. Available: '
                            . number_format($before, 4, '.', '')
                            . ', required: '
                            . number_format(
                                $quantityToFulfil,
                                4,
                                '.',
                                ''
                            )
                            . '.'
                        );
                    }

                    $averageCost = $this->money(
                        $stock->averageCost()
                    );

                    $this->stockRepository->updateBalance(
                        $companyId,
                        $sale->warehouseId(),
                        (int) $product->id(),
                        $after,
                        $stock->reservedQuantity(),
                        $averageCost,
                        $movementAt
                    );

                    $this->movementRepository->create([
                        'company_id' => $companyId,
                        'warehouse_id' => $sale->warehouseId(),
                        'product_id' => (int) $product->id(),
                        'movement_type' => 'sale',
                        'quantity_in' => 0,
                        'quantity_out' => $quantityToFulfil,
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'unit_cost' => $averageCost,
                        'total_cost' => $this->money(
                            $quantityToFulfil * $averageCost
                        ),
                        'reference_type' => 'sale',
                        'reference_id' => $saleId,
                        'reference_number' => $sale->saleNumber(),
                        'related_warehouse_id' => null,
                        'notes' => 'Sale completion',
                        'movement_at' => $movementAt,
                        'created_by' => $userId,
                    ]);
                }

                $fulfilled =
                    $this->itemRepository
                        ->updateFulfilledQuantity(
                            (int) $item->id(),
                            $item->quantity()
                        );

                if (!$fulfilled instanceof SaleItem) {
                    throw new ValidationException(
                        'Sale fulfilled quantity could not be updated.'
                    );
                }
            }

            $completed = $this->saleRepository->markCompleted(
                $companyId,
                $saleId,
                $userId
            );

            if (
                !$completed instanceof Sale
                || !$completed->isCompleted()
            ) {
                throw new ValidationException(
                    'Sale could not be marked as completed.'
                );
            }

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded($database);
            throw $exception;
        }

        $this->audit->record(
            'sales.completed',
            'sale',
            $saleId,
            [
                'sale' => $completed->toArray(),
            ]
        );

        return $completed;
    }

    /**
     * Compatibility alias.
     *
     * Older copied code may still call receive(); for Sales,
     * the correct business action is complete().
     */
    public function receive(
        int $companyId,
        int $saleId,
        int $userId
    ): Sale {
        return $this->complete(
            $companyId,
            $saleId,
            $userId
        );
    }

    public function cancel(
        int $companyId,
        int $saleId,
        int $userId,
        mixed $reason = null
    ): Sale {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId
        );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Only draft sales can be cancelled.'
            );
        }

        $reason = $this->nullableString(
            $reason,
            500
        );

        $cancelled = $this->saleRepository->markCancelled(
            $companyId,
            $saleId,
            $userId,
            $reason
        );

        if (
            !$cancelled instanceof Sale
            || !$cancelled->isCancelled()
        ) {
            throw new ValidationException(
                'Sale could not be cancelled.'
            );
        }

        $this->audit->record(
            'sales.cancelled',
            'sale',
            $saleId,
            [
                'sale' => $cancelled->toArray(),
            ]
        );

        return $cancelled;
    }

    public function delete(
        int $companyId,
        int $saleId,
        int $userId
    ): void {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId
        );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Only draft sales can be deleted.'
            );
        }

        if (
            !$this->saleRepository->deleteDraft(
                $companyId,
                $saleId,
                $userId
            )
        ) {
            throw new ValidationException(
                'Sale could not be deleted.'
            );
        }

        $this->audit->record(
            'sales.deleted',
            'sale',
            $saleId,
            [
                'sale' => $sale->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $saleId,
        int $userId
    ): Sale {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId,
            true
        );

        if (!$sale->isDeleted()) {
            throw new ValidationException(
                'Sale is not deleted.'
            );
        }

        if (
            $this->saleRepository->saleNumberExists(
                $companyId,
                $sale->saleNumber(),
                $saleId
            )
        ) {
            throw new ValidationException(
                'Another sale is already using this sale number.'
            );
        }

        if (
            !$this->saleRepository->restoreDeleted(
                $companyId,
                $saleId,
                $userId
            )
        ) {
            throw new ValidationException(
                'Sale could not be restored.'
            );
        }

        $restored = $this->find(
            $companyId,
            $saleId
        );

        $this->audit->record(
            'sales.restored',
            'sale',
            $saleId,
            [
                'sale' => $restored->toArray(),
            ]
        );

        return $restored;
    }

    public function recalculateTotals(
        int $companyId,
        int $saleId,
        int $userId
    ): Sale {
        $this->validatePositiveId($userId, 'User ID');

        $sale = $this->find(
            $companyId,
            $saleId
        );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Sale totals can only be recalculated while draft.'
            );
        }

        $totals = $this->itemRepository->totalsBySale(
            $saleId
        );

        $grandTotal = $this->money(
            $totals['grand_total']
            + $sale->shippingAmount()
            + $sale->otherAmount()
        );

        $paidAmount = $this->money(
            $sale->paidAmount()
        );

        if (
            $paidAmount
            > $grandTotal + 0.00005
        ) {
            throw new ValidationException(
                'Paid amount is larger than the new sale total.'
            );
        }

        $balanceDue = $this->money(
            max(0, $grandTotal - $paidAmount)
        );

        $paymentStatus = $this->paymentStatus(
            $paidAmount,
            $grandTotal
        );

        $updated = $this->saleRepository->updateTotals(
            $companyId,
            $saleId,
            $totals['subtotal'],
            $totals['discount_amount'],
            $totals['tax_amount'],
            $sale->shippingAmount(),
            $sale->otherAmount(),
            $grandTotal,
            $paidAmount,
            $balanceDue,
            $paymentStatus,
            $userId
        );

        if (!$updated instanceof Sale) {
            throw new ValidationException(
                'Sale totals could not be recalculated.'
            );
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     product_id: int,
     *     unit_id: int,
     *     tax_id: int|null,
     *     quantity: float,
     *     unit_price: float,
     *     discount_type: string|null,
     *     discount_value: float,
     *     discount_amount: float,
     *     taxable_amount: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     line_subtotal: float,
     *     line_total: float,
     *     notes: string|null
     * }
     */
    private function validateItem(
        int $companyId,
        array $input
    ): array {
        $productId = $this->requiredId(
            $input['product_id'] ?? null,
            'Product'
        );

        $product = $this->requireProduct(
            $companyId,
            $productId
        );

        $productData = $product->toArray();

        $defaultUnitId = $productData['sale_unit_id']
            ?? $productData['base_unit_id']
            ?? null;

        $unitId = $this->requiredId(
            $input['unit_id'] ?? $defaultUnitId,
            'Unit'
        );

        $this->requireUnit(
            $companyId,
            $unitId
        );

        /*
         * Sales may use only the base unit or configured sale unit.
         * No unit-conversion table exists yet, so quantities are posted
         * directly in the selected allowed unit.
         */
        $allowedUnitIds = array_values(
            array_unique(
                array_filter(
                    [
                        $productData['base_unit_id'] ?? null,
                        $productData['sale_unit_id'] ?? null,
                    ],
                    static fn (mixed $value): bool =>
                        is_numeric($value)
                        && (int) $value > 0
                )
            )
        );

        if (
            $allowedUnitIds !== []
            && !in_array(
                $unitId,
                array_map('intval', $allowedUnitIds),
                true
            )
        ) {
            throw new ValidationException(
                'Selected unit is not a valid sale unit for this product.'
            );
        }

        $taxId = $this->nullableId(
            $input['tax_id']
                ?? $productData['tax_id']
                ?? null
        );

        $tax = null;

        if ($taxId !== null) {
            $tax = $this->requireTax(
                $companyId,
                $taxId
            );

            $taxData = $tax->toArray();

            if (
                empty($taxData['applies_to_sales'])
            ) {
                throw new ValidationException(
                    'Selected tax does not apply to sales.'
                );
            }
        }

        $quantity = $this->positiveQuantity(
            $input['quantity'] ?? null,
            'Quantity'
        );

        $unitPrice = $this->nonNegativeAmount(
            $input['unit_price']
                ?? $productData['sale_price']
                ?? 0,
            'Unit price'
        );

        $lineSubtotal = $this->money(
            $quantity * $unitPrice
        );

        $discountType = strtolower(
            trim(
                (string) (
                    $input['discount_type']
                    ?? ''
                )
            )
        );

        if ($discountType === '') {
            $discountType = null;
        }

        if (
            $discountType !== null
            && !in_array(
                $discountType,
                ['percentage', 'fixed'],
                true
            )
        ) {
            throw new ValidationException(
                'Discount type must be percentage, fixed or empty.'
            );
        }

        $discountValue = $this->nonNegativeAmount(
            $input['discount_value'] ?? 0,
            'Discount value'
        );

        if (
            $discountType === 'percentage'
            && $discountValue > 100
        ) {
            throw new ValidationException(
                'Percentage discount must not exceed 100.'
            );
        }

        $discountAmount = 0.0;

        if ($discountType === 'percentage') {
            $discountAmount = $this->money(
                $lineSubtotal
                * ($discountValue / 100)
            );
        } elseif ($discountType === 'fixed') {
            $discountAmount = $this->money(
                min($discountValue, $lineSubtotal)
            );
        }

        $afterDiscount = $this->money(
            max(
                0,
                $lineSubtotal - $discountAmount
            )
        );

        $taxableAmount = $afterDiscount;
        $taxRate = 0.0;
        $taxAmount = 0.0;
        $lineTotal = $afterDiscount;

        if ($tax instanceof Tax) {
            $taxData = $tax->toArray();

            $taxType = strtolower(
                (string) (
                    $taxData['tax_type']
                    ?? 'percentage'
                )
            );

            $taxRate = $this->nonNegativeAmount(
                $taxData['rate'] ?? 0,
                'Tax rate'
            );

            $priceIncludesTax = !empty(
                $taxData['price_includes_tax']
            );

            if ($taxType === 'percentage') {
                if (
                    $priceIncludesTax
                    && $taxRate > 0
                ) {
                    $taxableAmount = $this->money(
                        $afterDiscount
                        / (1 + ($taxRate / 100))
                    );

                    $taxAmount = $this->money(
                        $afterDiscount
                        - $taxableAmount
                    );

                    $lineTotal = $afterDiscount;
                } else {
                    $taxableAmount = $afterDiscount;

                    $taxAmount = $this->money(
                        $taxableAmount
                        * ($taxRate / 100)
                    );

                    $lineTotal = $this->money(
                        $taxableAmount + $taxAmount
                    );
                }
            } else {
                /*
                 * Non-percentage taxes are treated as fixed line tax.
                 */
                $taxableAmount = $afterDiscount;
                $taxAmount = $this->money($taxRate);
                $lineTotal = $this->money(
                    $taxableAmount + $taxAmount
                );
            }
        }

        return [
            'product_id' => $productId,
            'unit_id' => $unitId,
            'tax_id' => $taxId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
            'notes' => $this->nullableString(
                $input['notes'] ?? null,
                500
            ),
        ];
    }

    private function requireCustomer(
        int $companyId,
        int $customerId
    ): Customer {
        $customer = $this->customerRepository->find(
            $companyId,
            $customerId
        );

        if (!$customer instanceof Customer) {
            throw new ValidationException(
                'Selected customer was not found.'
            );
        }

        $data = $customer->toArray();

        if (($data['status'] ?? '') !== 'active') {
            throw new ValidationException(
                'Selected customer is inactive.'
            );
        }

        return $customer;
    }

    private function requireWarehouse(
        int $companyId,
        int $warehouseId
    ): Warehouse {
        $warehouse = $this->warehouseRepository->find(
            $companyId,
            $warehouseId
        );

        if (!$warehouse instanceof Warehouse) {
            throw new ValidationException(
                'Selected warehouse was not found.'
            );
        }

        $data = $warehouse->toArray();

        if (($data['status'] ?? '') !== 'active') {
            throw new ValidationException(
                'Selected warehouse is inactive.'
            );
        }

        return $warehouse;
    }

    private function requireProduct(
        int $companyId,
        int $productId
    ): Product {
        $product = $this->productRepository->find(
            $companyId,
            $productId
        );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Selected product was not found.'
            );
        }

        $data = $product->toArray();

        if (($data['status'] ?? '') !== 'active') {
            throw new ValidationException(
                'Selected product is inactive.'
            );
        }

        return $product;
    }

    private function requireUnit(
        int $companyId,
        int $unitId
    ): Unit {
        $unit = $this->unitRepository->find(
            $companyId,
            $unitId
        );

        if (!$unit instanceof Unit) {
            throw new ValidationException(
                'Selected unit was not found.'
            );
        }

        $data = $unit->toArray();

        if (($data['status'] ?? '') !== 'active') {
            throw new ValidationException(
                'Selected unit is inactive.'
            );
        }

        return $unit;
    }

    private function requireTax(
        int $companyId,
        int $taxId
    ): Tax {
        $tax = $this->taxRepository->find(
            $companyId,
            $taxId
        );

        if (!$tax instanceof Tax) {
            throw new ValidationException(
                'Selected tax was not found.'
            );
        }

        $data = $tax->toArray();

        if (($data['status'] ?? '') !== 'active') {
            throw new ValidationException(
                'Selected tax is inactive.'
            );
        }

        return $tax;
    }

    private function generateSaleNumber(
        int $companyId
    ): string {
        $prefix = 'SAL-'
            . gmdate('Ymd')
            . '-';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix = strtoupper(
                str_pad(
                    base_convert(
                        (string) random_int(
                            0,
                            36 ** 5 - 1
                        ),
                        10,
                        36
                    ),
                    5,
                    '0',
                    STR_PAD_LEFT
                )
            );

            $saleNumber = $prefix . $suffix;

            if (
                !$this->saleRepository
                    ->saleNumberExists(
                        $companyId,
                        $saleNumber
                    )
            ) {
                return $saleNumber;
            }
        }

        throw new ValidationException(
            'Sale number could not be generated.'
        );
    }

    private function validateSaleNumber(
        int $companyId,
        string $saleNumber,
        ?int $exceptSaleId = null
    ): void {
        if (
            $saleNumber === ''
            || mb_strlen($saleNumber) > 100
        ) {
            throw new ValidationException(
                'Sale number is required and must not exceed 100 characters.'
            );
        }

        if (
            $this->saleRepository
                ->saleNumberExists(
                    $companyId,
                    $saleNumber,
                    $exceptSaleId
                )
        ) {
            throw new ValidationException(
                'Sale number is already in use.'
            );
        }
    }

    private function paymentStatus(
        float $paidAmount,
        float $grandTotal
    ): string {
        $paidAmount = $this->money($paidAmount);
        $grandTotal = $this->money($grandTotal);

        if (
            $grandTotal <= 0.00005
            || $paidAmount >= $grandTotal - 0.00005
        ) {
            return 'paid';
        }

        if ($paidAmount > 0.00005) {
            return 'partial';
        }

        return 'unpaid';
    }

    private function requiredId(
        mixed $value,
        string $field
    ): int {
        $id = $this->nullableId($value);

        if ($id === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $id;
    }

    private function nullableId(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
            && (int) $value > 0
        ) {
            return (int) $value;
        }

        if (
            is_float($value)
            && floor($value) === $value
            && $value > 0
        ) {
            return (int) $value;
        }

        throw new ValidationException(
            'Expected a positive ID.'
        );
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

    private function positiveQuantity(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $number = $this->quantity(
            (float) $value
        );

        if ($number <= 0) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }

        return $number;
    }

    private function nonNegativeAmount(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $number = $this->money(
            (float) $value
        );

        if (
            !is_finite($number)
            || $number < 0
        ) {
            throw new ValidationException(
                "{$field} must be zero or greater."
            );
        }

        return $number;
    }

    private function nullableString(
        mixed $value,
        int $maximumLength
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new ValidationException(
                'Expected a text value.'
            );
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $maximumLength) {
            throw new ValidationException(
                "Text must not exceed {$maximumLength} characters."
            );
        }

        return $text;
    }

    private function nullableText(
        mixed $value,
        int $maximumLength
    ): ?string {
        return $this->nullableString(
            $value,
            $maximumLength
        );
    }

    private function dateValue(
        mixed $value,
        string $field
    ): string {
        $date = $this->parseDate(
            $value,
            $field
        );

        if ($date === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $date->format('Y-m-d');
    }

    private function nullableDateValue(
        mixed $value,
        string $field
    ): ?string {
        $date = $this->parseDate(
            $value,
            $field
        );

        return $date?->format('Y-m-d');
    }

    private function parseDate(
        mixed $value,
        string $field
    ): ?DateTimeImmutable {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw new ValidationException(
                "{$field} must use Y-m-d format."
            );
        }

        $value = trim($value);

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d') !== $value
        ) {
            throw new ValidationException(
                "{$field} must use Y-m-d format."
            );
        }

        return $date;
    }

    private function money(
        float|int|string $value
    ): float {
        return round(
            (float) $value,
            4
        );
    }

    private function quantity(
        float|int|string $value
    ): float {
        return round(
            (float) $value,
            4
        );
    }

    private function beginTransaction(
        PDO $database
    ): void {
        if ($database->inTransaction()) {
            throw new ValidationException(
                'A database transaction is already active.'
            );
        }

        if (!$database->beginTransaction()) {
            throw new ValidationException(
                'Sale transaction could not be started.'
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