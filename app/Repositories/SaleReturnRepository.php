<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\SaleReturn;
use RuntimeException;

/**
 * @extends BaseRepository<SaleReturn>
 */
final class SaleReturnRepository extends BaseRepository
{
    protected string $table = 'sale_returns';

    protected string $modelClass = SaleReturn::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'sale_id',
        'customer_id',
        'warehouse_id',
        'return_number',
        'return_date',
        'status',
        'refund_status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'refunded_amount',
        'refund_balance',
        'reason',
        'notes',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $createColumns = [
        'company_id',
        'sale_id',
        'customer_id',
        'warehouse_id',
        'return_number',
        'return_date',
        'status',
        'refund_status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'refunded_amount',
        'refund_balance',
        'reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'customer_id',
        'warehouse_id',
        'return_date',
        'refund_status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'refunded_amount',
        'refund_balance',
        'reason',
        'notes',
        'updated_by',
    ];

    /**
     * @return array{
     *     items: list<SaleReturn>,
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
        string $refundStatus,
        ?int $saleId,
        ?int $customerId,
        ?int $warehouseId,
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
                `return_number` LIKE :return_number
                OR `reason` LIKE :reason
                OR `notes` LIKE :notes
                OR `cancellation_reason`
                    LIKE :cancellation_reason
            )';

            $searchValue =
                '%' . $search . '%';

            $parameters['return_number'] =
                $searchValue;

            $parameters['reason'] =
                $searchValue;

            $parameters['notes'] =
                $searchValue;

            $parameters['cancellation_reason'] =
                $searchValue;
        }

        if (
            in_array(
                $status,
                [
                    'draft',
                    'completed',
                    'cancelled',
                ],
                true
            )
        ) {
            $conditions[] =
                '`status` = :status';

            $parameters['status'] =
                $status;
        }

        if (
            in_array(
                $refundStatus,
                [
                    'none',
                    'partial',
                    'refunded',
                ],
                true
            )
        ) {
            $conditions[] =
                '`refund_status` = :refund_status';

            $parameters['refund_status'] =
                $refundStatus;
        }

        if ($saleId !== null) {
            $conditions[] =
                '`sale_id` = :sale_id';

            $parameters['sale_id'] =
                $saleId;
        }

        if ($customerId !== null) {
            $conditions[] =
                '`customer_id` = :customer_id';

            $parameters['customer_id'] =
                $customerId;
        }

        if ($warehouseId !== null) {
            $conditions[] =
                '`warehouse_id` = :warehouse_id';

            $parameters['warehouse_id'] =
                $warehouseId;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `sale_returns`
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
             FROM `sale_returns`
             WHERE {$where}
             ORDER BY
                `return_date` DESC,
                `id` DESC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<SaleReturn> $items */
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
        int $saleReturnId,
        bool $includeDeleted = false
    ): ?SaleReturn {
        $conditions = [
            '`company_id` = :company_id',
            '`id` = :sale_return_id',
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
             FROM `sale_returns`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' =>
                    $companyId,

                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $return =
            $this->hydrate($row);

        return $return instanceof SaleReturn
            ? $return
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $saleReturnId
    ): ?SaleReturn {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `sale_returns`
             WHERE `company_id` = :company_id
               AND `id` = :sale_return_id
               AND `deleted_at` IS NULL
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' =>
                    $companyId,

                'sale_return_id' =>
                    $saleReturnId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $return =
            $this->hydrate($row);

        return $return instanceof SaleReturn
            ? $return
            : null;
    }

    public function findByNumber(
        int $companyId,
        string $returnNumber,
        bool $includeDeleted = false
    ): ?SaleReturn {
        $conditions = [
            '`company_id` = :company_id',
            '`return_number` = :return_number',
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
             FROM `sale_returns`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' =>
                    $companyId,

                'return_number' =>
                    $returnNumber,
            ]
        );

        if ($row === null) {
            return null;
        }

        $return =
            $this->hydrate($row);

        return $return instanceof SaleReturn
            ? $return
            : null;
    }

    public function returnNumberExists(
        int $companyId,
        string $returnNumber,
        ?int $exceptSaleReturnId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM `sale_returns`
                WHERE `company_id` = :company_id
                  AND `return_number` = :return_number';

        $parameters = [
            'company_id' =>
                $companyId,

            'return_number' =>
                $returnNumber,
        ];

        if ($exceptSaleReturnId !== null) {
            $sql .=
                ' AND `id` <> :except_sale_return_id';

            $parameters[
                'except_sale_return_id'
            ] = $exceptSaleReturnId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        ) > 0;
    }

    /**
     * @return list<SaleReturn>
     */
    public function bySale(
        int $companyId,
        int $saleId,
        bool $includeDeleted = false
    ): array {
        $conditions = [
            '`company_id` = :company_id',
            '`sale_id` = :sale_id',
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
             FROM `sale_returns`
             WHERE {$where}
             ORDER BY
                `return_date` ASC,
                `id` ASC",
            [
                'company_id' =>
                    $companyId,

                'sale_id' =>
                    $saleId,
            ]
        );

        /** @var list<SaleReturn> $items */
        $items =
            $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data
    ): SaleReturn {
        $values =
            $this->onlyAllowedColumns(
                [
                    'company_id' =>
                        $data['company_id'],

                    'sale_id' =>
                        $data['sale_id'],

                    'customer_id' =>
                        $data['customer_id'],

                    'warehouse_id' =>
                        $data['warehouse_id'],

                    'return_number' =>
                        $data['return_number'],

                    'return_date' =>
                        $data['return_date'],

                    'status' =>
                        $data['status'],

                    'refund_status' =>
                        $data['refund_status'],

                    'subtotal' =>
                        $data['subtotal'],

                    'discount_amount' =>
                        $data['discount_amount'],

                    'tax_amount' =>
                        $data['tax_amount'],

                    'grand_total' =>
                        $data['grand_total'],

                    'refunded_amount' =>
                        $data['refunded_amount'],

                    'refund_balance' =>
                        $data['refund_balance'],

                    'reason' =>
                        $data['reason'],

                    'notes' =>
                        $data['notes'],

                    'created_by' =>
                        $data['created_by'],

                    'updated_by' =>
                        $data['created_by'],
                ],
                $this->createColumns
            );

        $this->execute(
            'INSERT INTO `sale_returns` (
                `company_id`,
                `sale_id`,
                `customer_id`,
                `warehouse_id`,
                `return_number`,
                `return_date`,
                `status`,
                `refund_status`,
                `subtotal`,
                `discount_amount`,
                `tax_amount`,
                `grand_total`,
                `refunded_amount`,
                `refund_balance`,
                `reason`,
                `notes`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :sale_id,
                :customer_id,
                :warehouse_id,
                :return_number,
                :return_date,
                :status,
                :refund_status,
                :subtotal,
                :discount_amount,
                :tax_amount,
                :grand_total,
                :refunded_amount,
                :refund_balance,
                :reason,
                :notes,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $saleReturnId =
            (int) $this->connection()
                ->lastInsertId();

        $return = $this->find(
            (int) $data['company_id'],
            $saleReturnId
        );

        if (!$return instanceof SaleReturn) {
            throw new RuntimeException(
                'The sale return was created but could not be reloaded.'
            );
        }

        return $return;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDraft(
        int $companyId,
        int $saleReturnId,
        array $data
    ): ?SaleReturn {
        $values =
            $this->onlyAllowedColumns(
                [
                    'customer_id' =>
                        $data['customer_id'],

                    'warehouse_id' =>
                        $data['warehouse_id'],

                    'return_date' =>
                        $data['return_date'],

                    'refund_status' =>
                        $data['refund_status'],

                    'subtotal' =>
                        $data['subtotal'],

                    'discount_amount' =>
                        $data['discount_amount'],

                    'tax_amount' =>
                        $data['tax_amount'],

                    'grand_total' =>
                        $data['grand_total'],

                    'refunded_amount' =>
                        $data['refunded_amount'],

                    'refund_balance' =>
                        $data['refund_balance'],

                    'reason' =>
                        $data['reason'],

                    'notes' =>
                        $data['notes'],

                    'updated_by' =>
                        $data['updated_by'],
                ],
                $this->updateColumns
            );

        $values['company_id'] =
            $companyId;

        $values['sale_return_id'] =
            $saleReturnId;

        $this->execute(
            'UPDATE `sale_returns`
             SET
                `customer_id` = :customer_id,
                `warehouse_id` = :warehouse_id,
                `return_date` = :return_date,
                `refund_status` = :refund_status,
                `subtotal` = :subtotal,
                `discount_amount` = :discount_amount,
                `tax_amount` = :tax_amount,
                `grand_total` = :grand_total,
                `refunded_amount` = :refunded_amount,
                `refund_balance` = :refund_balance,
                `reason` = :reason,
                `notes` = :notes,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `status` = \'draft\'
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $saleReturnId
        );
    }

    public function updateTotals(
        int $companyId,
        int $saleReturnId,
        float $subtotal,
        float $discountAmount,
        float $taxAmount,
        float $grandTotal,
        int $userId
    ): ?SaleReturn {
        $this->execute(
            'UPDATE `sale_returns`
             SET
                `subtotal` = :subtotal,
                `discount_amount` = :discount_amount,
                `tax_amount` = :tax_amount,
                `grand_total` = :grand_total,
                `refund_balance` =
                    GREATEST(
                        :grand_total - `refunded_amount`,
                        0
                    ),
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `status` = \'draft\'
               AND `deleted_at` IS NULL',
            [
                'subtotal' =>
                    $subtotal,

                'discount_amount' =>
                    $discountAmount,

                'tax_amount' =>
                    $taxAmount,

                'grand_total' =>
                    $grandTotal,

                'updated_by' =>
                    $userId,

                'sale_return_id' =>
                    $saleReturnId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleReturnId
        );
    }

    public function updateRefund(
        int $companyId,
        int $saleReturnId,
        float $refundedAmount,
        float $refundBalance,
        string $refundStatus,
        int $userId
    ): ?SaleReturn {
        $this->execute(
            'UPDATE `sale_returns`
             SET
                `refunded_amount` = :refunded_amount,
                `refund_balance` = :refund_balance,
                `refund_status` = :refund_status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            [
                'refunded_amount' =>
                    $refundedAmount,

                'refund_balance' =>
                    $refundBalance,

                'refund_status' =>
                    $refundStatus,

                'updated_by' =>
                    $userId,

                'sale_return_id' =>
                    $saleReturnId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleReturnId
        );
    }

    public function markCompleted(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): ?SaleReturn {
        $this->execute(
            "UPDATE `sale_returns`
             SET
                `status` = 'completed',
                `completed_at` = UTC_TIMESTAMP(),
                `completed_by` = :completed_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'completed_by' =>
                    $userId,

                'updated_by' =>
                    $userId,

                'sale_return_id' =>
                    $saleReturnId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleReturnId
        );
    }

    public function markCancelled(
        int $companyId,
        int $saleReturnId,
        int $userId,
        ?string $reason
    ): ?SaleReturn {
        $this->execute(
            "UPDATE `sale_returns`
             SET
                `status` = 'cancelled',
                `cancelled_at` = UTC_TIMESTAMP(),
                `cancelled_by` = :cancelled_by,
                `cancellation_reason` =
                    :cancellation_reason,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'cancelled_by' =>
                    $userId,

                'cancellation_reason' =>
                    $reason,

                'updated_by' =>
                    $userId,

                'sale_return_id' =>
                    $saleReturnId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleReturnId
        );
    }

    public function deleteDraft(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): bool {
        $statement = $this->execute(
            "UPDATE `sale_returns`
             SET
                `deleted_at` = UTC_TIMESTAMP(),
                `deleted_by` = :deleted_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'deleted_by' =>
                    $userId,

                'updated_by' =>
                    $userId,

                'sale_return_id' =>
                    $saleReturnId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $statement->rowCount() > 0;
    }

    public function restoreDeleted(
        int $companyId,
        int $saleReturnId,
        int $userId
    ): bool {
        $statement = $this->execute(
            'UPDATE `sale_returns`
             SET
                `deleted_at` = NULL,
                `deleted_by` = NULL,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_return_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NOT NULL',
            [
                'updated_by' =>
                    $userId,

                'sale_return_id' =>
                    $saleReturnId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $statement->rowCount() > 0;
    }

    public function countCompletedBySale(
        int $companyId,
        int $saleId
    ): int {
        return (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `sale_returns`
             WHERE `company_id` = :company_id
               AND `sale_id` = :sale_id
               AND `status` = 'completed'
               AND `deleted_at` IS NULL",
            [
                'company_id' =>
                    $companyId,

                'sale_id' =>
                    $saleId,
            ]
        );
    }
}