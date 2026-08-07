<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\Purchase;
use App\Repositories\PurchaseRepository;
use App\Repositories\SupplierRepository;
use App\Services\PurchasePaymentService;
use Throwable;

final class PurchasePaymentController extends BaseAdminController
{
    private PurchasePaymentService $service;

    private PurchaseRepository $purchaseRepository;

    private SupplierRepository $supplierRepository;

    public function __construct()
    {
        $this->service =
            new PurchasePaymentService();

        $this->purchaseRepository =
            new PurchaseRepository();

        $this->supplierRepository =
            new SupplierRepository();
    }

    /**
     * Purchase payment list.
     */
    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'purchase_payments.view'
        );

        $companyId =
            (int) $currentUser['company_id'];

        try {
            $result = $this->service->paginate(
                $companyId,
                [
                    'search' =>
                        $_GET['search'] ?? '',

                    'payment_method' =>
                        $_GET['payment_method'] ?? '',

                    'purchase_id' =>
                        $_GET['purchase_id'] ?? null,

                    'supplier_id' =>
                        $_GET['supplier_id'] ?? null,

                    'deleted' =>
                        $_GET['deleted'] ?? false,

                    'page' =>
                        $_GET['page'] ?? 1,

                    'per_page' => 20,
                ]
            );

            View::render(
                'purchase_payments.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'payments' =>
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

                        'payment_method' =>
                            trim(
                                (string) (
                                    $_GET[
                                        'payment_method'
                                    ]
                                    ?? ''
                                )
                            ),

                        'purchase_id' =>
                            $this->nullableInteger(
                                $_GET['purchase_id']
                                    ?? null
                            ),

                        'supplier_id' =>
                            $this->nullableInteger(
                                $_GET['supplier_id']
                                    ?? null
                            ),

                        'deleted' =>
                            isset(
                                $_GET['deleted']
                            )
                            && $_GET['deleted']
                                === '1',
                    ],

                    'paymentMethodOptions' =>
                        $this->service
                            ->paymentMethodOptions(),

                    'supplierOptions' =>
                        $this->supplierRepository
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

            redirect(
                '/purchase-payments'
            );
        }
    }

    /**
     * Create payment form.
     */
    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'purchase_payments.create'
        );

        $companyId =
            (int) $currentUser['company_id'];

        $purchaseId = $this->requiredInteger(
            $_GET['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $purchase =
                $this->purchaseRepository->find(
                    $companyId,
                    $purchaseId
                );

            if (!$purchase instanceof Purchase) {
                throw new ValidationException(
                    'Purchase not found.'
                );
            }

            if (!$purchase->isReceived()) {
                throw new ValidationException(
                    'Purchase must be received before recording a payment.'
                );
            }

            if ($purchase->isCancelled()) {
                throw new ValidationException(
                    'Cancelled purchase cannot receive payments.'
                );
            }

            $summary = $this->service->summary(
                $companyId,
                $purchaseId
            );

            View::render(
                'purchase_payments.form',
                [
                    'currentUser' =>
                        $currentUser,

                    'purchase' =>
                        $purchase,

                    'summary' =>
                        $summary,

                    'paymentMethodOptions' =>
                        $this->service
                            ->paymentMethodOptions(),

                    'input' =>
                        $_SESSION['_old']
                        ?? [
                            'payment_number' => '',
                            'payment_date' =>
                                date('Y-m-d'),
                            'amount' =>
                                $summary[
                                    'balance_due'
                                ],
                            'payment_method' =>
                                'cash',
                            'reference_number' =>
                                '',
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
     * Record payment.
     */
    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'purchase_payments.create'
        );

        $this->verifyCsrf();

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $payment = $this->service->create(
                (int) $currentUser['company_id'],
                $purchaseId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Purchase payment recorded successfully.'
            );

            redirect(
                '/purchases/show?id='
                . $payment->purchaseId()
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
                '/purchase-payments/create?purchase_id='
                . $purchaseId
            );
        }
    }

    /**
     * Soft-delete payment.
     */
    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'purchase_payments.delete'
        );

        $this->verifyCsrf();

        $paymentId = $this->requiredInteger(
            $_POST['payment_id'] ?? null,
            'Payment'
        );

        $purchaseId = $this->requiredInteger(
            $_POST['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $paymentId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Purchase payment deleted successfully.'
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
     * Restore deleted payment.
     */
    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'purchase_payments.restore'
        );

        $this->verifyCsrf();

        $paymentId = $this->requiredInteger(
            $_POST['payment_id'] ?? null,
            'Payment'
        );

        try {
            $payment =
                $this->service->restore(
                    (int) $currentUser['company_id'],
                    $paymentId,
                    (int) $currentUser['id']
                );

            flash(
                'success',
                'Purchase payment restored successfully.'
            );

            redirect(
                '/purchases/show?id='
                . $payment->purchaseId()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/purchase-payments?deleted=1'
            );
        }
    }

    /**
     * Purchase-specific payment history.
     */
    public function history(): void
    {
        $currentUser = $this->requirePermission(
            'purchase_payments.view'
        );

        $companyId =
            (int) $currentUser['company_id'];

        $purchaseId = $this->requiredInteger(
            $_GET['purchase_id'] ?? null,
            'Purchase'
        );

        try {
            $purchase =
                $this->purchaseRepository->find(
                    $companyId,
                    $purchaseId
                );

            if (!$purchase instanceof Purchase) {
                throw new ValidationException(
                    'Purchase not found.'
                );
            }

            $payments =
                $this->service->byPurchase(
                    $companyId,
                    $purchaseId,
                    true
                );

            $summary =
                $this->service->summary(
                    $companyId,
                    $purchaseId
                );

            View::render(
                'purchase_payments.history',
                [
                    'currentUser' =>
                        $currentUser,

                    'purchase' =>
                        $purchase,

                    'payments' =>
                        $payments,

                    'summary' =>
                        $summary,

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

            redirect(
                '/purchases'
            );
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
}