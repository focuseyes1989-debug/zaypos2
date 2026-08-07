<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Customer;
use RuntimeException;

/**
 * @extends BaseRepository<Customer>
 */
final class CustomerRepository extends BaseRepository
{
    protected string $table = 'customers';

    protected string $modelClass = Customer::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'tax_number',
        'customer_group',
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
        'phone',
        'email',
        'address',
        'tax_number',
        'customer_group',
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
        'phone',
        'email',
        'address',
        'tax_number',
        'customer_group',
        'credit_limit',
        'opening_balance',
        'notes',
        'status',
        'updated_by',
    ];

    /**
     * @return array{
     *     items: list<Customer>,
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
                OR `phone` LIKE :search_phone
                OR `email` LIKE :search_email
                OR `tax_number` LIKE :search_tax
                OR `customer_group` LIKE :search_group
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_phone'] = $searchValue;
            $parameters['search_email'] = $searchValue;
            $parameters['search_tax'] = $searchValue;
            $parameters['search_group'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `customers`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `customers`
             WHERE {$where}
             ORDER BY
                `name` ASC,
                `id` ASC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Customer> $items */
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
        int $customerId,
        bool $includeDeleted = false
    ): ?Customer {
        $customer = $this->findById(
            $companyId,
            $customerId,
            $includeDeleted
        );

        return $customer instanceof Customer
            ? $customer
            : null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     customer_group: string|null
     * }>
     */
    public function activeOptions(int $companyId): array
    {
        $rows = $this->fetchAll(
            "SELECT
                `id`,
                `name`,
                `code`,
                `phone`,
                `customer_group`
             FROM `customers`
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
                'phone' => $row['phone'] === null
                    ? null
                    : (string) $row['phone'],
                'customer_group' => $row['customer_group'] === null
                    ? null
                    : (string) $row['customer_group'],
            ];
        }

        return $options;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptCustomerId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'code',
            $code,
            $exceptCustomerId
        );
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptCustomerId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'name',
            $name,
            $exceptCustomerId,
            true
        );
    }

    /**
     * @param array{
     *     company_id: int,
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     tax_number: string|null,
     *     customer_group: string|null,
     *     credit_limit: float|int|string,
     *     opening_balance: float|int|string,
     *     notes: string|null,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(array $data): Customer
    {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'tax_number' => $data['tax_number'],
                'customer_group' => $data['customer_group'],
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
            'INSERT INTO `customers` (
                `company_id`,
                `name`,
                `code`,
                `phone`,
                `email`,
                `address`,
                `tax_number`,
                `customer_group`,
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
                :phone,
                :email,
                :address,
                :tax_number,
                :customer_group,
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

        $customerId = (int) $this->connection()->lastInsertId();

        $customer = $this->find(
            (int) $data['company_id'],
            $customerId
        );

        if (!$customer instanceof Customer) {
            throw new RuntimeException(
                'The customer was created but could not be reloaded.'
            );
        }

        return $customer;
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     tax_number: string|null,
     *     customer_group: string|null,
     *     credit_limit: float|int|string,
     *     opening_balance: float|int|string,
     *     notes: string|null,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $customerId,
        array $data
    ): ?Customer {
        $values = $this->onlyAllowedColumns(
            $data,
            $this->updateColumns
        );

        $values['customer_id'] = $customerId;
        $values['company_id'] = $companyId;

        $this->execute(
            'UPDATE `customers`
             SET
                `name` = :name,
                `code` = :code,
                `phone` = :phone,
                `email` = :email,
                `address` = :address,
                `tax_number` = :tax_number,
                `customer_group` = :customer_group,
                `credit_limit` = :credit_limit,
                `opening_balance` = :opening_balance,
                `notes` = :notes,
                `status` = :status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :customer_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $customerId
        );
    }
}