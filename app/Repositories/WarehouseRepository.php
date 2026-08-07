<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Warehouse;
use RuntimeException;
use Throwable;

/**
 * @extends BaseRepository<Warehouse>
 */
final class WarehouseRepository extends BaseRepository
{
    protected string $table = 'warehouses';

    protected string $modelClass = Warehouse::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'name',
        'code',
        'location_name',
        'phone',
        'email',
        'address',
        'manager_name',
        'is_default',
        'allow_negative_stock',
        'notes',
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
        'location_name',
        'phone',
        'email',
        'address',
        'manager_name',
        'is_default',
        'allow_negative_stock',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'name',
        'code',
        'location_name',
        'phone',
        'email',
        'address',
        'manager_name',
        'is_default',
        'allow_negative_stock',
        'notes',
        'status',
        'updated_by',
    ];

    /**
     * @return array{
     *     items: list<Warehouse>,
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
                OR `location_name` LIKE :search_location
                OR `phone` LIKE :search_phone
                OR `email` LIKE :search_email
                OR `manager_name` LIKE :search_manager
                OR `address` LIKE :search_address
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_location'] = $searchValue;
            $parameters['search_phone'] = $searchValue;
            $parameters['search_email'] = $searchValue;
            $parameters['search_manager'] = $searchValue;
            $parameters['search_address'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `warehouses`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `warehouses`
             WHERE {$where}
             ORDER BY
                `is_default` DESC,
                `name` ASC,
                `id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Warehouse> $items */
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
        int $warehouseId,
        bool $includeDeleted = false
    ): ?Warehouse {
        $warehouse = $this->findById(
            $companyId,
            $warehouseId,
            $includeDeleted
        );

        return $warehouse instanceof Warehouse
            ? $warehouse
            : null;
    }

    public function findDefault(
        int $companyId
    ): ?Warehouse {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `warehouses`
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

        $warehouse = $this->hydrate($row);

        return $warehouse instanceof Warehouse
            ? $warehouse
            : null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     location_name: string|null,
     *     is_default: bool,
     *     allow_negative_stock: bool
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
                `location_name`,
                `is_default`,
                `allow_negative_stock`
             FROM `warehouses`
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
                'location_name' => $row['location_name'] === null
                    ? null
                    : (string) $row['location_name'],
                'is_default' => (bool) $row['is_default'],
                'allow_negative_stock' => (bool) $row[
                    'allow_negative_stock'
                ],
            ];
        }

        return $options;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptWarehouseId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'code',
            $code,
            $exceptWarehouseId
        );
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptWarehouseId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'name',
            $name,
            $exceptWarehouseId,
            true
        );
    }

    public function hasOtherDefault(
        int $companyId,
        ?int $exceptWarehouseId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM `warehouses`
                WHERE `company_id` = :company_id
                  AND `is_default` = 1
                  AND `deleted_at` IS NULL';

        $parameters = [
            'company_id' => $companyId,
        ];

        if ($exceptWarehouseId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $parameters['except_id'] = $exceptWarehouseId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        ) > 0;
    }

    public function countActive(
        int $companyId,
        ?int $exceptWarehouseId = null
    ): int {
        $sql = "SELECT COUNT(*)
                FROM `warehouses`
                WHERE `company_id` = :company_id
                  AND `status` = 'active'
                  AND `deleted_at` IS NULL";

        $parameters = [
            'company_id' => $companyId,
        ];

        if ($exceptWarehouseId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $parameters['except_id'] = $exceptWarehouseId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        );
    }

    /**
     * Remove default status from the company's other warehouses.
     */
    public function clearDefault(
        int $companyId,
        int $userId,
        ?int $exceptWarehouseId = null
    ): void {
        $sql = 'UPDATE `warehouses`
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

        if ($exceptWarehouseId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $parameters['except_id'] = $exceptWarehouseId;
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
     *     location_name: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     manager_name: string|null,
     *     is_default: bool|int,
     *     allow_negative_stock: bool|int,
     *     notes: string|null,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(
        array $data
    ): Warehouse {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'location_name' => $data['location_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'manager_name' => $data['manager_name'],
                'is_default' => (int) $data['is_default'],
                'allow_negative_stock' => (int) $data[
                    'allow_negative_stock'
                ],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'updated_by' => $data['created_by'],
            ],
            $this->createColumns
        );

        $this->execute(
            'INSERT INTO `warehouses` (
                `company_id`,
                `name`,
                `code`,
                `location_name`,
                `phone`,
                `email`,
                `address`,
                `manager_name`,
                `is_default`,
                `allow_negative_stock`,
                `notes`,
                `status`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :name,
                :code,
                :location_name,
                :phone,
                :email,
                :address,
                :manager_name,
                :is_default,
                :allow_negative_stock,
                :notes,
                :status,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $warehouseId = (int) $this->connection()->lastInsertId();

        $warehouse = $this->find(
            (int) $data['company_id'],
            $warehouseId
        );

        if (!$warehouse instanceof Warehouse) {
            throw new RuntimeException(
                'The warehouse was created but could not be reloaded.'
            );
        }

        return $warehouse;
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     location_name: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     manager_name: string|null,
     *     is_default: bool|int,
     *     allow_negative_stock: bool|int,
     *     notes: string|null,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $warehouseId,
        array $data
    ): ?Warehouse {
        $values = $this->onlyAllowedColumns(
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'location_name' => $data['location_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'manager_name' => $data['manager_name'],
                'is_default' => (int) $data['is_default'],
                'allow_negative_stock' => (int) $data[
                    'allow_negative_stock'
                ],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'updated_by' => $data['updated_by'],
            ],
            $this->updateColumns
        );

        $values['warehouse_id'] = $warehouseId;
        $values['company_id'] = $companyId;

        $this->execute(
            'UPDATE `warehouses`
             SET
                `name` = :name,
                `code` = :code,
                `location_name` = :location_name,
                `phone` = :phone,
                `email` = :email,
                `address` = :address,
                `manager_name` = :manager_name,
                `is_default` = :is_default,
                `allow_negative_stock` = :allow_negative_stock,
                `notes` = :notes,
                `status` = :status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :warehouse_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $warehouseId
        );
    }

    /**
     * Execute repository operations in one database transaction.
     *
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