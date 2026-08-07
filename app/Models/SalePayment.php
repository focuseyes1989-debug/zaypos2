<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final class SalePayment extends BaseModel
{
    private ?int $companyId = null;

    private ?int $saleId = null;

    private ?int $customerId = null;

    private string $paymentNumber = '';

    private ?DateTimeImmutable $paymentDate = null;

    private float $amount = 0.0;

    private string $paymentMethod = 'cash';

    private ?string $referenceNumber = null;

    private ?string $notes = null;

    private ?int $createdBy = null;

    private ?int $updatedBy = null;

    private ?int $deletedBy = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        if ($data !== []) {
            $this->fill($data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): self
    {
        $this->fillBaseAttributes($data);

        $this->companyId = $this->positiveInteger(
            $data['company_id'] ?? null,
            'Company ID'
        );

        $this->saleId = $this->positiveInteger(
            $data['sale_id'] ?? null,
            'Sale ID'
        );

        $this->customerId =
            $this->optionalPositiveInteger(
                $data['customer_id'] ?? null,
                'Customer ID'
            );

        $this->paymentNumber = $this->requiredString(
            $data['payment_number'] ?? '',
            'Payment number',
            100
        );

        $this->paymentDate = $this->requiredDate(
            $data['payment_date'] ?? null,
            'Payment date'
        );

        $this->amount = $this->positiveAmount(
            $data['amount'] ?? 0
        );

        $this->paymentMethod =
            $this->normalizePaymentMethod(
                $data['payment_method'] ?? 'cash'
            );

        $this->referenceNumber =
            $this->optionalString(
                $data['reference_number'] ?? null,
                190
            );

        $this->notes = $this->optionalString(
            $data['notes'] ?? null
        );

        $this->createdBy =
            $this->optionalPositiveInteger(
                $data['created_by'] ?? null,
                'Created by'
            );

        $this->updatedBy =
            $this->optionalPositiveInteger(
                $data['updated_by'] ?? null,
                'Updated by'
            );

        $this->deletedBy =
            $this->optionalPositiveInteger(
                $data['deleted_by'] ?? null,
                'Deleted by'
            );

        return $this;
    }

    public function companyId(): int
    {
        if ($this->companyId === null) {
            throw new InvalidArgumentException(
                'Company ID is not available.'
            );
        }

        return $this->companyId;
    }

    public function saleId(): int
    {
        if ($this->saleId === null) {
            throw new InvalidArgumentException(
                'Sale ID is not available.'
            );
        }

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
        if ($this->paymentDate === null) {
            throw new InvalidArgumentException(
                'Payment date is not available.'
            );
        }

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

    public function isCash(): bool
    {
        return $this->paymentMethod === 'cash';
    }

    public function isBankTransfer(): bool
    {
        return $this->paymentMethod === 'bank_transfer';
    }

    public function isCard(): bool
    {
        return $this->paymentMethod === 'card';
    }

    public function isMobilePayment(): bool
    {
        return $this->paymentMethod === 'mobile_payment';
    }

    /**
     * @return array<int, string>
     */
    public static function paymentMethods(): array
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
    public static function paymentMethodOptions(): array
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

    public function paymentMethodLabel(): string
    {
        return self::paymentMethodOptions()[
            $this->paymentMethod
        ] ?? ucfirst(
            str_replace(
                '_',
                ' ',
                $this->paymentMethod
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),

            'company_id' => $this->companyId,

            'sale_id' => $this->saleId,

            'customer_id' => $this->customerId,

            'payment_number' =>
                $this->paymentNumber,

            'payment_date' =>
                $this->paymentDate?->format(
                    'Y-m-d'
                ),

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

    private function positiveInteger(
        mixed $value,
        string $label
    ): int {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        $integer = (int) $value;

        if ($integer < 1) {
            throw new InvalidArgumentException(
                $label
                . ' must be greater than zero.'
            );
        }

        return $integer;
    }

    private function optionalPositiveInteger(
        mixed $value,
        string $label
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                $label
                . ' must be a valid identifier.'
            );
        }

        $integer = (int) $value;

        if ($integer < 1) {
            throw new InvalidArgumentException(
                $label
                . ' must be greater than zero.'
            );
        }

        return $integer;
    }

    private function requiredString(
        mixed $value,
        string $label,
        ?int $maximumLength = null
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        if (
            $maximumLength !== null
            && mb_strlen($value)
                > $maximumLength
        ) {
            throw new InvalidArgumentException(
                $label
                . ' must not exceed '
                . $maximumLength
                . ' characters.'
            );
        }

        return $value;
    }

    private function optionalString(
        mixed $value,
        ?int $maximumLength = null
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Expected a valid string.'
            );
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            $maximumLength !== null
            && mb_strlen($value)
                > $maximumLength
        ) {
            throw new InvalidArgumentException(
                'Value must not exceed '
                . $maximumLength
                . ' characters.'
            );
        }

        return $value;
    }

    private function requiredDate(
        mixed $value,
        string $label
    ): DateTimeImmutable {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            throw new InvalidArgumentException(
                $label . ' is required.'
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
                $label
                . ' must be a valid date.'
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
                'Payment amount is required.'
            );
        }

        $amount = round(
            (float) $value,
            4
        );

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Payment amount must be greater than zero.'
            );
        }

        return $amount;
    }

    private function normalizePaymentMethod(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Payment method is required.'
            );
        }

        $value = strtolower(
            trim($value)
        );

        if ($value === '') {
            $value = 'cash';
        }

        if (
            !in_array(
                $value,
                self::paymentMethods(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid sale payment method.'
            );
        }

        return $value;
    }
}