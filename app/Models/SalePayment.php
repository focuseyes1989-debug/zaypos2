<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ValidationException;
use DateTimeImmutable;

final class SalePayment
{
    private ?int $id;

    private int $companyId;

    private int $saleId;

    private ?int $customerId;

    private string $paymentNumber;

    private DateTimeImmutable $paymentDate;

    private float $amount;

    private string $paymentMethod;

    private ?string $referenceNumber;

    private ?string $notes;

    private ?int $createdBy;

    private ?int $updatedBy;

    private ?int $deletedBy;

    private ?DateTimeImmutable $createdAt;

    private ?DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->id = $this->nullablePositiveInteger(
            $data['id'] ?? null
        );

        $this->companyId = $this->requiredPositiveInteger(
            $data['company_id'] ?? null,
            'Company ID'
        );

        $this->saleId = $this->requiredPositiveInteger(
            $data['sale_id'] ?? null,
            'Sale ID'
        );

        $this->customerId = $this->nullablePositiveInteger(
            $data['customer_id'] ?? null
        );

        $this->paymentNumber = $this->requiredString(
            $data['payment_number'] ?? null,
            'Payment number',
            100
        );

        $this->paymentDate = $this->requiredDate(
            $data['payment_date'] ?? null,
            'Payment date'
        );

        $this->amount = $this->positiveAmount(
            $data['amount'] ?? null
        );

        $this->paymentMethod = $this->normalizePaymentMethod(
            $data['payment_method'] ?? 'cash'
        );

        $this->referenceNumber = $this->nullableString(
            $data['reference_number'] ?? null,
            190
        );

        $this->notes = $this->nullableText(
            $data['notes'] ?? null
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

        $this->createdAt = $this->nullableDateTime(
            $data['created_at'] ?? null
        );

        $this->updatedAt = $this->nullableDateTime(
            $data['updated_at'] ?? null
        );

        $this->deletedAt = $this->nullableDateTime(
            $data['deleted_at'] ?? null
        );
    }

    public function id(): ?int
    {
        return $this->id;
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

    public function paymentNumber(): string
    {
        return $this->paymentNumber;
    }

    public function paymentDate(): DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function paymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function referenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function notes(): ?string
    {
        return $this->notes;
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

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'sale_id' => $this->saleId,
            'customer_id' => $this->customerId,

            'payment_number' =>
                $this->paymentNumber,

            'payment_date' =>
                $this->paymentDate
                    ->format('Y-m-d'),

            'amount' => $this->amount,

            'payment_method' =>
                $this->paymentMethod,

            'reference_number' =>
                $this->referenceNumber,

            'notes' => $this->notes,

            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'deleted_by' => $this->deletedBy,

            'created_at' =>
                $this->createdAt
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'updated_at' =>
                $this->updatedAt
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'deleted_at' =>
                $this->deletedAt
                    ?->format(
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
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $integer;
    }

    private function nullablePositiveInteger(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
            && (int) $value > 0
        ) {
            return (int) $value;
        }

        if (
            is_float($value)
            && floor($value) === $value
            && $value > 0
        ) {
            return (int) $value;
        }

        throw new ValidationException(
            'Expected a positive integer.'
        );
    }

    private function requiredString(
        mixed $value,
        string $field,
        int $maximumLength
    ): string {
        $string = $this->nullableString(
            $value,
            $maximumLength
        );

        if ($string === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $string;
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

        if (!is_scalar($value)) {
            throw new ValidationException(
                'Expected a text value.'
            );
        }

        $string = trim(
            (string) $value
        );

        if ($string === '') {
            return null;
        }

        if (
            mb_strlen($string)
            > $maximumLength
        ) {
            throw new ValidationException(
                "Text must not exceed {$maximumLength} characters."
            );
        }

        return $string;
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

        if (!is_scalar($value)) {
            throw new ValidationException(
                'Expected a text value.'
            );
        }

        $text = trim(
            (string) $value
        );

        return $text === ''
            ? null
            : $text;
    }

    private function positiveAmount(
        mixed $value
    ): float {
        if (!is_numeric($value)) {
            throw new ValidationException(
                'Payment amount must be a valid number.'
            );
        }

        $amount = round(
            (float) $value,
            4
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

    private function normalizePaymentMethod(
        mixed $value
    ): string {
        if (!is_scalar($value)) {
            throw new ValidationException(
                'Payment method is invalid.'
            );
        }

        $method = strtolower(
            trim((string) $value)
        );

        $allowed = [
            'cash',
            'card',
            'bank_transfer',
            'mobile_payment',
            'cheque',
            'other',
        ];

        if (
            !in_array(
                $method,
                $allowed,
                true
            )
        ) {
            throw new ValidationException(
                'Invalid payment method.'
            );
        }

        return $method;
    }

    private function requiredDate(
        mixed $value,
        string $field
    ): DateTimeImmutable {
        $date = $this->nullableDate(
            $value,
            $field
        );

        if ($date === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $date;
    }

    private function nullableDate(
        mixed $value,
        string $field
    ): ?DateTimeImmutable {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw new ValidationException(
                "{$field} must use Y-m-d format."
            );
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                trim($value)
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
        ) {
            throw new ValidationException(
                "{$field} must use Y-m-d format."
            );
        }

        return $date;
    }

    private function nullableDateTime(
        mixed $value
    ): ?DateTimeImmutable {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw new ValidationException(
                'Invalid date/time value.'
            );
        }

        $date =
            DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                trim($value)
            );

        if ($date === false) {
            throw new ValidationException(
                'Invalid date/time value.'
            );
        }

        return $date;
    }
}