<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Models\Sale;
use App\Repositories\CustomerRepository;
use App\Repositories\WarehouseRepository;
use App\Services\PosCheckoutService;
use App\Services\ProductService;
use Throwable;

final class PosController extends BaseAdminController
{
    private PosCheckoutService $service;

    private ProductService $productService;

    private CustomerRepository $customerRepository;

    private WarehouseRepository $warehouseRepository;

    public function __construct()
    {
        $this->service =
            new PosCheckoutService();

        $this->productService =
            new ProductService();

        $this->customerRepository =
            new CustomerRepository();

        $this->warehouseRepository =
            new WarehouseRepository();
    }

    /**
     * Main POS checkout screen.
     */
    public function index(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.access'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $saleId =
            $this->nullableInteger(
                $_GET['sale_id']
                    ?? null
            );

        $search =
            trim(
                (string) (
                    $_GET['search']
                    ?? ''
                )
            );

        if (mb_strlen($search) > 190) {
            $search =
                mb_substr(
                    $search,
                    0,
                    190
                );
        }

        try {
            $cart =
                null;

            $selectedWarehouseId =
                $this->nullableInteger(
                    $_GET['warehouse_id']
                        ?? null
                );

            if ($saleId !== null) {
                $cart =
                    $this->service->cart(
                        $companyId,
                        $saleId
                    );

                /** @var Sale $sale */
                $sale =
                    $cart['sale'];

                /*
                 * Once checkout has completed,
                 * the cashier should see the receipt.
                 */
                if (
                    !$sale->isDraft()
                    && $sale->status()
                        === 'completed'
                ) {
                    redirect(
                        '/pos/receipt?sale_id='
                        . $sale->id()
                    );
                }

                if (!$sale->isDraft()) {
                    throw new ValidationException(
                        'Only draft sales can be opened in POS.'
                    );
                }

                $selectedWarehouseId =
                    $sale->warehouseId();
            }

            $productResult =
                $this->productService
                    ->paginate(
                        $companyId,
                        [
                            'search' =>
                                $search,

                            'status' =>
                                'active',

                            'product_type' =>
                                '',

                            'category_id' =>
                                null,

                            'brand_id' =>
                                null,

                            'deleted' =>
                                false,

                            'page' =>
                                1,

                            'per_page' =>
                                60,
                        ]
                    );

            $products =
                [];

            foreach (
                $productResult['items']
                as $product
            ) {
                if (
                    !$product
                    instanceof Product
                ) {
                    continue;
                }

                $availability =
                    null;

                if (
                    $selectedWarehouseId
                    !== null
                ) {
                    try {
                        $availability =
                            $this->service
                                ->productAvailability(
                                    $companyId,
                                    $selectedWarehouseId,
                                    $product
                                );
                    } catch (Throwable) {
                        $availability =
                            null;
                    }
                }

                $products[] = [
                    'product' =>
                        $product,

                    'availability' =>
                        $availability,
                ];
            }

            View::render(
                'pos.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'cart' =>
                        $cart,

                    'products' =>
                        $products,

                    'productTotal' =>
                        $productResult[
                            'total'
                        ],

                    'search' =>
                        $search,

                    'customerOptions' =>
                        $this
                            ->customerRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'warehouseOptions' =>
                        $this
                            ->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'selectedWarehouseId' =>
                        $selectedWarehouseId,

                    'paymentMethodOptions' =>
                        $this->service
                            ->paymentMethodOptions(),

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            /*
             * If a bad sale ID was supplied,
             * return to a clean POS screen.
             */
            if ($saleId !== null) {
                redirect('/pos');
            }

            View::render(
                'pos.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'cart' =>
                        null,

                    'products' =>
                        [],

                    'productTotal' =>
                        0,

                    'search' =>
                        $search,

                    'customerOptions' =>
                        $this
                            ->customerRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'warehouseOptions' =>
                        $this
                            ->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'selectedWarehouseId' =>
                        null,

                    'paymentMethodOptions' =>
                        $this->service
                            ->paymentMethodOptions(),

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        }
    }

    /**
     * Start a new POS draft sale.
     */
    public function start(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.checkout'
            );

        $this->verifyCsrf();

        try {
            $sale =
                $this->service
                    ->createDraft(
                        (int) $currentUser[
                            'company_id'
                        ],
                        (int) $currentUser[
                            'id'
                        ],
                        [
                            'warehouse_id' =>
                                $_POST[
                                    'warehouse_id'
                                ] ?? null,

                            'customer_id' =>
                                $_POST[
                                    'customer_id'
                                ] ?? null,

                            'sale_date' =>
                                $_POST[
                                    'sale_date'
                                ] ?? gmdate(
                                    'Y-m-d'
                                ),

                            'customer_reference' =>
                                $_POST[
                                    'customer_reference'
                                ] ?? null,

                            'notes' =>
                                'POS checkout',
                        ]
                    );

            flash(
                'success',
                'POS sale started.'
            );

            redirect(
                '/pos?sale_id='
                . $sale->id()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/pos');
        }
    }

    /**
     * Barcode scanner endpoint.
     */
    public function scan(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.checkout'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $barcode =
                trim(
                    (string) (
                        $_POST['barcode']
                        ?? ''
                    )
                );

            $quantity =
                $this->positiveNumber(
                    $_POST['quantity']
                        ?? 1,
                    'Quantity'
                );

            $this->service
                ->scanBarcode(
                    (int) $currentUser[
                        'company_id'
                    ],
                    $saleId,
                    (int) $currentUser[
                        'id'
                    ],
                    $barcode,
                    $quantity
                );

            flash(
                'success',
                'Product added to cart.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/pos?sale_id='
            . $saleId
        );
    }

    /**
     * Product-card Add button.
     */
    public function addProduct(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.checkout'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $productId =
                $this->requiredInteger(
                    $_POST['product_id']
                        ?? null,
                    'Product'
                );

            $quantity =
                $this->positiveNumber(
                    $_POST['quantity']
                        ?? 1,
                    'Quantity'
                );

            $this->service
                ->addProduct(
                    (int) $currentUser[
                        'company_id'
                    ],
                    $saleId,
                    (int) $currentUser[
                        'id'
                    ],
                    $productId,
                    $quantity
                );

            flash(
                'success',
                'Product added to cart.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/pos?sale_id='
            . $saleId
        );
    }

    /**
     * Update cart item quantity.
     */
    public function updateItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.checkout'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $itemId =
                $this->requiredInteger(
                    $_POST['item_id']
                        ?? null,
                    'Sale item'
                );

            $quantity =
                $this->positiveNumber(
                    $_POST['quantity']
                        ?? null,
                    'Quantity'
                );

            $this->service
                ->updateQuantity(
                    (int) $currentUser[
                        'company_id'
                    ],
                    $saleId,
                    $itemId,
                    (int) $currentUser[
                        'id'
                    ],
                    $quantity
                );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/pos?sale_id='
            . $saleId
        );
    }

    /**
     * Remove cart item.
     */
    public function removeItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.checkout'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $itemId =
                $this->requiredInteger(
                    $_POST['item_id']
                        ?? null,
                    'Sale item'
                );

            $this->service
                ->removeItem(
                    (int) $currentUser[
                        'company_id'
                    ],
                    $saleId,
                    $itemId,
                    (int) $currentUser[
                        'id'
                    ]
                );

            flash(
                'success',
                'Item removed from cart.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/pos?sale_id='
            . $saleId
        );
    }

    /**
     * Complete POS checkout.
     *
     * For cash transactions:
     *
     * cash_received = money tendered by customer.
     * payment amount = sale balance.
     * change = tendered - sale balance.
     *
     * This prevents recording change as a sale payment.
     */
    public function checkout(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.checkout'
            );

        $this->verifyCsrf();

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $userId =
            (int) $currentUser['id'];

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $cart =
                $this->service->cart(
                    $companyId,
                    $saleId
                );

            /** @var Sale $sale */
            $sale =
                $cart['sale'];

            if (!$sale->isDraft()) {
                throw new ValidationException(
                    'Only draft sales can be checked out.'
                );
            }

            if (
                $cart['items'] === []
            ) {
                throw new ValidationException(
                    'POS cart is empty.'
                );
            }

            $balance =
                round(
                    (float)
                        $sale->grandTotal(),
                    4
                );

            $paymentMethod =
                trim(
                    (string) (
                        $_POST[
                            'payment_method'
                        ]
                        ?? 'cash'
                    )
                );

            $paymentAmount =
                0.0;

            $change =
                0.0;

            if ($paymentMethod === 'cash') {
                $cashReceived =
                    $this->nonNegativeNumber(
                        $_POST[
                            'cash_received'
                        ] ?? 0,
                        'Cash received'
                    );

                /*
                 * Cash 0 is intentionally allowed:
                 * it produces a completed unpaid /
                 * credit transaction.
                 */
                if (
                    $cashReceived > 0.00005
                    && $cashReceived
                        + 0.00005
                        < $balance
                ) {
                    /*
                     * Partial cash payment is allowed.
                     */
                    $paymentAmount =
                        $cashReceived;
                } elseif (
                    $cashReceived
                    >= $balance
                    && $balance
                        > 0.00005
                ) {
                    $paymentAmount =
                        $balance;

                    $change =
                        round(
                            $cashReceived
                            - $balance,
                            4
                        );
                }
            } else {
                $paymentAmount =
                    $this->nonNegativeNumber(
                        $_POST[
                            'payment_amount'
                        ]
                        ?? $balance,
                        'Payment amount'
                    );

                if (
                    $paymentAmount
                    > $balance
                    + 0.00005
                ) {
                    throw new ValidationException(
                        'Payment amount must not exceed sale total.'
                    );
                }
            }

            $result =
                $this->service
                    ->checkout(
                        $companyId,
                        $saleId,
                        $userId,
                        [
                            'amount' =>
                                $paymentAmount,

                            'payment_method' =>
                                $paymentMethod,

                            'payment_date' =>
                                $_POST[
                                    'payment_date'
                                ] ?? gmdate(
                                    'Y-m-d'
                                ),

                            'reference_number' =>
                                $_POST[
                                    'reference_number'
                                ] ?? null,

                            'notes' =>
                                'POS checkout',
                        ]
                    );

            if ($change > 0.00005) {
                flash(
                    'pos_change',
                    number_format(
                        $change,
                        2,
                        '.',
                        ''
                    )
                );
            }

            flash(
                'success',
                'Sale completed successfully.'
            );

            redirect(
                '/pos/receipt?sale_id='
                . $result['sale']->id()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/pos?sale_id='
                . $saleId
            );
        }
    }

/**
 * Hold the current POS draft sale.
 */
public function hold(): void
{
    $currentUser =
        $this->requirePermission(
            'pos_hold.hold'
        );

    $this->verifyCsrf();

    $saleId =
        $this->requiredInteger(
            $_POST['sale_id']
                ?? null,
            'Sale'
        );

    try {
        $sale =
            $this->service->holdSale(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id']
            );

        flash(
            'success',
            'Sale '
            . $sale->saleNumber()
            . ' has been held.'
        );

        redirect('/pos');
    } catch (Throwable $exception) {
        flash(
            'error',
            $exception->getMessage()
        );

        redirect(
            '/pos?sale_id='
            . $saleId
        );
    }
}

/**
 * List held POS sales.
 */
public function held(): void
{
    $currentUser =
        $this->requirePermission(
            'pos_hold.view'
        );

    $companyId =
        (int) $currentUser[
            'company_id'
        ];

    $page =
        $this->nullableInteger(
            $_GET['page']
                ?? null
        )
        ?? 1;

    $mineOnly =
        filter_var(
            $_GET['mine']
                ?? false,
            FILTER_VALIDATE_BOOL
        );

    try {
        $result =
            $this->service->heldSales(
                $companyId,
                $page,
                20,
                $mineOnly
                    ? (int) $currentUser['id']
                    : null
            );

        View::render(
            'pos.held',
            [
                'currentUser' =>
                    $currentUser,

                'result' =>
                    $result,

                'items' =>
                    $result['items'],

                'mineOnly' =>
                    $mineOnly,

                'success' =>
                    flash('success'),

                'error' =>
                    flash('error'),
            ]
        );
    } catch (Throwable $exception) {
        flash(
            'error',
            $exception->getMessage()
        );

        redirect('/pos');
    }
}

/**
 * Resume one held POS sale.
 */
public function resume(): void
{
    $currentUser =
        $this->requirePermission(
            'pos_hold.resume'
        );

    $this->verifyCsrf();

    $saleId =
        $this->requiredInteger(
            $_POST['sale_id']
                ?? null,
            'Sale'
        );

    try {
        $sale =
            $this->service->resumeSale(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id']
            );

        flash(
            'success',
            'Held sale resumed.'
        );

        redirect(
            '/pos?sale_id='
            . $sale->id()
        );
    } catch (Throwable $exception) {
        flash(
            'error',
            $exception->getMessage()
        );

        redirect('/pos/held');
    }
}

/**
 * Cancel one held POS sale.
 */
public function cancelHeld(): void
{
    $currentUser =
        $this->requirePermission(
            'pos_hold.cancel'
        );

    $this->verifyCsrf();

    $saleId =
        $this->requiredInteger(
            $_POST['sale_id']
                ?? null,
            'Sale'
        );

    try {
        $sale =
            $this->service->cart(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId
            )['sale'];

        if (
            !$sale instanceof Sale
            || !$sale->isHeld()
        ) {
            throw new ValidationException(
                'Held POS sale not found.'
            );
        }

        $saleService =
            new \App\Services\SaleService();

        $saleService->cancel(
            (int) $currentUser[
                'company_id'
            ],
            $saleId,
            (int) $currentUser['id'],
            'Cancelled from held POS sales'
        );

        flash(
            'success',
            'Held sale cancelled.'
        );
    } catch (Throwable $exception) {
        flash(
            'error',
            $exception->getMessage()
        );
    }

    redirect('/pos/held');
}
    /**
     * POS receipt.
     */
    public function receipt(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos.access'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleId =
            $this->requiredInteger(
                $_GET['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $receipt =
                $this->service->receipt(
                    $companyId,
                    $saleId
                );

            View::render(
                'pos.receipt',
                [
                    'currentUser' =>
                        $currentUser,

                    'sale' =>
                        $receipt['sale'],

                    'items' =>
                        $receipt['items'],

                    'payments' =>
                        $receipt['payments'],

                    'paymentSummary' =>
                        $receipt[
                            'payment_summary'
                        ],

                    'change' =>
                        flash('pos_change'),

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/pos');
        }
    }

    private function nullableInteger(
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
            return null;
        }

        $integer =
            (int) $value;

        return $integer > 0
            ? $integer
            : null;
    }

    private function requiredInteger(
        mixed $value,
        string $field
    ): int {
        $integer =
            $this->nullableInteger(
                $value
            );

        if ($integer === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $integer;
    }

    private function positiveNumber(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be numeric."
            );
        }

        $number =
            round(
                (float) $value,
                4
            );

        if ($number <= 0.00005) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }

        return $number;
    }

    private function nonNegativeNumber(
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
                "{$field} must be numeric."
            );
        }

        $number =
            round(
                (float) $value,
                4
            );

        if ($number < 0) {
            throw new ValidationException(
                "{$field} must not be negative."
            );
        }

        return $number;
    }
}