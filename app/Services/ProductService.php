<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Unit;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;

final class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository =
            new ProductRepository(),
        private readonly CategoryRepository $categoryRepository =
            new CategoryRepository(),
        private readonly BrandRepository $brandRepository =
            new BrandRepository(),
        private readonly UnitRepository $unitRepository =
            new UnitRepository(),
        private readonly TaxRepository $taxRepository =
            new TaxRepository(),
        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Product>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        array $filters
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        $status = strtolower(
            trim((string) ($filters['status'] ?? ''))
        );

        $productType = strtolower(
            trim((string) ($filters['product_type'] ?? ''))
        );

        $categoryId = $this->nullableId(
            $filters['category_id'] ?? null
        );

        $brandId = $this->nullableId(
            $filters['brand_id'] ?? null
        );

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = (int) ($filters['per_page'] ?? 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $onlyDeleted = filter_var(
            $filters['deleted'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (mb_strlen($search) > 190) {
            throw new ValidationException(
                'Search text must be 190 characters or fewer.'
            );
        }

        if (
            $status !== ''
            && !in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid product status filter.'
            );
        }

        if (
            $productType !== ''
            && !in_array(
                $productType,
                ['stock', 'service'],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid product type filter.'
            );
        }

        return $this->repository->paginate(
            $companyId,
            $search,
            $status,
            $productType,
            $categoryId,
            $brandId,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $productId,
        bool $includeDeleted = false
    ): Product {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        $product = $this->repository->find(
            $companyId,
            $productId,
            $includeDeleted
        );

        if (!$product instanceof Product) {
            throw new ValidationException(
                'Product not found.'
            );
        }

        return $product;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeOptions(
        int $companyId
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        return $this->repository->activeOptions(
            $companyId
        );
    }

    public function findByBarcode(
        int $companyId,
        string $barcode
    ): ?Product {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $barcode = trim($barcode);

        if ($barcode === '') {
            return null;
        }

        return $this->repository->findByBarcode(
            $companyId,
            $barcode
        );
    }

    public function findBySku(
        int $companyId,
        string $sku
    ): ?Product {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $sku = trim($sku);

        if ($sku === '') {
            return null;
        }

        return $this->repository->findBySku(
            $companyId,
            $sku
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): Product {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $data = $this->validateProduct(
            $companyId,
            $input
        );

        $product = $this->repository->create([
            'company_id' => $companyId,
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'],
            'base_unit_id' => $data['base_unit_id'],
            'purchase_unit_id' => $data['purchase_unit_id'],
            'sale_unit_id' => $data['sale_unit_id'],
            'tax_id' => $data['tax_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'sku' => $data['sku'],
            'barcode' => $data['barcode'],
            'product_type' => $data['product_type'],
            'purchase_price' => $data['purchase_price'],
            'sale_price' => $data['sale_price'],
            'wholesale_price' => $data['wholesale_price'],
            'track_stock' => $data['track_stock'],
            'allow_negative_stock' =>
                $data['allow_negative_stock'],
            'reorder_level' => $data['reorder_level'],
            'description' => $data['description'],
            'status' => $data['status'],
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'products.created',
            'product',
            $product->id(),
            [
                'product' => $product->toArray(),
            ]
        );

        return $product;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $productId,
        int $userId,
        array $input
    ): Product {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $existing = $this->find(
            $companyId,
            $productId
        );

        $data = $this->validateProduct(
            $companyId,
            $input,
            $productId
        );

        $updated = $this->repository->update(
            $companyId,
            $productId,
            [
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'base_unit_id' => $data['base_unit_id'],
                'purchase_unit_id' =>
                    $data['purchase_unit_id'],
                'sale_unit_id' => $data['sale_unit_id'],
                'tax_id' => $data['tax_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'sku' => $data['sku'],
                'barcode' => $data['barcode'],
                'product_type' => $data['product_type'],
                'purchase_price' =>
                    $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'wholesale_price' =>
                    $data['wholesale_price'],
                'track_stock' => $data['track_stock'],
                'allow_negative_stock' =>
                    $data['allow_negative_stock'],
                'reorder_level' => $data['reorder_level'],
                'description' => $data['description'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Product) {
            throw new ValidationException(
                'Product could not be updated.'
            );
        }

        $this->audit->record(
            'products.updated',
            'product',
            $productId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $productId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $product = $this->find(
            $companyId,
            $productId
        );

        $deleted = $this->repository->softDelete(
            $companyId,
            $productId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Product could not be deleted.'
            );
        }

        $this->audit->record(
            'products.deleted',
            'product',
            $productId,
            [
                'product' => $product->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $productId,
        int $userId
    ): Product {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $productId,
            'Product ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $product = $this->find(
            $companyId,
            $productId,
            true
        );

        if (!$product->isDeleted()) {
            throw new ValidationException(
                'Product is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $product->code(),
                $productId
            )
        ) {
            throw new ValidationException(
                'Another product is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $product->name(),
                $productId
            )
        ) {
            throw new ValidationException(
                'Another product is already using this name.'
            );
        }

        if (
            $product->sku() !== null
            && $this->repository->skuExists(
                $companyId,
                $product->sku(),
                $productId
            )
        ) {
            throw new ValidationException(
                'Another product is already using this SKU.'
            );
        }

        if (
            $product->barcode() !== null
            && $this->repository->barcodeExists(
                $companyId,
                $product->barcode(),
                $productId
            )
        ) {
            throw new ValidationException(
                'Another product is already using this barcode.'
            );
        }

        $this->validateReferences(
            $companyId,
            $product->categoryId(),
            $product->brandId(),
            $product->baseUnitId(),
            $product->purchaseUnitId(),
            $product->saleUnitId(),
            $product->taxId()
        );

        $restored = $this->repository->restore(
            $companyId,
            $productId,
            $userId
        );

        if (!$restored) {
            throw new ValidationException(
                'Product could not be restored.'
            );
        }

        $product = $this->find(
            $companyId,
            $productId
        );

        $this->audit->record(
            'products.restored',
            'product',
            $productId,
            [
                'product' => $product->toArray(),
            ]
        );

        return $product;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     category_id: int|null,
     *     brand_id: int|null,
     *     base_unit_id: int,
     *     purchase_unit_id: int,
     *     sale_unit_id: int,
     *     tax_id: int|null,
     *     name: string,
     *     code: string,
     *     sku: string|null,
     *     barcode: string|null,
     *     product_type: string,
     *     purchase_price: float,
     *     sale_price: float,
     *     wholesale_price: float,
     *     track_stock: bool,
     *     allow_negative_stock: bool,
     *     reorder_level: float,
     *     description: string|null,
     *     status: string
     * }
     */
    private function validateProduct(
        int $companyId,
        array $input,
        ?int $productId = null
    ): array {
        $name = trim(
            (string) ($input['name'] ?? '')
        );

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
        );

        $sku = trim(
            (string) ($input['sku'] ?? '')
        );

        $barcode = trim(
            (string) ($input['barcode'] ?? '')
        );

        $productType = strtolower(
            trim(
                (string) (
                    $input['product_type']
                    ?? 'stock'
                )
            )
        );

        $description = trim(
            (string) ($input['description'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        $categoryId = $this->nullableId(
            $input['category_id'] ?? null
        );

        $brandId = $this->nullableId(
            $input['brand_id'] ?? null
        );

        $baseUnitId = $this->requiredId(
            $input['base_unit_id'] ?? null,
            'Base unit'
        );

        $purchaseUnitId = $this->nullableId(
            $input['purchase_unit_id'] ?? null
        ) ?? $baseUnitId;

        $saleUnitId = $this->nullableId(
            $input['sale_unit_id'] ?? null
        ) ?? $baseUnitId;

        $taxId = $this->nullableId(
            $input['tax_id'] ?? null
        );

        $trackStock = $this->booleanInput(
            $input['track_stock'] ?? false
        );

        $allowNegativeStock = $this->booleanInput(
            $input['allow_negative_stock'] ?? false
        );

        if ($name === '') {
            throw new ValidationException(
                'Product name is required.'
            );
        }

        if (mb_strlen($name) > 190) {
            throw new ValidationException(
                'Product name must be 190 characters or fewer.'
            );
        }

        if (
            $code === ''
            || mb_strlen($code) > 80
            || !preg_match(
                '/^[a-z0-9][a-z0-9_-]*$/',
                $code
            )
        ) {
            throw new ValidationException(
                'Product code must start with a letter or number '
                . 'and may contain lowercase letters, numbers, '
                . 'dashes and underscores.'
            );
        }

        if (
            $sku !== ''
            && mb_strlen($sku) > 100
        ) {
            throw new ValidationException(
                'SKU must be 100 characters or fewer.'
            );
        }

        if (
            $barcode !== ''
            && mb_strlen($barcode) > 100
        ) {
            throw new ValidationException(
                'Barcode must be 100 characters or fewer.'
            );
        }

        if (
            !in_array(
                $productType,
                ['stock', 'service'],
                true
            )
        ) {
            throw new ValidationException(
                'Product type must be stock or service.'
            );
        }

        if (
            !in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {
            throw new ValidationException(
                'Product status must be active or inactive.'
            );
        }

        $purchasePrice = $this->validateAmount(
            $input['purchase_price'] ?? 0,
            'Purchase price'
        );

        $salePrice = $this->validateAmount(
            $input['sale_price'] ?? 0,
            'Sale price'
        );

        $wholesalePrice = $this->validateAmount(
            $input['wholesale_price'] ?? 0,
            'Wholesale price'
        );

        $reorderLevel = $this->validateAmount(
            $input['reorder_level'] ?? 0,
            'Reorder level'
        );

        if ($productType === 'service') {
            $trackStock = false;
            $allowNegativeStock = false;
            $reorderLevel = 0.0;
        }

        if (!$trackStock) {
            $allowNegativeStock = false;
            $reorderLevel = 0.0;
        }

        if (
            $description !== ''
            && mb_strlen($description) > 5000
        ) {
            throw new ValidationException(
                'Description must be 5000 characters or fewer.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $code,
                $productId
            )
        ) {
            throw new ValidationException(
                'Product code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $productId
            )
        ) {
            throw new ValidationException(
                'Product name is already in use.'
            );
        }

        if (
            $sku !== ''
            && $this->repository->skuExists(
                $companyId,
                $sku,
                $productId
            )
        ) {
            throw new ValidationException(
                'SKU is already in use.'
            );
        }

        if (
            $barcode !== ''
            && $this->repository->barcodeExists(
                $companyId,
                $barcode,
                $productId
            )
        ) {
            throw new ValidationException(
                'Barcode is already in use.'
            );
        }

        $this->validateReferences(
            $companyId,
            $categoryId,
            $brandId,
            $baseUnitId,
            $purchaseUnitId,
            $saleUnitId,
            $taxId
        );

        return [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'base_unit_id' => $baseUnitId,
            'purchase_unit_id' => $purchaseUnitId,
            'sale_unit_id' => $saleUnitId,
            'tax_id' => $taxId,
            'name' => $name,
            'code' => $code,
            'sku' => $sku === '' ? null : $sku,
            'barcode' => $barcode === '' ? null : $barcode,
            'product_type' => $productType,
            'purchase_price' => $purchasePrice,
            'sale_price' => $salePrice,
            'wholesale_price' => $wholesalePrice,
            'track_stock' => $trackStock,
            'allow_negative_stock' => $allowNegativeStock,
            'reorder_level' => $reorderLevel,
            'description' => $description === ''
                ? null
                : $description,
            'status' => $status,
        ];
    }

    private function validateReferences(
        int $companyId,
        ?int $categoryId,
        ?int $brandId,
        int $baseUnitId,
        int $purchaseUnitId,
        int $saleUnitId,
        ?int $taxId
    ): void {
        if ($categoryId !== null) {
            $category = $this->categoryRepository->find(
                $companyId,
                $categoryId
            );

            if (
                !$category instanceof Category
                || !$category->isActive()
            ) {
                throw new ValidationException(
                    'Selected category is invalid or inactive.'
                );
            }
        }

        if ($brandId !== null) {
            $brand = $this->brandRepository->find(
                $companyId,
                $brandId
            );

            if (
                !$brand instanceof Brand
                || !$brand->isActive()
            ) {
                throw new ValidationException(
                    'Selected brand is invalid or inactive.'
                );
            }
        }

        $baseUnit = $this->unitRepository->find(
            $companyId,
            $baseUnitId
        );

        if (
            !$baseUnit instanceof Unit
            || !$baseUnit->isActive()
        ) {
            throw new ValidationException(
                'Selected base unit is invalid or inactive.'
            );
        }

        $purchaseUnit = $this->unitRepository->find(
            $companyId,
            $purchaseUnitId
        );

        if (
            !$purchaseUnit instanceof Unit
            || !$purchaseUnit->isActive()
        ) {
            throw new ValidationException(
                'Selected purchase unit is invalid or inactive.'
            );
        }

        $saleUnit = $this->unitRepository->find(
            $companyId,
            $saleUnitId
        );

        if (
            !$saleUnit instanceof Unit
            || !$saleUnit->isActive()
        ) {
            throw new ValidationException(
                'Selected sale unit is invalid or inactive.'
            );
        }

        if ($taxId !== null) {
            $tax = $this->taxRepository->find(
                $companyId,
                $taxId
            );

            if (
                !$tax instanceof Tax
                || !$tax->isActive()
            ) {
                throw new ValidationException(
                    'Selected tax is invalid or inactive.'
                );
            }
        }
    }

    private function validateAmount(
        mixed $value,
        string $field
    ): float {
        if ($value === '' || $value === null) {
            $value = 0;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $amount = round(
            (float) $value,
            4
        );

        if (
            !is_finite($amount)
            || $amount < 0
        ) {
            throw new ValidationException(
                "{$field} must be zero or greater."
            );
        }

        if ($amount > 99999999999999.9999) {
            throw new ValidationException(
                "{$field} is too large."
            );
        }

        return $amount;
    }

    private function nullableId(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
            || $value === 0
            || $value === '0'
        ) {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new ValidationException(
                'Invalid related record ID.'
            );
        }

        $id = (int) $value;

        if ($id < 1) {
            throw new ValidationException(
                'Related record ID must be greater than zero.'
            );
        }

        return $id;
    }

    private function requiredId(
        mixed $value,
        string $field
    ): int {
        $id = $this->nullableId($value);

        if ($id === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $id;
    }

    private function booleanInput(
        mixed $value
    ): bool {
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

    private function validatePositiveId(
        int $value,
        string $field
    ): void {
        if ($value < 1) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }
    }
}