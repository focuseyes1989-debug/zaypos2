<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class Product extends BaseModel
{
    private int $companyId;

    private ?int $categoryId = null;
    private ?int $brandId = null;

    private int $baseUnitId;
    private ?int $purchaseUnitId = null;
    private ?int $saleUnitId = null;
    private ?int $taxId = null;

    private string $name;
    private string $code;

    private ?string $sku = null;
    private ?string $barcode = null;

    private string $productType = 'stock';

    private float $purchasePrice = 0.0;
    private float $salePrice = 0.0;
    private float $wholesalePrice = 0.0;

    private bool $trackStock = true;
    private bool $allowNegativeStock = false;

    private float $reorderLevel = 0.0;

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

        $this->categoryId = $this->nullablePositiveInteger(
            $data['category_id'] ?? null
        );

        $this->brandId = $this->nullablePositiveInteger(
            $data['brand_id'] ?? null
        );

        $this->baseUnitId = $this->requiredPositiveInteger(
            $data['base_unit_id'] ?? null,
            'Base unit ID'
        );

        $this->purchaseUnitId = $this->nullablePositiveInteger(
            $data['purchase_unit_id'] ?? null
        );

        $this->saleUnitId = $this->nullablePositiveInteger(
            $data['sale_unit_id'] ?? null
        );

        $this->taxId = $this->nullablePositiveInteger(
            $data['tax_id'] ?? null
        );

        $this->name = $this->requiredString(
            $data['name'] ?? null,
            'Product name',
            190
        );

        $this->code = $this->requiredString(
            $data['code'] ?? null,
            'Product code',
            80
        );

        $this->sku = $this->nullableString(
            $data['sku'] ?? null,
            100
        );

        $this->barcode = $this->nullableString(
            $data['barcode'] ?? null,
            100
        );

        $this->productType = $this->validateProductType(
            $data['product_type'] ?? 'stock'
        );

        $this->purchasePrice = $this->nonNegativeNumber(
            $data['purchase_price'] ?? 0,
            'Purchase price'
        );

        $this->salePrice = $this->nonNegativeNumber(
            $data['sale_price'] ?? 0,
            'Sale price'
        );

        $this->wholesalePrice = $this->nonNegativeNumber(
            $data['wholesale_price'] ?? 0,
            'Wholesale price'
        );

        $this->trackStock = $this->booleanValue(
            $data['track_stock'] ?? true
        );

        $this->allowNegativeStock = $this->booleanValue(
            $data['allow_negative_stock'] ?? false
        );

        $this->reorderLevel = $this->nonNegativeNumber(
            $data['reorder_level'] ?? 0,
            'Reorder level'
        );

        $this->description = $this->nullableText(
            $data['description'] ?? null
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

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function brandId(): ?int
    {
        return $this->brandId;
    }

    public function baseUnitId(): int
    {
        return $this->baseUnitId;
    }

    public function purchaseUnitId(): ?int
    {
        return $this->purchaseUnitId;
    }

    public function saleUnitId(): ?int
    {
        return $this->saleUnitId;
    }

    public function taxId(): ?int
    {
        return $this->taxId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function barcode(): ?string
    {
        return $this->barcode;
    }

    public function productType(): string
    {
        return $this->productType;
    }

    public function purchasePrice(): float
    {
        return $this->purchasePrice;
    }

    public function salePrice(): float
    {
        return $this->salePrice;
    }

    public function wholesalePrice(): float
    {
        return $this->wholesalePrice;
    }

    public function tracksStock(): bool
    {
        return $this->trackStock;
    }

    public function allowsNegativeStock(): bool
    {
        return $this->allowNegativeStock;
    }

    public function reorderLevel(): float
    {
        return $this->reorderLevel;
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

    public function isStockProduct(): bool
    {
        return $this->productType === 'stock';
    }

    public function isServiceProduct(): bool
    {
        return $this->productType === 'service';
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

            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,

            'base_unit_id' => $this->baseUnitId,
            'purchase_unit_id' => $this->purchaseUnitId,
            'sale_unit_id' => $this->saleUnitId,
            'tax_id' => $this->taxId,

            'name' => $this->name,
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,

            'product_type' => $this->productType,

            'purchase_price' => $this->purchasePrice,
            'sale_price' => $this->salePrice,
            'wholesale_price' => $this->wholesalePrice,

            'track_stock' => $this->trackStock,
            'allow_negative_stock' => $this->allowNegativeStock,
            'reorder_level' => $this->reorderLevel,

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

    private function nonNegativeNumber(
        mixed $value,
        string $field
    ): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "{$field} must be a valid number."
            );
        }

        $number = (float) $value;

        if (!is_finite($number) || $number < 0) {
            throw new InvalidArgumentException(
                "{$field} must be zero or greater."
            );
        }

        return $number;
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

    private function validateProductType(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Product type is invalid.'
            );
        }

        $type = strtolower(trim($value));

        if (!in_array($type, ['stock', 'service'], true)) {
            throw new InvalidArgumentException(
                'Product type must be stock or service.'
            );
        }

        return $type;
    }

    private function validateStatus(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Product status is invalid.'
            );
        }

        $status = strtolower(trim($value));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(
                'Product status must be active or inactive.'
            );
        }

        return $status;
    }
}