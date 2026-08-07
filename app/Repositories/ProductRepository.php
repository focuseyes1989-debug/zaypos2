<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Product;
use RuntimeException;

/**
 * @extends BaseRepository<Product>
 */
final class ProductRepository extends BaseRepository
{
    protected string $table = 'products';

    protected string $modelClass = Product::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'category_id',
        'brand_id',
        'base_unit_id',
        'purchase_unit_id',
        'sale_unit_id',
        'tax_id',
        'name',
        'code',
        'sku',
        'barcode',
        'image_path',
        'product_type',
        'purchase_price',
        'sale_price',
        'wholesale_price',
        'track_stock',
        'allow_negative_stock',
        'reorder_level',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $createColumns = [
        'company_id',
        'category_id',
        'brand_id',
        'base_unit_id',
        'purchase_unit_id',
        'sale_unit_id',
        'tax_id',
        'name',
        'code',
        'sku',
        'barcode',
        'image_path',
        'product_type',
        'purchase_price',
        'sale_price',
        'wholesale_price',
        'track_stock',
        'allow_negative_stock',
        'reorder_level',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'category_id',
        'brand_id',
        'base_unit_id',
        'purchase_unit_id',
        'sale_unit_id',
        'tax_id',
        'name',
        'code',
        'sku',
        'barcode',
        'image_path',
        'product_type',
        'purchase_price',
        'sale_price',
        'wholesale_price',
        'track_stock',
        'allow_negative_stock',
        'reorder_level',
        'description',
        'status',
        'updated_by',
    ];

    /**
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
        string $search,
        string $status,
        string $productType,
        ?int $categoryId,
        ?int $brandId,
        int $page,
        int $perPage = 20,
        bool $onlyDeleted = false
    ): array {
        $pagination = $this->pagination(
            $page,
            $perPage
        );

        $conditions = [
            '`company_id` = :company_id',
        ];

        $parameters = [
            'company_id' => $companyId,
        ];

        $conditions[] = $onlyDeleted
            ? '`deleted_at` IS NOT NULL'
            : '`deleted_at` IS NULL';

        if ($search !== '') {
            $conditions[] = '(
                `name` LIKE :search_name
                OR `code` LIKE :search_code
                OR `sku` LIKE :search_sku
                OR `barcode` LIKE :search_barcode
                OR `description` LIKE :search_description
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_sku'] = $searchValue;
            $parameters['search_barcode'] = $searchValue;
            $parameters['search_description'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
        }

        if (in_array($productType, ['stock', 'service'], true)) {
            $conditions[] = '`product_type` = :product_type';
            $parameters['product_type'] = $productType;
        }

        if ($categoryId !== null) {
            $conditions[] = '`category_id` = :category_id';
            $parameters['category_id'] = $categoryId;
        }

        if ($brandId !== null) {
            $conditions[] = '`brand_id` = :brand_id';
            $parameters['brand_id'] = $brandId;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `products`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `products`
             WHERE {$where}
             ORDER BY
                `name` ASC,
                `id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Product> $items */
        $items = $this->hydrateMany($rows);

        return $this->paginationResult(
            $items,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    public function find(
        int $companyId,
        int $productId,
        bool $includeDeleted = false
    ): ?Product {
        $product = $this->findById(
            $companyId,
            $productId,
            $includeDeleted
        );

        return $product instanceof Product
            ? $product
            : null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     sku: string|null,
     *     barcode: string|null,
     *     image_path: string|null,
     *     product_type: string,
     *     sale_price: float,
     *     track_stock: bool
     * }>
     */
    public function activeOptions(int $companyId): array
    {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `sku`,
                `barcode`,
                `image_path`,
                `product_type`,
                `sale_price`,
                `track_stock`
             FROM `products`
             WHERE `company_id` = :company_id
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY
                `name` ASC,
                `id` ASC",
            [
                'company_id' => $companyId,
            ]
        );

        $options = [];

        foreach ($rows as $row) {
            $options[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'code' => (string) $row['code'],
                'sku' => $row['sku'] === null
                    ? null
                    : (string) $row['sku'],
                'barcode' => $row['barcode'] === null
                    ? null
                    : (string) $row['barcode'],
                'image_path' => $row['image_path'] === null
                    ? null
                    : (string) $row['image_path'],
                'product_type' => (string) $row['product_type'],
                'sale_price' => (float) $row['sale_price'],
                'track_stock' => (bool) $row['track_stock'],
            ];
        }

        return $options;
    }

    public function findByBarcode(
        int $companyId,
        string $barcode
    ): ?Product {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `products`
             WHERE `company_id` = :company_id
               AND `barcode` = :barcode
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             LIMIT 1",
            [
                'company_id' => $companyId,
                'barcode' => $barcode,
            ]
        );

        if ($row === null) {
            return null;
        }

        $product = $this->hydrate($row);

        return $product instanceof Product
            ? $product
            : null;
    }

    public function findBySku(
        int $companyId,
        string $sku
    ): ?Product {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `products`
             WHERE `company_id` = :company_id
               AND `sku` = :sku
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             LIMIT 1",
            [
                'company_id' => $companyId,
                'sku' => $sku,
            ]
        );

        if ($row === null) {
            return null;
        }

        $product = $this->hydrate($row);

        return $product instanceof Product
            ? $product
            : null;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptProductId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'code',
            $code,
            $exceptProductId
        );
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptProductId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'name',
            $name,
            $exceptProductId,
            true
        );
    }

    public function skuExists(
        int $companyId,
        string $sku,
        ?int $exceptProductId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'sku',
            $sku,
            $exceptProductId
        );
    }

    public function barcodeExists(
        int $companyId,
        string $barcode,
        ?int $exceptProductId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'barcode',
            $barcode,
            $exceptProductId
        );
    }

    /**
     * @param array{
     *     company_id: int,
     *     category_id: int|null,
     *     brand_id: int|null,
     *     base_unit_id: int,
     *     purchase_unit_id: int|null,
     *     sale_unit_id: int|null,
     *     tax_id: int|null,
     *     name: string,
     *     code: string,
     *     sku: string|null,
     *     barcode: string|null,
     *     image_path: string|null,
     *     product_type: string,
     *     purchase_price: float|int|string,
     *     sale_price: float|int|string,
     *     wholesale_price: float|int|string,
     *     track_stock: bool|int,
     *     allow_negative_stock: bool|int,
     *     reorder_level: float|int|string,
     *     description: string|null,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(array $data): Product
    {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' => $data['company_id'],
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
                'image_path' => $data['image_path'],
                'product_type' => $data['product_type'],
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'wholesale_price' => $data['wholesale_price'],
                'track_stock' => (int) $data['track_stock'],
                'allow_negative_stock' => (int) $data[
                    'allow_negative_stock'
                ],
                'reorder_level' => $data['reorder_level'],
                'description' => $data['description'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'updated_by' => $data['created_by'],
            ],
            $this->createColumns
        );

        $this->execute(
            'INSERT INTO `products` (
                `company_id`,
                `category_id`,
                `brand_id`,
                `base_unit_id`,
                `purchase_unit_id`,
                `sale_unit_id`,
                `tax_id`,
                `name`,
                `code`,
                `sku`,
                `barcode`,
                `image_path`,
                `product_type`,
                `purchase_price`,
                `sale_price`,
                `wholesale_price`,
                `track_stock`,
                `allow_negative_stock`,
                `reorder_level`,
                `description`,
                `status`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :category_id,
                :brand_id,
                :base_unit_id,
                :purchase_unit_id,
                :sale_unit_id,
                :tax_id,
                :name,
                :code,
                :sku,
                :barcode,
                :image_path,
                :product_type,
                :purchase_price,
                :sale_price,
                :wholesale_price,
                :track_stock,
                :allow_negative_stock,
                :reorder_level,
                :description,
                :status,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $productId = (int) $this->connection()->lastInsertId();

        $product = $this->find(
            (int) $data['company_id'],
            $productId
        );

        if (!$product instanceof Product) {
            throw new RuntimeException(
                'The product was created but could not be reloaded.'
            );
        }

        return $product;
    }

    /**
     * @param array{
     *     category_id: int|null,
     *     brand_id: int|null,
     *     base_unit_id: int,
     *     purchase_unit_id: int|null,
     *     sale_unit_id: int|null,
     *     tax_id: int|null,
     *     name: string,
     *     code: string,
     *     sku: string|null,
     *     barcode: string|null,
     *     image_path: string|null,
     *     product_type: string,
     *     purchase_price: float|int|string,
     *     sale_price: float|int|string,
     *     wholesale_price: float|int|string,
     *     track_stock: bool|int,
     *     allow_negative_stock: bool|int,
     *     reorder_level: float|int|string,
     *     description: string|null,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $productId,
        array $data
    ): ?Product {
        $values = $this->onlyAllowedColumns(
            [
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
                'image_path' => $data['image_path'],
                'product_type' => $data['product_type'],
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'wholesale_price' => $data['wholesale_price'],
                'track_stock' => (int) $data['track_stock'],
                'allow_negative_stock' => (int) $data[
                    'allow_negative_stock'
                ],
                'reorder_level' => $data['reorder_level'],
                'description' => $data['description'],
                'status' => $data['status'],
                'updated_by' => $data['updated_by'],
            ],
            $this->updateColumns
        );

        $values['product_id'] = $productId;
        $values['company_id'] = $companyId;

        $this->execute(
            'UPDATE `products`
             SET
                `category_id` = :category_id,
                `brand_id` = :brand_id,
                `base_unit_id` = :base_unit_id,
                `purchase_unit_id` = :purchase_unit_id,
                `sale_unit_id` = :sale_unit_id,
                `tax_id` = :tax_id,
                `name` = :name,
                `code` = :code,
                `sku` = :sku,
                `barcode` = :barcode,
                `image_path` = :image_path,
                `product_type` = :product_type,
                `purchase_price` = :purchase_price,
                `sale_price` = :sale_price,
                `wholesale_price` = :wholesale_price,
                `track_stock` = :track_stock,
                `allow_negative_stock` = :allow_negative_stock,
                `reorder_level` = :reorder_level,
                `description` = :description,
                `status` = :status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :product_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $productId
        );
    }
}