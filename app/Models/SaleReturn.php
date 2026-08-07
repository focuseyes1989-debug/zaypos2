<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final class SaleReturn extends BaseModel
{
    private int $companyId;

    private int $saleId;

    private ?int $customerId = null;

    private int $warehouseId;

    private string $returnNumber;

    private DateTimeImmutable $returnDate;

    private string $status = 'draft';

    private string $refundStatus = 'none';

    private float $subtotal = 0.0;

    private float $discountAmount = 0.0;

    private float $taxAmount = 0.0;

    private float $grandTotal = 0.0;

    private float $refundedAmount = 0.0;

    private float $refundBalance = 0.0;

    private ?string $reason = null;

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

        $this->companyId =
            $this->requiredPositiveInteger(
                $data['company_id'] ?? null,
                'Company ID'
            );

        $this->saleId =
            $this->requiredPositiveInteger(
                $data['sale_id'] ?? null,
                'Sale ID'
            );

        $this->customerId =
            $this->nullablePositiveInteger(
                $data['customer_id'] ?? null
            );

        $this->warehouseId =
            $this->requiredPositiveInteger(
                $data['warehouse_id'] ?? null,
                'Warehouse ID'
            );

        $this->returnNumber =
            $this->requiredString(
                $data['return_number'] ?? null,
                'Return number',
                100
            );

        $this->returnDate =
            $this->requiredDate(
                $data['return_date'] ?? null,
                'Return date'
            );

        $this->status =
            $this->validateStatus(
                $data['status'] ?? 'draft'
            );

        $this->refundStatus =
            $this->validateRefundStatus(
                $data['refund_status'] ?? 'none'
            );

        $this->subtotal =
            $this->nonNegativeDecimal(
                $data['subtotal'] ?? 0,
                'Subtotal'
            );

        $this->discountAmount =
            $this->nonNegativeDecimal(
                $data['discount_amount'] ?? 0,
                'Discount amount'
            );

        $this->taxAmount =
            $this->nonNegativeDecimal(
                $data['tax_amount'] ?? 0,
                'Tax amount'
            );

        $this->grandTotal =
            $this->nonNegativeDecimal(
                $data['grand_total'] ?? 0,
                'Grand total'
            );

        $this->refundedAmount =
            $this->nonNegativeDecimal(
                $data['refunded_amount'] ?? 0,
                'Refunded amount'
            );

        $this->refundBalance =
            $this->nonNegativeDecimal(
                $data['refund_balance'] ?? 0,
                'Refund balance'
            );

        if (
            $this->refundedAmount
            > $this->grandTotal + 0.00005
        ) {
            throw new InvalidArgumentException(
                'Refunded amount must not exceed return grand total.'
            );
        }

        if (
            $this->refundBalance
            > $this->grandTotal + 0.00005
        ) {
            throw new InvalidArgumentException(
                'Refund balance must not exceed return grand total.'
            );
        }

        $this->reason =
            $this->nullableString(
                $data['reason'] ?? null,
                500
            );

        $this->notes =
            $this->nullableText(
                $data['notes'] ?? null
            );

        $this->completedAt =
            $this->nullableDateTime(
                $data['completed_at'] ?? null
            );

        $this->completedBy =
            $this->nullablePositiveInteger(
                $data['completed_by'] ?? null
            );

        $this->cancelledAt =
            $this->nullableDateTime(
                $data['cancelled_at'] ?? null
            );

        $this->cancelledBy =
            $this->nullablePositiveInteger(
                $data['cancelled_by'] ?? null
            );

        $this->cancellationReason =
            $this->nullableString(
                $data['cancellation_reason'] ?? null,
                500
            );

        $this->createdBy =
            $this->nullablePositiveInteger(
                $data['created_by'] ?? null
            );

        $this->updatedBy =
            $this->nullablePositiveInteger(
                $data['updated_by'] ?? null
            );

        $this->deletedBy =
            $this->nullablePositiveInteger(
                $data['deleted_by'] ?? null
            );
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function saleId(): int
    {
        return $this->saleId;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function returnNumber(): string
    {
        return $this->returnNumber;
    }

    public function returnDate(): DateTimeImmutable
    {
        return $this->returnDate;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function refundStatus(): string
    {
        return $this->refundStatus;
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

    public function grandTotal(): float
    {
        return $this->grandTotal;
    }

    public function refundedAmount(): float
    {
        return $this->refundedAmount;
    }

    public function refundBalance(): float
    {
        return $this->refundBalance;
    }

    public function reason(): ?string
    {
        return $this->reason;
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

    public function hasNoRefund(): bool
    {
        return $this->refundStatus === 'none';
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->refundStatus === 'partial';
    }

    public function isRefunded(): bool
    {
        return $this->refundStatus === 'refunded';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' =>
                $this->id(),

            'company_id' =>
                $this->companyId,

            'sale_id' =>
                $this->saleId,

            'customer_id' =>
                $this->customerId,

            'warehouse_id' =>
                $this->warehouseId,

            'return_number' =>
                $this->returnNumber,

            'return_date' =>
                $this->returnDate
                    ->format('Y-m-d'),

            'status' =>
                $this->status,

            'refund_status' =>
                $this->refundStatus,

            'subtotal' =>
                $this->subtotal,

            'discount_amount' =>
                $this->discountAmount,

            'tax_amount' =>
                $this->taxAmount,

            'grand_total' =>
                $this->grandTotal,

            'refunded_amount' =>
                $this->refundedAmount,

            'refund_balance' =>
                $this->refundBalance,

            'reason' =>
                $this->reason,

            'notes' =>
                $this->notes,

            'completed_at' =>
                $this->completedAt?->format(
                    'Y-m-d H:i:s'
                ),

            'completed_by' =>
                $this->completedBy,

            'cancelled_at' =>
                $this->cancelledAt?->format(
                    'Y-m-d H:i:s'
                ),

            'cancelled_by' =>
                $this->cancelledBy,

            'cancellation_reason' =>
                $this->cancellationReason,

            'created_by' =>
                $this->createdBy,

            'updated_by' =>
                $this->updatedBy,

            'deleted_by' =>
                $this->deletedBy,

            'created_at' =>
                $this->createdAt()?->format(
                    'Y-m-d H:i:s'
                ),

            'updated_at' =>
                $this->updatedAt()?->format(
                    'Y-m-d H:i:s'
                ),

            'deleted_at' =>
                $this->deletedAt()?->format(
                    'Y-m-d H:i:s'
                ),
        ];
    }

    private function requiredPositiveInteger(
        mixed $value,
        string $field
    ): int {
        $integer =
            $this->nullablePositiveInteger(
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
        $date =
            $this->nullableDate(
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
                'Sale return status is invalid.'
            );
        }

        $status =
            strtolower(
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
                'Sale return status must be draft, completed or cancelled.'
            );
        }

        return $status;
    }

    private function validateRefundStatus(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Sale return refund status is invalid.'
            );
        }

        $status =
            strtolower(
                trim($value)
            );

        if (
            !in_array(
                $status,
                [
                    'none',
                    'partial',
                    'refunded',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Refund status must be none, partial or refunded.'
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

        $number =
            round(
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