<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\SaleReturnRefund;
use RuntimeException;

/**
 * @extends BaseRepository<SaleReturnRefund>
 */
final class SaleReturnRefundRepository extends BaseRepository
{
    protected string $table = 'sale_return_refunds';

    protected string $modelClass = SaleReturnRefund::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'sale_return_id',
        'refund_number',
        'refund_date',
        'amount',
        'refund_method',
        'reference_number',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @return array{
     *     items: list<SaleReturnRefund>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        string $search,
        string $refundMethod,
        ?int $saleReturnId,
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
                `refund_number` LIKE :refund_number
                OR `reference_number` LIKE :reference_number
                OR `notes` LIKE :notes
            )';

            $searchValue = '%' . $search . '%';

            $parameters['refund_number'] =
                $searchValue;

            $parameters['reference_number'] =
                $searchValue;

            $parameters['notes'] =
                $searchValue;
        }

        if ($refundMethod !== '') {
            $conditions[] =
                '`refund_method` = :refund_method';

            $parameters['refund_method'] =
                $refundMethod;
        }

        if ($saleReturnId !== null) {
            $conditions[] =
                '`sale_return_id` = :sale_return_id';

            $parameters['sale_return_id'] =
                $saleReturnId;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `sale_return_refunds`
             WHERE {$where}",
            $parameters
        );

        $listParameters =
            $parameters;

        $listParameters['limit'] =
            $pagination['per_page'];

        $listParameters['offset'] =
            $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_refunds`
             WHERE {$where}
             ORDER BY
                `refund_date` DESC,
                `id` DESC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<SaleReturnRefund> $items */
        $items =
            $this->hydrateMany($rows);

        return $this->paginationResult(
            $items,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    public function find(
        int $companyId,
        int $refundId,
        bool $includeDeleted = false
    ): ?SaleReturnRefund {
        $conditions = [
            '`company_id` = :company_id',
            '`id` = :refund_id',
        ];

        if (!$includeDeleted) {
            $conditions[] =
                '`deleted_at` IS NULL';
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_refunds`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' =>
                    $companyId,

                'refund_id' =>
                    $refundId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $refund =
            $this->hydrate($row);

        return $refund instanceof SaleReturnRefund
            ? $refund
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $refundId
    ): ?SaleReturnRefund {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_refunds`
             WHERE `company_id` = :company_id
               AND `id` = :refund_id
               AND `deleted_at` IS NULL
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' =>
                    $companyId,

                'refund_id' =>
                    $refundId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $refund =
            $this->hydrate($row);

        return $refund instanceof SaleReturnRefund
            ? $refund
            : null;
    }

    public function findByNumber(
        int $companyId,
        string $refundNumber,
        bool $includeDeleted = false
    ): ?SaleReturnRefund {
        $conditions = [
            '`company_id` = :company_id',
            '`refund_number` = :refund_number',
        ];

        if (!$includeDeleted) {
            $conditions[] =
                '`deleted_at` IS NULL';
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_refunds`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' =>
                    $companyId,

                'refund_number' =>
                    $refundNumber,
            ]
        );

        if ($row === null) {
            return null;
        }

        $refund =
            $this->hydrate($row);

        return $refund instanceof SaleReturnRefund
            ? $refund
            : null;
    }

    public function refundNumberExists(
        int $companyId,
        string $refundNumber,
        ?int $exceptRefundId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM `sale_return_refunds`
                WHERE `company_id` = :company_id
                  AND `refund_number` = :refund_number';

        $parameters = [
            'company_id' =>
                $companyId,

            'refund_number' =>
                $refundNumber,
        ];

        if ($exceptRefundId !== null) {
            $sql .=
                ' AND `id` <> :except_refund_id';

            $parameters['except_refund_id'] =
                $exceptRefundId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        ) > 0;
    }

    /**
     * @return list<SaleReturnRefund>
     */
    public function byReturn(
        int $companyId,
        int $saleReturnId,
        bool $includeDeleted = false
    ): array {
        $conditions = [
            '`company_id` = :company_id',
            '`sale_return_id` = :sale_return_id',
        ];

        if (!$includeDeleted) {
            $conditions[] =
                '`deleted_at` IS NULL';
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `sale_return_refunds`
             WHERE {$where}
             ORDER BY
                `refund_date` ASC,
                `id` ASC",
            [
                'company_id' =>
                    $companyId,

                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        /** @var list<SaleReturnRefund> $items */
        $items =
            $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data
    ): SaleReturnRefund {
        $this->execute(
            'INSERT INTO `sale_return_refunds` (
                `company_id`,
                `sale_return_id`,
                `refund_number`,
                `refund_date`,
                `amount`,
                `refund_method`,
                `reference_number`,
                `notes`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :sale_return_id,
                :refund_number,
                :refund_date,
                :amount,
                :refund_method,
                :reference_number,
                :notes,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            [
                'company_id' =>
                    $data['company_id'],

                'sale_return_id' =>
                    $data['sale_return_id'],

                'refund_number' =>
                    $data['refund_number'],

                'refund_date' =>
                    $data['refund_date'],

                'amount' =>
                    $data['amount'],

                'refund_method' =>
                    $data['refund_method'],

                'reference_number' =>
                    $data['reference_number'],

                'notes' =>
                    $data['notes'],

                'created_by' =>
                    $data['created_by'],

                'updated_by' =>
                    $data['created_by'],
            ]
        );

        $refundId =
            (int) $this->connection()
                ->lastInsertId();

        $refund = $this->find(
            (int) $data['company_id'],
            $refundId
        );

        if (!$refund instanceof SaleReturnRefund) {
            throw new RuntimeException(
                'The sale return refund was created but could not be reloaded.'
            );
        }

        return $refund;
    }

    public function softDelete(
        int $companyId,
        int $refundId,
        int $userId
    ): bool {
        $statement = $this->execute(
            'UPDATE `sale_return_refunds`
             SET
                `deleted_at` = UTC_TIMESTAMP(),
                `deleted_by` = :deleted_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :refund_id
               AND `deleted_at` IS NULL',
            [
                'deleted_by' =>
                    $userId,

                'updated_by' =>
                    $userId,

                'company_id' =>
                    $companyId,

                'refund_id' =>
                    $refundId,
            ]
        );

        return $statement->rowCount() > 0;
    }

    public function restore(
        int $companyId,
        int $refundId,
        int $userId
    ): bool {
        $statement = $this->execute(
            'UPDATE `sale_return_refunds`
             SET
                `deleted_at` = NULL,
                `deleted_by` = NULL,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :refund_id
               AND `deleted_at` IS NOT NULL',
            [
                'updated_by' =>
                    $userId,

                'company_id' =>
                    $companyId,

                'refund_id' =>
                    $refundId,
            ]
        );

        return $statement->rowCount() > 0;
    }

    public function sumActiveRefunds(
        int $companyId,
        int $saleReturnId
    ): float {
        return (float) $this->fetchValue(
            'SELECT COALESCE(
                SUM(`amount`),
                0
             )
             FROM `sale_return_refunds`
             WHERE `company_id` = :company_id
               AND `sale_return_id` = :sale_return_id
               AND `deleted_at` IS NULL',
            [
                'company_id' =>
                    $companyId,

                'sale_return_id' =>
                    $saleReturnId,
            ]
        );
    }

    public function countActiveRefunds(
        int $companyId,
        int $saleReturnId
    ): int {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `sale_return_refunds`
             WHERE `company_id` = :company_id
               AND `sale_return_id` = :sale_return_id
               AND `deleted_at` IS NULL',
            [
                'company_id' =>
                    $companyId,

                'sale_return_id' =>
                    $saleReturnId,
            ]
        );
    }
}