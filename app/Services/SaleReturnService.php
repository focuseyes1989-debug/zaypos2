<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnRefund;
use App\Models\WarehouseStock;
use App\Repositories\ProductRepository;
use App\Repositories\SaleItemRepository;
use App\Repositories\SaleRepository;
use App\Repositories\SaleReturnItemRepository;
use App\Repositories\SaleReturnRefundRepository;
use App\Repositories\SaleReturnRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\WarehouseStockRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SaleReturnService
{
    public function __construct(
        private readonly SaleReturnRepository $returnRepository =
            new SaleReturnRepository(),

        private readonly SaleReturnItemRepository $itemRepository =
            new SaleReturnItemRepository(),

        private readonly SaleReturnRefundRepository $refundRepository =
            new SaleReturnRefundRepository(),

        private readonly SaleRepository $saleRepository =
            new SaleRepository(),

        private readonly SaleItemRepository $saleItemRepository =
            new SaleItemRepository(),

        private readonly ProductRepository $productRepository =
            new ProductRepository(),

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
     *     items: list<SaleReturn>,
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
            (string) (
                $filters['search']
                ?? ''
            )
        );

        if (mb_strlen($search) > 190) {
            throw new ValidationException(
                'Search text must not exceed 190 characters.'
            );
        }

        $status = strtolower(
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            )
        );

        if (
            $status !== ''
            && !in_array(
                $status,
                [
                    'draft',
                    'completed',
                    'cancelled',
                ],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid sale return status filter.'
            );
        }

        $refundStatus = strtolower(
            trim(
                (string) (
                    $filters['refund_status']
                    ?? ''
                )
            )
        );

        if (
            $refundStatus !== ''
            && !in_array(
                $refundStatus,
                [
                    'none',
                    'partial',
                    'refunded',
                ],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid refund status filter.'
            );
        }

        $saleId = $this->nullableId(
            $filters['sale_id'] ?? null
        );

        $customerId = $this->nullableId(
            $filters['customer_id'] ?? null
        );

        $warehouseId = $this->nullableId(
            $filters['warehouse_id'] ?? null
        );

        $page = max(
            1,
            (int) (
                $filters['page']
                ?? 1
            )
        );

        $perPage = (int) (
            $filters['per_page']
            ?? 20
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

        return $this->returnRepository->paginate(
            $companyId,
            $search,
            $status,
            $refundStatus,
            $saleId,
            $customerId,
            $warehouseId,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $saleReturnId,
        bool $includeDeleted = false
    ): SaleReturn {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleReturnId,
            'Sale return ID'
        );

        $return = $this->returnRepository->find(
            $companyId,
            $saleReturnId,
            $includeDeleted
        );

        if (!$return instanceof SaleReturn) {
            throw new ValidationException(
                'Sale return not found.'
            );
        }

        return $return;
    }

    /**
     * @return list<SaleReturn>
     */
    public function bySale(
        int $companyId,
        int $saleId,
        bool $includeDeleted = false
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleId,
            'Sale ID'
        );

        $sale = $this->saleRepository->find(
            $companyId,
            $saleId
        );

        if (!$sale instanceof Sale) {
            throw new ValidationException(
                'Sale not found.'
            );
        }

        return $this->returnRepository->bySale(
            $companyId,
            $saleId,
            $includeDeleted
        );
    }

    /**
     * @return list<SaleReturnItem>
     */
    public function items(
        int $companyId,
        int $saleReturnId
    ): array {
        $this->find(
            $companyId,
            $saleReturnId,
            true
        );

        return $this->itemRepository->byReturn(
            $saleReturnId
        );
    }

    /**
     * @return list<SaleReturnRefund>
     */
    public function refunds(
        int $companyId,
        int $saleReturnId,
        bool $includeDeleted = false
    ): array {
        $this->find(
            $companyId,
            $saleReturnId,
            true
        );

        return $this->refundRepository->byReturn(
            $companyId,
            $saleReturnId,
            $includeDeleted
        );
    }

    /**
     * @return array{
     *     return: SaleReturn,
     *     sale: Sale,
     *     items: list<SaleReturnItem>,
     *     refunds: list<SaleReturnRefund>
     * }
     */
    public function detail(
        int $companyId,
        int $saleReturnId,
        bool $includeDeleted = false
    ): array {
        $return = $this->find(
            $companyId,
            $saleReturnId,
            $includeDeleted
        );

        $sale = $this->saleRepository->find(
            $companyId,
            $return->saleId()
        );

        if (!$sale instanceof Sale) {
            throw new ValidationException(
                'Original sale not found.'
            );
        }

        return [
            'return' => $return,

            'sale' => $sale,

            'items' =>
                $this->itemRepository->byReturn(
                    $saleReturnId
                ),

            'refunds' =>
                $this->refundRepository->byReturn(
                    $companyId,
                    $saleReturnId,
                    $includeDeleted
                ),
        ];
    }

    /**
     * Create an empty draft return
     * against a completed sale.
     *
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): SaleReturn {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $saleId = $this->requiredId(
            $input['sale_id'] ?? null,
            'Sale'
        );

        $sale = $this->requireCompletedSale(
            $companyId,
            $saleId
        );

        $returnNumber = trim(
            (string) (
                $input['return_number']
                ?? ''
            )
        );

        if ($returnNumber === '') {
            $returnNumber =
                $this->generateReturnNumber(
                    $companyId
                );
        }

        $this->validateReturnNumber(
            $companyId,
            $returnNumber
        );

        $returnDate = $this->dateValue(
            $input['return_date']
                ?? gmdate('Y-m-d'),
            'Return date'
        );

        $reason = $this->nullableString(
            $input['reason'] ?? null,
            500
        );

        $notes = $this->nullableText(
            $input['notes'] ?? null,
            5000
        );

        $return = $this->returnRepository->create([
            'company_id' =>
                $companyId,

            'sale_id' =>
                $saleId,

            'customer_id' =>
                $sale->customerId(),

            'warehouse_id' =>
                $sale->warehouseId(),

            'return_number' =>
                $returnNumber,

            'return_date' =>
                $returnDate,

            'status' =>
                'draft',

            'refund_status' =>
                'none',

            'subtotal' =>
                0,

            'discount_amount' =>
                0,

            'tax_amount' =>
                0,

            'grand_total' =>
                0,

            'refunded_amount' =>
                0,

            'refund_balance' =>
                0,

            'reason' =>
                $reason,

            'notes' =>
                $notes,

            'created_by' =>
                $userId,
        ]);

        $this->audit->record(
            'sale_returns.created',
            'sale_return',
            $return->id(),
            [
                'return' =>
                    $return->toArray(),

                'sale_id' =>
                    $saleId,
            ]
        );

        return $return;
    }

    /**
     * Update draft return header.
     *
     * Original sale/customer/warehouse remain fixed.
     *
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $saleReturnId,
        int $userId,
        array $input
    ): SaleReturn {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $existing = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$existing->isDraft()) {
            throw new ValidationException(
                'Only draft sale returns can be updated.'
            );
        }

        $this->requireCompletedSale(
            $companyId,
            $existing->saleId()
        );

        $returnDate = $this->dateValue(
            $input['return_date']
                ?? $existing
                    ->returnDate()
                    ->format('Y-m-d'),
            'Return date'
        );

        $reason = $this->nullableString(
            $input['reason']
                ?? $existing->reason(),
            500
        );

        $notes = $this->nullableText(
            $input['notes']
                ?? $existing->notes(),
            5000
        );

        $totals =
            $this->itemRepository
                ->totalsByReturn(
                    $saleReturnId
                );

        $updated =
            $this->returnRepository
                ->updateDraft(
                    $companyId,
                    $saleReturnId,
                    [
                        'customer_id' =>
                            $existing
                                ->customerId(),

                        'warehouse_id' =>
                            $existing
                                ->warehouseId(),

                        'return_date' =>
                            $returnDate,

                        'refund_status' =>
                            $existing
                                ->refundStatus(),

                        'subtotal' =>
                            $totals['subtotal'],

                        'discount_amount' =>
                            $totals[
                                'discount_amount'
                            ],

                        'tax_amount' =>
                            $totals[
                                'tax_amount'
                            ],

                        'grand_total' =>
                            $totals[
                                'grand_total'
                            ],

                        'refunded_amount' =>
                            $existing
                                ->refundedAmount(),

                        'refund_balance' =>
                            $this->money(
                                max(
                                    0,
                                    $totals[
                                        'grand_total'
                                    ]
                                    - $existing
                                        ->refundedAmount()
                                )
                            ),

                        'reason' =>
                            $reason,

                        'notes' =>
                            $notes,

                        'updated_by' =>
                            $userId,
                    ]
                );

        if (!$updated instanceof SaleReturn) {
            throw new ValidationException(
                'Sale return could not be updated.'
            );
        }

        $this->audit->record(
            'sale_returns.updated',
            'sale_return',
            $saleReturnId,
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
        int $saleReturnId,
        int $userId,
        array $input
    ): SaleReturnItem {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$return->isDraft()) {
            throw new ValidationException(
                'Items can only be added to draft sale returns.'
            );
        }

        $data = $this->validateReturnItem(
            $companyId,
            $return,
            $input
        );

        if (
            $this->itemRepository
                ->findByReturnAndSaleItem(
                    $saleReturnId,
                    $data['sale_item_id']
                )
            instanceof SaleReturnItem
        ) {
            throw new ValidationException(
                'This sale item is already included in the return.'
            );
        }

        $item =
            $this->itemRepository->create([
                'sale_return_id' =>
                    $saleReturnId,

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
            ]);

        $this->recalculateTotals(
            $companyId,
            $saleReturnId,
            $userId
        );

        $this->audit->record(
            'sale_returns.item_created',
            'sale_return',
            $saleReturnId,
            [
                'item' =>
                    $item->toArray(),
            ]
        );

        return $item;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateItem(
        int $companyId,
        int $saleReturnId,
        int $itemId,
        int $userId,
        array $input
    ): SaleReturnItem {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$return->isDraft()) {
            throw new ValidationException(
                'Items can only be updated on draft sale returns.'
            );
        }

        $existing =
            $this->itemRepository->find(
                $itemId
            );

        if (
            !$existing instanceof SaleReturnItem
            || $existing->saleReturnId()
                !== $saleReturnId
        ) {
            throw new ValidationException(
                'Sale return item not found.'
            );
        }

        if (
            !array_key_exists(
                'sale_item_id',
                $input
            )
        ) {
            $input['sale_item_id'] =
                $existing->saleItemId();
        }

        $data = $this->validateReturnItem(
            $companyId,
            $return,
            $input
        );

        $duplicate =
            $this->itemRepository
                ->findByReturnAndSaleItem(
                    $saleReturnId,
                    $data['sale_item_id']
                );

        if (
            $duplicate instanceof SaleReturnItem
            && (int) $duplicate->id()
                !== $itemId
        ) {
            throw new ValidationException(
                'This sale item is already included in the return.'
            );
        }

        $updated =
            $this->itemRepository->update(
                $itemId,
                [
                    'sale_item_id' =>
                        $data[
                            'sale_item_id'
                        ],

                    'product_id' =>
                        $data[
                            'product_id'
                        ],

                    'unit_id' =>
                        $data[
                            'unit_id'
                        ],

                    'tax_id' =>
                        $data[
                            'tax_id'
                        ],

                    'quantity' =>
                        $data[
                            'quantity'
                        ],

                    'unit_price' =>
                        $data[
                            'unit_price'
                        ],

                    'discount_amount' =>
                        $data[
                            'discount_amount'
                        ],

                    'taxable_amount' =>
                        $data[
                            'taxable_amount'
                        ],

                    'tax_rate' =>
                        $data[
                            'tax_rate'
                        ],

                    'tax_amount' =>
                        $data[
                            'tax_amount'
                        ],

                    'line_subtotal' =>
                        $data[
                            'line_subtotal'
                        ],

                    'line_total' =>
                        $data[
                            'line_total'
                        ],

                    'reason' =>
                        $data['reason'],

                    'notes' =>
                        $data['notes'],
                ]
            );

        if (!$updated instanceof SaleReturnItem) {
            throw new ValidationException(
                'Sale return item could not be updated.'
            );
        }

        $this->recalculateTotals(
            $companyId,
            $saleReturnId,
            $userId
        );

        $this->audit->record(
            'sale_returns.item_updated',
            'sale_return',
            $saleReturnId,
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
        int $saleReturnId,
        int $itemId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$return->isDraft()) {
            throw new ValidationException(
                'Items can only be removed from draft sale returns.'
            );
        }

        $item =
            $this->itemRepository->find(
                $itemId
            );

        if (
            !$item instanceof SaleReturnItem
            || $item->saleReturnId()
                !== $saleReturnId
        ) {
            throw new ValidationException(
                'Sale return item not found.'
            );
        }

        if (
            !$this->itemRepository
                ->delete($itemId)
        ) {
            throw new ValidationException(
                'Sale return item could not be deleted.'
            );
        }

        $this->recalculateTotals(
            $companyId,
            $saleReturnId,
            $userId
        );

        $this->audit->record(
            'sale_returns.item_deleted',
            'sale_return',
            $saleReturnId,
            [
                'item' =>
                    $item->toArray(),
            ]
        );
    }

    public function recalculateTotals(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): SaleReturn {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$return->isDraft()) {
            throw new ValidationException(
                'Sale return totals can only be recalculated while draft.'
            );
        }

        $totals =
            $this->itemRepository
                ->totalsByReturn(
                    $saleReturnId
                );

        $updated =
            $this->returnRepository
                ->updateTotals(
                    $companyId,
                    $saleReturnId,
                    $this->money(
                        $totals['subtotal']
                    ),
                    $this->money(
                        $totals[
                            'discount_amount'
                        ]
                    ),
                    $this->money(
                        $totals['tax_amount']
                    ),
                    $this->money(
                        $totals['grand_total']
                    ),
                    $userId
                );

        if (!$updated instanceof SaleReturn) {
            throw new ValidationException(
                'Sale return totals could not be recalculated.'
            );
        }

        return $updated;
    }

    /**
     * Complete return and post stock IN atomically.
     */
    public function complete(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): SaleReturn {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleReturnId,
            'Sale return ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $return =
                $this->returnRepository
                    ->findForUpdate(
                        $companyId,
                        $saleReturnId
                    );

            if (!$return instanceof SaleReturn) {
                throw new ValidationException(
                    'Sale return not found.'
                );
            }

            if (!$return->isDraft()) {
                throw new ValidationException(
                    'Only draft sale returns can be completed.'
                );
            }

            /*
             * Lock original sale as serialization point.
             * Concurrent return completions for the same
             * sale will therefore not over-return quantity.
             */
            $sale =
                $this->saleRepository
                    ->findForUpdate(
                        $companyId,
                        $return->saleId()
                    );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Original sale not found.'
                );
            }

            if (
                $sale->status()
                !== 'completed'
            ) {
                throw new ValidationException(
                    'Only completed sales can be returned.'
                );
            }

            $items =
                $this->itemRepository
                    ->byReturn(
                        $saleReturnId
                    );

            if ($items === []) {
                throw new ValidationException(
                    'Sale return must contain at least one item before completion.'
                );
            }

            $totals =
                $this->itemRepository
                    ->totalsByReturn(
                        $saleReturnId
                    );

            $updatedTotals =
                $this->returnRepository
                    ->updateTotals(
                        $companyId,
                        $saleReturnId,
                        $this->money(
                            $totals[
                                'subtotal'
                            ]
                        ),
                        $this->money(
                            $totals[
                                'discount_amount'
                            ]
                        ),
                        $this->money(
                            $totals[
                                'tax_amount'
                            ]
                        ),
                        $this->money(
                            $totals[
                                'grand_total'
                            ]
                        ),
                        $userId
                    );

            if (
                !$updatedTotals
                instanceof SaleReturn
            ) {
                throw new ValidationException(
                    'Sale return totals could not be updated.'
                );
            }

            $movementAt =
                gmdate('Y-m-d H:i:s');

            foreach ($items as $item) {
                $saleItem =
                    $this->saleItemRepository
                        ->find(
                            $item->saleItemId()
                        );

                if (
                    !$saleItem
                    instanceof SaleItem
                    || $saleItem->saleId()
                        !== $sale->id()
                ) {
                    throw new ValidationException(
                        'Original sale item could not be validated.'
                    );
                }

                $soldQuantity =
                    $this->quantity(
                        $saleItem
                            ->fulfilledQuantity()
                    );

                if ($soldQuantity <= 0) {
                    throw new ValidationException(
                        'Original sale item has no fulfilled quantity to return.'
                    );
                }

                $alreadyReturned =
                    $this->quantity(
                        $this->itemRepository
                            ->completedReturnedQuantity(
                                $companyId,
                                $saleItem->id()
                            )
                    );

                $quantityToReturn =
                    $this->quantity(
                        $item->quantity()
                    );

                if (
                    $alreadyReturned
                    + $quantityToReturn
                    > $soldQuantity
                    + 0.00005
                ) {
                    $available =
                        $this->quantity(
                            max(
                                0,
                                $soldQuantity
                                - $alreadyReturned
                            )
                        );

                    throw new ValidationException(
                        'Return quantity exceeds the remaining returnable quantity for sale item #'
                        . $saleItem->id()
                        . '. Available: '
                        . number_format(
                            $available,
                            4,
                            '.',
                            ''
                        )
                        . '.'
                    );
                }

                $product =
                    $this->requireProduct(
                        $companyId,
                        $item->productId()
                    );

                $productData =
                    $product->toArray();

                $tracksStock =
                    (
                        $productData[
                            'product_type'
                        ]
                        ?? ''
                    ) === 'stock'
                    && !empty(
                        $productData[
                            'track_stock'
                        ]
                    );

                if (!$tracksStock) {
                    continue;
                }

                $stock =
                    $this->stockRepository
                        ->findForUpdate(
                            $companyId,
                            $return
                                ->warehouseId(),
                            (int) $product->id()
                        );

                if (
                    !$stock
                    instanceof WarehouseStock
                ) {
                    $this->stockRepository->create(
                        $companyId,
                        $return->warehouseId(),
                        (int) $product->id(),
                        0,
                        0,
                        0
                    );

                    $stock =
                        $this->stockRepository
                            ->findForUpdate(
                                $companyId,
                                $return
                                    ->warehouseId(),
                                (int) $product->id()
                            );
                }

                if (
                    !$stock
                    instanceof WarehouseStock
                ) {
                    throw new ValidationException(
                        'Warehouse stock could not be loaded.'
                    );
                }

                $before =
                    $this->quantity(
                        $stock->quantity()
                    );

                $after =
                    $this->quantity(
                        $before
                        + $quantityToReturn
                    );

                /*
                 * Keep current moving average unchanged.
                 * Exact historical sale COGS recovery can
                 * be added later when sale movement costing
                 * is explicitly linked to return items.
                 */
                $averageCost =
                    $this->money(
                        $stock->averageCost()
                    );

                $this->stockRepository
                    ->updateBalance(
                        $companyId,
                        $return
                            ->warehouseId(),
                        (int) $product->id(),
                        $after,
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
                            $return
                                ->warehouseId(),

                        'product_id' =>
                            (int) $product->id(),

                        'movement_type' =>
                            'sale_return',

                        'quantity_in' =>
                            $quantityToReturn,

                        'quantity_out' =>
                            0,

                        'quantity_before' =>
                            $before,

                        'quantity_after' =>
                            $after,

                        'unit_cost' =>
                            $averageCost,

                        'total_cost' =>
                            $this->money(
                                $quantityToReturn
                                * $averageCost
                            ),

                        'reference_type' =>
                            'sale_return',

                        'reference_id' =>
                            $saleReturnId,

                        'reference_number' =>
                            $return
                                ->returnNumber(),

                        'related_warehouse_id' =>
                            null,

                        'notes' =>
                            'Sale return completion',

                        'movement_at' =>
                            $movementAt,

                        'created_by' =>
                            $userId,
                    ]);
            }

            $completed =
                $this->returnRepository
                    ->markCompleted(
                        $companyId,
                        $saleReturnId,
                        $userId
                    );

            if (
                !$completed
                instanceof SaleReturn
                || !$completed->isCompleted()
            ) {
                throw new ValidationException(
                    'Sale return could not be marked as completed.'
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
            'sale_returns.completed',
            'sale_return',
            $saleReturnId,
            [
                'return' =>
                    $completed->toArray(),
            ]
        );

        return $completed;
    }

    public function cancel(
        int $companyId,
        int $saleReturnId,
        int $userId,
        mixed $reason = null
    ): SaleReturn {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$return->isDraft()) {
            throw new ValidationException(
                'Only draft sale returns can be cancelled.'
            );
        }

        $reason = $this->nullableString(
            $reason,
            500
        );

        $cancelled =
            $this->returnRepository
                ->markCancelled(
                    $companyId,
                    $saleReturnId,
                    $userId,
                    $reason
                );

        if (
            !$cancelled
            instanceof SaleReturn
            || !$cancelled->isCancelled()
        ) {
            throw new ValidationException(
                'Sale return could not be cancelled.'
            );
        }

        $this->audit->record(
            'sale_returns.cancelled',
            'sale_return',
            $saleReturnId,
            [
                'return' =>
                    $cancelled->toArray(),
            ]
        );

        return $cancelled;
    }

    public function delete(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        if (!$return->isDraft()) {
            throw new ValidationException(
                'Only draft sale returns can be deleted.'
            );
        }

        if (
            !$this->returnRepository
                ->deleteDraft(
                    $companyId,
                    $saleReturnId,
                    $userId
                )
        ) {
            throw new ValidationException(
                'Sale return could not be deleted.'
            );
        }

        $this->audit->record(
            'sale_returns.deleted',
            'sale_return',
            $saleReturnId,
            [
                'return' =>
                    $return->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): SaleReturn {
        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $return = $this->find(
            $companyId,
            $saleReturnId,
            true
        );

        if (!$return->isDeleted()) {
            throw new ValidationException(
                'Sale return is not deleted.'
            );
        }

        if (
            $this->returnRepository
                ->returnNumberExists(
                    $companyId,
                    $return
                        ->returnNumber(),
                    $saleReturnId
                )
        ) {
            throw new ValidationException(
                'Another sale return is already using this return number.'
            );
        }

        if (
            !$this->returnRepository
                ->restoreDeleted(
                    $companyId,
                    $saleReturnId,
                    $userId
                )
        ) {
            throw new ValidationException(
                'Sale return could not be restored.'
            );
        }

        $restored = $this->find(
            $companyId,
            $saleReturnId
        );

        $this->audit->record(
            'sale_returns.restored',
            'sale_return',
            $saleReturnId,
            [
                'return' =>
                    $restored->toArray(),
            ]
        );

        return $restored;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<SaleReturnRefund>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function refundPaginate(
        int $companyId,
        array $filters
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        if (mb_strlen($search) > 190) {
            throw new ValidationException(
                'Search text must not exceed 190 characters.'
            );
        }

        $refundMethod = strtolower(
            trim(
                (string) (
                    $filters['refund_method']
                    ?? ''
                )
            )
        );

        if (
            $refundMethod !== ''
            && !in_array(
                $refundMethod,
                SaleReturnRefund
                    ::refundMethods(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid refund method filter.'
            );
        }

        $saleReturnId =
            $this->nullableId(
                $filters[
                    'sale_return_id'
                ] ?? null
            );

        $page = max(
            1,
            (int) (
                $filters['page']
                ?? 1
            )
        );

        $perPage = (int) (
            $filters['per_page']
            ?? 20
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

        return $this->refundRepository
            ->paginate(
                $companyId,
                $search,
                $refundMethod,
                $saleReturnId,
                $page,
                $perPage,
                $onlyDeleted
            );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createRefund(
        int $companyId,
        int $saleReturnId,
        int $userId,
        array $input
    ): SaleReturnRefund {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleReturnId,
            'Sale return ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $return =
                $this->returnRepository
                    ->findForUpdate(
                        $companyId,
                        $saleReturnId
                    );

            if (!$return instanceof SaleReturn) {
                throw new ValidationException(
                    'Sale return not found.'
                );
            }

            $this->validateReturnForRefund(
                $return
            );

            $amount =
                $this->positiveAmount(
                    $input['amount']
                        ?? null,
                    'Refund amount'
                );

            $activeRefunded =
                $this->money(
                    $this->refundRepository
                        ->sumActiveRefunds(
                            $companyId,
                            $saleReturnId
                        )
                );

            $balance =
                $this->money(
                    max(
                        0,
                        $return
                            ->grandTotal()
                        - $activeRefunded
                    )
                );

            if (
                $amount
                > $balance + 0.00005
            ) {
                throw new ValidationException(
                    'Refund amount exceeds the remaining refund balance.'
                );
            }

            $refundNumber = trim(
                (string) (
                    $input[
                        'refund_number'
                    ]
                    ?? ''
                )
            );

            if ($refundNumber === '') {
                $refundNumber =
                    $this
                        ->generateRefundNumber(
                            $companyId
                        );
            }

            $this->validateRefundNumber(
                $companyId,
                $refundNumber
            );

            $refundDate =
                $this->dateValue(
                    $input[
                        'refund_date'
                    ]
                        ?? gmdate(
                            'Y-m-d'
                        ),
                    'Refund date'
                );

            $refundMethod =
                strtolower(
                    trim(
                        (string) (
                            $input[
                                'refund_method'
                            ]
                            ?? 'cash'
                        )
                    )
                );

            if (
                !in_array(
                    $refundMethod,
                    SaleReturnRefund
                        ::refundMethods(),
                    true
                )
            ) {
                throw new ValidationException(
                    'Invalid refund method.'
                );
            }

            $referenceNumber =
                $this->nullableString(
                    $input[
                        'reference_number'
                    ] ?? null,
                    190
                );

            $notes =
                $this->nullableText(
                    $input['notes']
                        ?? null,
                    5000
                );

            $refund =
                $this->refundRepository
                    ->create([
                        'company_id' =>
                            $companyId,

                        'sale_return_id' =>
                            $saleReturnId,

                        'refund_number' =>
                            $refundNumber,

                        'refund_date' =>
                            $refundDate,

                        'amount' =>
                            $amount,

                        'refund_method' =>
                            $refundMethod,

                        'reference_number' =>
                            $referenceNumber,

                        'notes' =>
                            $notes,

                        'created_by' =>
                            $userId,
                    ]);

            $this->synchronizeRefundHeader(
                $return,
                $userId
            );

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'sale_returns.refund_created',
            'sale_return_refund',
            $refund->id(),
            [
                'refund' =>
                    $refund->toArray(),

                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        return $refund;
    }

    public function deleteRefund(
        int $companyId,
        int $refundId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $refundId,
            'Refund ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $refund =
                $this->refundRepository
                    ->findForUpdate(
                        $companyId,
                        $refundId
                    );

            if (
                !$refund
                instanceof SaleReturnRefund
            ) {
                throw new ValidationException(
                    'Sale return refund not found.'
                );
            }

            $return =
                $this->returnRepository
                    ->findForUpdate(
                        $companyId,
                        $refund
                            ->saleReturnId()
                    );

            if (!$return instanceof SaleReturn) {
                throw new ValidationException(
                    'Sale return not found.'
                );
            }

            $this->validateReturnForRefund(
                $return
            );

            if (
                !$this->refundRepository
                    ->softDelete(
                        $companyId,
                        $refundId,
                        $userId
                    )
            ) {
                throw new ValidationException(
                    'Sale return refund could not be deleted.'
                );
            }

            $this->synchronizeRefundHeader(
                $return,
                $userId
            );

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'sale_returns.refund_deleted',
            'sale_return_refund',
            $refundId,
            [
                'refund' =>
                    $refund->toArray(),

                'sale_return_id' =>
                    $refund
                        ->saleReturnId(),
            ]
        );
    }

    public function restoreRefund(
        int $companyId,
        int $refundId,
        int $userId
    ): SaleReturnRefund {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $refundId,
            'Refund ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $refund =
                $this->refundRepository
                    ->find(
                        $companyId,
                        $refundId,
                        true
                    );

            if (
                !$refund
                instanceof SaleReturnRefund
            ) {
                throw new ValidationException(
                    'Sale return refund not found.'
                );
            }

            if (!$refund->isDeleted()) {
                throw new ValidationException(
                    'Sale return refund is not deleted.'
                );
            }

            $return =
                $this->returnRepository
                    ->findForUpdate(
                        $companyId,
                        $refund
                            ->saleReturnId()
                    );

            if (!$return instanceof SaleReturn) {
                throw new ValidationException(
                    'Sale return not found.'
                );
            }

            $this->validateReturnForRefund(
                $return
            );

            $activeRefunded =
                $this->money(
                    $this->refundRepository
                        ->sumActiveRefunds(
                            $companyId,
                            $return->id()
                        )
                );

            $afterRestore =
                $this->money(
                    $activeRefunded
                    + $refund->amount()
                );

            if (
                $afterRestore
                > $return->grandTotal()
                + 0.00005
            ) {
                throw new ValidationException(
                    'Refund cannot be restored because it would exceed the return total.'
                );
            }

            if (
                !$this->refundRepository
                    ->restore(
                        $companyId,
                        $refundId,
                        $userId
                    )
            ) {
                throw new ValidationException(
                    'Sale return refund could not be restored.'
                );
            }

            $restored =
                $this->refundRepository
                    ->find(
                        $companyId,
                        $refundId
                    );

            if (
                !$restored
                instanceof SaleReturnRefund
            ) {
                throw new ValidationException(
                    'Restored refund could not be reloaded.'
                );
            }

            $this->synchronizeRefundHeader(
                $return,
                $userId
            );

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        $this->audit->record(
            'sale_returns.refund_restored',
            'sale_return_refund',
            $refundId,
            [
                'refund' =>
                    $restored->toArray(),

                'sale_return_id' =>
                    $restored
                        ->saleReturnId(),
            ]
        );

        return $restored;
    }

    /**
     * @return array{
     *     grand_total: float,
     *     refunded_amount: float,
     *     refund_balance: float,
     *     refund_status: string
     * }
     */
    public function refundSummary(
        int $companyId,
        int $saleReturnId
    ): array {
        $return = $this->find(
            $companyId,
            $saleReturnId
        );

        $refunded =
            $this->money(
                $this->refundRepository
                    ->sumActiveRefunds(
                        $companyId,
                        $saleReturnId
                    )
            );

        $balance =
            $this->money(
                max(
                    0,
                    $return->grandTotal()
                    - $refunded
                )
            );

        return [
            'grand_total' =>
                $return->grandTotal(),

            'refunded_amount' =>
                $refunded,

            'refund_balance' =>
                $balance,

            'refund_status' =>
                $this->refundStatus(
                    $refunded,
                    $return->grandTotal()
                ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function refundMethodOptions(): array
    {
        return SaleReturnRefund
            ::refundMethodOptions();
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     sale_item_id: int,
     *     product_id: int,
     *     unit_id: int,
     *     tax_id: int|null,
     *     quantity: float,
     *     unit_price: float,
     *     discount_amount: float,
     *     taxable_amount: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     line_subtotal: float,
     *     line_total: float,
     *     reason: string|null,
     *     notes: string|null
     * }
     */
    private function validateReturnItem(
        int $companyId,
        SaleReturn $return,
        array $input
    ): array {
        $saleItemId =
            $this->requiredId(
                $input['sale_item_id']
                    ?? null,
                'Sale item'
            );

        $saleItem =
            $this->saleItemRepository
                ->find(
                    $saleItemId
                );

        if (
            !$saleItem instanceof SaleItem
            || $saleItem->saleId()
                !== $return->saleId()
        ) {
            throw new ValidationException(
                'Selected sale item does not belong to the original sale.'
            );
        }

        $quantity =
            $this->positiveQuantity(
                $input['quantity']
                    ?? null,
                'Return quantity'
            );

        $fulfilledQuantity =
            $this->quantity(
                $saleItem
                    ->fulfilledQuantity()
            );

        if ($fulfilledQuantity <= 0) {
            throw new ValidationException(
                'Selected sale item has no fulfilled quantity to return.'
            );
        }

        $alreadyReturned =
            $this->quantity(
                $this->itemRepository
                    ->completedReturnedQuantity(
                        $companyId,
                        $saleItemId
                    )
            );

        $available =
            $this->quantity(
                max(
                    0,
                    $fulfilledQuantity
                    - $alreadyReturned
                )
            );

        if (
            $quantity
            > $available + 0.00005
        ) {
            throw new ValidationException(
                'Return quantity exceeds available returnable quantity. Available: '
                . number_format(
                    $available,
                    4,
                    '.',
                    ''
                )
                . '.'
            );
        }

        /*
         * Preserve original sale pricing exactly
         * by prorating original line amounts.
         */
        $originalQuantity =
            $this->quantity(
                $saleItem->quantity()
            );

        if ($originalQuantity <= 0) {
            throw new ValidationException(
                'Original sale item quantity is invalid.'
            );
        }

        $ratio =
            $quantity
            / $originalQuantity;

        $lineSubtotal =
            $this->money(
                $saleItem
                    ->lineSubtotal()
                * $ratio
            );

        $discountAmount =
            $this->money(
                $saleItem
                    ->discountAmount()
                * $ratio
            );

        $taxableAmount =
            $this->money(
                $saleItem
                    ->taxableAmount()
                * $ratio
            );

        $taxAmount =
            $this->money(
                $saleItem
                    ->taxAmount()
                * $ratio
            );

        $lineTotal =
            $this->money(
                $saleItem
                    ->lineTotal()
                * $ratio
            );

        return [
            'sale_item_id' =>
                $saleItemId,

            'product_id' =>
                $saleItem->productId(),

            'unit_id' =>
                $saleItem->unitId(),

            'tax_id' =>
                $saleItem->taxId(),

            'quantity' =>
                $quantity,

            'unit_price' =>
                $this->money(
                    $saleItem
                        ->unitPrice()
                ),

            'discount_amount' =>
                $discountAmount,

            'taxable_amount' =>
                $taxableAmount,

            'tax_rate' =>
                $this->money(
                    $saleItem
                        ->taxRate()
                ),

            'tax_amount' =>
                $taxAmount,

            'line_subtotal' =>
                $lineSubtotal,

            'line_total' =>
                $lineTotal,

            'reason' =>
                $this->nullableString(
                    $input['reason']
                        ?? null,
                    500
                ),

            'notes' =>
                $this->nullableText(
                    $input['notes']
                        ?? null,
                    5000
                ),
        ];
    }

    private function requireCompletedSale(
        int $companyId,
        int $saleId
    ): Sale {
        $sale =
            $this->saleRepository->find(
                $companyId,
                $saleId
            );

        if (!$sale instanceof Sale) {
            throw new ValidationException(
                'Sale not found.'
            );
        }

        if (
            $sale->status()
            !== 'completed'
        ) {
            throw new ValidationException(
                'Only completed sales can be returned.'
            );
        }

        if ($sale->isDeleted()) {
            throw new ValidationException(
                'Deleted sales cannot be returned.'
            );
        }

        return $sale;
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
                'Returned product was not found.'
            );
        }

        return $product;
    }

    private function validateReturnForRefund(
        SaleReturn $return
    ): void {
        if (!$return->isCompleted()) {
            throw new ValidationException(
                'Refunds can only be recorded against completed sale returns.'
            );
        }

        if ($return->isDeleted()) {
            throw new ValidationException(
                'Deleted sale returns cannot be refunded.'
            );
        }
    }

    private function synchronizeRefundHeader(
        SaleReturn $return,
        int $userId
    ): SaleReturn {
        $refundedAmount =
            $this->money(
                $this->refundRepository
                    ->sumActiveRefunds(
                        $return->companyId(),
                        $return->id()
                    )
            );

        if (
            $refundedAmount
            > $return->grandTotal()
            + 0.00005
        ) {
            throw new ValidationException(
                'Refunded amount exceeds sale return total.'
            );
        }

        $refundBalance =
            $this->money(
                max(
                    0,
                    $return->grandTotal()
                    - $refundedAmount
                )
            );

        $refundStatus =
            $this->refundStatus(
                $refundedAmount,
                $return->grandTotal()
            );

        $updated =
            $this->returnRepository
                ->updateRefund(
                    $return->companyId(),
                    $return->id(),
                    $refundedAmount,
                    $refundBalance,
                    $refundStatus,
                    $userId
                );

        if (!$updated instanceof SaleReturn) {
            throw new ValidationException(
                'Sale return refund balance could not be updated.'
            );
        }

        return $updated;
    }

    private function refundStatus(
        float $refundedAmount,
        float $grandTotal
    ): string {
        $refundedAmount =
            $this->money(
                $refundedAmount
            );

        $grandTotal =
            $this->money(
                $grandTotal
            );

        if ($refundedAmount <= 0.00005) {
            return 'none';
        }

        if (
            $grandTotal > 0.00005
            && $refundedAmount
                >= $grandTotal - 0.00005
        ) {
            return 'refunded';
        }

        return 'partial';
    }

    private function generateReturnNumber(
        int $companyId
    ): string {
        $prefix =
            'SRET-'
            . gmdate('Ymd')
            . '-';

        for (
            $attempt = 0;
            $attempt < 20;
            $attempt++
        ) {
            $suffix =
                strtoupper(
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

            $number =
                $prefix . $suffix;

            if (
                !$this->returnRepository
                    ->returnNumberExists(
                        $companyId,
                        $number
                    )
            ) {
                return $number;
            }
        }

        throw new ValidationException(
            'Sale return number could not be generated.'
        );
    }

    private function validateReturnNumber(
        int $companyId,
        string $returnNumber,
        ?int $exceptReturnId = null
    ): void {
        if (
            $returnNumber === ''
            || mb_strlen(
                $returnNumber
            ) > 100
        ) {
            throw new ValidationException(
                'Sale return number is required and must not exceed 100 characters.'
            );
        }

        if (
            $this->returnRepository
                ->returnNumberExists(
                    $companyId,
                    $returnNumber,
                    $exceptReturnId
                )
        ) {
            throw new ValidationException(
                'Sale return number is already in use.'
            );
        }
    }

    private function generateRefundNumber(
        int $companyId
    ): string {
        $prefix =
            'SRREF-'
            . gmdate('Ymd')
            . '-';

        for (
            $attempt = 0;
            $attempt < 20;
            $attempt++
        ) {
            $suffix =
                strtoupper(
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

            $number =
                $prefix . $suffix;

            if (
                !$this->refundRepository
                    ->refundNumberExists(
                        $companyId,
                        $number
                    )
            ) {
                return $number;
            }
        }

        throw new ValidationException(
            'Refund number could not be generated.'
        );
    }

    private function validateRefundNumber(
        int $companyId,
        string $refundNumber,
        ?int $exceptRefundId = null
    ): void {
        if (
            $refundNumber === ''
            || mb_strlen(
                $refundNumber
            ) > 100
        ) {
            throw new ValidationException(
                'Refund number is required and must not exceed 100 characters.'
            );
        }

        if (
            $this->refundRepository
                ->refundNumberExists(
                    $companyId,
                    $refundNumber,
                    $exceptRefundId
                )
        ) {
            throw new ValidationException(
                'Refund number is already in use.'
            );
        }
    }

    private function requiredId(
        mixed $value,
        string $field
    ): int {
        $id =
            $this->nullableId(
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

        $number =
            $this->quantity(
                (float) $value
            );

        if ($number <= 0) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }

        return $number;
    }

    private function positiveAmount(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $number =
            $this->money(
                (float) $value
            );

        if (
            !is_finite($number)
            || $number <= 0
        ) {
            throw new ValidationException(
                "{$field} must be greater than zero."
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

        $text =
            trim(
                (string) $value
            );

        if ($text === '') {
            return null;
        }

        if (
            mb_strlen($text)
            > $maximumLength
        ) {
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
        $date =
            $this->parseDate(
                $value,
                $field
            );

        if ($date === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $date->format(
            'Y-m-d'
        );
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

        if (
            $value
            instanceof DateTimeImmutable
        ) {
            return $value;
        }

        if (!is_string($value)) {
            throw new ValidationException(
                "{$field} must use Y-m-d format."
            );
        }

        $value = trim(
            $value
        );

        $date =
            DateTimeImmutable
                ::createFromFormat(
                    '!Y-m-d',
                    $value
                );

        $errors =
            DateTimeImmutable
                ::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors[
                        'warning_count'
                    ] > 0
                    || $errors[
                        'error_count'
                    ] > 0
                )
            )
            || $date->format(
                'Y-m-d'
            ) !== $value
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

        if (
            !$database
                ->beginTransaction()
        ) {
            throw new ValidationException(
                'Sale return transaction could not be started.'
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