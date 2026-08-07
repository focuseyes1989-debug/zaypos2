<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class Tax extends BaseModel
{
    private int $companyId;
    private string $name;
    private string $code;
    private string $taxType = 'percentage';
    private float $rate = 0.0;
    private bool $priceIncludesTax = false;
    private bool $appliesToSales = true;
    private bool $appliesToPurchases = false;
    private bool $isDefault = false;
    private ?string $description = null;
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
            'Tax name',
            160
        );

        $this->code = $this->requiredString(
            $data['code'] ?? null,
            'Tax code',
            80
        );

        $this->taxType = $this->validateTaxType(
            $data['tax_type'] ?? 'percentage'
        );

        $this->rate = $this->validateRate(
            $data['rate'] ?? 0,
            $this->taxType
        );

        $this->priceIncludesTax = $this->booleanValue(
            $data['price_includes_tax'] ?? false
        );

        $this->appliesToSales = $this->booleanValue(
            $data['applies_to_sales'] ?? true
        );

        $this->appliesToPurchases = $this->booleanValue(
            $data['applies_to_purchases'] ?? false
        );

        $this->isDefault = $this->booleanValue(
            $data['is_default'] ?? false
        );

        $this->description = $this->nullableString(
            $data['description'] ?? null,
            500
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

    public function taxType(): string
    {
        return $this->taxType;
    }

    public function rate(): float
    {
        return $this->rate;
    }

    public function priceIncludesTax(): bool
    {
        return $this->priceIncludesTax;
    }

    public function appliesToSales(): bool
    {
        return $this->appliesToSales;
    }

    public function appliesToPurchases(): bool
    {
        return $this->appliesToPurchases;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function description(): ?string
    {
        return $this->description;
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

    public function isPercentage(): bool
    {
        return $this->taxType === 'percentage';
    }

    public function isFixed(): bool
    {
        return $this->taxType === 'fixed';
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
            'tax_type' => $this->taxType,
            'rate' => $this->rate,
            'price_includes_tax' => $this->priceIncludesTax,
            'applies_to_sales' => $this->appliesToSales,
            'applies_to_purchases' => $this->appliesToPurchases,
            'is_default' => $this->isDefault,
            'description' => $this->description,
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

    private function validateTaxType(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Tax type is invalid.'
            );
        }

        $taxType = strtolower(trim($value));

        if (!in_array($taxType, ['percentage', 'fixed'], true)) {
            throw new InvalidArgumentException(
                'Tax type must be percentage or fixed.'
            );
        }

        return $taxType;
    }

    private function validateRate(
        mixed $value,
        string $taxType
    ): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                'Tax rate must be numeric.'
            );
        }

        $rate = round((float) $value, 4);

        if ($rate < 0) {
            throw new InvalidArgumentException(
                'Tax rate must not be negative.'
            );
        }

        if (
            $taxType === 'percentage'
            && $rate > 100
        ) {
            throw new InvalidArgumentException(
                'Percentage tax rate must not exceed 100.'
            );
        }

        if ($rate > 99999999999999.9999) {
            throw new InvalidArgumentException(
                'Tax rate is too large.'
            );
        }

        return $rate;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                ['1', 'true', 'yes', 'on'],
                true
            );
        }

        return false;
    }

    private function validateStatus(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Tax status is invalid.'
            );
        }

        $status = strtolower(trim($value));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(
                'Tax status must be active or inactive.'
            );
        }

        return $status;
    }
}