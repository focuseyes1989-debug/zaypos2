<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\PurchasePayment;
use RuntimeException;

/**
 * @extends BaseRepository<PurchasePayment>
 */
final class PurchasePaymentRepository extends BaseRepository
{
    protected string $table = 'purchase_payments';

    protected string $modelClass = PurchasePayment::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'purchase_id',
        'supplier_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
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
     *     items: list<PurchasePayment>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        string $search,
        string $paymentMethod,
        ?int $purchaseId,
        ?int $supplierId,
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
                `payment_number` LIKE :payment_number
                OR `reference_number` LIKE :reference_number
                OR `notes` LIKE :notes
            )';

            $searchValue = '%' . $search . '%';

            $parameters['payment_number'] = $searchValue;
            $parameters['reference_number'] = $searchValue;
            $parameters['notes'] = $searchValue;
        }

        if ($paymentMethod !== '') {
            $conditions[] = '`payment_method` = :payment_method';
            $parameters['payment_method'] = $paymentMethod;
        }

        if ($purchaseId !== null) {
            $conditions[] = '`purchase_id` = :purchase_id';
            $parameters['purchase_id'] = $purchaseId;
        }

        if ($supplierId !== null) {
            $conditions[] = '`supplier_id` = :supplier_id';
            $parameters['supplier_id'] = $supplierId;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `purchase_payments`
             WHERE {$where}",
            $parameters
        );

        $listParameters = $parameters;
        $listParameters['limit'] = $pagination['per_page'];
        $listParameters['offset'] = $pagination['offset'];

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_payments`
             WHERE {$where}
             ORDER BY
                `payment_date` DESC,
                `id` DESC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<PurchasePayment> $items */
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
        int $paymentId,
        bool $includeDeleted = false
    ): ?PurchasePayment {
        $conditions = [
            '`company_id` = :company_id',
            '`id` = :payment_id',
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
             FROM `purchase_payments`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' => $companyId,
                'payment_id' => $paymentId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $payment = $this->hydrate($row);

        return $payment instanceof PurchasePayment
            ? $payment
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $paymentId
    ): ?PurchasePayment {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_payments`
             WHERE `company_id` = :company_id
               AND `id` = :payment_id
               AND `deleted_at` IS NULL
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' => $companyId,
                'payment_id' => $paymentId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $payment = $this->hydrate($row);

        return $payment instanceof PurchasePayment
            ? $payment
            : null;
    }

    public function findByNumber(
        int $companyId,
        string $paymentNumber,
        bool $includeDeleted = false
    ): ?PurchasePayment {
        $conditions = [
            '`company_id` = :company_id',
            '`payment_number` = :payment_number',
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
             FROM `purchase_payments`
             WHERE {$where}
             LIMIT 1",
            [
                'company_id' => $companyId,
                'payment_number' => $paymentNumber,
            ]
        );

        if ($row === null) {
            return null;
        }

        $payment = $this->hydrate($row);

        return $payment instanceof PurchasePayment
            ? $payment
            : null;
    }

    public function paymentNumberExists(
        int $companyId,
        string $paymentNumber,
        ?int $exceptPaymentId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM `purchase_payments`
                WHERE `company_id` = :company_id
                  AND `payment_number` = :payment_number';

        $parameters = [
            'company_id' => $companyId,
            'payment_number' => $paymentNumber,
        ];

        if ($exceptPaymentId !== null) {
            $sql .= ' AND `id` <> :except_payment_id';

            $parameters['except_payment_id'] =
                $exceptPaymentId;
        }

        return (int) $this->fetchValue(
            $sql,
            $parameters
        ) > 0;
    }

    /**
     * @return list<PurchasePayment>
     */
    public function byPurchase(
        int $companyId,
        int $purchaseId,
        bool $includeDeleted = false
    ): array {
        $conditions = [
            '`company_id` = :company_id',
            '`purchase_id` = :purchase_id',
        ];

        if (!$includeDeleted) {
            $conditions[] = '`deleted_at` IS NULL';
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_payments`
             WHERE {$where}
             ORDER BY
                `payment_date` ASC,
                `id` ASC",
            [
                'company_id' => $companyId,
                'purchase_id' => $purchaseId,
            ]
        );

        /** @var list<PurchasePayment> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @return list<PurchasePayment>
     */
    public function bySupplier(
        int $companyId,
        int $supplierId,
        int $limit = 100
    ): array {
        $limit = max(
            1,
            min(500, $limit)
        );

        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `purchase_payments`
             WHERE `company_id` = :company_id
               AND `supplier_id` = :supplier_id
               AND `deleted_at` IS NULL
             ORDER BY
                `payment_date` DESC,
                `id` DESC
             LIMIT :limit",
            [
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
                'limit' => $limit,
            ]
        );

        /** @var list<PurchasePayment> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @param array{
     *     company_id: int,
     *     purchase_id: int,
     *     supplier_id: int,
     *     payment_number: string,
     *     payment_date: string,
     *     amount: float|int|string,
     *     payment_method: string,
     *     reference_number: string|null,
     *     notes: string|null,
     *     created_by: int|null
     * } $data
     */
    public function create(
        array $data
    ): PurchasePayment {
        $this->execute(
            'INSERT INTO `purchase_payments` (
                `company_id`,
                `purchase_id`,
                `supplier_id`,
                `payment_number`,
                `payment_date`,
                `amount`,
                `payment_method`,
                `reference_number`,
                `notes`,
                `created_by`,
                `updated_by`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :purchase_id,
                :supplier_id,
                :payment_number,
                :payment_date,
                :amount,
                :payment_method,
                :reference_number,
                :notes,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )',
            [
                'company_id' => $data['company_id'],
                'purchase_id' => $data['purchase_id'],
                'supplier_id' => $data['supplier_id'],
                'payment_number' => $data['payment_number'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'],
                'notes' => $data['notes'],
                'created_by' => $data['created_by'],
                'updated_by' => $data['created_by'],
            ]
        );

        $paymentId = (int) $this->connection()
            ->lastInsertId();

        $payment = $this->find(
            (int) $data['company_id'],
            $paymentId
        );

        if (!$payment instanceof PurchasePayment) {
            throw new RuntimeException(
                'Purchase payment was created but could not be reloaded.'
            );
        }

        return $payment;
    }

    public function softDelete(
        int $companyId,
        int $paymentId,
        int $userId
    ): bool {
        $affected = $this->execute(
            'UPDATE `purchase_payments`
             SET
                `deleted_at` = UTC_TIMESTAMP(),
                `deleted_by` = :deleted_by,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :payment_id
               AND `deleted_at` IS NULL',
            [
                'deleted_by' => $userId,
                'updated_by' => $userId,
                'company_id' => $companyId,
                'payment_id' => $paymentId,
            ]
        );

        return $affected > 0;
    }

    public function restore(
        int $companyId,
        int $paymentId,
        int $userId
    ): bool {
        $affected = $this->execute(
            'UPDATE `purchase_payments`
             SET
                `deleted_at` = NULL,
                `deleted_by` = NULL,
                `updated_by` = :updated_by,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :payment_id
               AND `deleted_at` IS NOT NULL',
            [
                'updated_by' => $userId,
                'company_id' => $companyId,
                'payment_id' => $paymentId,
            ]
        );

        return $affected > 0;
    }

    public function sumActivePayments(
        int $companyId,
        int $purchaseId
    ): float {
        return (float) $this->fetchValue(
            'SELECT COALESCE(SUM(`amount`), 0)
             FROM `purchase_payments`
             WHERE `company_id` = :company_id
               AND `purchase_id` = :purchase_id
               AND `deleted_at` IS NULL',
            [
                'company_id' => $companyId,
                'purchase_id' => $purchaseId,
            ]
        );
    }

    public function countActivePayments(
        int $companyId,
        int $purchaseId
    ): int {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `purchase_payments`
             WHERE `company_id` = :company_id
               AND `purchase_id` = :purchase_id
               AND `deleted_at` IS NULL',
            [
                'company_id' => $companyId,
                'purchase_id' => $purchaseId,
            ]
        );
    }
}