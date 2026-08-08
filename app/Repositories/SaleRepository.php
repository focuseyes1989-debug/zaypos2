<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Sale;
use RuntimeException;

/**
 * @extends BaseRepository<Sale>
 */
final class SaleRepository extends BaseRepository
{
    protected string $table = 'sales';

    protected string $modelClass = Sale::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'customer_id',
        'warehouse_id',
        'pos_shift_id',

        'is_held',
        'held_at',
        'held_by',

        'sale_number',
        'customer_reference',
        'sale_date',
        'due_date',
        'status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'other_amount',
        'grand_total',
        'paid_amount',
        'balance_due',
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
        'customer_id',
        'warehouse_id',
        'pos_shift_id',
        'sale_number',
        'customer_reference',
        'sale_date',
        'due_date',
        'status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'other_amount',
        'grand_total',
        'paid_amount',
        'balance_due',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected array $updateColumns = [
        'customer_id',
        'warehouse_id',
        'customer_reference',
        'sale_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'other_amount',
        'grand_total',
        'paid_amount',
        'balance_due',
        'payment_status',
        'notes',
        'updated_by',
    ];

    public function paginate(
        int $companyId,
        string $search,
        string $status,
        string $paymentStatus,
        ?int $customerId,
        ?int $warehouseId,
        int $page,
        int $perPage = 20,
        bool $onlyDeleted = false
    ): array {
        $pagination =
            $this->pagination(
                $page,
                $perPage
            );

        $conditions = [
            '`company_id` = :company_id',
        ];

        $parameters = [
            'company_id' => $companyId,
        ];

        $conditions[] =
            $onlyDeleted
                ? '`deleted_at` IS NOT NULL'
                : '`deleted_at` IS NULL';

        if ($search !== '') {
            $conditions[] = '(
                `sale_number` LIKE :sale_number
                OR `customer_reference` LIKE :customer_reference
                OR `notes` LIKE :notes
                OR `cancellation_reason` LIKE :cancellation_reason
            )';

            $searchValue =
                '%' . $search . '%';

            $parameters['sale_number'] =
                $searchValue;

            $parameters['customer_reference'] =
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
                $paymentStatus,
                [
                    'unpaid',
                    'partial',
                    'paid',
                ],
                true
            )
        ) {
            $conditions[] =
                '`payment_status` = :payment_status';

            $parameters['payment_status'] =
                $paymentStatus;
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

        $where =
            implode(
                ' AND ',
                $conditions
            );

        $total =
            (int) $this->fetchValue(
                "SELECT COUNT(*)
                 FROM `sales`
                 WHERE {$where}",
                $parameters
            );

        $listParameters =
            $parameters;

        $listParameters['limit'] =
            $pagination['per_page'];

        $listParameters['offset'] =
            $pagination['offset'];

        $rows =
            $this->fetchAll(
                "SELECT {$this->selectColumnList()}
                 FROM `sales`
                 WHERE {$where}
                 ORDER BY
                    `sale_date` DESC,
                    `id` DESC
                 LIMIT :limit OFFSET :offset",
                $listParameters
            );

        /** @var list<Sale> $items */
        $items =
            $this->hydrateMany(
                $rows
            );

        return $this->paginationResult(
            $items,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * Paginate held POS draft sales.
     */
    public function paginateHeld(
        int $companyId,
        int $page = 1,
        int $perPage = 20,
        ?int $userId = null
    ): array {
        $pagination =
            $this->pagination(
                $page,
                $perPage
            );

        $conditions = [
            '`company_id` = :company_id',
            "`status` = 'draft'",
            '`is_held` = 1',
            '`deleted_at` IS NULL',
        ];

        $parameters = [
            'company_id' =>
                $companyId,
        ];

        if ($userId !== null) {
            $conditions[] =
                '`held_by` = :held_by';

            $parameters['held_by'] =
                $userId;
        }

        $where =
            implode(
                ' AND ',
                $conditions
            );

        $total =
            (int) $this->fetchValue(
                "SELECT COUNT(*)
                 FROM `sales`
                 WHERE {$where}",
                $parameters
            );

        $listParameters =
            $parameters;

        $listParameters['limit'] =
            $pagination['per_page'];

        $listParameters['offset'] =
            $pagination['offset'];

        $rows =
            $this->fetchAll(
                "SELECT {$this->selectColumnList()}
                 FROM `sales`
                 WHERE {$where}
                 ORDER BY
                    `held_at` DESC,
                    `id` DESC
                 LIMIT :limit OFFSET :offset",
                $listParameters
            );

        /** @var list<Sale> $items */
        $items =
            $this->hydrateMany(
                $rows
            );

        return $this->paginationResult(
            $items,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    public function find(
        int $companyId,
        int $saleId,
        bool $includeDeleted = false
    ): ?Sale {
        $sale =
            $this->findById(
                $companyId,
                $saleId,
                $includeDeleted
            );

        return $sale instanceof Sale
            ? $sale
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $saleId
    ): ?Sale {
        $row =
            $this->fetchOne(
                "SELECT {$this->selectColumnList()}
                 FROM `sales`
                 WHERE `company_id` = :company_id
                   AND `id` = :sale_id
                   AND `deleted_at` IS NULL
                 LIMIT 1
                 FOR UPDATE",
                [
                    'company_id' =>
                        $companyId,

                    'sale_id' =>
                        $saleId,
                ]
            );

        if ($row === null) {
            return null;
        }

        $sale =
            $this->hydrate(
                $row
            );

        return $sale instanceof Sale
            ? $sale
            : null;
    }

    /**
     * Find one held POS draft.
     */
    public function findHeld(
        int $companyId,
        int $saleId
    ): ?Sale {
        $row =
            $this->fetchOne(
                "SELECT {$this->selectColumnList()}
                 FROM `sales`
                 WHERE `company_id` = :company_id
                   AND `id` = :sale_id
                   AND `status` = 'draft'
                   AND `is_held` = 1
                   AND `deleted_at` IS NULL
                 LIMIT 1",
                [
                    'company_id' =>
                        $companyId,

                    'sale_id' =>
                        $saleId,
                ]
            );

        if ($row === null) {
            return null;
        }

        $sale =
            $this->hydrate(
                $row
            );

        return $sale instanceof Sale
            ? $sale
            : null;
    }

    public function findByNumber(
        int $companyId,
        string $saleNumber,
        bool $includeDeleted = false
    ): ?Sale {
        $conditions = [
            '`company_id` = :company_id',
            '`sale_number` = :sale_number',
        ];

        if (!$includeDeleted) {
            $conditions[] =
                '`deleted_at` IS NULL';
        }

        $where =
            implode(
                ' AND ',
                $conditions
            );

        $row =
            $this->fetchOne(
                "SELECT {$this->selectColumnList()}
                 FROM `sales`
                 WHERE {$where}
                 LIMIT 1",
                [
                    'company_id' =>
                        $companyId,

                    'sale_number' =>
                        $saleNumber,
                ]
            );

        if ($row === null) {
            return null;
        }

        $sale =
            $this->hydrate(
                $row
            );

        return $sale instanceof Sale
            ? $sale
            : null;
    }

    public function saleNumberExists(
        int $companyId,
        string $saleNumber,
        ?int $exceptSaleId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'sale_number',
            $saleNumber,
            $exceptSaleId
        );
    }

    public function create(
        array $data
    ): Sale {
        $values =
            $this->onlyAllowedColumns(
                [
                    'company_id' =>
                        $data['company_id'],

                    'customer_id' =>
                        $data['customer_id'],

                    'warehouse_id' =>
                        $data['warehouse_id'],

                    'pos_shift_id' =>
                        $data['pos_shift_id']
                        ?? null,

                    'sale_number' =>
                        $data['sale_number'],

                    'customer_reference' =>
                        $data['customer_reference'],

                    'sale_date' =>
                        $data['sale_date'],

                    'due_date' =>
                        $data['due_date'],

                    'status' =>
                        $data['status'],

                    'payment_status' =>
                        $data['payment_status'],

                    'subtotal' =>
                        $data['subtotal'],

                    'discount_amount' =>
                        $data['discount_amount'],

                    'tax_amount' =>
                        $data['tax_amount'],

                    'shipping_amount' =>
                        $data['shipping_amount'],

                    'other_amount' =>
                        $data['other_amount'],

                    'grand_total' =>
                        $data['grand_total'],

                    'paid_amount' =>
                        $data['paid_amount'],

                    'balance_due' =>
                        $data['balance_due'],

                    'notes' =>
                        $data['notes'],

                    'created_by' =>
                        $data['created_by'],

                    'updated_by' =>
                        $data['created_by'],
                ],
                $this->createColumns
            );

        /*
         * is_held is intentionally not included here.
         *
         * New sales use the database default:
         *
         * is_held = 0
         * held_at = NULL
         * held_by = NULL
         */
        $this->execute(
            'INSERT INTO `sales` (
                `company_id`,
                `customer_id`,
                `warehouse_id`,
                `pos_shift_id`,
                `sale_number`,
                `customer_reference`,
                `sale_date`,
                `due_date`,
                `status`,
                `payment_status`,
                `subtotal`,
                `discount_amount`,
                `tax_amount`,
                `shipping_amount`,
                `other_amount`,
                `grand_total`,
                `paid_amount`,
                `balance_due`,
                `notes`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :customer_id,
                :warehouse_id,
                :pos_shift_id,
                :sale_number,
                :customer_reference,
                :sale_date,
                :due_date,
                :status,
                :payment_status,
                :subtotal,
                :discount_amount,
                :tax_amount,
                :shipping_amount,
                :other_amount,
                :grand_total,
                :paid_amount,
                :balance_due,
                :notes,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            $values
        );

        $saleId =
            (int) $this
                ->connection()
                ->lastInsertId();

        $sale =
            $this->find(
                (int) $data['company_id'],
                $saleId
            );

        if (!$sale instanceof Sale) {
            throw new RuntimeException(
                'The sale was created but could not be reloaded.'
            );
        }

        return $sale;
    }

    public function updateDraft(
        int $companyId,
        int $saleId,
        array $data
    ): ?Sale {
        $values =
            $this->onlyAllowedColumns(
                [
                    'customer_id' =>
                        $data['customer_id'],

                    'warehouse_id' =>
                        $data['warehouse_id'],

                    'customer_reference' =>
                        $data[
                            'customer_reference'
                        ],

                    'sale_date' =>
                        $data['sale_date'],

                    'due_date' =>
                        $data['due_date'],

                    'subtotal' =>
                        $data['subtotal'],

                    'discount_amount' =>
                        $data[
                            'discount_amount'
                        ],

                    'tax_amount' =>
                        $data['tax_amount'],

                    'shipping_amount' =>
                        $data[
                            'shipping_amount'
                        ],

                    'other_amount' =>
                        $data['other_amount'],

                    'grand_total' =>
                        $data['grand_total'],

                    'paid_amount' =>
                        $data['paid_amount'],

                    'balance_due' =>
                        $data['balance_due'],

                    'payment_status' =>
                        $data[
                            'payment_status'
                        ],

                    'notes' =>
                        $data['notes'],

                    'updated_by' =>
                        $data['updated_by'],
                ],
                $this->updateColumns
            );

        $values['company_id'] =
            $companyId;

        $values['sale_id'] =
            $saleId;

        $this->execute(
            'UPDATE `sales`
             SET
                `customer_id` = :customer_id,
                `warehouse_id` = :warehouse_id,
                `customer_reference` = :customer_reference,
                `sale_date` = :sale_date,
                `due_date` = :due_date,
                `subtotal` = :subtotal,
                `discount_amount` = :discount_amount,
                `tax_amount` = :tax_amount,
                `shipping_amount` = :shipping_amount,
                `other_amount` = :other_amount,
                `grand_total` = :grand_total,
                `paid_amount` = :paid_amount,
                `balance_due` = :balance_due,
                `payment_status` = :payment_status,
                `notes` = :notes,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_id
               AND `company_id` = :company_id
               AND `status` = \'draft\'
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    /**
     * Mark an active draft POS sale as held.
     */
    public function holdDraft(
        int $companyId,
        int $saleId,
        int $userId
    ): ?Sale {
        $this->execute(
            "UPDATE `sales`
             SET
                `is_held` = 1,
                `held_at` = UTC_TIMESTAMP(),
                `held_by` = :held_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `is_held` = 0
               AND `deleted_at` IS NULL",
            [
                'held_by' =>
                    $userId,

                'updated_by' =>
                    $userId,

                'sale_id' =>
                    $saleId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    /**
     * Resume a held POS draft.
     */
    public function resumeHeld(
        int $companyId,
        int $saleId,
        int $userId
    ): ?Sale {
        $this->execute(
            "UPDATE `sales`
             SET
                `is_held` = 0,
                `held_at` = NULL,
                `held_by` = NULL,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `is_held` = 1
               AND `deleted_at` IS NULL",
            [
                'updated_by' =>
                    $userId,

                'sale_id' =>
                    $saleId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    public function markCompleted(
        int $companyId,
        int $saleId,
        int $userId
    ): ?Sale {
        $this->execute(
            "UPDATE `sales`
             SET
                `status` = 'completed',

                `is_held` = 0,
                `held_at` = NULL,
                `held_by` = NULL,

                `completed_at` = UTC_TIMESTAMP(),
                `completed_by` = :completed_by,

                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()

             WHERE `id` = :sale_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'completed_by' =>
                    $userId,

                'updated_by' =>
                    $userId,

                'sale_id' =>
                    $saleId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    public function markCancelled(
        int $companyId,
        int $saleId,
        int $userId,
        ?string $reason
    ): ?Sale {
        $this->execute(
            "UPDATE `sales`
             SET
                `status` = 'cancelled',

                `is_held` = 0,
                `held_at` = NULL,
                `held_by` = NULL,

                `cancelled_at` = UTC_TIMESTAMP(),
                `cancelled_by` = :cancelled_by,

                `cancellation_reason` =
                    :cancellation_reason,

                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()

             WHERE `id` = :sale_id
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

                'sale_id' =>
                    $saleId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    public function updatePayment(
        int $companyId,
        int $saleId,
        float $paidAmount,
        float $balanceDue,
        string $paymentStatus,
        int $userId
    ): ?Sale {
        $this->execute(
            'UPDATE `sales`
             SET
                `paid_amount` = :paid_amount,
                `balance_due` = :balance_due,
                `payment_status` = :payment_status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            [
                'paid_amount' =>
                    $paidAmount,

                'balance_due' =>
                    $balanceDue,

                'payment_status' =>
                    $paymentStatus,

                'updated_by' =>
                    $userId,

                'sale_id' =>
                    $saleId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    public function updateTotals(
        int $companyId,
        int $saleId,
        float $subtotal,
        float $discountAmount,
        float $taxAmount,
        float $shippingAmount,
        float $otherAmount,
        float $grandTotal,
        float $paidAmount,
        float $balanceDue,
        string $paymentStatus,
        int $userId
    ): ?Sale {
        $this->execute(
            'UPDATE `sales`
             SET
                `subtotal` = :subtotal,
                `discount_amount` = :discount_amount,
                `tax_amount` = :tax_amount,
                `shipping_amount` = :shipping_amount,
                `other_amount` = :other_amount,
                `grand_total` = :grand_total,
                `paid_amount` = :paid_amount,
                `balance_due` = :balance_due,
                `payment_status` = :payment_status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :sale_id
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

                'shipping_amount' =>
                    $shippingAmount,

                'other_amount' =>
                    $otherAmount,

                'grand_total' =>
                    $grandTotal,

                'paid_amount' =>
                    $paidAmount,

                'balance_due' =>
                    $balanceDue,

                'payment_status' =>
                    $paymentStatus,

                'updated_by' =>
                    $userId,

                'sale_id' =>
                    $saleId,

                'company_id' =>
                    $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    public function assignPosShift(
        int $companyId,
        int $saleId,
        ?int $posShiftId,
        int $userId
    ): ?Sale {
        $this->execute(
            'UPDATE `sales`
             SET
                `pos_shift_id` = :pos_shift_id,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :sale_id
               AND `status` = \'draft\'
               AND `deleted_at` IS NULL',
            [
                'pos_shift_id' =>
                    $posShiftId,

                'updated_by' =>
                    $userId,

                'company_id' =>
                    $companyId,

                'sale_id' =>
                    $saleId,
            ]
        );

        return $this->find(
            $companyId,
            $saleId
        );
    }

    public function deleteDraft(
        int $companyId,
        int $saleId,
        int $userId
    ): bool {
        $statement =
            $this->execute(
                "UPDATE `sales`
                 SET
                    `deleted_at` = UTC_TIMESTAMP(),
                    `deleted_by` = :deleted_by,

                    `is_held` = 0,
                    `held_at` = NULL,
                    `held_by` = NULL,

                    `updated_by` = :updated_by,
                    `updated_at` = UTC_TIMESTAMP()

                 WHERE `id` = :sale_id
                   AND `company_id` = :company_id
                   AND `status` = 'draft'
                   AND `deleted_at` IS NULL",
                [
                    'deleted_by' =>
                        $userId,

                    'updated_by' =>
                        $userId,

                    'sale_id' =>
                        $saleId,

                    'company_id' =>
                        $companyId,
                ]
            );

        return $statement
            ->rowCount() > 0;
    }

    public function restoreDeleted(
        int $companyId,
        int $saleId,
        int $userId
    ): bool {
        $statement =
            $this->execute(
                'UPDATE `sales`
                 SET
                    `deleted_at` = NULL,
                    `deleted_by` = NULL,

                    `is_held` = 0,
                    `held_at` = NULL,
                    `held_by` = NULL,

                    `updated_by` = :updated_by,
                    `updated_at` = UTC_TIMESTAMP()

                 WHERE `id` = :sale_id
                   AND `company_id` = :company_id
                   AND `deleted_at` IS NOT NULL',
                [
                    'updated_by' =>
                        $userId,

                    'sale_id' =>
                        $saleId,

                    'company_id' =>
                        $companyId,
                ]
            );

        return $statement
            ->rowCount() > 0;
    }
}