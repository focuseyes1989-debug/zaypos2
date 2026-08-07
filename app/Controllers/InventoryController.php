<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;
use App\Repositories\WarehouseRepository;
use App\Services\InventoryService;
use Throwable;

final class InventoryController extends BaseAdminController
{
    private InventoryService $service;

    private ProductRepository $productRepository;

    private WarehouseRepository $warehouseRepository;

    public function __construct()
    {
        $this->service = new InventoryService();

        $this->productRepository =
            new ProductRepository();

        $this->warehouseRepository =
            new WarehouseRepository();
    }

    /**
     * Current warehouse stock balances.
     */
    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.view'
        );

        $companyId = (int) $currentUser['company_id'];

        try {
            $result = $this->service->paginateStock(
                $companyId,
                [
                    'warehouse_id' =>
                        $_GET['warehouse_id'] ?? null,

                    'product_id' =>
                        $_GET['product_id'] ?? null,

                    'page' =>
                        $_GET['page'] ?? 1,

                    'per_page' => 20,
                ]
            );

            View::render(
                'inventory.index',
                [
                    'currentUser' => $currentUser,

                    'stocks' => $result['items'],

                    'total' => $result['total'],

                    'page' => $result['page'],

                    'perPage' =>
                        $result['per_page'],

                    'lastPage' =>
                        $result['last_page'],

                    'warehouseOptions' =>
                        $this->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'productOptions' =>
                        $this->productRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'filters' => [
                        'warehouse_id' =>
                            $this->nullableInteger(
                                $_GET['warehouse_id']
                                    ?? null
                            ),

                        'product_id' =>
                            $this->nullableInteger(
                                $_GET['product_id']
                                    ?? null
                            ),
                    ],

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (ValidationException $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/inventory');
        }
    }

    /**
     * Stock movement ledger/history.
     */
    public function movements(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.movements.view'
        );

        $companyId = (int) $currentUser['company_id'];

        try {
            $result =
                $this->service->paginateMovements(
                    $companyId,
                    [
                        'warehouse_id' =>
                            $_GET['warehouse_id']
                                ?? null,

                        'product_id' =>
                            $_GET['product_id']
                                ?? null,

                        'movement_type' =>
                            $_GET['movement_type']
                                ?? '',

                        'search' =>
                            $_GET['search']
                                ?? '',

                        'page' =>
                            $_GET['page']
                                ?? 1,

                        'per_page' => 50,
                    ]
                );

            View::render(
                'inventory.movements',
                [
                    'currentUser' => $currentUser,

                    'movements' =>
                        $result['items'],

                    'total' =>
                        $result['total'],

                    'page' =>
                        $result['page'],

                    'perPage' =>
                        $result['per_page'],

                    'lastPage' =>
                        $result['last_page'],

                    'warehouseOptions' =>
                        $this->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'productOptions' =>
                        $this->productRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'movementTypes' =>
                        $this->service
                            ->movementTypes(),

                    'filters' => [
                        'warehouse_id' =>
                            $this->nullableInteger(
                                $_GET['warehouse_id']
                                    ?? null
                            ),

                        'product_id' =>
                            $this->nullableInteger(
                                $_GET['product_id']
                                    ?? null
                            ),

                        'movement_type' =>
                            trim(
                                (string) (
                                    $_GET['movement_type']
                                    ?? ''
                                )
                            ),

                        'search' =>
                            trim(
                                (string) (
                                    $_GET['search']
                                    ?? ''
                                )
                            ),
                    ],

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (ValidationException $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/inventory/movements');
        }
    }

    /**
     * Opening stock form.
     */
    public function opening(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.adjust'
        );

        $companyId = (int) $currentUser['company_id'];

        View::render(
            'inventory.opening',
            [
                'currentUser' => $currentUser,

                'warehouseOptions' =>
                    $this->warehouseRepository
                        ->activeOptions(
                            $companyId
                        ),

                'productOptions' =>
                    $this->stockProductOptions(
                        $companyId
                    ),

                'input' =>
                    $_SESSION['_old']
                    ?? [
                        'warehouse_id' => '',
                        'product_id' => '',
                        'quantity' => '',
                        'unit_cost' => 0,
                        'notes' => '',
                    ],

                'error' =>
                    flash('error'),
            ]
        );

        unset($_SESSION['_old']);
    }

    /**
     * Save opening stock.
     */
    public function storeOpening(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.adjust'
        );

        $this->verifyCsrf();

        try {
            $movement =
                $this->service->openingStock(
                    (int) $currentUser['company_id'],

                    (int) $currentUser['id'],

                    $this->requiredInteger(
                        $_POST['warehouse_id']
                            ?? null,
                        'Warehouse'
                    ),

                    $this->requiredInteger(
                        $_POST['product_id']
                            ?? null,
                        'Product'
                    ),

                    $_POST['quantity']
                        ?? null,

                    $_POST['unit_cost']
                        ?? 0,

                    isset($_POST['notes'])
                        ? (string) $_POST['notes']
                        : null
                );

            flash(
                'success',
                'Opening stock created successfully.'
            );

            redirect(
                '/inventory?warehouse_id='
                . $movement->warehouseId()
                . '&product_id='
                . $movement->productId()
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/inventory/opening');
        }
    }

    /**
     * Manual stock adjustment form.
     */
    public function adjustment(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.adjust'
        );

        $companyId = (int) $currentUser['company_id'];

        View::render(
            'inventory.adjustment',
            [
                'currentUser' => $currentUser,

                'warehouseOptions' =>
                    $this->warehouseRepository
                        ->activeOptions(
                            $companyId
                        ),

                'productOptions' =>
                    $this->stockProductOptions(
                        $companyId
                    ),

                'input' =>
                    $_SESSION['_old']
                    ?? [
                        'warehouse_id' => '',
                        'product_id' => '',
                        'direction' => 'in',
                        'quantity' => '',
                        'unit_cost' => 0,
                        'reference_number' => '',
                        'notes' => '',
                    ],

                'error' =>
                    flash('error'),
            ]
        );

        unset($_SESSION['_old']);
    }

    /**
     * Save manual adjustment.
     */
    public function storeAdjustment(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.adjust'
        );

        $this->verifyCsrf();

        try {
            $movement =
                $this->service->adjust(
                    (int) $currentUser['company_id'],

                    (int) $currentUser['id'],

                    $this->requiredInteger(
                        $_POST['warehouse_id']
                            ?? null,
                        'Warehouse'
                    ),

                    $this->requiredInteger(
                        $_POST['product_id']
                            ?? null,
                        'Product'
                    ),

                    (string) (
                        $_POST['direction']
                        ?? ''
                    ),

                    $_POST['quantity']
                        ?? null,

                    $_POST['unit_cost']
                        ?? 0,

                    isset(
                        $_POST['reference_number']
                    )
                        ? (string) $_POST[
                            'reference_number'
                        ]
                        : null,

                    isset($_POST['notes'])
                        ? (string) $_POST['notes']
                        : null
                );

            flash(
                'success',
                'Stock adjustment saved successfully.'
            );

            redirect(
                '/inventory?warehouse_id='
                . $movement->warehouseId()
                . '&product_id='
                . $movement->productId()
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/inventory/adjustment');
        }
    }

    /**
     * Warehouse transfer form.
     */
    public function transfer(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.transfer'
        );

        $companyId = (int) $currentUser['company_id'];

        View::render(
            'inventory.transfer',
            [
                'currentUser' => $currentUser,

                'warehouseOptions' =>
                    $this->warehouseRepository
                        ->activeOptions(
                            $companyId
                        ),

                'productOptions' =>
                    $this->stockProductOptions(
                        $companyId
                    ),

                'input' =>
                    $_SESSION['_old']
                    ?? [
                        'from_warehouse_id' => '',
                        'to_warehouse_id' => '',
                        'product_id' => '',
                        'quantity' => '',
                        'reference_number' => '',
                        'notes' => '',
                    ],

                'error' =>
                    flash('error'),
            ]
        );

        unset($_SESSION['_old']);
    }

    /**
     * Execute warehouse transfer.
     */
    public function storeTransfer(): void
    {
        $currentUser = $this->requirePermission(
            'inventory.transfer'
        );

        $this->verifyCsrf();

        try {
            $result =
                $this->service->transfer(
                    (int) $currentUser['company_id'],

                    (int) $currentUser['id'],

                    $this->requiredInteger(
                        $_POST['from_warehouse_id']
                            ?? null,
                        'Source warehouse'
                    ),

                    $this->requiredInteger(
                        $_POST['to_warehouse_id']
                            ?? null,
                        'Destination warehouse'
                    ),

                    $this->requiredInteger(
                        $_POST['product_id']
                            ?? null,
                        'Product'
                    ),

                    $_POST['quantity']
                        ?? null,

                    isset(
                        $_POST['reference_number']
                    )
                        ? (string) $_POST[
                            'reference_number'
                        ]
                        : null,

                    isset($_POST['notes'])
                        ? (string) $_POST['notes']
                        : null
                );

            flash(
                'success',
                'Stock transferred successfully.'
            );

            redirect(
                '/inventory?warehouse_id='
                . $result['in']->warehouseId()
                . '&product_id='
                . $result['in']->productId()
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/inventory/transfer');
        }
    }

    /**
     * Only stock-tracked products should appear in
     * inventory operation forms.
     *
     * @return list<array<string, mixed>>
     */
    private function stockProductOptions(
        int $companyId
    ): array {
        $options =
            $this->productRepository
                ->activeOptions(
                    $companyId
                );

        return array_values(
            array_filter(
                $options,
                static function (
                    array $product
                ): bool {
                    return (
                        ($product['product_type'] ?? '')
                            === 'stock'
                        && !empty(
                            $product['track_stock']
                        )
                    );
                }
            )
        );
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

        $integer = (int) $value;

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
}