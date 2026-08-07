<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Supplier;
use RuntimeException;

/**
 * @extends BaseRepository<Supplier>
 */
final class SupplierRepository extends BaseRepository
{
    protected string $table = 'suppliers';

    protected string $modelClass = Supplier::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'payment_terms_days',
        'credit_limit',
        'opening_balance',
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
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'payment_terms_days',
        'credit_limit',
        'opening_balance',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'payment_terms_days',
        'credit_limit',
        'opening_balance',
        'notes',
        'status',
        'updated_by',
    ];

    /**
     * @return array{
     *     items: list<Supplier>,
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
                OR `contact_person` LIKE :search_contact
                OR `phone` LIKE :search_phone
                OR `email` LIKE :search_email
                OR `tax_number` LIKE :search_tax
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_contact'] = $searchValue;
            $parameters['search_phone'] = $searchValue;
            $parameters['search_email'] = $searchValue;
            $parameters['search_tax'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `suppliers`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `suppliers`
             WHERE {$where}
             ORDER BY
                `name` ASC,
                `id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Supplier> $items */
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
        int $supplierId,
        bool $includeDeleted = false
    ): ?Supplier {
        $supplier = $this->findById(
            $companyId,
            $supplierId,
            $includeDeleted
        );

        return $supplier instanceof Supplier
            ? $supplier
            : null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     contact_person: string|null,
     *     phone: string|null
     * }>
     */
    public function activeOptions(int $companyId): array
    {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `contact_person`,
                `phone`
             FROM `suppliers`
             WHERE `company_id` = :company_id
               AND `status` = 'active'
               AND `deleted_at` IS NULL
             ORDER BY `name` ASC, `id` ASC",
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
                'contact_person' => $row['contact_person'] === null
                    ? null
                    : (string) $row['contact_person'],
                'phone' => $row['phone'] === null
                    ? null
                    : (string) $row['phone'],
            ];
        }

        return $options;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptSupplierId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'code',
            $code,
            $exceptSupplierId
        );
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptSupplierId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'name',
            $name,
            $exceptSupplierId,
            true
        );
    }

    /**
     * @param array{
     *     company_id: int,
     *     name: string,
     *     code: string,
     *     contact_person: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     tax_number: string|null,
     *     payment_terms_days: int,
     *     credit_limit: float|int|string,
     *     opening_balance: float|int|string,
     *     notes: string|null,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(array $data): Supplier
    {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'contact_person' => $data['contact_person'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'tax_number' => $data['tax_number'],
                'payment_terms_days' => $data['payment_terms_days'],
                'credit_limit' => $data['credit_limit'],
                'opening_balance' => $data['opening_balance'],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'updated_by' => $data['created_by'],
            ],
            $this->createColumns
        );

        $this->execute(
            'INSERT INTO `suppliers` (
                `company_id`,
                `name`,
                `code`,
                `contact_person`,
                `phone`,
                `email`,
                `address`,
                `tax_number`,
                `payment_terms_days`,
                `credit_limit`,
                `opening_balance`,
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
                :contact_person,
                :phone,
                :email,
                :address,
                :tax_number,
                :payment_terms_days,
                :credit_limit,
                :opening_balance,
                :notes,
                :status,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $supplierId = (int) $this->connection()->lastInsertId();

        $supplier = $this->find(
            (int) $data['company_id'],
            $supplierId
        );

        if (!$supplier instanceof Supplier) {
            throw new RuntimeException(
                'The supplier was created but could not be reloaded.'
            );
        }

        return $supplier;
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     contact_person: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     tax_number: string|null,
     *     payment_terms_days: int,
     *     credit_limit: float|int|string,
     *     opening_balance: float|int|string,
     *     notes: string|null,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $supplierId,
        array $data
    ): ?Supplier {
        $values = $this->onlyAllowedColumns(
            $data,
            $this->updateColumns
        );

        $values['supplier_id'] = $supplierId;
        $values['company_id'] = $companyId;

        $this->execute(
            'UPDATE `suppliers`
             SET
                `name` = :name,
                `code` = :code,
                `contact_person` = :contact_person,
                `phone` = :phone,
                `email` = :email,
                `address` = :address,
                `tax_number` = :tax_number,
                `payment_terms_days` = :payment_terms_days,
                `credit_limit` = :credit_limit,
                `opening_balance` = :opening_balance,
                `notes` = :notes,
                `status` = :status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :supplier_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $supplierId
        );
    }
}