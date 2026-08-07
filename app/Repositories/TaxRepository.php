<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Tax;
use RuntimeException;
use Throwable;

/**
 * @extends BaseRepository<Tax>
 */
final class TaxRepository extends BaseRepository
{
    protected string $table = 'taxes';

    protected string $modelClass = Tax::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'name',
        'code',
        'tax_type',
        'rate',
        'price_includes_tax',
        'applies_to_sales',
        'applies_to_purchases',
        'is_default',
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
        'name',
        'code',
        'tax_type',
        'rate',
        'price_includes_tax',
        'applies_to_sales',
        'applies_to_purchases',
        'is_default',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'name',
        'code',
        'tax_type',
        'rate',
        'price_includes_tax',
        'applies_to_sales',
        'applies_to_purchases',
        'is_default',
        'description',
        'status',
        'updated_by',
    ];

    /**
     * @return array{
     *     items: list<Tax>,
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
                OR `tax_type` LIKE :search_type
                OR `description` LIKE :search_description
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_type'] = $searchValue;
            $parameters['search_description'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `taxes`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `taxes`
             WHERE {$where}
             ORDER BY
                `is_default` DESC,
                `name` ASC,
                `id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Tax> $items */
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
        int $taxId,
        bool $includeDeleted = false
    ): ?Tax {
        $tax = $this->findById(
            $companyId,
            $taxId,
            $includeDeleted
        );

        return $tax instanceof Tax
            ? $tax
            : null;
    }

    public function findDefault(
        int $companyId
    ): ?Tax {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `taxes`
             WHERE `company_id` = :company_id
               AND `is_default` = 1
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY `id` ASC
             LIMIT 1",
            [
                'company_id' => $companyId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $tax = $this->hydrate($row);

        return $tax instanceof Tax
            ? $tax
            : null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     tax_type: string,
     *     rate: float,
     *     price_includes_tax: bool,
     *     applies_to_sales: bool,
     *     applies_to_purchases: bool,
     *     is_default: bool
     * }>
     */
    public function activeOptions(
        int $companyId
    ): array {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `tax_type`,
                `rate`,
                `price_includes_tax`,
                `applies_to_sales`,
                `applies_to_purchases`,
                `is_default`
             FROM `taxes`
             WHERE `company_id` = :company_id
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY
                `is_default` DESC,
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
                'tax_type' => (string) $row['tax_type'],
                'rate' => (float) $row['rate'],
                'price_includes_tax' => (bool) $row[
                    'price_includes_tax'
                ],
                'applies_to_sales' => (bool) $row[
                    'applies_to_sales'
                ],
                'applies_to_purchases' => (bool) $row[
                    'applies_to_purchases'
                ],
                'is_default' => (bool) $row['is_default'],
            ];
        }

        return $options;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     tax_type: string,
     *     rate: float,
     *     price_includes_tax: bool,
     *     is_default: bool
     * }>
     */
    public function activeSalesOptions(
        int $companyId
    ): array {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `tax_type`,
                `rate`,
                `price_includes_tax`,
                `is_default`
             FROM `taxes`
             WHERE `company_id` = :company_id
               AND `applies_to_sales` = 1
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY
                `is_default` DESC,
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
                'tax_type' => (string) $row['tax_type'],
                'rate' => (float) $row['rate'],
                'price_includes_tax' => (bool) $row[
                    'price_includes_tax'
                ],
                'is_default' => (bool) $row['is_default'],
            ];
        }

        return $options;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     tax_type: string,
     *     rate: float,
     *     price_includes_tax: bool,
     *     is_default: bool
     * }>
     */
    public function activePurchaseOptions(
        int $companyId
    ): array {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `tax_type`,
                `rate`,
                `price_includes_tax`,
                `is_default`
             FROM `taxes`
             WHERE `company_id` = :company_id
               AND `applies_to_purchases` = 1
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY
                `is_default` DESC,
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
                'tax_type' => (string) $row['tax_type'],
                'rate' => (float) $row['rate'],
                'price_includes_tax' => (bool) $row[
                    'price_includes_tax'
                ],
                'is_default' => (bool) $row['is_default'],
            ];
        }

        return $options;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptTaxId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'code',
            $code,
            $exceptTaxId
        );
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptTaxId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'name',
            $name,
            $exceptTaxId,
            true
        );
    }

    public function hasOtherDefault(
        int $companyId,
        ?int $exceptTaxId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM `taxes`
                WHERE `company_id` = :company_id
                  AND `is_default` = 1
                  AND `deleted_at` IS NULL';

        $parameters = [
            'company_id' => $companyId,
        ];

        if ($exceptTaxId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $parameters['except_id'] = $exceptTaxId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        ) > 0;
    }

    public function countActive(
        int $companyId,
        ?int $exceptTaxId = null
    ): int {
        $sql = "SELECT COUNT(*)
                FROM `taxes`
                WHERE `company_id` = :company_id
                  AND `status` = 'active'
                  AND `deleted_at` IS NULL";

        $parameters = [
            'company_id' => $companyId,
        ];

        if ($exceptTaxId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $parameters['except_id'] = $exceptTaxId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        );
    }

    public function clearDefault(
        int $companyId,
        int $userId,
        ?int $exceptTaxId = null
    ): void {
        $sql = 'UPDATE `taxes`
                SET `is_default` = 0,
                    `updated_by` = :updated_by,
                    `updated_at` = UTC_TIMESTAMP()
                WHERE `company_id` = :company_id
                  AND `is_default` = 1
                  AND `deleted_at` IS NULL';

        $parameters = [
            'updated_by' => $userId,
            'company_id' => $companyId,
        ];

        if ($exceptTaxId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $parameters['except_id'] = $exceptTaxId;
        }

        $this->execute(
            $sql,
            $parameters
        );
    }

    /**
     * @param array{
     *     company_id: int,
     *     name: string,
     *     code: string,
     *     tax_type: string,
     *     rate: float|int|string,
     *     price_includes_tax: bool|int,
     *     applies_to_sales: bool|int,
     *     applies_to_purchases: bool|int,
     *     is_default: bool|int,
     *     description: string|null,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(
        array $data
    ): Tax {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'tax_type' => $data['tax_type'],
                'rate' => $data['rate'],
                'price_includes_tax' => (int) $data[
                    'price_includes_tax'
                ],
                'applies_to_sales' => (int) $data[
                    'applies_to_sales'
                ],
                'applies_to_purchases' => (int) $data[
                    'applies_to_purchases'
                ],
                'is_default' => (int) $data['is_default'],
                'description' => $data['description'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'updated_by' => $data['created_by'],
            ],
            $this->createColumns
        );

        $this->execute(
            'INSERT INTO `taxes` (
                `company_id`,
                `name`,
                `code`,
                `tax_type`,
                `rate`,
                `price_includes_tax`,
                `applies_to_sales`,
                `applies_to_purchases`,
                `is_default`,
                `description`,
                `status`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :name,
                :code,
                :tax_type,
                :rate,
                :price_includes_tax,
                :applies_to_sales,
                :applies_to_purchases,
                :is_default,
                :description,
                :status,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $taxId = (int) $this->connection()->lastInsertId();

        $tax = $this->find(
            (int) $data['company_id'],
            $taxId
        );

        if (!$tax instanceof Tax) {
            throw new RuntimeException(
                'The tax was created but could not be reloaded.'
            );
        }

        return $tax;
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     tax_type: string,
     *     rate: float|int|string,
     *     price_includes_tax: bool|int,
     *     applies_to_sales: bool|int,
     *     applies_to_purchases: bool|int,
     *     is_default: bool|int,
     *     description: string|null,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $taxId,
        array $data
    ): ?Tax {
        $values = $this->onlyAllowedColumns(
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'tax_type' => $data['tax_type'],
                'rate' => $data['rate'],
                'price_includes_tax' => (int) $data[
                    'price_includes_tax'
                ],
                'applies_to_sales' => (int) $data[
                    'applies_to_sales'
                ],
                'applies_to_purchases' => (int) $data[
                    'applies_to_purchases'
                ],
                'is_default' => (int) $data['is_default'],
                'description' => $data['description'],
                'status' => $data['status'],
                'updated_by' => $data['updated_by'],
            ],
            $this->updateColumns
        );

        $values['tax_id'] = $taxId;
        $values['company_id'] = $companyId;

        $this->execute(
            'UPDATE `taxes`
             SET
                `name` = :name,
                `code` = :code,
                `tax_type` = :tax_type,
                `rate` = :rate,
                `price_includes_tax` = :price_includes_tax,
                `applies_to_sales` = :applies_to_sales,
                `applies_to_purchases` = :applies_to_purchases,
                `is_default` = :is_default,
                `description` = :description,
                `status` = :status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :tax_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $taxId
        );
    }

    /**
     * @template TResult
     *
     * @param callable(self): TResult $callback
     *
     * @return TResult
     */
    public function transaction(
        callable $callback
    ): mixed {
        $connection = $this->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $result = $callback($this);

            if ($ownsTransaction) {
                $connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $connection->inTransaction()
            ) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}