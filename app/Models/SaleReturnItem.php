<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class SaleReturnItem extends BaseModel
{
    private int $saleReturnId;

    private int $saleItemId;

    private int $productId;

    private int $unitId;

    private ?int $taxId = null;

    private float $quantity = 0.0;

    private float $unitPrice = 0.0;

    private float $discountAmount = 0.0;

    private float $taxableAmount = 0.0;

    private float $taxRate = 0.0;

    private float $taxAmount = 0.0;

    private float $lineSubtotal = 0.0;

    private float $lineTotal = 0.0;

    private ?string $reason = null;

    private ?string $notes = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->fillBaseAttributes($data);

        $this->saleReturnId =
            $this->requiredPositiveInteger(
                $data['sale_return_id'] ?? null,
                'Sale return ID'
            );

        $this->saleItemId =
            $this->requiredPositiveInteger(
                $data['sale_item_id'] ?? null,
                'Sale item ID'
            );

        $this->productId =
            $this->requiredPositiveInteger(
                $data['product_id'] ?? null,
                'Product ID'
            );

        $this->unitId =
            $this->requiredPositiveInteger(
                $data['unit_id'] ?? null,
                'Unit ID'
            );

        $this->taxId =
            $this->nullablePositiveInteger(
                $data['tax_id'] ?? null
            );

        $this->quantity =
            $this->positiveDecimal(
                $data['quantity'] ?? null,
                'Quantity'
            );

        $this->unitPrice =
            $this->nonNegativeDecimal(
                $data['unit_price'] ?? 0,
                'Unit price'
            );

        $this->discountAmount =
            $this->nonNegativeDecimal(
                $data['discount_amount'] ?? 0,
                'Discount amount'
            );

        $this->taxableAmount =
            $this->nonNegativeDecimal(
                $data['taxable_amount'] ?? 0,
                'Taxable amount'
            );

        $this->taxRate =
            $this->nonNegativeDecimal(
                $data['tax_rate'] ?? 0,
                'Tax rate'
            );

        if ($this->taxRate > 1000000) {
            throw new InvalidArgumentException(
                'Tax rate is too large.'
            );
        }

        $this->taxAmount =
            $this->nonNegativeDecimal(
                $data['tax_amount'] ?? 0,
                'Tax amount'
            );

        $this->lineSubtotal =
            $this->nonNegativeDecimal(
                $data['line_subtotal'] ?? 0,
                'Line subtotal'
            );

        $this->lineTotal =
            $this->nonNegativeDecimal(
                $data['line_total'] ?? 0,
                'Line total'
            );

        $this->reason =
            $this->nullableString(
                $data['reason'] ?? null,
                500
            );

        $this->notes =
            $this->nullableText(
                $data['notes'] ?? null
            );
    }

    public function saleReturnId(): int
    {
        return $this->saleReturnId;
    }

    public function saleItemId(): int
    {
        return $this->saleItemId;
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

    public function unitPrice(): float
    {
        return $this->unitPrice;
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

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' =>
                $this->id(),

            'sale_return_id' =>
                $this->saleReturnId,

            'sale_item_id' =>
                $this->saleItemId,

            'product_id' =>
                $this->productId,

            'unit_id' =>
                $this->unitId,

            'tax_id' =>
                $this->taxId,

            'quantity' =>
                $this->quantity,

            'unit_price' =>
                $this->unitPrice,

            'discount_amount' =>
                $this->discountAmount,

            'taxable_amount' =>
                $this->taxableAmount,

            'tax_rate' =>
                $this->taxRate,

            'tax_amount' =>
                $this->taxAmount,

            'line_subtotal' =>
                $this->lineSubtotal,

            'line_total' =>
                $this->lineTotal,

            'reason' =>
                $this->reason,

            'notes' =>
                $this->notes,

            'created_at' =>
                $this->createdAt()?->format(
                    'Y-m-d H:i:s'
                ),

            'updated_at' =>
                $this->updatedAt()?->format(
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