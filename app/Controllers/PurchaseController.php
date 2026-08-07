<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Repositories\WarehouseRepository;
use App\Services\PurchaseService;
use Throwable;

final class PurchaseController extends BaseAdminController
{
    private PurchaseService $service;

    private SupplierRepository $supplierRepository;

    private WarehouseRepository $warehouseRepository;

    private ProductRepository $productRepository;

    private UnitRepository $unitRepository;

    private TaxRepository $taxRepository;

    public function __construct()
    {
        $this->service = new PurchaseService();

        $this->supplierRepository =
            new SupplierRepository();

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
     * Purchase list.
     */
    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.view'
        );

        $companyId = (int) $currentUser['company_id'];

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

                    'supplier_id' =>
                        $_GET['supplier_id'] ?? null,

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
                'purchases.index',
                [
                    'currentUser' => $currentUser,

                    'purchases' => $result['items'],

                    'total' => $result['total'],

                    'page' => $result['page'],

                    'perPage' =>
                        $result['per_page'],

                    'lastPage' =>
                        $result['last_page'],

                    'filters' => [
                        'search' => trim(
                            (string) (
                                $_GET['search'] ?? ''
                            )
                        ),

                        'status' => trim(
                            (string) (
                                $_GET['status'] ?? ''
                            )
                        ),

                        'payment_status' => trim(
                            (string) (
                                $_GET['payment_status']
                                ?? ''
                            )
                        ),

                        'supplier_id' =>
                            $this->nullableInteger(
                                $_GET['supplier_id']
                                    ?? null
                            ),

                        'warehouse_id' =>
                            $this->nullableInteger(
                                $_GET['warehouse_id']
                                    ?? null
                            ),

                        'deleted' =>
                            isset($_GET['deleted'])
                            && $_GET['deleted'] === '1',
                    ],

                    'supplierOptions' =>
                        $this->supplierRepository
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

            redirect('/purchases');
        }
    }

    /**
     * Purchase detail.
     */
    public function show(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.view'
        );

        $companyId = (int) $currentUser['company_id'];

        $purchaseId = $this->requiredInteger(
            $_GET['id'] ?? null,
            'Purchase'
        );

        try {
            $detail = $this->service->detail(
                $companyId,
                $purchaseId,
                true
            );

            View::render(
                'purchases.show',
                [
                    'currentUser' => $currentUser,

                    'purchase' =>
                        $detail['purchase'],

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

            redirect('/purchases');
        }
    }

    /**
     * New purchase form.
     */
    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.create'
        );

        $companyId = (int) $currentUser['company_id'];

        View::render(
            'purchases.form',
            [
                'currentUser' => $currentUser,

                'purchase' => null,

                'supplierOptions' =>
                    $this->supplierRepository
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
                        'purchase_number' => '',
                        'supplier_id' => '',
                        'warehouse_id' => '',
                        'supplier_invoice_number' => '',
                        'purchase_date' =>
                            date('Y-m-d'),
                        'due_date' => '',
                        'shipping_amount' => 0,
                        'other_amount' => 0,
                        'paid_amount' => 0,
                        'notes' => '',
                    ],

                'error' =>
                    flash('error'),
            ]
        );

        unset($_SESSION['_old']);
    }

    /**
     * Create draft purchase.
     */
    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.create'
        );

        $this->verifyCsrf();

        try {
            $purchase = $this->service->create(
                (int) $currentUser['company_id'],
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Purchase created successfully.'
            );

            redirect(
                '/purchases/edit?id='
                . $purchase->id()
            );
        } catch (Throwable $exception) {
            $this->rememberOld($_POST);

            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/purchases/create');
        }
    }

    /**
     * Edit draft purchase and items.
     */
    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.update'
        );

        $companyId = (int) $currentUser['company_id'];

        $purchaseId = $this->requiredInteger(
            $_GET['id'] ?? null,
            'Purchase'
        );

        try {
            $detail = $this->service->detail(
                $companyId,
                $purchaseId
            );

            if (!$detail['purchase']->isDraft()) {
                throw new ValidationException(
                    'Only draft purchases can be edited.'
                );
            }

            View::render(
                'purchases.edit',
                [
                    'currentUser' => $currentUser,

                    'purchase' =>
                        $detail['purchase'],

                    'items' =>
                        $detail['items'],

                    'supplierOptions' =>
                        $this->supplierRepository
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

            unset($_SESSION['_old']);
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/purchases');
        }
    }

    /**
     * Update draft purchase header.
     */
    public function update(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.update'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Purchase updated successfully.'
            );

            redirect(
                '/purchases/edit?id='
                . $purchaseId
            );
        } catch (Throwable $exception) {
            $this->rememberOld($_POST);

            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/purchases/edit?id='
                . $purchaseId
            );
        }
    }

    /**
     * Add purchase item.
     */
    public function addItem(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.update'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->addItem(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Purchase item added successfully.'
            );
        } catch (Throwable $exception) {
            $this->rememberOld($_POST);

            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/purchases/edit?id='
            . $purchaseId
        );
    }

    /**
     * Update purchase item.
     */
    public function updateItem(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.update'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        $itemId = $this->requiredInteger(
            $_POST['item_id'] ?? null,
            'Purchase item'
        );

        try {
            $this->service->updateItem(
                (int) $currentUser['company_id'],
                $purchaseId,
                $itemId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Purchase item updated successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/purchases/edit?id='
            . $purchaseId
        );
    }

    /**
     * Delete purchase item.
     */
    public function deleteItem(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.update'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        $itemId = $this->requiredInteger(
            $_POST['item_id'] ?? null,
            'Purchase item'
        );

        try {
            $this->service->deleteItem(
                (int) $currentUser['company_id'],
                $purchaseId,
                $itemId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Purchase item deleted successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/purchases/edit?id='
            . $purchaseId
        );
    }

    /**
     * Receive purchase into inventory.
     */
    public function receive(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.receive'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->receive(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Purchase received and inventory updated successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/purchases/show?id='
            . $purchaseId
        );
    }

    /**
     * Cancel draft purchase.
     */
    public function cancel(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.cancel'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->cancel(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id'],
                isset($_POST['reason'])
                    ? (string) $_POST['reason']
                    : null
            );

            flash(
                'success',
                'Purchase cancelled successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/purchases/show?id='
            . $purchaseId
        );
    }

    /**
     * Soft-delete draft purchase.
     */
    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.delete'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Purchase deleted successfully.'
            );

            redirect('/purchases');
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/purchases/show?id='
                . $purchaseId
            );
        }
    }

    /**
     * Restore deleted purchase.
     */
    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'purchases.restore'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Purchase restored successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect('/purchases?deleted=1');
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
        $integer = $this->nullableInteger(
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