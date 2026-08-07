<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final class SaleReturnRefund extends BaseModel
{
    private int $companyId;

    private int $saleReturnId;

    private string $refundNumber;

    private DateTimeImmutable $refundDate;

    private float $amount = 0.0;

    private string $refundMethod = 'cash';

    private ?string $referenceNumber = null;

    private ?string $notes = null;

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

        $this->saleReturnId =
            $this->requiredPositiveInteger(
                $data['sale_return_id'] ?? null,
                'Sale return ID'
            );

        $this->refundNumber =
            $this->requiredString(
                $data['refund_number'] ?? null,
                'Refund number',
                100
            );

        $this->refundDate =
            $this->requiredDate(
                $data['refund_date'] ?? null,
                'Refund date'
            );

        $this->amount =
            $this->positiveAmount(
                $data['amount'] ?? null
            );

        $this->refundMethod =
            $this->normalizeRefundMethod(
                $data['refund_method'] ?? 'cash'
            );

        $this->referenceNumber =
            $this->nullableString(
                $data['reference_number'] ?? null,
                190
            );

        $this->notes =
            $this->nullableText(
                $data['notes'] ?? null
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

    public function saleReturnId(): int
    {
        return $this->saleReturnId;
    }

    public function refundNumber(): string
    {
        return $this->refundNumber;
    }

    public function refundDate(): DateTimeImmutable
    {
        return $this->refundDate;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function refundMethod(): string
    {
        return $this->refundMethod;
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

    public function isCash(): bool
    {
        return $this->refundMethod === 'cash';
    }

    public function isBankTransfer(): bool
    {
        return $this->refundMethod === 'bank_transfer';
    }

    public function isCard(): bool
    {
        return $this->refundMethod === 'card';
    }

    public function isMobilePayment(): bool
    {
        return $this->refundMethod === 'mobile_payment';
    }

    /**
     * @return array<int, string>
     */
    public static function refundMethods(): array
    {
        return [
            'cash',
            'bank_transfer',
            'card',
            'mobile_payment',
            'cheque',
            'other',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function refundMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
            'mobile_payment' => 'Mobile Payment',
            'cheque' => 'Cheque',
            'other' => 'Other',
        ];
    }

    public function refundMethodLabel(): string
    {
        return self::refundMethodOptions()[
            $this->refundMethod
        ] ?? ucfirst(
            str_replace(
                '_',
                ' ',
                $this->refundMethod
            )
        );
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

            'sale_return_id' =>
                $this->saleReturnId,

            'refund_number' =>
                $this->refundNumber,

            'refund_date' =>
                $this->refundDate
                    ->format('Y-m-d'),

            'amount' =>
                $this->amount,

            'refund_method' =>
                $this->refundMethod,

            'reference_number' =>
                $this->referenceNumber,

            'notes' =>
                $this->notes,

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

    private function requiredDate(
        mixed $value,
        string $field
    ): DateTimeImmutable {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            throw new InvalidArgumentException(
                "{$field} is required."
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
                "{$field} must be a valid date."
            );
        }

        return $date;
    }

    private function positiveAmount(
        mixed $value
    ): float {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            throw new InvalidArgumentException(
                'Refund amount is required.'
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
            throw new InvalidArgumentException(
                'Refund amount must be greater than zero.'
            );
        }

        return $amount;
    }

    private function normalizeRefundMethod(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Refund method is required.'
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
                self::refundMethods(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid sale return refund method.'
            );
        }

        return $method;
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
}