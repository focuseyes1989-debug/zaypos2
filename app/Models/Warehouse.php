<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class Warehouse extends BaseModel
{
    private int $companyId;
    private string $name;
    private string $code;
    private ?string $locationName = null;
    private ?string $phone = null;
    private ?string $email = null;
    private ?string $address = null;
    private ?string $managerName = null;
    private bool $isDefault = false;
    private bool $allowNegativeStock = false;
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
            'Warehouse name',
            160
        );

        $this->code = $this->requiredString(
            $data['code'] ?? null,
            'Warehouse code',
            80
        );

        $this->locationName = $this->nullableString(
            $data['location_name'] ?? null,
            160
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

        $this->managerName = $this->nullableString(
            $data['manager_name'] ?? null,
            160
        );

        $this->isDefault = $this->booleanValue(
            $data['is_default'] ?? false
        );

        $this->allowNegativeStock = $this->booleanValue(
            $data['allow_negative_stock'] ?? false
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

    public function locationName(): ?string
    {
        return $this->locationName;
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

    public function managerName(): ?string
    {
        return $this->managerName;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function allowsNegativeStock(): bool
    {
        return $this->allowNegativeStock;
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
            'location_name' => $this->locationName,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'manager_name' => $this->managerName,
            'is_default' => $this->isDefault,
            'allow_negative_stock' => $this->allowNegativeStock,
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
                'Warehouse email address is invalid.'
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
                'Warehouse status is invalid.'
            );
        }

        $status = strtolower(trim($value));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(
                'Warehouse status must be active or inactive.'
            );
        }

        return $status;
    }
}