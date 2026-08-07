<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class WarehouseStock extends BaseModel
{
    private int $companyId;
    private int $warehouseId;
    private int $productId;

    private float $quantity = 0.0;
    private float $reservedQuantity = 0.0;
    private float $averageCost = 0.0;

    private ?\DateTimeImmutable $lastMovementAt = null;

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

        $this->warehouseId = $this->requiredPositiveInteger(
            $data['warehouse_id'] ?? null,
            'Warehouse ID'
        );

        $this->productId = $this->requiredPositiveInteger(
            $data['product_id'] ?? null,
            'Product ID'
        );

        $this->quantity = $this->decimalValue(
            $data['quantity'] ?? 0,
            'Quantity',
            true
        );

        $this->reservedQuantity = $this->decimalValue(
            $data['reserved_quantity'] ?? 0,
            'Reserved quantity',
            false
        );

        $this->averageCost = $this->decimalValue(
            $data['average_cost'] ?? 0,
            'Average cost',
            false
        );

        $this->lastMovementAt = $this->nullableDateTime(
            $data['last_movement_at'] ?? null
        );
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function reservedQuantity(): float
    {
        return $this->reservedQuantity;
    }

    public function availableQuantity(): float
    {
        return $this->quantity - $this->reservedQuantity;
    }

    public function averageCost(): float
    {
        return $this->averageCost;
    }

    public function lastMovementAt(): ?\DateTimeImmutable
    {
        return $this->lastMovementAt;
    }

    public function hasStock(): bool
    {
        return $this->quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->availableQuantity() <= 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'company_id' => $this->companyId,
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'reserved_quantity' => $this->reservedQuantity,
            'available_quantity' => $this->availableQuantity(),
            'average_cost' => $this->averageCost,
            'last_movement_at' => $this->lastMovementAt?->format(
                'Y-m-d H:i:s'
            ),
            'created_at' => $this->createdAt()?->format(
                'Y-m-d H:i:s'
            ),
            'updated_at' => $this->updatedAt()?->format(
                'Y-m-d H:i:s'
            ),
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

    private function decimalValue(
        mixed $value,
        string $field,
        bool $allowNegative
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

        if (!is_finite($number)) {
            throw new InvalidArgumentException(
                "{$field} must be a finite number."
            );
        }

        if (!$allowNegative && $number < 0) {
            throw new InvalidArgumentException(
                "{$field} must be zero or greater."
            );
        }

        return $number;
    }
}