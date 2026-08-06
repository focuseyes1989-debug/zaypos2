<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class Category extends BaseModel
{
    private int $companyId;

    private ?int $parentId = null;

    private string $name;

    private string $code;

    private ?string $description = null;

    private int $sortOrder = 0;

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

        $this->parentId = $this->nullablePositiveInteger(
            $data['parent_id'] ?? null
        );

        $this->name = $this->requiredString(
            $data['name'] ?? null,
            'Category name',
            120
        );

        $this->code = $this->requiredString(
            $data['code'] ?? null,
            'Category code',
            80
        );

        $this->description = $this->nullableString(
            $data['description'] ?? null,
            500
        );

        $this->sortOrder = $this->nonNegativeInteger(
            $data['sort_order'] ?? 0,
            'Sort order'
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

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
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
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
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

    private function nonNegativeInteger(
        mixed $value,
        string $field
    ): int {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "{$field} must be numeric."
            );
        }

        $integer = (int) $value;

        if ($integer < 0) {
            throw new InvalidArgumentException(
                "{$field} must not be negative."
            );
        }

        return $integer;
    }

    private function validateStatus(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Category status is invalid.'
            );
        }

        $status = strtolower(trim($value));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(
                'Category status must be active or inactive.'
            );
        }

        return $status;
    }
}