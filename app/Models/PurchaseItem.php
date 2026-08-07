<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class PurchaseItem extends BaseModel
{
    private int $purchaseId;
    private int $productId;
    private int $unitId;
    private ?int $taxId = null;

    private float $quantity = 0.0;
    private float $receivedQuantity = 0.0;

    private float $unitCost = 0.0;

    private ?string $discountType = null;
    private float $discountValue = 0.0;
    private float $discountAmount = 0.0;

    private float $taxableAmount = 0.0;
    private float $taxRate = 0.0;
    private float $taxAmount = 0.0;

    private float $lineSubtotal = 0.0;
    private float $lineTotal = 0.0;

    private ?string $notes = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->fillBaseAttributes($data);

        $this->purchaseId = $this->requiredPositiveInteger(
            $data['purchase_id'] ?? null,
            'Purchase ID'
        );

        $this->productId = $this->requiredPositiveInteger(
            $data['product_id'] ?? null,
            'Product ID'
        );

        $this->unitId = $this->requiredPositiveInteger(
            $data['unit_id'] ?? null,
            'Unit ID'
        );

        $this->taxId = $this->nullablePositiveInteger(
            $data['tax_id'] ?? null
        );

        $this->quantity = $this->positiveDecimal(
            $data['quantity'] ?? 0,
            'Quantity'
        );

        $this->receivedQuantity = $this->nonNegativeDecimal(
            $data['received_quantity'] ?? 0,
            'Received quantity'
        );

        if (
            $this->receivedQuantity
            > $this->quantity + 0.00005
        ) {
            throw new InvalidArgumentException(
                'Received quantity must not exceed purchase quantity.'
            );
        }

        $this->unitCost = $this->nonNegativeDecimal(
            $data['unit_cost'] ?? 0,
            'Unit cost'
        );

        $this->discountType = $this->validateDiscountType(
            $data['discount_type'] ?? null
        );

        $this->discountValue = $this->nonNegativeDecimal(
            $data['discount_value'] ?? 0,
            'Discount value'
        );

        if (
            $this->discountType === 'percentage'
            && $this->discountValue > 100
        ) {
            throw new InvalidArgumentException(
                'Percentage discount must not exceed 100.'
            );
        }

        $this->discountAmount = $this->nonNegativeDecimal(
            $data['discount_amount'] ?? 0,
            'Discount amount'
        );

        $this->taxableAmount = $this->nonNegativeDecimal(
            $data['taxable_amount'] ?? 0,
            'Taxable amount'
        );

        $this->taxRate = $this->nonNegativeDecimal(
            $data['tax_rate'] ?? 0,
            'Tax rate'
        );

        if ($this->taxRate > 1000000) {
            throw new InvalidArgumentException(
                'Tax rate is too large.'
            );
        }

        $this->taxAmount = $this->nonNegativeDecimal(
            $data['tax_amount'] ?? 0,
            'Tax amount'
        );

        $this->lineSubtotal = $this->nonNegativeDecimal(
            $data['line_subtotal'] ?? 0,
            'Line subtotal'
        );

        $this->lineTotal = $this->nonNegativeDecimal(
            $data['line_total'] ?? 0,
            'Line total'
        );

        $this->notes = $this->nullableString(
            $data['notes'] ?? null,
            500
        );
    }

    public function purchaseId(): int
    {
        return $this->purchaseId;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function unitId(): int
    {
        return $this->unitId;
    }

    public function taxId(): ?int
    {
        return $this->taxId;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function receivedQuantity(): float
    {
        return $this->receivedQuantity;
    }

    public function remainingQuantity(): float
    {
        return max(
            0.0,
            round(
                $this->quantity - $this->receivedQuantity,
                4
            )
        );
    }

    public function unitCost(): float
    {
        return $this->unitCost;
    }

    public function discountType(): ?string
    {
        return $this->discountType;
    }

    public function discountValue(): float
    {
        return $this->discountValue;
    }

    public function discountAmount(): float
    {
        return $this->discountAmount;
    }

    public function taxableAmount(): float
    {
        return $this->taxableAmount;
    }

    public function taxRate(): float
    {
        return $this->taxRate;
    }

    public function taxAmount(): float
    {
        return $this->taxAmount;
    }

    public function lineSubtotal(): float
    {
        return $this->lineSubtotal;
    }

    public function lineTotal(): float
    {
        return $this->lineTotal;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function isFullyReceived(): bool
    {
        return $this->remainingQuantity() <= 0.00005;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),

            'purchase_id' => $this->purchaseId,
            'product_id' => $this->productId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,

            'quantity' => $this->quantity,
            'received_quantity' => $this->receivedQuantity,
            'remaining_quantity' => $this->remainingQuantity(),

            'unit_cost' => $this->unitCost,

            'discount_type' => $this->discountType,
            'discount_value' => $this->discountValue,
            'discount_amount' => $this->discountAmount,

            'taxable_amount' => $this->taxableAmount,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,

            'line_subtotal' => $this->lineSubtotal,
            'line_total' => $this->lineTotal,

            'notes' => $this->notes,

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

    private function positiveDecimal(
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
            || $number <= 0
        ) {
            throw new InvalidArgumentException(
                "{$field} must be greater than zero."
            );
        }

        return $number;
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

    private function validateDiscountType(
        mixed $value
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Discount type is invalid.'
            );
        }

        $type = strtolower(trim($value));

        if (
            !in_array(
                $type,
                ['fixed', 'percentage'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Discount type must be fixed or percentage.'
            );
        }

        return $type;
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
}