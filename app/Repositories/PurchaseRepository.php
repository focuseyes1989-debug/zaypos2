<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Purchase;
use RuntimeException;

/**
 * @extends BaseRepository<Purchase>
 */
final class PurchaseRepository extends BaseRepository
{
    protected string $table = 'purchases';

    protected string $modelClass = Purchase::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'supplier_id',
        'warehouse_id',
        'purchase_number',
        'supplier_invoice_number',
        'purchase_date',
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
        'received_at',
        'received_by',
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
        'supplier_id',
        'warehouse_id',
        'purchase_number',
        'supplier_invoice_number',
        'purchase_date',
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
        'supplier_id',
        'warehouse_id',
        'supplier_invoice_number',
        'purchase_date',
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

    /**
     * @return array{
     *     items: list<Purchase>,
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
        string $paymentStatus,
        ?int $supplierId,
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
                `purchase_number` LIKE :purchase_number
                OR `supplier_invoice_number` LIKE :supplier_invoice
                OR `notes` LIKE :notes
                OR `cancellation_reason` LIKE :cancellation_reason
            )';

            $searchValue = '%' . $search . '%';

            $parameters['purchase_number'] = $searchValue;
            $parameters['supplier_invoice'] = $searchValue;
            $parameters['notes'] = $searchValue;
            $parameters['cancellation_reason'] = $searchValue;
        }

        if (
            in_array(
                $status,
                [
                    'draft',
                    'received',
                    'cancelled',
                ],
                true
            )
        ) {
            $conditions[] = '`status` = :status';
            $parameters['status'] = $status;
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
            $conditions[] = '`payment_status` = :payment_status';
            $parameters['payment_status'] = $paymentStatus;
        }

        if ($supplierId !== null) {
            $conditions[] = '`supplier_id` = :supplier_id';
            $parameters['supplier_id'] = $supplierId;
        }

        if ($warehouseId !== null) {
            $conditions[] = '`warehouse_id` = :warehouse_id';
            $parameters['warehouse_id'] = $warehouseId;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `purchases`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;

        $listParameters['limit'] =
            $pagination['per_page'];

        $listParameters['offset'] =
            $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `purchases`
             WHERE {$where}
             ORDER BY
                `purchase_date` DESC,
                `id` DESC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<Purchase> $items */
        $items = $this->hydrateMany(
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
        int $purchaseId,
        bool $includeDeleted = false
    ): ?Purchase {
        $purchase = $this->findById(
            $companyId,
            $purchaseId,
            $includeDeleted
        );

        return $purchase instanceof Purchase
            ? $purchase
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $purchaseId
    ): ?Purchase {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `purchases`
             WHERE `company_id` = :company_id
               AND `id` = :purchase_id
               AND `deleted_at` IS NULL
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' => $companyId,
                'purchase_id' => $purchaseId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $purchase = $this->hydrate(
            $row
        );

        return $purchase instanceof Purchase
            ? $purchase
            : null;
    }

    public function findByNumber(
        int $companyId,
        string $purchaseNumber,
        bool $includeDeleted = false
    ): ?Purchase {
        $conditions = [
            '`company_id` = :company_id',
            '`purchase_number` = :purchase_number',
        ];

        if (!$includeDeleted) {
            $conditions[] = '`deleted_at` IS NULL';
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `purchases`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' => $companyId,
                'purchase_number' => $purchaseNumber,
            ]
        );

        if ($row === null) {
            return null;
        }

        $purchase = $this->hydrate(
            $row
        );

        return $purchase instanceof Purchase
            ? $purchase
            : null;
    }

    public function purchaseNumberExists(
        int $companyId,
        string $purchaseNumber,
        ?int $exceptPurchaseId = null
    ): bool {
        return $this->valueExists(
            $companyId,
            'purchase_number',
            $purchaseNumber,
            $exceptPurchaseId
        );
    }

    /**
     * @param array{
     *     company_id: int,
     *     supplier_id: int,
     *     warehouse_id: int,
     *     purchase_number: string,
     *     supplier_invoice_number: string|null,
     *     purchase_date: string,
     *     due_date: string|null,
     *     status: string,
     *     payment_status: string,
     *     subtotal: float|int|string,
     *     discount_amount: float|int|string,
     *     tax_amount: float|int|string,
     *     shipping_amount: float|int|string,
     *     other_amount: float|int|string,
     *     grand_total: float|int|string,
     *     paid_amount: float|int|string,
     *     balance_due: float|int|string,
     *     notes: string|null,
     *     created_by: int|null
     * } $data
     */
    public function create(
        array $data
    ): Purchase {
        $values = $this->onlyAllowedColumns(
            [
                'company_id' =>
                    $data['company_id'],

                'supplier_id' =>
                    $data['supplier_id'],

                'warehouse_id' =>
                    $data['warehouse_id'],

                'purchase_number' =>
                    $data['purchase_number'],

                'supplier_invoice_number' =>
                    $data['supplier_invoice_number'],

                'purchase_date' =>
                    $data['purchase_date'],

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

        $this->execute(
            'INSERT INTO `purchases` (
                `company_id`,
                `supplier_id`,
                `warehouse_id`,
                `purchase_number`,
                `supplier_invoice_number`,
                `purchase_date`,
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
                :supplier_id,
                :warehouse_id,
                :purchase_number,
                :supplier_invoice_number,
                :purchase_date,
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

        $purchaseId =
            (int) $this->connection()
                ->lastInsertId();

        $purchase = $this->find(
            (int) $data['company_id'],
            $purchaseId
        );

        if (!$purchase instanceof Purchase) {
            throw new RuntimeException(
                'The purchase was created but could not be reloaded.'
            );
        }

        return $purchase;
    }

    /**
     * Update editable data of a draft purchase.
     *
     * @param array{
     *     supplier_id: int,
     *     warehouse_id: int,
     *     supplier_invoice_number: string|null,
     *     purchase_date: string,
     *     due_date: string|null,
     *     subtotal: float|int|string,
     *     discount_amount: float|int|string,
     *     tax_amount: float|int|string,
     *     shipping_amount: float|int|string,
     *     other_amount: float|int|string,
     *     grand_total: float|int|string,
     *     paid_amount: float|int|string,
     *     balance_due: float|int|string,
     *     payment_status: string,
     *     notes: string|null,
     *     updated_by: int|null
     * } $data
     */
    public function updateDraft(
        int $companyId,
        int $purchaseId,
        array $data
    ): ?Purchase {
        $values = $this->onlyAllowedColumns(
            [
                'supplier_id' =>
                    $data['supplier_id'],

                'warehouse_id' =>
                    $data['warehouse_id'],

                'supplier_invoice_number' =>
                    $data['supplier_invoice_number'],

                'purchase_date' =>
                    $data['purchase_date'],

                'due_date' =>
                    $data['due_date'],

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

                'payment_status' =>
                    $data['payment_status'],

                'notes' =>
                    $data['notes'],

                'updated_by' =>
                    $data['updated_by'],
            ],
            $this->updateColumns
        );

        $values['company_id'] =
            $companyId;

        $values['purchase_id'] =
            $purchaseId;

        $this->execute(
            'UPDATE `purchases`
             SET
                `supplier_id` = :supplier_id,
                `warehouse_id` = :warehouse_id,
                `supplier_invoice_number` = :supplier_invoice_number,
                `purchase_date` = :purchase_date,
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
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `status` = \'draft\'
               AND `deleted_at` IS NULL',
            $values
        );

        return $this->find(
            $companyId,
            $purchaseId
        );
    }

    public function markReceived(
        int $companyId,
        int $purchaseId,
        int $userId
    ): ?Purchase {
        $this->execute(
            "UPDATE `purchases`
             SET
                `status` = 'received',
                `received_at` = UTC_TIMESTAMP(),
                `received_by` = :received_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'received_by' => $userId,
                'updated_by' => $userId,
                'purchase_id' => $purchaseId,
                'company_id' => $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $purchaseId
        );
    }

    public function markCancelled(
        int $companyId,
        int $purchaseId,
        int $userId,
        ?string $reason
    ): ?Purchase {
        $this->execute(
            "UPDATE `purchases`
             SET
                `status` = 'cancelled',
                `cancelled_at` = UTC_TIMESTAMP(),
                `cancelled_by` = :cancelled_by,
                `cancellation_reason` = :cancellation_reason,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
                'updated_by' => $userId,
                'purchase_id' => $purchaseId,
                'company_id' => $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $purchaseId
        );
    }

    public function updatePayment(
        int $companyId,
        int $purchaseId,
        float $paidAmount,
        float $balanceDue,
        string $paymentStatus,
        int $userId
    ): ?Purchase {
        $this->execute(
            'UPDATE `purchases`
             SET
                `paid_amount` = :paid_amount,
                `balance_due` = :balance_due,
                `payment_status` = :payment_status,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NULL',
            [
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'updated_by' => $userId,
                'purchase_id' => $purchaseId,
                'company_id' => $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $purchaseId
        );
    }

    /**
     * Update totals without changing header identity.
     */
    public function updateTotals(
        int $companyId,
        int $purchaseId,
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
    ): ?Purchase {
        $this->execute(
            'UPDATE `purchases`
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
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `status` = \'draft\'
               AND `deleted_at` IS NULL',
            [
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'other_amount' => $otherAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'updated_by' => $userId,
                'purchase_id' => $purchaseId,
                'company_id' => $companyId,
            ]
        );

        return $this->find(
            $companyId,
            $purchaseId
        );
    }

    /**
     * Soft-delete only draft purchases.
     */
    public function deleteDraft(
        int $companyId,
        int $purchaseId,
        int $userId
    ): bool {
        $affected = $this->execute(
            "UPDATE `purchases`
             SET
                `deleted_at` = UTC_TIMESTAMP(),
                `deleted_by` = :deleted_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `status` = 'draft'
               AND `deleted_at` IS NULL",
            [
                'deleted_by' => $userId,
                'updated_by' => $userId,
                'purchase_id' => $purchaseId,
                'company_id' => $companyId,
            ]
        );

        return $affected > 0;
    }

    public function restoreDeleted(
        int $companyId,
        int $purchaseId,
        int $userId
    ): bool {
        $affected = $this->execute(
            'UPDATE `purchases`
             SET
                `deleted_at` = NULL,
                `deleted_by` = NULL,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :purchase_id
               AND `company_id` = :company_id
               AND `deleted_at` IS NOT NULL',
            [
                'updated_by' => $userId,
                'purchase_id' => $purchaseId,
                'company_id' => $companyId,
            ]
        );

        return $affected > 0;
    }
}