<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\WarehouseStock;
use App\Repositories\SaleItemRepository;
use App\Repositories\WarehouseStockRepository;

final class PosCheckoutService
{
    public function __construct(
        private readonly ProductService $productService =
            new ProductService(),

        private readonly SaleService $saleService =
            new SaleService(),

        private readonly SalePaymentService $paymentService =
            new SalePaymentService(),

        private readonly SaleItemRepository $saleItemRepository =
            new SaleItemRepository(),

        private readonly WarehouseStockRepository $stockRepository =
            new WarehouseStockRepository(),

        private readonly PosShiftService $shiftService =
            new PosShiftService()
    ) {
    }

    /**
     * Create a new POS draft transaction.
     *
     * @param array<string, mixed> $input
     */
    public function createDraft(
        int $companyId,
        int $userId,
        array $input
    ): Sale {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $userId,
            'User ID'
        );

        $warehouseId =
            $this->positiveId(
                $input['warehouse_id']
                    ?? null,
                'Warehouse'
            );

        $shift =
            $this->shiftService->currentShift(
                $companyId,
                $userId
            );

        if ($shift === null) {
            throw new ValidationException(
                'An open POS shift is required before starting a sale.'
            );
        }

        if (!$shift->isOpen()) {
            throw new ValidationException(
                'The current POS shift is not open.'
            );
        }

        if ($shift->warehouseId() !== $warehouseId) {
            throw new ValidationException(
                'The POS warehouse must match the current shift warehouse.'
            );
        }

        return $this->saleService->create(
            $companyId,
            $userId,
            [
                'customer_id' =>
                    $input['customer_id']
                    ?? null,

                'warehouse_id' =>
                    $warehouseId,

                'pos_shift_id' =>
                    (int) $shift->id(),

                'sale_number' =>
                    $input['sale_number']
                    ?? '',

                'customer_reference' =>
                    $input['customer_reference']
                    ?? null,

                'sale_date' =>
                    $input['sale_date']
                    ?? gmdate('Y-m-d'),

                /*
                 * POS transactions normally do not
                 * require a due date.
                 */
                'due_date' =>
                    $input['due_date']
                    ?? null,

                'shipping_amount' =>
                    $input['shipping_amount']
                    ?? 0,

                'other_amount' =>
                    $input['other_amount']
                    ?? 0,

                /*
                 * Dedicated payment ledger is used
                 * after sale completion.
                 */
                'paid_amount' =>
                    0,

                'notes' =>
                    $input['notes']
                    ?? null,
            ]
        );
    }

    /**
     * Find a sellable product using barcode.
     *
     * @return array<string, mixed>
     */
    public function lookupBarcode(
        int $companyId,
        int $warehouseId,
        string $barcode
    ): array {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $warehouseId,
            'Warehouse'
        );

        $barcode =
            trim($barcode);

        if ($barcode === '') {
            throw new ValidationException(
                'Barcode is required.'
            );
        }

        $product =
            $this->productService
                ->findByBarcode(
                    $companyId,
                    $barcode
                );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Product was not found for this barcode.'
            );
        }

        return $this->productAvailability(
            $companyId,
            $warehouseId,
            $product
        );
    }

    /**
     * Find a sellable product using SKU.
     *
     * @return array<string, mixed>
     */
    public function lookupSku(
        int $companyId,
        int $warehouseId,
        string $sku
    ): array {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $warehouseId,
            'Warehouse'
        );

        $sku =
            trim($sku);

        if ($sku === '') {
            throw new ValidationException(
                'SKU is required.'
            );
        }

        $product =
            $this->productService
                ->findBySku(
                    $companyId,
                    $sku
                );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Product was not found for this SKU.'
            );
        }

        return $this->productAvailability(
            $companyId,
            $warehouseId,
            $product
        );
    }

    /**
     * Return stock / selling information for a product.
     *
     * @return array{
     *     product: Product,
     *     stock: ?WarehouseStock,
     *     available_quantity: ?float,
     *     tracks_stock: bool,
     *     allow_negative_stock: bool,
     *     can_sell: bool
     * }
     */
    public function productAvailability(
        int $companyId,
        int $warehouseId,
        Product $product
    ): array {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $warehouseId,
            'Warehouse'
        );

        if (
            $product->companyId()
            !== $companyId
        ) {
            throw new ValidationException(
                'Product does not belong to this company.'
            );
        }

        if (!$product->isActive()) {
            throw new ValidationException(
                'Product is inactive.'
            );
        }

        /*
         * Services and non-stock products do not
         * require warehouse quantity.
         */
        if (
            !$product->isStockProduct()
            || !$product->tracksStock()
        ) {
            return [
                'product' =>
                    $product,

                'stock' =>
                    null,

                'available_quantity' =>
                    null,

                'tracks_stock' =>
                    false,

                'allow_negative_stock' =>
                    $product
                        ->allowsNegativeStock(),

                'can_sell' =>
                    true,
            ];
        }

        $stock =
            $this->stockRepository->find(
                $companyId,
                $warehouseId,
                (int) $product->id()
            );

        $available =
            $stock instanceof WarehouseStock
                ? $stock->availableQuantity()
                : 0.0;

        $allowNegative =
            $product
                ->allowsNegativeStock();

        return [
            'product' =>
                $product,

            'stock' =>
                $stock,

            'available_quantity' =>
                $available,

            'tracks_stock' =>
                true,

            'allow_negative_stock' =>
                $allowNegative,

            'can_sell' =>
                $allowNegative
                || $available > 0.00005,
        ];
    }

    /**
     * Add one product to a POS cart.
     *
     * If the same product/unit already exists,
     * increase its quantity instead of creating
     * another row.
     */
    public function addProduct(
        int $companyId,
        int $saleId,
        int $userId,
        int $productId,
        float $quantity = 1.0
    ): SaleItem {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $saleId,
            'Sale ID'
        );

        $this->positiveId(
            $userId,
            'User ID'
        );

        if ($quantity <= 0.00005) {
            throw new ValidationException(
                'Quantity must be greater than zero.'
            );
        }

        $sale =
            $this->saleService->find(
                $companyId,
                $saleId
            );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Only draft POS sales can be changed.'
            );
        }

        $product =
            $this->productService->find(
                $companyId,
                $productId
            );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Product not found.'
            );
        }

        if (!$product->isActive()) {
            throw new ValidationException(
                'Product is inactive.'
            );
        }

        $saleUnitId =
            $product->saleUnitId()
            ?? $product->baseUnitId();

        $this->assertStockForCart(
            $companyId,
            $sale,
            $product,
            $quantity
        );

        /*
         * Barcode scans of the same product should
         * increase an existing standard POS row.
         *
         * Only merge rows using the normal product
         * selling price and no discount.
         */
        foreach (
            $this->saleService->items(
                $companyId,
                $saleId
            )
            as $existingItem
        ) {
            if (
                !$existingItem
                instanceof SaleItem
            ) {
                continue;
            }

            if (
                $existingItem->productId()
                    !== (int) $product->id()
                || $existingItem->unitId()
                    !== $saleUnitId
                || $existingItem->taxId()
                    !== $product->taxId()
                || $existingItem
                    ->discountAmount()
                    > 0.00005
            ) {
                continue;
            }

            $newQuantity =
                $existingItem->quantity()
                + $quantity;

            return $this->saleService
                ->updateItem(
                    $companyId,
                    $saleId,
                    (int) $existingItem->id(),
                    $userId,
                    [
                        'product_id' =>
                            $product->id(),

                        'unit_id' =>
                            $saleUnitId,

                        'tax_id' =>
                            $product->taxId(),

                        'quantity' =>
                            $newQuantity,

                        'unit_price' =>
                            $existingItem
                                ->unitPrice(),

                        'discount_type' =>
                            $existingItem
                                ->discountType(),

                        'discount_value' =>
                            $existingItem
                                ->discountValue(),

                        'notes' =>
                            $existingItem
                                ->notes(),
                    ]
                );
        }

        return $this->saleService->addItem(
            $companyId,
            $saleId,
            $userId,
            [
                'product_id' =>
                    $product->id(),

                'unit_id' =>
                    $saleUnitId,

                'tax_id' =>
                    $product->taxId(),

                'quantity' =>
                    $quantity,

                'unit_price' =>
                    $product->salePrice(),

                'discount_type' =>
                    null,

                'discount_value' =>
                    0,

                'notes' =>
                    null,
            ]
        );
    }

    /**
     * Add product using a barcode scan.
     */
    public function scanBarcode(
        int $companyId,
        int $saleId,
        int $userId,
        string $barcode,
        float $quantity = 1.0
    ): SaleItem {
        $sale =
            $this->saleService->find(
                $companyId,
                $saleId
            );

        $lookup =
            $this->lookupBarcode(
                $companyId,
                $sale->warehouseId(),
                $barcode
            );

        /** @var Product $product */
        $product =
            $lookup['product'];

        if (
            empty(
                $lookup['can_sell']
            )
        ) {
            throw new ValidationException(
                'Product is out of stock.'
            );
        }

        return $this->addProduct(
            $companyId,
            $saleId,
            $userId,
            (int) $product->id(),
            $quantity
        );
    }

    /**
     * Update cart item quantity.
     */
    public function updateQuantity(
        int $companyId,
        int $saleId,
        int $itemId,
        int $userId,
        float $quantity
    ): SaleItem {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $saleId,
            'Sale ID'
        );

        $this->positiveId(
            $itemId,
            'Sale item ID'
        );

        $this->positiveId(
            $userId,
            'User ID'
        );

        if ($quantity <= 0.00005) {
            throw new ValidationException(
                'Quantity must be greater than zero.'
            );
        }

        $sale =
            $this->saleService->find(
                $companyId,
                $saleId
            );

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Only draft POS sales can be changed.'
            );
        }

        $item =
            $this->saleItemRepository->find(
                $itemId
            );

        if (
            !$item instanceof SaleItem
            || $item->saleId()
                !== $saleId
        ) {
            throw new ValidationException(
                'Sale item not found.'
            );
        }

        $product =
            $this->productService->find(
                $companyId,
                $item->productId()
            );

        $additionalQuantity =
            max(
                0,
                $quantity
                - $item->quantity()
            );

        if (
            $additionalQuantity
            > 0.00005
        ) {
            $this->assertStockForCart(
                $companyId,
                $sale,
                $product,
                $additionalQuantity,
                $itemId
            );
        }

        return $this->saleService
            ->updateItem(
                $companyId,
                $saleId,
                $itemId,
                $userId,
                [
                    'product_id' =>
                        $item->productId(),

                    'unit_id' =>
                        $item->unitId(),

                    'tax_id' =>
                        $item->taxId(),

                    'quantity' =>
                        $quantity,

                    'unit_price' =>
                        $item->unitPrice(),

                    'discount_type' =>
                        $item
                            ->discountType(),

                    'discount_value' =>
                        $item
                            ->discountValue(),

                    'notes' =>
                        $item->notes(),
                ]
            );
    }

    /**
     * Remove item from POS cart.
     */
    public function removeItem(
        int $companyId,
        int $saleId,
        int $itemId,
        int $userId
    ): void {
        $this->saleService->deleteItem(
            $companyId,
            $saleId,
            $itemId,
            $userId
        );
    }

    /**
     * Return current cart / sale state.
     *
     * @return array<string, mixed>
     */
    public function cart(
        int $companyId,
        int $saleId
    ): array {
        $detail =
            $this->saleService->detail(
                $companyId,
                $saleId
            );

        $sale =
            $detail['sale'];

        if (!$sale instanceof Sale) {
            throw new ValidationException(
                'POS sale not found.'
            );
        }

        return [
            'sale' =>
                $sale,

            'items' =>
                $detail['items'],

            'payment_summary' =>
                $sale->isDraft()
                    ? [
                        'grand_total' =>
                            $sale->grandTotal(),

                        'paid_amount' =>
                            $sale->paidAmount(),

                        'balance_due' =>
                            $sale->balanceDue(),

                        'payment_status' =>
                            $sale->paymentStatus(),
                    ]
                    : $this->paymentService
                        ->summary(
                            $companyId,
                            $saleId
                        ),
        ];
    }

    /**
     * Complete a POS sale and optionally record payment.
     *
     * Sale completion happens first because the
     * existing payment service intentionally accepts
     * completed sales only.
     *
     * @param array<string, mixed> $payment
     *
     * @return array{
     *     sale: Sale,
     *     payment: ?SalePayment,
     *     payment_summary: array<string, mixed>
     * }
     */
    public function checkout(
        int $companyId,
        int $saleId,
        int $userId,
        array $payment = []
    ): array {
        $this->positiveId(
            $companyId,
            'Company ID'
        );

        $this->positiveId(
            $saleId,
            'Sale ID'
        );

        $this->positiveId(
            $userId,
            'User ID'
        );

        $sale =
            $this->saleService->find(
                $companyId,
                $saleId
            );

        $shift =
            $this->shiftService->currentShift(
                $companyId,
                $userId
            );

        if ($shift === null) {
            throw new ValidationException(
                'An open POS shift is required before checkout.'
            );
        }

        if (!$shift->isOpen()) {
            throw new ValidationException(
                'The current POS shift is not open.'
            );
        }

        if ($sale->posShiftId() === null) {
            throw new ValidationException(
                'POS sale is not linked to a shift.'
            );
        }

        if ((int) $shift->id() !== $sale->posShiftId()) {
            throw new ValidationException(
                'POS sale belongs to a different shift.'
            );
        }

        if ($shift->warehouseId() !== $sale->warehouseId()) {
            throw new ValidationException(
                'POS sale warehouse does not match the current shift warehouse.'
            );
        }

        if (!$sale->isDraft()) {
            throw new ValidationException(
                'Only draft POS sales can be checked out.'
            );
        }

        $items =
            $this->saleService->items(
                $companyId,
                $saleId
            );

        if ($items === []) {
            throw new ValidationException(
                'POS cart is empty.'
            );
        }

        /*
         * SaleService::complete() is responsible for
         * atomic inventory deduction and stock movement.
         */
        $completedSale =
            $this->saleService->complete(
                $companyId,
                $saleId,
                $userId
            );

        $paymentRecord =
            null;

        $paymentAmount =
            $this->number(
                $payment['amount']
                ?? 0
            );

        /*
         * Zero payment means credit / unpaid sale.
         */
        if ($paymentAmount > 0.00005) {
            $paymentRecord =
                $this->paymentService
                    ->create(
                        $companyId,
                        $saleId,
                        $userId,
                        [
                            'payment_number' =>
                                $payment[
                                    'payment_number'
                                ] ?? '',

                            'payment_date' =>
                                $payment[
                                    'payment_date'
                                ] ?? gmdate(
                                    'Y-m-d'
                                ),

                            'amount' =>
                                $paymentAmount,

                            'payment_method' =>
                                $payment[
                                    'payment_method'
                                ] ?? 'cash',

                            'reference_number' =>
                                $payment[
                                    'reference_number'
                                ] ?? null,

                            'notes' =>
                                $payment[
                                    'notes'
                                ] ?? 'POS checkout',
                        ]
                    );
        }

        $this->shiftService->recalculate(
            $companyId,
            (int) $shift->id()
        );

        return [
            'sale' =>
                $this->saleService->find(
                    $companyId,
                    $saleId
                ),

            'payment' =>
                $paymentRecord,

            'payment_summary' =>
                $this->paymentService
                    ->summary(
                        $companyId,
                        $saleId
                    ),
        ];
    }

    /**
     * Receipt data for a completed POS sale.
     *
     * @return array<string, mixed>
     */
    public function receipt(
        int $companyId,
        int $saleId
    ): array {
        $detail =
            $this->saleService->detail(
                $companyId,
                $saleId
            );

        $sale =
            $detail['sale'];

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
                'Receipt is available only for completed sales.'
            );
        }

        return [
            'sale' =>
                $sale,

            'items' =>
                $detail['items'],

            'payments' =>
                $this->paymentService
                    ->bySale(
                        $companyId,
                        $saleId
                    ),

            'payment_summary' =>
                $this->paymentService
                    ->summary(
                        $companyId,
                        $saleId
                    ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentMethodOptions(): array
    {
        return $this->paymentService
            ->paymentMethodOptions();
    }

    /**
     * Validate available stock while building cart.
     *
     * Final stock validation still happens again inside
     * SaleService::complete() under row locks.
     */
    private function assertStockForCart(
        int $companyId,
        Sale $sale,
        Product $product,
        float $quantityToAdd,
        ?int $ignoreItemId = null
    ): void {
        if (
            !$product->isStockProduct()
            || !$product->tracksStock()
            || $product->allowsNegativeStock()
        ) {
            return;
        }

        $stock =
            $this->stockRepository->find(
                $companyId,
                $sale->warehouseId(),
                (int) $product->id()
            );

        $available =
            $stock instanceof WarehouseStock
                ? $stock->availableQuantity()
                : 0.0;

        $alreadyInCart =
            0.0;

        foreach (
            $this->saleService->items(
                $companyId,
                (int) $sale->id()
            )
            as $item
        ) {
            if (
                !$item instanceof SaleItem
                || $item->productId()
                    !== (int) $product->id()
            ) {
                continue;
            }

            if (
                $ignoreItemId !== null
                && (int) $item->id()
                    === $ignoreItemId
            ) {
                continue;
            }

            $alreadyInCart +=
                $item->quantity();
        }

        $required =
            $alreadyInCart
            + $quantityToAdd;

        if (
            $required
            > $available + 0.00005
        ) {
            throw new ValidationException(
                'Insufficient stock for '
                . $product->name()
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
    }

    private function positiveId(
        mixed $value,
        string $field
    ): int {
        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        $id =
            (int) $value;

        if ($id <= 0) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }

        return $id;
    }

    private function number(
        mixed $value
    ): float {
        if (
            $value === null
            || $value === ''
        ) {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                'Payment amount must be numeric.'
            );
        }

        $number =
            round(
                (float) $value,
                4
            );

        if ($number < 0) {
            throw new ValidationException(
                'Payment amount must not be negative.'
            );
        }

        return $number;
    }
}