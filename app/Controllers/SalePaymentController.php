<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\Sale;
use App\Repositories\CustomerRepository;
use App\Repositories\SaleRepository;
use App\Services\SalePaymentService;
use Throwable;

final class SalePaymentController extends BaseAdminController
{
    private SalePaymentService $service;

    private SaleRepository $saleRepository;

    private CustomerRepository $customerRepository;

    public function __construct()
    {
        $this->service =
            new SalePaymentService();

        $this->saleRepository =
            new SaleRepository();

        $this->customerRepository =
            new CustomerRepository();
    }

    /**
     * Sale payment list.
     */
    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'sale_payments.view'
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

                    'sale_id' =>
                        $_GET['sale_id'] ?? null,

                    'customer_id' =>
                        $_GET['customer_id'] ?? null,

                    'deleted' =>
                        $_GET['deleted'] ?? false,

                    'page' =>
                        $_GET['page'] ?? 1,

                    'per_page' => 20,
                ]
            );

            View::render(
                'sale_payments.index',
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

                        'sale_id' =>
                            $this->nullableInteger(
                                $_GET['sale_id']
                                    ?? null
                            ),

                        'customer_id' =>
                            $this->nullableInteger(
                                $_GET['customer_id']
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

                    'customerOptions' =>
                        $this->customerRepository
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
                '/sale-payments'
            );
        }
    }

    /**
     * Create payment form.
     */
    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'sale_payments.create'
        );

        $companyId =
            (int) $currentUser['company_id'];

        $saleId = $this->requiredInteger(
            $_GET['sale_id'] ?? null,
            'Sale'
        );

        try {
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

            if ($sale->status() !== 'completed') {
                throw new ValidationException(
                    'Sale must be completed before recording a payment.'
                );
            }

            if ($sale->isCancelled()) {
                throw new ValidationException(
                    'Cancelled sale cannot receive payments.'
                );
            }

            if ($sale->isDeleted()) {
                throw new ValidationException(
                    'Deleted sale cannot receive payments.'
                );
            }

            $summary = $this->service->summary(
                $companyId,
                $saleId
            );

            if (
                (float) $summary['balance_due']
                <= 0.00005
            ) {
                throw new ValidationException(
                    'Sale is already fully paid.'
                );
            }

            View::render(
                'sale_payments.form',
                [
                    'currentUser' =>
                        $currentUser,

                    'sale' =>
                        $sale,

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
                '/sales/show?id='
                . $saleId
            );
        }
    }

    /**
     * Record payment.
     */
    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'sale_payments.create'
        );

        $this->verifyCsrf();

        $saleId = $this->requiredInteger(
            $_POST['sale_id'] ?? null,
            'Sale'
        );

        try {
            $payment = $this->service->create(
                (int) $currentUser['company_id'],
                $saleId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Sale payment recorded successfully.'
            );

            redirect(
                '/sales/show?id='
                . $payment->saleId()
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
                '/sale-payments/create?sale_id='
                . $saleId
            );
        }
    }

    /**
     * Soft-delete payment.
     */
    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'sale_payments.delete'
        );

        $this->verifyCsrf();

        $paymentId = $this->requiredInteger(
            $_POST['payment_id'] ?? null,
            'Payment'
        );

        $saleId = $this->requiredInteger(
            $_POST['sale_id'] ?? null,
            'Sale'
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $paymentId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Sale payment deleted successfully.'
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
     * Restore deleted payment.
     */
    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'sale_payments.restore'
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
                'Sale payment restored successfully.'
            );

            redirect(
                '/sales/show?id='
                . $payment->saleId()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-payments?deleted=1'
            );
        }
    }

    /**
     * Sale-specific payment history.
     */
    public function history(): void
    {
        $currentUser = $this->requirePermission(
            'sale_payments.view'
        );

        $companyId =
            (int) $currentUser['company_id'];

        $saleId = $this->requiredInteger(
            $_GET['sale_id'] ?? null,
            'Sale'
        );

        try {
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

            $payments =
                $this->service->bySale(
                    $companyId,
                    $saleId,
                    true
                );

            $summary =
                $this->service->summary(
                    $companyId,
                    $saleId
                );

            View::render(
                'sale_payments.history',
                [
                    'currentUser' =>
                        $currentUser,

                    'sale' =>
                        $sale,

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
                '/sales'
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