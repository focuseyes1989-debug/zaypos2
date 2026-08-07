<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Repositories\ProductRepository;
use App\Repositories\PurchaseItemRepository;
use App\Repositories\PurchaseRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Repositories\WarehouseRepository;
use App\Repositories\WarehouseStockRepository;
use PDO;
use Throwable;

final class PurchaseService
{
    public function __construct(
        private readonly PurchaseRepository $purchaseRepository =
            new PurchaseRepository(),

        private readonly PurchaseItemRepository $itemRepository =
            new PurchaseItemRepository(),

        private readonly SupplierRepository $supplierRepository =
            new SupplierRepository(),

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
     *     items: list<Purchase>,
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
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        $status = strtolower(
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            )
        );

        $paymentStatus = strtolower(
            trim(
                (string) (
                    $filters['payment_status']
                    ?? ''
                )
            )
        );

        $supplierId = $this->nullableId(
            $filters['supplier_id'] ?? null
        );

        $warehouseId = $this->nullableId(
            $filters['warehouse_id'] ?? null
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
                [
                    'draft',
                    'received',
                    'cancelled',
                ],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid purchase status filter.'
            );
        }

        if (
            $paymentStatus !== ''
            && !in_array(
                $paymentStatus,
                [
                    'unpaid',
                    'partial',
                    'paid',
                ],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment status filter.'
            );
        }

        return $this->purchaseRepository->paginate(
            $companyId,
            $search,
            $status,
            $paymentStatus,
            $supplierId,
            $warehouseId,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $purchaseId,
        bool $includeDeleted = false
    ): Purchase {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $purchaseId,
            'Purchase ID'
        );

        $purchase =
            $this->purchaseRepository->find(
                $companyId,
                $purchaseId,
                $includeDeleted
            );

        if (!$purchase instanceof Purchase) {
            throw new ValidationException(
                'Purchase not found.'
            );
        }

        return $purchase;
    }

    /**
     * @return list<PurchaseItem>
     */
    public function items(
        int $companyId,
        int $purchaseId
    ): array {
        $this->find(
            $companyId,
            $purchaseId,
            true
        );

        return $this->itemRepository->byPurchase(
            $purchaseId
        );
    }

    /**
     * @return array{
     *     purchase: Purchase,
     *     items: list<PurchaseItem>
     * }
     */
    public function detail(
        int $companyId,
        int $purchaseId,
        bool $includeDeleted = false
    ): array {
        $purchase = $this->find(
            $companyId,
            $purchaseId,
            $includeDeleted
        );

        return [
            'purchase' => $purchase,
            'items' =>
                $this->itemRepository->byPurchase(
                    $purchaseId
                ),
        ];
    }

    /**
     * Create an empty draft purchase.
     *
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): Purchase {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $supplierId = $this->requiredId(
            $input['supplier_id'] ?? null,
            'Supplier'
        );

        $warehouseId = $this->requiredId(
            $input['warehouse_id'] ?? null,
            'Warehouse'
        );

        $this->requireSupplier(
            $companyId,
            $supplierId
        );

        $this->requireWarehouse(
            $companyId,
            $warehouseId
        );

        $purchaseNumber = trim(
            (string) (
                $input['purchase_number']
                ?? ''
            )
        );

        if ($purchaseNumber === '') {
            $purchaseNumber =
                $this->generatePurchaseNumber(
                    $companyId
                );
        }

        $this->validatePurchaseNumber(
            $companyId,
            $purchaseNumber
        );

        $supplierInvoiceNumber =
            $this->nullableString(
                $input['supplier_invoice_number']
                    ?? null,
                100
            );

        $purchaseDate = $this->dateValue(
            $input['purchase_date']
                ?? gmdate('Y-m-d'),
            'Purchase date'
        );

        $dueDate = $this->nullableDateValue(
            $input['due_date'] ?? null,
            'Due date'
        );

        if (
            $dueDate !== null
            && $dueDate < $purchaseDate
        ) {
            throw new ValidationException(
                'Due date must not be earlier than purchase date.'
            );
        }

        $shippingAmount =
            $this->nonNegativeAmount(
                $input['shipping_amount'] ?? 0,
                'Shipping amount'
            );

        $otherAmount =
            $this->nonNegativeAmount(
                $input['other_amount'] ?? 0,
                'Other amount'
            );

        $paidAmount =
            $this->nonNegativeAmount(
                $input['paid_amount'] ?? 0,
                'Paid amount'
            );

        /*
         * New draft starts with no items.
         * Header-level extras may exist already.
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

        $paymentStatus =
            $this->paymentStatus(
                $paidAmount,
                $grandTotal
            );

        $balanceDue = $this->money(
            max(
                0,
                $grandTotal - $paidAmount
            )
        );

        $notes = $this->nullableText(
            $input['notes'] ?? null,
            5000
        );

        $purchase =
            $this->purchaseRepository->create([
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'purchase_number' =>
                    $purchaseNumber,
                'supplier_invoice_number' =>
                    $supplierInvoiceNumber,
                'purchase_date' =>
                    $purchaseDate,
                'due_date' =>
                    $dueDate,
                'status' => 'draft',
                'payment_status' =>
                    $paymentStatus,
                'subtotal' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'shipping_amount' =>
                    $shippingAmount,
                'other_amount' =>
                    $otherAmount,
                'grand_total' =>
                    $grandTotal,
                'paid_amount' =>
                    $paidAmount,
                'balance_due' =>
                    $balanceDue,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

        $this->audit->record(
            'purchases.created',
            'purchase',
            $purchase->id(),
            [
                'purchase' =>
                    $purchase->toArray(),
            ]
        );

        return $purchase;
    }

    /**
     * Update draft purchase header.
     *
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $purchaseId,
        int $userId,
        array $input
    ): Purchase {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $existing = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$existing->isDraft()) {
            throw new ValidationException(
                'Only draft purchases can be updated.'
            );
        }

        $supplierId = $this->requiredId(
            $input['supplier_id']
                ?? $existing->supplierId(),
            'Supplier'
        );

        $warehouseId = $this->requiredId(
            $input['warehouse_id']
                ?? $existing->warehouseId(),
            'Warehouse'
        );

        $this->requireSupplier(
            $companyId,
            $supplierId
        );

        $this->requireWarehouse(
            $companyId,
            $warehouseId
        );

        $supplierInvoiceNumber =
            $this->nullableString(
                $input['supplier_invoice_number']
                    ?? $existing
                        ->supplierInvoiceNumber(),
                100
            );

        $purchaseDate = $this->dateValue(
            $input['purchase_date']
                ?? $existing
                    ->purchaseDate()
                    ->format('Y-m-d'),
            'Purchase date'
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
            && $dueDate < $purchaseDate
        ) {
            throw new ValidationException(
                'Due date must not be earlier than purchase date.'
            );
        }

        $shippingAmount =
            $this->nonNegativeAmount(
                $input['shipping_amount']
                    ?? $existing
                        ->shippingAmount(),
                'Shipping amount'
            );

        $otherAmount =
            $this->nonNegativeAmount(
                $input['other_amount']
                    ?? $existing
                        ->otherAmount(),
                'Other amount'
            );

        $paidAmount =
            $this->nonNegativeAmount(
                $input['paid_amount']
                    ?? $existing
                        ->paidAmount(),
                'Paid amount'
            );

        $notes = $this->nullableText(
            $input['notes']
                ?? $existing->notes(),
            5000
        );

        $totals =
            $this->itemRepository
                ->totalsByPurchase(
                    $purchaseId
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
            max(
                0,
                $grandTotal - $paidAmount
            )
        );

        $paymentStatus =
            $this->paymentStatus(
                $paidAmount,
                $grandTotal
            );

        $updated =
            $this->purchaseRepository
                ->updateDraft(
                    $companyId,
                    $purchaseId,
                    [
                        'supplier_id' =>
                            $supplierId,
                        'warehouse_id' =>
                            $warehouseId,
                        'supplier_invoice_number' =>
                            $supplierInvoiceNumber,
                        'purchase_date' =>
                            $purchaseDate,
                        'due_date' =>
                            $dueDate,
                        'subtotal' =>
                            $totals['subtotal'],
                        'discount_amount' =>
                            $totals[
                                'discount_amount'
                            ],
                        'tax_amount' =>
                            $totals['tax_amount'],
                        'shipping_amount' =>
                            $shippingAmount,
                        'other_amount' =>
                            $otherAmount,
                        'grand_total' =>
                            $grandTotal,
                        'paid_amount' =>
                            $paidAmount,
                        'balance_due' =>
                            $balanceDue,
                        'payment_status' =>
                            $paymentStatus,
                        'notes' => $notes,
                        'updated_by' =>
                            $userId,
                    ]
                );

        if (!$updated instanceof Purchase) {
            throw new ValidationException(
                'Purchase could not be updated.'
            );
        }

        $this->audit->record(
            'purchases.updated',
            'purchase',
            $purchaseId,
            [
                'before' =>
                    $existing->toArray(),
                'after' =>
                    $updated->toArray(),
            ]
        );

        return $updated;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addItem(
        int $companyId,
        int $purchaseId,
        int $userId,
        array $input
    ): PurchaseItem {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $purchase = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$purchase->isDraft()) {
            throw new ValidationException(
                'Items can only be added to draft purchases.'
            );
        }

        $data = $this->validateItem(
            $companyId,
            $input
        );

        $item =
            $this->itemRepository->create([
                'purchase_id' => $purchaseId,
                'product_id' =>
                    $data['product_id'],
                'unit_id' =>
                    $data['unit_id'],
                'tax_id' =>
                    $data['tax_id'],
                'quantity' =>
                    $data['quantity'],
                'received_quantity' => 0,
                'unit_cost' =>
                    $data['unit_cost'],
                'discount_type' =>
                    $data['discount_type'],
                'discount_value' =>
                    $data['discount_value'],
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
                'notes' =>
                    $data['notes'],
            ]);

        $this->recalculateTotals(
            $companyId,
            $purchaseId,
            $userId
        );

        $this->audit->record(
            'purchases.item_created',
            'purchase',
            $purchaseId,
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
        int $purchaseId,
        int $itemId,
        int $userId,
        array $input
    ): PurchaseItem {
        $purchase = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$purchase->isDraft()) {
            throw new ValidationException(
                'Items can only be updated on draft purchases.'
            );
        }

        $existing =
            $this->itemRepository->find(
                $itemId
            );

        if (
            !$existing instanceof PurchaseItem
            || $existing->purchaseId()
                !== $purchaseId
        ) {
            throw new ValidationException(
                'Purchase item not found.'
            );
        }

        $data = $this->validateItem(
            $companyId,
            $input
        );

        $updated =
            $this->itemRepository->update(
                $itemId,
                [
                    'product_id' =>
                        $data['product_id'],
                    'unit_id' =>
                        $data['unit_id'],
                    'tax_id' =>
                        $data['tax_id'],
                    'quantity' =>
                        $data['quantity'],
                    'unit_cost' =>
                        $data['unit_cost'],
                    'discount_type' =>
                        $data['discount_type'],
                    'discount_value' =>
                        $data['discount_value'],
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
                    'notes' =>
                        $data['notes'],
                ]
            );

        if (!$updated instanceof PurchaseItem) {
            throw new ValidationException(
                'Purchase item could not be updated.'
            );
        }

        $this->recalculateTotals(
            $companyId,
            $purchaseId,
            $userId
        );

        $this->audit->record(
            'purchases.item_updated',
            'purchase',
            $purchaseId,
            [
                'before' =>
                    $existing->toArray(),
                'after' =>
                    $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function deleteItem(
        int $companyId,
        int $purchaseId,
        int $itemId,
        int $userId
    ): void {
        $purchase = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$purchase->isDraft()) {
            throw new ValidationException(
                'Items can only be removed from draft purchases.'
            );
        }

        $item =
            $this->itemRepository->find(
                $itemId
            );

        if (
            !$item instanceof PurchaseItem
            || $item->purchaseId()
                !== $purchaseId
        ) {
            throw new ValidationException(
                'Purchase item not found.'
            );
        }

        if (
            !$this->itemRepository->delete(
                $itemId
            )
        ) {
            throw new ValidationException(
                'Purchase item could not be deleted.'
            );
        }

        $this->recalculateTotals(
            $companyId,
            $purchaseId,
            $userId
        );

        $this->audit->record(
            'purchases.item_deleted',
            'purchase',
            $purchaseId,
            [
                'item' => $item->toArray(),
            ]
        );
    }

    /**
     * Receive all outstanding quantities.
     *
     * This operation is atomic:
     *
     * purchase
     * purchase_items
     * warehouse_stocks
     * stock_movements
     *
     * are all committed together.
     */
    public function receive(
        int $companyId,
        int $purchaseId,
        int $userId
    ): Purchase {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $purchaseId,
            'Purchase ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $database = Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $purchase =
                $this->purchaseRepository
                    ->findForUpdate(
                        $companyId,
                        $purchaseId
                    );

            if (!$purchase instanceof Purchase) {
                throw new ValidationException(
                    'Purchase not found.'
                );
            }

            if (!$purchase->isDraft()) {
                throw new ValidationException(
                    'Only draft purchases can be received.'
                );
            }

            $this->requireWarehouse(
                $companyId,
                $purchase->warehouseId()
            );

            $items =
                $this->itemRepository
                    ->byPurchase(
                        $purchaseId
                    );

            if ($items === []) {
                throw new ValidationException(
                    'Purchase must contain at least one item before receiving.'
                );
            }

            $movementAt = gmdate(
                'Y-m-d H:i:s'
            );

            foreach ($items as $item) {
                $quantityToReceive =
                    $this->quantity(
                        $item->remainingQuantity()
                    );

                if ($quantityToReceive <= 0) {
                    continue;
                }

                $product =
                    $this->requireProduct(
                        $companyId,
                        $item->productId()
                    );

                $productData =
                    $product->toArray();

                /*
                 * Service / non-stock products remain
                 * on the supplier invoice but do not
                 * change inventory.
                 */
                $tracksStock =
                    ($productData['product_type']
                        ?? '') === 'stock'
                    && !empty(
                        $productData['track_stock']
                    );

                if ($tracksStock) {
                    $stock =
                        $this->stockRepository
                            ->findForUpdate(
                                $companyId,
                                $purchase
                                    ->warehouseId(),
                                $product->id()
                            );

                    if (
                        !$stock
                        instanceof WarehouseStock
                    ) {
                        $this->stockRepository->create(
                            $companyId,
                            $purchase
                                ->warehouseId(),
                            (int) $product->id(),
                            0,
                            0,
                            0
                        );

                        $stock =
                            $this->stockRepository
                                ->findForUpdate(
                                    $companyId,
                                    $purchase
                                        ->warehouseId(),
                                    (int) $product
                                        ->id()
                                );
                    }

                    if (
                        !$stock
                        instanceof WarehouseStock
                    ) {
                        throw new ValidationException(
                            'Warehouse stock record could not be initialized.'
                        );
                    }

                    $quantityBefore =
                        $stock->quantity();

                    $quantityAfter =
                        $this->quantity(
                            $quantityBefore
                            + $quantityToReceive
                        );

                    $averageCost =
                        $this->weightedAverageCost(
                            $quantityBefore,
                            $stock->averageCost(),
                            $quantityToReceive,
                            $item->unitCost()
                        );

                    $this->stockRepository
                        ->updateBalance(
                            $companyId,
                            $purchase
                                ->warehouseId(),
                            (int) $product->id(),
                            $quantityAfter,
                            $stock
                                ->reservedQuantity(),
                            $averageCost,
                            $movementAt
                        );

                    $this->movementRepository
                        ->create([
                            'company_id' =>
                                $companyId,

                            'warehouse_id' =>
                                $purchase
                                    ->warehouseId(),

                            'product_id' =>
                                (int) $product->id(),

                            'movement_type' =>
                                'purchase',

                            'quantity_in' =>
                                $quantityToReceive,

                            'quantity_out' => 0,

                            'quantity_before' =>
                                $quantityBefore,

                            'quantity_after' =>
                                $quantityAfter,

                            'unit_cost' =>
                                $item->unitCost(),

                            'total_cost' =>
                                $this->money(
                                    $quantityToReceive
                                    * $item
                                        ->unitCost()
                                ),

                            'reference_type' =>
                                'purchase',

                            'reference_id' =>
                                $purchaseId,

                            'reference_number' =>
                                $purchase
                                    ->purchaseNumber(),

                            'related_warehouse_id' =>
                                null,

                            'notes' =>
                                'Purchase receipt',

                            'movement_at' =>
                                $movementAt,

                            'created_by' =>
                                $userId,
                        ]);
                }

                $newReceivedQuantity =
                    $this->quantity(
                        $item
                            ->receivedQuantity()
                        + $quantityToReceive
                    );

                $updatedItem =
                    $this->itemRepository
                        ->updateReceivedQuantity(
                            (int) $item->id(),
                            $newReceivedQuantity
                        );

                if (
                    !$updatedItem
                    instanceof PurchaseItem
                ) {
                    throw new ValidationException(
                        'Purchase received quantity could not be updated.'
                    );
                }
            }

            $received =
                $this->purchaseRepository
                    ->markReceived(
                        $companyId,
                        $purchaseId,
                        $userId
                    );

            if (
                !$received
                instanceof Purchase
                || !$received->isReceived()
            ) {
                throw new ValidationException(
                    'Purchase could not be marked as received.'
                );
            }

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'purchases.received',
            'purchase',
            $purchaseId,
            [
                'purchase' =>
                    $received->toArray(),
            ]
        );

        return $received;
    }

    public function cancel(
        int $companyId,
        int $purchaseId,
        int $userId,
        ?string $reason = null
    ): Purchase {
        $purchase = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$purchase->isDraft()) {
            throw new ValidationException(
                'Only draft purchases can be cancelled.'
            );
        }

        $reason = $this->nullableString(
            $reason,
            500
        );

        $cancelled =
            $this->purchaseRepository
                ->markCancelled(
                    $companyId,
                    $purchaseId,
                    $userId,
                    $reason
                );

        if (
            !$cancelled instanceof Purchase
            || !$cancelled->isCancelled()
        ) {
            throw new ValidationException(
                'Purchase could not be cancelled.'
            );
        }

        $this->audit->record(
            'purchases.cancelled',
            'purchase',
            $purchaseId,
            [
                'purchase' =>
                    $cancelled->toArray(),
            ]
        );

        return $cancelled;
    }

    public function delete(
        int $companyId,
        int $purchaseId,
        int $userId
    ): void {
        $purchase = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$purchase->isDraft()) {
            throw new ValidationException(
                'Only draft purchases can be deleted.'
            );
        }

        if (
            !$this->purchaseRepository
                ->deleteDraft(
                    $companyId,
                    $purchaseId,
                    $userId
                )
        ) {
            throw new ValidationException(
                'Purchase could not be deleted.'
            );
        }

        $this->audit->record(
            'purchases.deleted',
            'purchase',
            $purchaseId,
            [
                'purchase' =>
                    $purchase->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $purchaseId,
        int $userId
    ): Purchase {
        $purchase = $this->find(
            $companyId,
            $purchaseId,
            true
        );

        if (!$purchase->isDeleted()) {
            throw new ValidationException(
                'Purchase is not deleted.'
            );
        }

        if (
            $this->purchaseRepository
                ->purchaseNumberExists(
                    $companyId,
                    $purchase
                        ->purchaseNumber(),
                    $purchaseId
                )
        ) {
            throw new ValidationException(
                'Another purchase is already using this purchase number.'
            );
        }

        if (
            !$this->purchaseRepository
                ->restoreDeleted(
                    $companyId,
                    $purchaseId,
                    $userId
                )
        ) {
            throw new ValidationException(
                'Purchase could not be restored.'
            );
        }

        $restored = $this->find(
            $companyId,
            $purchaseId
        );

        $this->audit->record(
            'purchases.restored',
            'purchase',
            $purchaseId,
            [
                'purchase' =>
                    $restored->toArray(),
            ]
        );

        return $restored;
    }

    private function recalculateTotals(
        int $companyId,
        int $purchaseId,
        int $userId
    ): Purchase {
        $purchase = $this->find(
            $companyId,
            $purchaseId
        );

        if (!$purchase->isDraft()) {
            throw new ValidationException(
                'Purchase totals can only be recalculated while draft.'
            );
        }

        $totals =
            $this->itemRepository
                ->totalsByPurchase(
                    $purchaseId
                );

        $grandTotal = $this->money(
            $totals['grand_total']
            + $purchase->shippingAmount()
            + $purchase->otherAmount()
        );

        $paidAmount =
            $purchase->paidAmount();

        if (
            $paidAmount
            > $grandTotal + 0.00005
        ) {
            /*
             * Item deletion can make an existing payment
             * larger than the new purchase total.
             */
            $paidAmount = $grandTotal;
        }

        $balanceDue = $this->money(
            max(
                0,
                $grandTotal - $paidAmount
            )
        );

        $paymentStatus =
            $this->paymentStatus(
                $paidAmount,
                $grandTotal
            );

        $updated =
            $this->purchaseRepository
                ->updateTotals(
                    $companyId,
                    $purchaseId,
                    $totals['subtotal'],
                    $totals['discount_amount'],
                    $totals['tax_amount'],
                    $purchase->shippingAmount(),
                    $purchase->otherAmount(),
                    $grandTotal,
                    $paidAmount,
                    $balanceDue,
                    $paymentStatus,
                    $userId
                );

        if (!$updated instanceof Purchase) {
            throw new ValidationException(
                'Purchase totals could not be recalculated.'
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
     *     unit_cost: float,
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

        $unitId = $this->requiredId(
            $input['unit_id'] ?? null,
            'Unit'
        );

        $this->requireUnit(
            $companyId,
            $unitId
        );

        $productData =
            $product->toArray();

        $allowedUnitIds = array_values(
            array_unique(
                array_filter(
                    [
                        $productData[
                            'base_unit_id'
                        ] ?? null,

                        $productData[
                            'purchase_unit_id'
                        ] ?? null,
                    ],
                    static fn (
                        mixed $value
                    ): bool => (
                        is_numeric($value)
                        && (int) $value > 0
                    )
                )
            )
        );

        if (
            $allowedUnitIds !== []
            && !in_array(
                $unitId,
                array_map(
                    'intval',
                    $allowedUnitIds
                ),
                true
            )
        ) {
            throw new ValidationException(
                'Selected unit is not a valid purchase unit for this product.'
            );
        }

        $taxId = $this->nullableId(
            $input['tax_id'] ?? null
        );

        $tax = null;

        if ($taxId !== null) {
            $tax = $this->requireTax(
                $companyId,
                $taxId
            );

            $taxData = $tax->toArray();

            if (
                empty(
                    $taxData[
                        'applies_to_purchases'
                    ]
                )
            ) {
                throw new ValidationException(
                    'Selected tax does not apply to purchases.'
                );
            }
        }

        $quantity = $this->positiveQuantity(
            $input['quantity'] ?? null,
            'Quantity'
        );

        $unitCost =
            $this->nonNegativeAmount(
                $input['unit_cost'] ?? 0,
                'Unit cost'
            );

        $lineSubtotal = $this->money(
            $quantity * $unitCost
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
                [
                    'fixed',
                    'percentage',
                ],
                true
            )
        ) {
            throw new ValidationException(
                'Discount type must be fixed or percentage.'
            );
        }

        $discountValue =
            $this->nonNegativeAmount(
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

        if ($discountType === 'percentage') {
            $discountAmount =
                $this->money(
                    $lineSubtotal
                    * $discountValue
                    / 100
                );
        } elseif ($discountType === 'fixed') {
            $discountAmount =
                $this->money(
                    min(
                        $lineSubtotal,
                        $discountValue
                    )
                );
        } else {
            $discountValue = 0.0;
            $discountAmount = 0.0;
        }

        $taxableAmount = $this->money(
            max(
                0,
                $lineSubtotal
                - $discountAmount
            )
        );

        $taxRate = 0.0;
        $taxAmount = 0.0;

        if ($tax instanceof Tax) {
            $taxData = $tax->toArray();

            $taxRate = $this->money(
                (float) (
                    $taxData['rate']
                    ?? 0
                )
            );

            $taxType = (string) (
                $taxData['tax_type']
                ?? 'percentage'
            );

            if ($taxType === 'percentage') {
                $taxAmount = $this->money(
                    $taxableAmount
                    * $taxRate
                    / 100
                );
            } else {
                /*
                 * Fixed tax is treated as a fixed
                 * line-level amount.
                 */
                $taxAmount = $this->money(
                    $taxRate
                );
            }
        }

        $lineTotal = $this->money(
            $taxableAmount
            + $taxAmount
        );

        return [
            'product_id' => $productId,
            'unit_id' => $unitId,
            'tax_id' => $taxId,

            'quantity' => $quantity,
            'unit_cost' => $unitCost,

            'discount_type' =>
                $discountType,

            'discount_value' =>
                $discountValue,

            'discount_amount' =>
                $discountAmount,

            'taxable_amount' =>
                $taxableAmount,

            'tax_rate' =>
                $taxRate,

            'tax_amount' =>
                $taxAmount,

            'line_subtotal' =>
                $lineSubtotal,

            'line_total' =>
                $lineTotal,

            'notes' =>
                $this->nullableString(
                    $input['notes'] ?? null,
                    500
                ),
        ];
    }

    private function requireSupplier(
        int $companyId,
        int $supplierId
    ): Supplier {
        $supplier =
            $this->supplierRepository->find(
                $companyId,
                $supplierId
            );

        if (!$supplier instanceof Supplier) {
            throw new ValidationException(
                'Selected supplier was not found.'
            );
        }

        $data = $supplier->toArray();

        if (
            ($data['status'] ?? '')
            !== 'active'
        ) {
            throw new ValidationException(
                'Selected supplier is inactive.'
            );
        }

        return $supplier;
    }

    private function requireWarehouse(
        int $companyId,
        int $warehouseId
    ): Warehouse {
        $warehouse =
            $this->warehouseRepository->find(
                $companyId,
                $warehouseId
            );

        if (!$warehouse instanceof Warehouse) {
            throw new ValidationException(
                'Selected warehouse was not found.'
            );
        }

        $data = $warehouse->toArray();

        if (
            ($data['status'] ?? '')
            !== 'active'
        ) {
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
        $product =
            $this->productRepository->find(
                $companyId,
                $productId
            );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Selected product was not found.'
            );
        }

        $data = $product->toArray();

        if (
            ($data['status'] ?? '')
            !== 'active'
        ) {
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
        $unit =
            $this->unitRepository->find(
                $companyId,
                $unitId
            );

        if (!$unit instanceof Unit) {
            throw new ValidationException(
                'Selected unit was not found.'
            );
        }

        $data = $unit->toArray();

        if (
            ($data['status'] ?? '')
            !== 'active'
        ) {
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
        $tax =
            $this->taxRepository->find(
                $companyId,
                $taxId
            );

        if (!$tax instanceof Tax) {
            throw new ValidationException(
                'Selected tax was not found.'
            );
        }

        $data = $tax->toArray();

        if (
            ($data['status'] ?? '')
            !== 'active'
        ) {
            throw new ValidationException(
                'Selected tax is inactive.'
            );
        }

        return $tax;
    }

    private function generatePurchaseNumber(
        int $companyId
    ): string {
        $prefix =
            'PUR-'
            . gmdate('Ymd')
            . '-';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            try {
                $suffix = strtoupper(
                    bin2hex(
                        random_bytes(3)
                    )
                );
            } catch (Throwable) {
                $suffix = strtoupper(
                    substr(
                        hash(
                            'sha256',
                            uniqid(
                                '',
                                true
                            )
                        ),
                        0,
                        6
                    )
                );
            }

            $number =
                $prefix . $suffix;

            if (
                !$this->purchaseRepository
                    ->purchaseNumberExists(
                        $companyId,
                        $number
                    )
            ) {
                return $number;
            }
        }

        throw new ValidationException(
            'Purchase number could not be generated.'
        );
    }

    private function validatePurchaseNumber(
        int $companyId,
        string $purchaseNumber
    ): void {
        if (
            $purchaseNumber === ''
            || mb_strlen(
                $purchaseNumber
            ) > 100
        ) {
            throw new ValidationException(
                'Purchase number is required and must not exceed 100 characters.'
            );
        }

        if (
            $this->purchaseRepository
                ->purchaseNumberExists(
                    $companyId,
                    $purchaseNumber
                )
        ) {
            throw new ValidationException(
                'Purchase number is already in use.'
            );
        }
    }

    private function weightedAverageCost(
        float $existingQuantity,
        float $existingAverageCost,
        float $incomingQuantity,
        float $incomingUnitCost
    ): float {
        if ($existingQuantity <= 0) {
            return $this->money(
                $incomingUnitCost
            );
        }

        $newQuantity =
            $existingQuantity
            + $incomingQuantity;

        if ($newQuantity <= 0) {
            return 0.0;
        }

        return $this->money(
            (
                $existingQuantity
                * $existingAverageCost

                + $incomingQuantity
                * $incomingUnitCost
            ) / $newQuantity
        );
    }

    private function paymentStatus(
        float $paidAmount,
        float $grandTotal
    ): string {
        if ($grandTotal <= 0.00005) {
            return 'paid';
        }

        if ($paidAmount <= 0.00005) {
            return 'unpaid';
        }

        if (
            $paidAmount
            >= $grandTotal - 0.00005
        ) {
            return 'paid';
        }

        return 'partial';
    }

    private function requiredId(
        mixed $value,
        string $field
    ): int {
        $id = $this->nullableId(
            $value
        );

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

    private function positiveQuantity(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $value = $this->quantity(
            (float) $value
        );

        if ($value <= 0) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }

        return $value;
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

        $amount = $this->money(
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

        return $amount;
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

        if (!is_string($value)) {
            throw new ValidationException(
                'Expected a text value.'
            );
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > $maximumLength
        ) {
            throw new ValidationException(
                "Text must not exceed {$maximumLength} characters."
            );
        }

        return $value;
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
        if (!is_string($value)) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        $value = trim($value);

        $date =
            \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        if (
            $date === false
            || $date->format('Y-m-d')
                !== $value
        ) {
            throw new ValidationException(
                "{$field} must use YYYY-MM-DD format."
            );
        }

        return $value;
    }

    private function nullableDateValue(
        mixed $value,
        string $field
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->dateValue(
            $value,
            $field
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

    private function quantity(
        float $value
    ): float {
        return round(
            $value,
            4
        );
    }

    private function money(
        float $value
    ): float {
        return round(
            $value,
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
                'Purchase transaction could not be started.'
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