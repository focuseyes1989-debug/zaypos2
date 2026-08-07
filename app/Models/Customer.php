<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class Customer extends BaseModel
{
    private int $companyId;
    private string $name;
    private string $code;
    private ?string $phone = null;
    private ?string $email = null;
    private ?string $address = null;
    private ?string $taxNumber = null;
    private ?string $customerGroup = null;
    private float $creditLimit = 0.0;
    private float $openingBalance = 0.0;
    private ?string $notes = null;
    private string $status = 'active';
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

        $this->name = $this->requiredString(
            $data['name'] ?? null,
            'Customer name',
            160
        );

        $this->code = $this->requiredString(
            $data['code'] ?? null,
            'Customer code',
            80
        );

        $this->phone = $this->nullableString(
            $data['phone'] ?? null,
            50
        );

        $this->email = $this->nullableEmail(
            $data['email'] ?? null
        );

        $this->address = $this->nullableString(
            $data['address'] ?? null,
            500
        );

        $this->taxNumber = $this->nullableString(
            $data['tax_number'] ?? null,
            100
        );

        $this->customerGroup = $this->nullableString(
            $data['customer_group'] ?? null,
            100
        );

        $this->creditLimit = $this->nonNegativeDecimal(
            $data['credit_limit'] ?? 0,
            'Credit limit'
        );

        $this->openingBalance = $this->decimal(
            $data['opening_balance'] ?? 0,
            'Opening balance'
        );

        $this->notes = $this->nullableText(
            $data['notes'] ?? null
        );

        $this->status = $this->validateStatus(
            $data['status'] ?? 'active'
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

    public function name(): string
    {
        return $this->name;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function taxNumber(): ?string
    {
        return $this->taxNumber;
    }

    public function customerGroup(): ?string
    {
        return $this->customerGroup;
    }

    public function creditLimit(): float
    {
        return $this->creditLimit;
    }

    public function openingBalance(): float
    {
        return $this->openingBalance;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function status(): string
    {
        return $this->status;
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'company_id' => $this->companyId,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'tax_number' => $this->taxNumber,
            'customer_group' => $this->customerGroup,
            'credit_limit' => $this->creditLimit,
            'opening_balance' => $this->openingBalance,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'deleted_by' => $this->deletedBy,
            'created_at' => $this->createdAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt()?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function requiredPositiveInteger(
        mixed $value,
        string $field
    ): int {
        $integer = $this->nullablePositiveInteger($value);

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

        if (mb_strlen($value) > $maximumLength) {
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
        if ($value === null || $value === '') {
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

        if (mb_strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(
                "Text must not exceed {$maximumLength} characters."
            );
        }

        return $value;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $email = $this->nullableString($value, 190);

        if ($email === null) {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                'Customer email address is invalid.'
            );
        }

        return mb_strtolower($email);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Expected a text value.'
            );
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nonNegativeDecimal(
        mixed $value,
        string $field
    ): float {
        $decimal = $this->decimal($value, $field);

        if ($decimal < 0) {
            throw new InvalidArgumentException(
                "{$field} must not be negative."
            );
        }

        return $decimal;
    }

    private function decimal(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "{$field} must be numeric."
            );
        }

        return round((float) $value, 2);
    }

    private function validateStatus(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Customer status is invalid.'
            );
        }

        $status = strtolower(trim($value));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(
                'Customer status must be active or inactive.'
            );
        }

        return $status;
    }
}