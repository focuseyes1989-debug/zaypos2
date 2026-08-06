<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Unit;
use RuntimeException;

/**
 * @extends BaseRepository<Unit>
 */
final class UnitRepository extends BaseRepository
{
    protected string $table = 'units';

    protected string $modelClass = Unit::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'name',
        'code',
        'symbol',
        'description',
        'decimal_places',
        'sort_order',
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
        'symbol',
        'description',
        'decimal_places',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'name',
        'code',
        'symbol',
        'description',
        'decimal_places',
        'sort_order',
        'status',
        'updated_by',
    ];

    /**
     * @return array{
     *     items: list<Unit>,
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
        $pagination = $this->pagination($page, $perPage);

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
                OR `symbol` LIKE :search_symbol
                OR `description` LIKE :search_description
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_symbol'] = $searchValue;
            $parameters['search_description'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `units`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `units`
             WHERE {$where}
             ORDER BY
                `sort_order` ASC,
                `name` ASC,
                `id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Unit> $items */
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
        int $unitId,
        bool $includeDeleted = false
    ): ?Unit {
        $unit = $this->findById(
            $companyId,
            $unitId,
            $includeDeleted
        );

        return $unit instanceof Unit ? $unit : null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     decimal_places: int
     * }>
     */
    public function activeOptions(int $companyId): array
    {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `symbol`,
                `decimal_places`
             FROM `units`
             WHERE `company_id` = :company_id
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC, `name` ASC",
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
                'symbol' => $row['symbol'] === null
                    ? null
                    : (string) $row['symbol'],
                'decimal_places' => (int) $row['decimal_places'],
            ];
        }

        return $options;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptUnitId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'code',
            $code,
            $exceptUnitId
        );
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptUnitId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'name',
            $name,
            $exceptUnitId,
            true
        );
    }

    /**
     * @param array{
     *     company_id: int,
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     description: string|null,
     *     decimal_places: int,
     *     sort_order: int,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(array $data): Unit
    {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'symbol' => $data['symbol'],
                'description' => $data['description'],
                'decimal_places' => $data['decimal_places'],
                'sort_order' => $data['sort_order'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'updated_by' => $data['created_by'],
            ],
            $this->createColumns
        );

        $this->execute(
            'INSERT INTO `units` (
                `company_id`,
                `name`,
                `code`,
                `symbol`,
                `description`,
                `decimal_places`,
                `sort_order`,
                `status`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :name,
                :code,
                :symbol,
                :description,
                :decimal_places,
                :sort_order,
                :status,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $unitId = (int) $this->connection()->lastInsertId();

        $unit = $this->find(
            $data['company_id'],
            $unitId
        );

        if (!$unit instanceof Unit) {
            throw new RuntimeException(
                'The unit was created but could not be reloaded.'
            );
        }

        return $unit;
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     description: string|null,
     *     decimal_places: int,
     *     sort_order: int,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $unitId,
        array $data
    ): ?Unit {
        $values = $this->onlyAllowedColumns(
            $data,
            $this->updateColumns
        );

        $values['unit_id'] = $unitId;
        $values['company_id'] = $companyId;

        $this->execute(
            'UPDATE `units`
             SET `name` = :name,
                 `code` = :code,
                 `symbol` = :symbol,
                 `description` = :description,
                 `decimal_places` = :decimal_places,
                 `sort_order` = :sort_order,
                 `status` = :status,
                 `updated_by` = :updated_by,
                 `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :unit_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find($companyId, $unitId);
    }
}