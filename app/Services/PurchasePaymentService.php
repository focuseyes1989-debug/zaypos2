<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Repositories\PurchasePaymentRepository;
use App\Repositories\PurchaseRepository;
use PDO;
use Throwable;

final class PurchasePaymentService
{
    public function __construct(
        private readonly PurchasePaymentRepository $paymentRepository =
            new PurchasePaymentRepository(),

        private readonly PurchaseRepository $purchaseRepository =
            new PurchaseRepository(),

        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<PurchasePayment>,
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
                PurchasePayment::paymentMethods(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment method filter.'
            );
        }

        $purchaseId = $this->nullableId(
            $filters['purchase_id'] ?? null
        );

        $supplierId = $this->nullableId(
            $filters['supplier_id'] ?? null
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
            $purchaseId,
            $supplierId,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $paymentId,
        bool $includeDeleted = false
    ): PurchasePayment {
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

        if (!$payment instanceof PurchasePayment) {
            throw new ValidationException(
                'Purchase payment not found.'
            );
        }

        return $payment;
    }

    /**
     * @return list<PurchasePayment>
     */
    public function byPurchase(
        int $companyId,
        int $purchaseId,
        bool $includeDeleted = false
    ): array {
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
                true
            );

        if (!$purchase instanceof Purchase) {
            throw new ValidationException(
                'Purchase not found.'
            );
        }

        return $this->paymentRepository->byPurchase(
            $companyId,
            $purchaseId,
            $includeDeleted
        );
    }

    /**
     * Record a supplier payment against a received purchase.
     *
     * Payment insert and purchase balance update are atomic.
     *
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $purchaseId,
        int $userId,
        array $input
    ): PurchasePayment {
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

            $this->validatePurchaseForPayment(
                $purchase
            );

            $existingPaid =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $purchaseId
                        )
                );

            /*
             * Existing Purchase records from before the payment
             * ledger feature may have a header-level paid amount.
             *
             * Once payment records exist, the ledger becomes the
             * source of truth.
             *
             * If no payment ledger exists yet but the purchase
             * already has a paid amount, prevent accidentally
             * paying beyond its remaining balance.
             */
            $activePaymentCount =
                $this->paymentRepository
                    ->countActivePayments(
                        $companyId,
                        $purchaseId
                    );

            $effectivePaid =
                $activePaymentCount > 0
                    ? $existingPaid
                    : max(
                        $existingPaid,
                        $purchase->paidAmount()
                    );

            $remainingBalance =
                $this->money(
                    max(
                        0,
                        $purchase->grandTotal()
                        - $effectivePaid
                    )
                );

            if ($remainingBalance <= 0.00005) {
                throw new ValidationException(
                    'Purchase is already fully paid.'
                );
            }

            if (
                $amount
                > $remainingBalance + 0.00005
            ) {
                throw new ValidationException(
                    'Payment amount must not exceed the purchase balance.'
                );
            }

            $payment =
                $this->paymentRepository->create([
                    'company_id' =>
                        $companyId,

                    'purchase_id' =>
                        $purchaseId,

                    'supplier_id' =>
                        $purchase->supplierId(),

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

            $newPaidAmount =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $purchaseId
                        )
                );

            /*
             * Preserve legacy/header payment only when the new
             * payment ledger started from an already-paid purchase.
             *
             * Normally new purchases should have payment ledger
             * entries as the source of truth.
             */
            if (
                $activePaymentCount === 0
                && $purchase->paidAmount() > 0.00005
            ) {
                $newPaidAmount = $this->money(
                    $purchase->paidAmount()
                    + $amount
                );
            }

            if (
                $newPaidAmount
                > $purchase->grandTotal()
                + 0.00005
            ) {
                throw new ValidationException(
                    'Purchase payment total exceeds the grand total.'
                );
            }

            $this->synchronizePurchasePayment(
                $purchase,
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
            'purchase_payments.created',
            'purchase_payment',
            $payment->id(),
            [
                'payment' =>
                    $payment->toArray(),

                'purchase_id' =>
                    $purchaseId,
            ]
        );

        return $payment;
    }

    /**
     * Soft-delete payment and recalculate purchase balance.
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

            if (!$payment instanceof PurchasePayment) {
                throw new ValidationException(
                    'Purchase payment not found.'
                );
            }

            $purchase =
                $this->purchaseRepository
                    ->findForUpdate(
                        $companyId,
                        $payment->purchaseId()
                    );

            if (!$purchase instanceof Purchase) {
                throw new ValidationException(
                    'Purchase not found.'
                );
            }

            if ($purchase->isDeleted()) {
                throw new ValidationException(
                    'Payment cannot be changed because the purchase is deleted.'
                );
            }

            if (
                !$this->paymentRepository
                    ->softDelete(
                        $companyId,
                        $paymentId,
                        $userId
                    )
            ) {
                throw new ValidationException(
                    'Purchase payment could not be deleted.'
                );
            }

            $paidAmount =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->purchaseId()
                        )
                );

            $this->synchronizePurchasePayment(
                $purchase,
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
            'purchase_payments.deleted',
            'purchase_payment',
            $paymentId,
            [
                'payment' =>
                    $payment->toArray(),

                'purchase_id' =>
                    $payment->purchaseId(),
            ]
        );
    }

    /**
     * Restore a deleted payment.
     *
     * Restoration is blocked when it would overpay the purchase.
     */
    public function restore(
        int $companyId,
        int $paymentId,
        int $userId
    ): PurchasePayment {
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

            if (!$payment instanceof PurchasePayment) {
                throw new ValidationException(
                    'Purchase payment not found.'
                );
            }

            if (!$payment->isDeleted()) {
                throw new ValidationException(
                    'Purchase payment is not deleted.'
                );
            }

            $purchase =
                $this->purchaseRepository
                    ->findForUpdate(
                        $companyId,
                        $payment->purchaseId()
                    );

            if (!$purchase instanceof Purchase) {
                throw new ValidationException(
                    'Purchase not found.'
                );
            }

            $this->validatePurchaseForPayment(
                $purchase
            );

            $activePaidAmount =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->purchaseId()
                        )
                );

            $restoredPaidAmount =
                $this->money(
                    $activePaidAmount
                    + $payment->amount()
                );

            if (
                $restoredPaidAmount
                > $purchase->grandTotal()
                + 0.00005
            ) {
                throw new ValidationException(
                    'Payment cannot be restored because it would exceed the purchase total.'
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
                    'Purchase payment could not be restored.'
                );
            }

            $restored =
                $this->paymentRepository
                    ->find(
                        $companyId,
                        $paymentId
                    );

            if (!$restored instanceof PurchasePayment) {
                throw new ValidationException(
                    'Restored payment could not be reloaded.'
                );
            }

            $paidAmount =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $payment->purchaseId()
                        )
                );

            $this->synchronizePurchasePayment(
                $purchase,
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
            'purchase_payments.restored',
            'purchase_payment',
            $paymentId,
            [
                'payment' =>
                    $restored->toArray(),

                'purchase_id' =>
                    $restored->purchaseId(),
            ]
        );

        return $restored;
    }

    /**
     * Synchronize purchase header from active payment ledger.
     */
    public function synchronizePurchase(
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

            $paidAmount =
                $this->money(
                    $this->paymentRepository
                        ->sumActivePayments(
                            $companyId,
                            $purchaseId
                        )
                );

            $updated =
                $this->synchronizePurchasePayment(
                    $purchase,
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
     * @return array{
     *     grand_total: float,
     *     paid_amount: float,
     *     balance_due: float,
     *     payment_status: string
     * }
     */
    public function summary(
        int $companyId,
        int $purchaseId
    ): array {
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
                $purchaseId
            );

        if (!$purchase instanceof Purchase) {
            throw new ValidationException(
                'Purchase not found.'
            );
        }

        $paidAmount = $this->money(
            $this->paymentRepository
                ->sumActivePayments(
                    $companyId,
                    $purchaseId
                )
        );

        $balanceDue = $this->money(
            max(
                0,
                $purchase->grandTotal()
                - $paidAmount
            )
        );

        return [
            'grand_total' =>
                $purchase->grandTotal(),

            'paid_amount' =>
                $paidAmount,

            'balance_due' =>
                $balanceDue,

            'payment_status' =>
                $this->paymentStatus(
                    $paidAmount,
                    $purchase->grandTotal()
                ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentMethodOptions(): array
    {
        return PurchasePayment::paymentMethodOptions();
    }

    private function synchronizePurchasePayment(
        Purchase $purchase,
        float $paidAmount,
        int $userId
    ): Purchase {
        $paidAmount = $this->money(
            max(
                0,
                $paidAmount
            )
        );

        $grandTotal =
            $purchase->grandTotal();

        if (
            $paidAmount
            > $grandTotal + 0.00005
        ) {
            throw new ValidationException(
                'Paid amount exceeds purchase grand total.'
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
                ->updatePayment(
                    $purchase->companyId(),
                    (int) $purchase->id(),
                    $paidAmount,
                    $balanceDue,
                    $paymentStatus,
                    $userId
                );

        if (!$updated instanceof Purchase) {
            throw new ValidationException(
                'Purchase payment balance could not be updated.'
            );
        }

        return $updated;
    }

    private function validatePurchaseForPayment(
        Purchase $purchase
    ): void {
        if ($purchase->isDeleted()) {
            throw new ValidationException(
                'Payments cannot be recorded against a deleted purchase.'
            );
        }

        if ($purchase->isCancelled()) {
            throw new ValidationException(
                'Payments cannot be recorded against a cancelled purchase.'
            );
        }

        if (!$purchase->isReceived()) {
            throw new ValidationException(
                'Purchase must be received before recording a payment.'
            );
        }

        if ($purchase->grandTotal() <= 0.00005) {
            throw new ValidationException(
                'Purchase total must be greater than zero before recording a payment.'
            );
        }
    }

    private function generatePaymentNumber(
        int $companyId
    ): string {
        $prefix =
            'PPAY-'
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
                $prefix . $suffix;

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
            'Purchase payment number could not be generated.'
        );
    }

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
                PurchasePayment::paymentMethods(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment method.'
            );
        }

        return $method;
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

        $amount = $this->money(
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
                'Purchase payment transaction could not be started.'
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