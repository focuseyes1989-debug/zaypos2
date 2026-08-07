<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class StockMovement extends BaseModel
{
    private const MOVEMENT_TYPES = [
        'opening',
        'purchase',
        'purchase_return',
        'sale',
        'sale_return',
        'adjustment_in',
        'adjustment_out',
        'transfer_in',
        'transfer_out',
    ];

    private int $companyId;
    private int $warehouseId;
    private int $productId;

    private string $movementType;

    private float $quantityIn = 0.0;
    private float $quantityOut = 0.0;

    private float $quantityBefore = 0.0;
    private float $quantityAfter = 0.0;

    private float $unitCost = 0.0;
    private float $totalCost = 0.0;

    private ?string $referenceType = null;
    private ?int $referenceId = null;
    private ?string $referenceNumber = null;

    private ?int $relatedWarehouseId = null;

    private ?string $notes = null;

    private \DateTimeImmutable $movementAt;

    private ?int $createdBy = null;

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

        $this->movementType = $this->validateMovementType(
            $data['movement_type'] ?? null
        );

        $this->quantityIn = $this->nonNegativeDecimal(
            $data['quantity_in'] ?? 0,
            'Quantity in'
        );

        $this->quantityOut = $this->nonNegativeDecimal(
            $data['quantity_out'] ?? 0,
            'Quantity out'
        );

        if (
            $this->quantityIn > 0
            && $this->quantityOut > 0
        ) {
            throw new InvalidArgumentException(
                'A stock movement cannot contain both quantity in and quantity out.'
            );
        }

        if (
            $this->quantityIn <= 0
            && $this->quantityOut <= 0
        ) {
            throw new InvalidArgumentException(
                'A stock movement must contain quantity in or quantity out.'
            );
        }

        $this->quantityBefore = $this->signedDecimal(
            $data['quantity_before'] ?? 0,
            'Quantity before'
        );

        $this->quantityAfter = $this->signedDecimal(
            $data['quantity_after'] ?? 0,
            'Quantity after'
        );

        $this->unitCost = $this->nonNegativeDecimal(
            $data['unit_cost'] ?? 0,
            'Unit cost'
        );

        $this->totalCost = $this->nonNegativeDecimal(
            $data['total_cost'] ?? 0,
            'Total cost'
        );

        $this->referenceType = $this->nullableString(
            $data['reference_type'] ?? null,
            50
        );

        $this->referenceId = $this->nullablePositiveInteger(
            $data['reference_id'] ?? null
        );

        $this->referenceNumber = $this->nullableString(
            $data['reference_number'] ?? null,
            100
        );

        $this->relatedWarehouseId = $this->nullablePositiveInteger(
            $data['related_warehouse_id'] ?? null
        );

        $this->notes = $this->nullableText(
            $data['notes'] ?? null
        );

        $this->movementAt = $this->requiredDateTime(
            $data['movement_at'] ?? null,
            'Movement date and time'
        );

        $this->createdBy = $this->nullablePositiveInteger(
            $data['created_by'] ?? null
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

    public function movementType(): string
    {
        return $this->movementType;
    }

    public function quantityIn(): float
    {
        return $this->quantityIn;
    }

    public function quantityOut(): float
    {
        return $this->quantityOut;
    }

    public function quantityBefore(): float
    {
        return $this->quantityBefore;
    }

    public function quantityAfter(): float
    {
        return $this->quantityAfter;
    }

    public function unitCost(): float
    {
        return $this->unitCost;
    }

    public function totalCost(): float
    {
        return $this->totalCost;
    }

    public function referenceType(): ?string
    {
        return $this->referenceType;
    }

    public function referenceId(): ?int
    {
        return $this->referenceId;
    }

    public function referenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function relatedWarehouseId(): ?int
    {
        return $this->relatedWarehouseId;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function movementAt(): \DateTimeImmutable
    {
        return $this->movementAt;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function isInbound(): bool
    {
        return $this->quantityIn > 0;
    }

    public function isOutbound(): bool
    {
        return $this->quantityOut > 0;
    }

    public function signedQuantity(): float
    {
        return $this->quantityIn - $this->quantityOut;
    }

    public function isTransfer(): bool
    {
        return in_array(
            $this->movementType,
            ['transfer_in', 'transfer_out'],
            true
        );
    }

    public function isAdjustment(): bool
    {
        return in_array(
            $this->movementType,
            ['adjustment_in', 'adjustment_out'],
            true
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
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
            'movement_type' => $this->movementType,

            'quantity_in' => $this->quantityIn,
            'quantity_out' => $this->quantityOut,
            'signed_quantity' => $this->signedQuantity(),

            'quantity_before' => $this->quantityBefore,
            'quantity_after' => $this->quantityAfter,

            'unit_cost' => $this->unitCost,
            'total_cost' => $this->totalCost,

            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'reference_number' => $this->referenceNumber,

            'related_warehouse_id' => $this->relatedWarehouseId,

            'notes' => $this->notes,

            'movement_at' => $this->movementAt->format(
                'Y-m-d H:i:s'
            ),

            'created_by' => $this->createdBy,

            'created_at' => $this->createdAt()?->format(
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

    private function validateMovementType(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Stock movement type is required.'
            );
        }

        $type = strtolower(trim($value));

        if (
            !in_array(
                $type,
                self::MOVEMENT_TYPES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid stock movement type.'
            );
        }

        return $type;
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

    private function signedDecimal(
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

        if (!is_finite($number)) {
            throw new InvalidArgumentException(
                "{$field} must be a finite number."
            );
        }

        return $number;
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

        if (mb_strlen($value) > $maximumLength) {
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

    private function requiredDateTime(
        mixed $value,
        string $field
    ): \DateTimeImmutable {
        $dateTime = $this->nullableDateTime(
            $value
        );

        if ($dateTime === null) {
            throw new InvalidArgumentException(
                "{$field} is required."
            );
        }

        return $dateTime;
    }
}