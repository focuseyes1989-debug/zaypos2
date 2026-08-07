<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Repositories\WarehouseRepository;
use App\Services\SaleService;
use Throwable;

final class SaleController extends BaseAdminController
{
    private SaleService $service;

    private CustomerRepository $customerRepository;

    private WarehouseRepository $warehouseRepository;

    private ProductRepository $productRepository;

    private UnitRepository $unitRepository;

    private TaxRepository $taxRepository;

    public function __construct()
    {
        $this->service = new SaleService();

        $this->customerRepository =
            new CustomerRepository();

        $this->warehouseRepository =
            new WarehouseRepository();

        $this->productRepository =
            new ProductRepository();

        $this->unitRepository =
            new UnitRepository();

        $this->taxRepository =
            new TaxRepository();
    }

    /**
     * Sale list.
     */
    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'sales.view'
        );

        $companyId =
            (int) $currentUser['company_id'];

        try {
            $result = $this->service->paginate(
                $companyId,
                [
                    'search' =>
                        $_GET['search'] ?? '',

                    'status' =>
                        $_GET['status'] ?? '',

                    'payment_status' =>
                        $_GET['payment_status'] ?? '',

                    'customer_id' =>
                        $_GET['customer_id'] ?? null,

                    'warehouse_id' =>
                        $_GET['warehouse_id'] ?? null,

                    'deleted' =>
                        $_GET['deleted'] ?? false,

                    'page' =>
                        $_GET['page'] ?? 1,

                    'per_page' => 20,
                ]
            );

            View::render(
                'sales.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'sales' =>
                        $result['items'],

                    'total' =>
                        $result['total'],

                    'page' =>
                        $result['page'],

                    'perPage' =>
                        $result['per_page'],

                    'lastPage' =>
                        $result['last_page'],

                    'filters' => [
                        'search' =>
                            trim(
                                (string) (
                                    $_GET['search']
                                    ?? ''
                                )
                            ),

                        'status' =>
                            trim(
                                (string) (
                                    $_GET['status']
                                    ?? ''
                                )
                            ),

                        'payment_status' =>
                            trim(
                                (string) (
                                    $_GET[
                                        'payment_status'
                                    ]
                                    ?? ''
                                )
                            ),

                        'customer_id' =>
                            $this->nullableInteger(
                                $_GET[
                                    'customer_id'
                                ]
                                ?? null
                            ),

                        'warehouse_id' =>
                            $this->nullableInteger(
                                $_GET[
                                    'warehouse_id'
                                ]
                                ?? null
                            ),

                        'deleted' =>
                            isset(
                                $_GET['deleted']
                            )
                            && $_GET['deleted']
                                === '1',
                    ],

                    'customerOptions' =>
                        $this->customerRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'warehouseOptions' =>
                        $this->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

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

            redirect('/sales');
        }
    }

    /**
     * Sale detail.
     */
    public function show(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.view'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $saleId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'Sale'
            );

        try {
            $detail =
                $this->service->detail(
                    $companyId,
                    $saleId,
                    true
                );

            View::render(
                'sales.show',
                [
                    'currentUser' =>
                        $currentUser,

                    'sale' =>
                        $detail['sale'],

                    'items' =>
                        $detail['items'],

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

            redirect('/sales');
        }
    }

    /**
     * New sale form.
     */
    public function create(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.create'
            );

        $companyId =
            (int) $currentUser['company_id'];

        View::render(
            'sales.form',
            [
                'currentUser' =>
                    $currentUser,

                'sale' => null,

                'customerOptions' =>
                    $this->customerRepository
                        ->activeOptions(
                            $companyId
                        ),

                'warehouseOptions' =>
                    $this->warehouseRepository
                        ->activeOptions(
                            $companyId
                        ),

                'input' =>
                    $_SESSION['_old']
                    ?? [
                        'sale_number' =>
                            '',

                        'customer_id' =>
                            '',

                        'warehouse_id' =>
                            '',

                        'customer_reference' =>
                            '',

                        'sale_date' =>
                            date('Y-m-d'),

                        'due_date' =>
                            '',

                        'shipping_amount' =>
                            0,

                        'other_amount' =>
                            0,

                        'paid_amount' =>
                            0,

                        'notes' =>
                            '',
                    ],

                'error' =>
                    flash('error'),
            ]
        );

        unset(
            $_SESSION['_old']
        );
    }

    /**
     * Create draft sale.
     */
    public function store(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.create'
            );

        $this->verifyCsrf();

        try {
            $sale =
                $this->service->create(
                    (int) $currentUser[
                        'company_id'
                    ],
                    (int) $currentUser['id'],
                    $_POST
                );

            flash(
                'success',
                'Sale created successfully.'
            );

            redirect(
                '/sales/edit?id='
                . $sale->id()
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sales/create'
            );
        }
    }

    /**
     * Edit draft sale and items.
     */
    public function edit(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.update'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'Sale'
            );

        try {
            $detail =
                $this->service->detail(
                    $companyId,
                    $saleId
                );

            if (
                !$detail['sale']
                    ->isDraft()
            ) {
                throw new ValidationException(
                    'Only draft sales can be edited.'
                );
            }

            View::render(
                'sales.edit',
                [
                    'currentUser' =>
                        $currentUser,

                    'sale' =>
                        $detail['sale'],

                    'items' =>
                        $detail['items'],

                    'customerOptions' =>
                        $this->customerRepository
                            ->activeOptions(
                                $companyId
                            ),

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

                    'unitOptions' =>
                        $this->unitRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'taxOptions' =>
                        $this->taxRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'input' =>
                        $_SESSION['_old']
                        ?? [],

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );

            unset(
                $_SESSION['_old']
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/sales');
        }
    }

    /**
     * Update draft sale header.
     */
    public function update(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.update'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $this->service->update(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Sale updated successfully.'
            );

            redirect(
                '/sales/edit?id='
                . $saleId
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sales/edit?id='
                . $saleId
            );
        }
    }

    /**
     * Add sale item.
     */
    public function addItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.update'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $this->service->addItem(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Sale item added successfully.'
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sales/edit?id='
            . $saleId
        );
    }

    /**
     * Update sale item.
     */
    public function updateItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.update'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        $itemId =
            $this->requiredInteger(
                $_POST['item_id']
                    ?? null,
                'Sale item'
            );

        try {
            $this->service->updateItem(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                $itemId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Sale item updated successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sales/edit?id='
            . $saleId
        );
    }

    /**
     * Delete sale item.
     */
    public function deleteItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.update'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        $itemId =
            $this->requiredInteger(
                $_POST['item_id']
                    ?? null,
                'Sale item'
            );

        try {
            $this->service->deleteItem(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                $itemId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Sale item deleted successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sales/edit?id='
            . $saleId
        );
    }

    /**
     * Complete sale and deduct inventory.
     */
    public function complete(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.complete'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $this->service->complete(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Sale completed and inventory updated successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sales/show?id='
            . $saleId
        );
    }

    /**
     * Cancel draft sale.
     */
    public function cancel(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.cancel'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $this->service->cancel(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id'],
                isset($_POST['reason'])
                    ? (string) $_POST['reason']
                    : null
            );

            flash(
                'success',
                'Sale cancelled successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sales/show?id='
            . $saleId
        );
    }

    /**
     * Soft-delete draft sale.
     */
    public function delete(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.delete'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $this->service->delete(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Sale deleted successfully.'
            );

            redirect('/sales');
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sales/show?id='
                . $saleId
            );
        }
    }

    /**
     * Restore deleted sale.
     */
    public function restore(): void
    {
        $currentUser =
            $this->requirePermission(
                'sales.restore'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $this->service->restore(
                (int) $currentUser[
                    'company_id'
                ],
                $saleId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Sale restored successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sales?deleted=1'
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