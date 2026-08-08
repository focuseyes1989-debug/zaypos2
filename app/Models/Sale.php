<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final class Sale extends BaseModel
{
    private int $companyId;

    private ?int $customerId = null;

    private int $warehouseId;

    private ?int $posShiftId = null;

    private bool $isHeld = false;

    private ?DateTimeImmutable $heldAt = null;

    private ?int $heldBy = null;

    private string $saleNumber;

    private ?string $customerReference = null;

    private DateTimeImmutable $saleDate;

    private ?DateTimeImmutable $dueDate = null;

    private string $status = 'draft';

    private string $paymentStatus = 'unpaid';

    private float $subtotal = 0.0;

    private float $discountAmount = 0.0;

    private float $taxAmount = 0.0;

    private float $shippingAmount = 0.0;

    private float $otherAmount = 0.0;

    private float $grandTotal = 0.0;

    private float $paidAmount = 0.0;

    private float $balanceDue = 0.0;

    private ?string $notes = null;

    private ?DateTimeImmutable $completedAt = null;

    private ?int $completedBy = null;

    private ?DateTimeImmutable $cancelledAt = null;

    private ?int $cancelledBy = null;

    private ?string $cancellationReason = null;

    private ?int $createdBy = null;

    private ?int $updatedBy = null;

    private ?int $deletedBy = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->fillBaseAttributes($data);

        $this->companyId = $this->requiredPositiveInteger(
            $data['company_id'] ?? null,
            'Company ID'
        );

        $this->customerId = $this->nullablePositiveInteger(
            $data['customer_id'] ?? null
        );

        $this->warehouseId = $this->requiredPositiveInteger(
            $data['warehouse_id'] ?? null,
            'Warehouse ID'
        );

        $this->posShiftId = $this->nullablePositiveInteger(
            $data['pos_shift_id'] ?? null
        );

        $this->isHeld =
            (int) (
                $data['is_held']
                ?? 0
            ) === 1;

        $this->heldAt = $this->nullableDateTime(
            $data['held_at'] ?? null
        );

        $this->heldBy = $this->nullablePositiveInteger(
            $data['held_by'] ?? null
        );

        $this->saleNumber = $this->requiredString(
            $data['sale_number'] ?? null,
            'Sale number',
            100
        );

        $this->customerReference = $this->nullableString(
            $data['customer_reference'] ?? null,
            100
        );

        $this->saleDate = $this->requiredDate(
            $data['sale_date'] ?? null,
            'Sale date'
        );

        $this->dueDate = $this->nullableDate(
            $data['due_date'] ?? null
        );

        if (
            $this->dueDate !== null
            && $this->dueDate < $this->saleDate
        ) {
            throw new InvalidArgumentException(
                'Due date must not be earlier than sale date.'
            );
        }

        $this->status = $this->validateStatus(
            $data['status'] ?? 'draft'
        );

        $this->paymentStatus = $this->validatePaymentStatus(
            $data['payment_status'] ?? 'unpaid'
        );

        $this->subtotal = $this->nonNegativeDecimal(
            $data['subtotal'] ?? 0,
            'Subtotal'
        );

        $this->discountAmount = $this->nonNegativeDecimal(
            $data['discount_amount'] ?? 0,
            'Discount amount'
        );

        $this->taxAmount = $this->nonNegativeDecimal(
            $data['tax_amount'] ?? 0,
            'Tax amount'
        );

        $this->shippingAmount = $this->nonNegativeDecimal(
            $data['shipping_amount'] ?? 0,
            'Shipping amount'
        );

        $this->otherAmount = $this->nonNegativeDecimal(
            $data['other_amount'] ?? 0,
            'Other amount'
        );

        $this->grandTotal = $this->nonNegativeDecimal(
            $data['grand_total'] ?? 0,
            'Grand total'
        );

        $this->paidAmount = $this->nonNegativeDecimal(
            $data['paid_amount'] ?? 0,
            'Paid amount'
        );

        $this->balanceDue = $this->nonNegativeDecimal(
            $data['balance_due'] ?? 0,
            'Balance due'
        );

        if (
            $this->paidAmount
            > $this->grandTotal + 0.00005
        ) {
            throw new InvalidArgumentException(
                'Paid amount must not exceed grand total.'
            );
        }

        $this->notes = $this->nullableText(
            $data['notes'] ?? null
        );

        $this->completedAt = $this->nullableDateTime(
            $data['completed_at'] ?? null
        );

        $this->completedBy = $this->nullablePositiveInteger(
            $data['completed_by'] ?? null
        );

        $this->cancelledAt = $this->nullableDateTime(
            $data['cancelled_at'] ?? null
        );

        $this->cancelledBy = $this->nullablePositiveInteger(
            $data['cancelled_by'] ?? null
        );

        $this->cancellationReason = $this->nullableString(
            $data['cancellation_reason'] ?? null,
            500
        );

        $this->createdBy = $this->nullablePositiveInteger(
            $data['created_by'] ?? null
        );

        $this->updatedBy = $this->nullablePositiveInteger(
            $data['updated_by'] ?? null
        );

        $this->deletedBy = $this->nullablePositiveInteger(
            $data['deleted_by'] ?? null
        );
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function posShiftId(): ?int
    {
        return $this->posShiftId;
    }

    public function isHeld(): bool
    {
        return $this->isHeld;
    }

    public function heldAt(): ?DateTimeImmutable
    {
        return $this->heldAt;
    }

    public function heldBy(): ?int
    {
        return $this->heldBy;
    }

    public function saleNumber(): string
    {
        return $this->saleNumber;
    }

    public function customerReference(): ?string
    {
        return $this->customerReference;
    }

    public function saleDate(): DateTimeImmutable
    {
        return $this->saleDate;
    }

    public function dueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function paymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function subtotal(): float
    {
        return $this->subtotal;
    }

    public function discountAmount(): float
    {
        return $this->discountAmount;
    }

    public function taxAmount(): float
    {
        return $this->taxAmount;
    }

    public function shippingAmount(): float
    {
        return $this->shippingAmount;
    }

    public function otherAmount(): float
    {
        return $this->otherAmount;
    }

    public function grandTotal(): float
    {
        return $this->grandTotal;
    }

    public function paidAmount(): float
    {
        return $this->paidAmount;
    }

    public function balanceDue(): float
    {
        return $this->balanceDue;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function completedBy(): ?int
    {
        return $this->completedBy;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelledBy(): ?int
    {
        return $this->cancelledBy;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function deletedBy(): ?int
    {
        return $this->deletedBy;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paymentStatus === 'partial';
    }

    public function isUnpaid(): bool
    {
        return $this->paymentStatus === 'unpaid';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),

            'company_id' => $this->companyId,
            'customer_id' => $this->customerId,
            'warehouse_id' => $this->warehouseId,
            'pos_shift_id' => $this->posShiftId,

            'is_held' => $this->isHeld,

            'held_at' => $this->heldAt?->format(
                'Y-m-d H:i:s'
            ),

            'held_by' => $this->heldBy,

            'sale_number' => $this->saleNumber,
            'customer_reference' => $this->customerReference,

            'sale_date' => $this->saleDate->format(
                'Y-m-d'
            ),

            'due_date' => $this->dueDate?->format(
                'Y-m-d'
            ),

            'status' => $this->status,
            'payment_status' => $this->paymentStatus,

            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'shipping_amount' => $this->shippingAmount,
            'other_amount' => $this->otherAmount,

            'grand_total' => $this->grandTotal,
            'paid_amount' => $this->paidAmount,
            'balance_due' => $this->balanceDue,

            'notes' => $this->notes,

            'completed_at' => $this->completedAt?->format(
                'Y-m-d H:i:s'
            ),

            'completed_by' => $this->completedBy,

            'cancelled_at' => $this->cancelledAt?->format(
                'Y-m-d H:i:s'
            ),

            'cancelled_by' => $this->cancelledBy,

            'cancellation_reason' =>
                $this->cancellationReason,

            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'deleted_by' => $this->deletedBy,

            'created_at' => $this->createdAt()?->format(
                'Y-m-d H:i:s'
            ),

            'updated_at' => $this->updatedAt()?->format(
                'Y-m-d H:i:s'
            ),

            'deleted_at' => $this->deletedAt()?->format(
                'Y-m-d H:i:s'
            ),
        ];
    }

    private function requiredPositiveInteger(
        mixed $value,
        string $field
    ): int {
        $integer = $this->nullablePositiveInteger(
            $value
        );

        if ($integer === null) {
            throw new InvalidArgumentException(
                "{$field} is required."
            );
        }

        return $integer;
    }

    private function requiredString(
        mixed $value,
        string $field,
        int $maximumLength
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                "{$field} is required."
            );
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                "{$field} is required."
            );
        }

        if (
            mb_strlen($value)
            > $maximumLength
        ) {
            throw new InvalidArgumentException(
                "{$field} must not exceed {$maximumLength} characters."
            );
        }

        return $value;
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
            throw new InvalidArgumentException(
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
            throw new InvalidArgumentException(
                "Text must not exceed {$maximumLength} characters."
            );
        }

        return $value;
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Expected a text value.'
            );
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function requiredDate(
        mixed $value,
        string $field
    ): DateTimeImmutable {
        $date = $this->nullableDate(
            $value
        );

        if ($date === null) {
            throw new InvalidArgumentException(
                "{$field} is required."
            );
        }

        return $date;
    }

    private function nullableDate(
        mixed $value
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
            throw new InvalidArgumentException(
                'Expected a valid date.'
            );
        }

        $value = trim($value);

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d')
                !== $value
        ) {
            throw new InvalidArgumentException(
                'Expected date format Y-m-d.'
            );
        }

        return $date;
    }

    private function validateStatus(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Sale status is invalid.'
            );
        }

        $status = strtolower(
            trim($value)
        );

        if (
            !in_array(
                $status,
                [
                    'draft',
                    'completed',
                    'cancelled',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Sale status must be draft, completed or cancelled.'
            );
        }

        return $status;
    }

    private function validatePaymentStatus(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Sale payment status is invalid.'
            );
        }

        $status = strtolower(
            trim($value)
        );

        if (
            !in_array(
                $status,
                [
                    'unpaid',
                    'partial',
                    'paid',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Sale payment status must be unpaid, partial or paid.'
            );
        }

        return $status;
    }

    private function nonNegativeDecimal(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "{$field} must be a valid number."
            );
        }

        $number = round(
            (float) $value,
            4
        );

        if (
            !is_finite($number)
            || $number < 0
        ) {
            throw new InvalidArgumentException(
                "{$field} must be zero or greater."
            );
        }

        return $number;
    }
}