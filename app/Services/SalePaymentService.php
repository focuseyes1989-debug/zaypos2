<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Repositories\SalePaymentRepository;
use App\Repositories\SaleRepository;
use PDO;
use Throwable;

final class SalePaymentService
{
    public function __construct(
        private readonly SalePaymentRepository $paymentRepository =
            new SalePaymentRepository(),

        private readonly SaleRepository $saleRepository =
            new SaleRepository(),

        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<SalePayment>,
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

        $paymentMethod = strtolower(
            trim(
                (string) (
                    $filters['payment_method']
                    ?? ''
                )
            )
        );

        if (
            $paymentMethod !== ''
            && !in_array(
                $paymentMethod,
                SalePayment::paymentMethods(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment method filter.'
            );
        }

        $saleId = $this->nullableId(
            $filters['sale_id'] ?? null
        );

        $customerId = $this->nullableId(
            $filters['customer_id'] ?? null
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

        return $this->paymentRepository->paginate(
            $companyId,
            $search,
            $paymentMethod,
            $saleId,
            $customerId,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $paymentId,
        bool $includeDeleted = false
    ): SalePayment {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $paymentId,
            'Payment ID'
        );

        $payment =
            $this->paymentRepository->find(
                $companyId,
                $paymentId,
                $includeDeleted
            );

        if (!$payment instanceof SalePayment) {
            throw new ValidationException(
                'Sale payment not found.'
            );
        }

        return $payment;
    }

    /**
     * @return list<SalePayment>
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

        $sale =
            $this->saleRepository->find(
                $companyId,
                $saleId,
                true
            );

        if (!$sale instanceof Sale) {
            throw new ValidationException(
                'Sale not found.'
            );
        }

        return $this->paymentRepository->bySale(
            $companyId,
            $saleId,
            $includeDeleted
        );
    }

    /**
     * Record a payment against a completed sale.
     *
     * The payment ledger insert and sale header
     * balance update are performed atomically.
     *
     * Existing sales may already contain a paid_amount
     * from before the dedicated sale payment ledger.
     * That legacy amount is preserved.
     *
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $saleId,
        int $userId,
        array $input
    ): SalePayment {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleId,
            'Sale ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $paymentNumber = trim(
            (string) (
                $input['payment_number']
                ?? ''
            )
        );

        if ($paymentNumber === '') {
            $paymentNumber =
                $this->generatePaymentNumber(
                    $companyId
                );
        }

        $this->validatePaymentNumber(
            $companyId,
            $paymentNumber
        );

        $paymentDate = $this->dateValue(
            $input['payment_date']
                ?? gmdate('Y-m-d'),
            'Payment date'
        );

        $amount = $this->positiveAmount(
            $input['amount'] ?? null
        );

        $paymentMethod =
            $this->paymentMethod(
                $input['payment_method']
                    ?? 'cash'
            );

        $referenceNumber =
            $this->nullableString(
                $input['reference_number']
                    ?? null,
                190
            );

        $notes = $this->nullableText(
            $input['notes'] ?? null,
            5000
        );

        $database = Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $sale =
                $this->saleRepository
                    ->findForUpdate(
                        $companyId,
                        $saleId
                    );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Sale not found.'
                );
            }

            $this->validateSaleForPayment(
                $sale
            );

            /*
             * Active ledger total before inserting
             * the new payment.
             */
            $existingLedgerPaid =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $saleId
                        )
                );

            /*
             * Header paid_amount can contain:
             *
             * legacy paid amount
             * +
             * current active payment ledger amount.
             *
             * Example:
             *
             * header paid = 7000
             * ledger paid = 2000
             * legacy paid = 5000
             */
            $legacyPaid =
                $this->legacyPaidBaseline(
                    $sale,
                    $existingLedgerPaid
                );

            $effectivePaid =
                $this->money(
                    $legacyPaid
                    + $existingLedgerPaid
                );

            $remainingBalance =
                $this->money(
                    max(
                        0,
                        $sale->grandTotal()
                        - $effectivePaid
                    )
                );

            if ($remainingBalance <= 0.00005) {
                throw new ValidationException(
                    'Sale is already fully paid.'
                );
            }

            if (
                $amount
                > $remainingBalance + 0.00005
            ) {
                throw new ValidationException(
                    'Payment amount must not exceed the sale balance.'
                );
            }

            $payment =
                $this->paymentRepository->create([
                    'company_id' =>
                        $companyId,

                    'sale_id' =>
                        $saleId,

                    'customer_id' =>
                        $sale->customerId(),

                    'payment_number' =>
                        $paymentNumber,

                    'payment_date' =>
                        $paymentDate,

                    'amount' =>
                        $amount,

                    'payment_method' =>
                        $paymentMethod,

                    'reference_number' =>
                        $referenceNumber,

                    'notes' =>
                        $notes,

                    'created_by' =>
                        $userId,
                ]);

            /*
             * Ledger total after payment insertion.
             */
            $newLedgerPaid =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $saleId
                        )
                );

            /*
             * Final header paid amount:
             *
             * legacy baseline
             * +
             * active ledger total.
             */
            $newPaidAmount =
                $this->money(
                    $legacyPaid
                    + $newLedgerPaid
                );

            if (
                $newPaidAmount
                > $sale->grandTotal()
                + 0.00005
            ) {
                throw new ValidationException(
                    'Sale payment total exceeds the grand total.'
                );
            }

            $this->synchronizeSalePayment(
                $sale,
                $newPaidAmount,
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
            'sale_payments.created',
            'sale_payment',
            $payment->id(),
            [
                'payment' =>
                    $payment->toArray(),

                'sale_id' =>
                    $saleId,
            ]
        );

        return $payment;
    }

    /**
     * Soft-delete a payment and recalculate
     * the sale header balance.
     */
    public function delete(
        int $companyId,
        int $paymentId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $paymentId,
            'Payment ID'
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

            $payment =
                $this->paymentRepository
                    ->findForUpdate(
                        $companyId,
                        $paymentId
                    );

            if (!$payment instanceof SalePayment) {
                throw new ValidationException(
                    'Sale payment not found.'
                );
            }

            $sale =
                $this->saleRepository
                    ->findForUpdate(
                        $companyId,
                        $payment->saleId()
                    );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Sale not found.'
                );
            }

            if ($sale->isDeleted()) {
                throw new ValidationException(
                    'Payment cannot be changed because the sale is deleted.'
                );
            }

            /*
             * Capture legacy baseline BEFORE deleting
             * the ledger record.
             */
            $activeLedgerPaidBeforeDelete =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->saleId()
                        )
                );

            $legacyPaid =
                $this->legacyPaidBaseline(
                    $sale,
                    $activeLedgerPaidBeforeDelete
                );

            if (
                !$this->paymentRepository
                    ->softDelete(
                        $companyId,
                        $paymentId,
                        $userId
                    )
            ) {
                throw new ValidationException(
                    'Sale payment could not be deleted.'
                );
            }

            /*
             * Active ledger amount after deletion.
             */
            $activeLedgerPaid =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->saleId()
                        )
                );

            $paidAmount =
                $this->money(
                    $legacyPaid
                    + $activeLedgerPaid
                );

            $this->synchronizeSalePayment(
                $sale,
                $paidAmount,
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
            'sale_payments.deleted',
            'sale_payment',
            $paymentId,
            [
                'payment' =>
                    $payment->toArray(),

                'sale_id' =>
                    $payment->saleId(),
            ]
        );
    }

    /**
     * Restore a deleted payment.
     *
     * Restoration is blocked when the restored
     * payment would overpay the sale.
     */
    public function restore(
        int $companyId,
        int $paymentId,
        int $userId
    ): SalePayment {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $paymentId,
            'Payment ID'
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

            $payment =
                $this->paymentRepository
                    ->find(
                        $companyId,
                        $paymentId,
                        true
                    );

            if (!$payment instanceof SalePayment) {
                throw new ValidationException(
                    'Sale payment not found.'
                );
            }

            if (!$payment->isDeleted()) {
                throw new ValidationException(
                    'Sale payment is not deleted.'
                );
            }

            $sale =
                $this->saleRepository
                    ->findForUpdate(
                        $companyId,
                        $payment->saleId()
                    );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Sale not found.'
                );
            }

            $this->validateSaleForPayment(
                $sale
            );

            /*
             * Current active ledger amount,
             * excluding the deleted payment.
             */
            $activeLedgerPaid =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->saleId()
                        )
                );

            /*
             * Preserve any paid amount that existed
             * outside of the sale payment ledger.
             */
            $legacyPaid =
                $this->legacyPaidBaseline(
                    $sale,
                    $activeLedgerPaid
                );

            $restoredPaidAmount =
                $this->money(
                    $legacyPaid
                    + $activeLedgerPaid
                    + $payment->amount()
                );

            if (
                $restoredPaidAmount
                > $sale->grandTotal()
                + 0.00005
            ) {
                throw new ValidationException(
                    'Payment cannot be restored because it would exceed the sale total.'
                );
            }

            if (
                !$this->paymentRepository
                    ->restore(
                        $companyId,
                        $paymentId,
                        $userId
                    )
            ) {
                throw new ValidationException(
                    'Sale payment could not be restored.'
                );
            }

            $restored =
                $this->paymentRepository
                    ->find(
                        $companyId,
                        $paymentId
                    );

            if (!$restored instanceof SalePayment) {
                throw new ValidationException(
                    'Restored payment could not be reloaded.'
                );
            }

            /*
             * Re-read ledger total after restore.
             */
            $activeLedgerPaidAfterRestore =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->saleId()
                        )
                );

            $paidAmount =
                $this->money(
                    $legacyPaid
                    + $activeLedgerPaidAfterRestore
                );

            $this->synchronizeSalePayment(
                $sale,
                $paidAmount,
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
            'sale_payments.restored',
            'sale_payment',
            $paymentId,
            [
                'payment' =>
                    $restored->toArray(),

                'sale_id' =>
                    $restored->saleId(),
            ]
        );

        return $restored;
    }

    /**
     * Synchronize sale payment totals from
     * the active payment ledger while preserving
     * an existing legacy header-level paid amount.
     */
    public function synchronizeSale(
        int $companyId,
        int $saleId,
        int $userId
    ): Sale {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleId,
            'Sale ID'
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

            $sale =
                $this->saleRepository
                    ->findForUpdate(
                        $companyId,
                        $saleId
                    );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Sale not found.'
                );
            }

            $ledgerPaid =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $saleId
                        )
                );

            $legacyPaid =
                $this->legacyPaidBaseline(
                    $sale,
                    $ledgerPaid
                );

            $paidAmount =
                $this->money(
                    $legacyPaid
                    + $ledgerPaid
                );

            $updated =
                $this->synchronizeSalePayment(
                    $sale,
                    $paidAmount,
                    $userId
                );

            $database->commit();
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }

        return $updated;
    }

    /**
     * Return current sale payment summary.
     *
     * @return array{
     *     grand_total: float,
     *     paid_amount: float,
     *     balance_due: float,
     *     payment_status: string
     * }
     */
    public function summary(
        int $companyId,
        int $saleId
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $saleId,
            'Sale ID'
        );

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

        $ledgerPaid =
            $this->money(
                $this->paymentRepository
                    ->sumActivePayments(
                        $companyId,
                        $saleId
                    )
            );

        /*
         * Derive the amount that existed before
         * the dedicated payment ledger.
         */
        $legacyPaid =
            $this->legacyPaidBaseline(
                $sale,
                $ledgerPaid
            );

        $paidAmount =
            $this->money(
                $legacyPaid
                + $ledgerPaid
            );

        $balanceDue =
            $this->money(
                max(
                    0,
                    $sale->grandTotal()
                    - $paidAmount
                )
            );

        return [
            'grand_total' =>
                $sale->grandTotal(),

            'paid_amount' =>
                $paidAmount,

            'balance_due' =>
                $balanceDue,

            'payment_status' =>
                $this->paymentStatus(
                    $paidAmount,
                    $sale->grandTotal()
                ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentMethodOptions(): array
    {
        return SalePayment::paymentMethodOptions();
    }

    /**
     * Synchronize payment fields on the sale header.
     */
    private function synchronizeSalePayment(
        Sale $sale,
        float $paidAmount,
        int $userId
    ): Sale {
        $paidAmount =
            $this->money(
                max(
                    0,
                    $paidAmount
                )
            );

        $grandTotal =
            $sale->grandTotal();

        if (
            $paidAmount
            > $grandTotal + 0.00005
        ) {
            throw new ValidationException(
                'Paid amount exceeds sale grand total.'
            );
        }

        $balanceDue =
            $this->money(
                max(
                    0,
                    $grandTotal
                    - $paidAmount
                )
            );

        $paymentStatus =
            $this->paymentStatus(
                $paidAmount,
                $grandTotal
            );

        $updated =
            $this->saleRepository
                ->updatePayment(
                    $sale->companyId(),
                    (int) $sale->id(),
                    $paidAmount,
                    $balanceDue,
                    $paymentStatus,
                    $userId
                );

        if (!$updated instanceof Sale) {
            throw new ValidationException(
                'Sale payment balance could not be updated.'
            );
        }

        return $updated;
    }

    /**
     * Calculate the portion of sale.paid_amount
     * that is not represented by the current
     * active payment ledger.
     *
     * Example:
     *
     * sale header paid = 7000
     * active ledger    = 2000
     * legacy baseline  = 5000
     */
    private function legacyPaidBaseline(
        Sale $sale,
        float $activeLedgerPaid
    ): float {
        return $this->money(
            max(
                0,
                $sale->paidAmount()
                - $activeLedgerPaid
            )
        );
    }

    /**
     * Validate whether a sale can receive payments.
     */
    private function validateSaleForPayment(
        Sale $sale
    ): void {
        if ($sale->isDeleted()) {
            throw new ValidationException(
                'Payments cannot be recorded against a deleted sale.'
            );
        }

        if ($sale->isCancelled()) {
            throw new ValidationException(
                'Payments cannot be recorded against a cancelled sale.'
            );
        }

        if ($sale->status() !== 'completed') {
            throw new ValidationException(
                'Sale must be completed before recording a payment.'
            );
        }

        if ($sale->grandTotal() <= 0.00005) {
            throw new ValidationException(
                'Sale total must be greater than zero before recording a payment.'
            );
        }
    }

    /**
     * Generate a unique sale payment number.
     */
    private function generatePaymentNumber(
        int $companyId
    ): string {
        $prefix =
            'SPAY-'
            . gmdate('Ymd')
            . '-';

        for (
            $attempt = 0;
            $attempt < 20;
            $attempt++
        ) {
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
                $prefix
                . $suffix;

            if (
                !$this->paymentRepository
                    ->paymentNumberExists(
                        $companyId,
                        $number
                    )
            ) {
                return $number;
            }
        }

        throw new ValidationException(
            'Sale payment number could not be generated.'
        );
    }

    /**
     * Validate a manually supplied payment number.
     */
    private function validatePaymentNumber(
        int $companyId,
        string $paymentNumber
    ): void {
        if (
            $paymentNumber === ''
            || mb_strlen(
                $paymentNumber
            ) > 100
        ) {
            throw new ValidationException(
                'Payment number is required and must not exceed 100 characters.'
            );
        }

        if (
            $this->paymentRepository
                ->paymentNumberExists(
                    $companyId,
                    $paymentNumber
                )
        ) {
            throw new ValidationException(
                'Payment number is already in use.'
            );
        }
    }

    /**
     * Normalize and validate payment method.
     */
    private function paymentMethod(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new ValidationException(
                'Payment method is required.'
            );
        }

        $method = strtolower(
            trim($value)
        );

        if ($method === '') {
            $method = 'cash';
        }

        if (
            !in_array(
                $method,
                SalePayment::paymentMethods(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment method.'
            );
        }

        return $method;
    }

    /**
     * Determine sale payment status.
     */
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

    /**
     * Validate payment amount.
     */
    private function positiveAmount(
        mixed $value
    ): float {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            throw new ValidationException(
                'Payment amount is required.'
            );
        }

        $amount =
            $this->money(
                (float) $value
            );

        if (
            !is_finite($amount)
            || $amount <= 0
        ) {
            throw new ValidationException(
                'Payment amount must be greater than zero.'
            );
        }

        return $amount;
    }

    /**
     * Convert optional related-record ID.
     */
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

    /**
     * Validate an optional string.
     */
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

    /**
     * Validate optional long text.
     */
    private function nullableText(
        mixed $value,
        int $maximumLength
    ): ?string {
        return $this->nullableString(
            $value,
            $maximumLength
        );
    }

    /**
     * Validate date input.
     */
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

    /**
     * Validate positive identifier.
     */
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

    /**
     * Normalize monetary precision.
     */
    private function money(
        float $value
    ): float {
        return round(
            $value,
            4
        );
    }

    /**
     * Begin an isolated payment transaction.
     */
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
                'Sale payment transaction could not be started.'
            );
        }
    }

    /**
     * Roll back an active transaction.
     */
    private function rollbackIfNeeded(
        PDO $database
    ): void {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
    }
}