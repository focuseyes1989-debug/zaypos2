<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\PosShift;
use RuntimeException;

/**
 * @extends BaseRepository<PosShift>
 */
final class PosShiftRepository extends BaseRepository
{
    protected string $table = 'pos_shifts';

    protected string $modelClass = PosShift::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'user_id',
        'shift_number',
        'status',
        'opening_cash',
        'cash_sales',
        'cash_in',
        'cash_out',
        'expected_cash',
        'closing_cash',
        'cash_variance',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'opening_notes',
        'closing_notes',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array{
     *     items: list<PosShift>,
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
        ?int $warehouseId,
        ?int $userId,
        int $page,
        int $perPage = 20
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

        if ($search !== '') {
            $conditions[] = '(
                `shift_number` LIKE :shift_number
                OR `opening_notes` LIKE :opening_notes
                OR `closing_notes` LIKE :closing_notes
            )';

            $searchValue = '%' . $search . '%';

            $parameters['shift_number'] =
                $searchValue;

            $parameters['opening_notes'] =
                $searchValue;

            $parameters['closing_notes'] =
                $searchValue;
        }

        if (
            in_array(
                $status,
                ['open', 'closed'],
                true
            )
        ) {
            $conditions[] =
                '`status` = :status';

            $parameters['status'] =
                $status;
        }

        if ($warehouseId !== null) {
            $conditions[] =
                '`warehouse_id` = :warehouse_id';

            $parameters['warehouse_id'] =
                $warehouseId;
        }

        if ($userId !== null) {
            $conditions[] =
                '`user_id` = :user_id';

            $parameters['user_id'] =
                $userId;
        }

        $where = implode(
            ' AND ',
            $conditions
        );

        $total = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM `pos_shifts`
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
             FROM `pos_shifts`
             WHERE {$where}
             ORDER BY
                `opened_at` DESC,
                `id` DESC
             LIMIT :limit OFFSET :offset",
            $listParameters
        );

        /** @var list<PosShift> $items */
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
        int $shiftId
    ): ?PosShift {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `id` = :shift_id
             LIMIT 1",
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $shift = $this->hydrate($row);

        return $shift instanceof PosShift
            ? $shift
            : null;
    }

    public function findForUpdate(
        int $companyId,
        int $shiftId
    ): ?PosShift {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `id` = :shift_id
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $shift = $this->hydrate($row);

        return $shift instanceof PosShift
            ? $shift
            : null;
    }

    public function findByNumber(
        int $companyId,
        string $shiftNumber
    ): ?PosShift {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `shift_number` = :shift_number
             LIMIT 1",
            [
                'company_id' => $companyId,
                'shift_number' => $shiftNumber,
            ]
        );

        if ($row === null) {
            return null;
        }

        $shift = $this->hydrate($row);

        return $shift instanceof PosShift
            ? $shift
            : null;
    }

    public function shiftNumberExists(
        int $companyId,
        string $shiftNumber
    ): bool {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `shift_number` = :shift_number',
            [
                'company_id' => $companyId,
                'shift_number' => $shiftNumber,
            ]
        ) > 0;
    }

    public function findOpenForUser(
        int $companyId,
        int $userId
    ): ?PosShift {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `user_id` = :user_id
               AND `status` = 'open'
             ORDER BY
                `opened_at` DESC,
                `id` DESC
             LIMIT 1",
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $shift = $this->hydrate($row);

        return $shift instanceof PosShift
            ? $shift
            : null;
    }

    public function findOpenForUserForUpdate(
        int $companyId,
        int $userId
    ): ?PosShift {
        $row = $this->fetchOne(
            "SELECT {$this->selectColumnList()}
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `user_id` = :user_id
               AND `status` = 'open'
             ORDER BY
                `opened_at` DESC,
                `id` DESC
             LIMIT 1
             FOR UPDATE",
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ]
        );

        if ($row === null) {
            return null;
        }

        $shift = $this->hydrate($row);

        return $shift instanceof PosShift
            ? $shift
            : null;
    }

    public function findOpenForWarehouse(
        int $companyId,
        int $warehouseId
    ): array {
        $rows = $this->fetchAll(
            "SELECT {$this->selectColumnList()}
             FROM `pos_shifts`
             WHERE `company_id` = :company_id
               AND `warehouse_id` = :warehouse_id
               AND `status` = 'open'
             ORDER BY
                `opened_at` ASC,
                `id` ASC",
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
            ]
        );

        /** @var list<PosShift> $items */
        $items = $this->hydrateMany($rows);

        return $items;
    }

    /**
     * @param array{
     *     company_id: int,
     *     branch_id: int|null,
     *     warehouse_id: int,
     *     user_id: int,
     *     shift_number: string,
     *     opening_cash: float|int|string,
     *     opened_by: int,
     *     opening_notes: string|null
     * } $data
     */
    public function create(
        array $data
    ): PosShift {
        $this->execute(
            "INSERT INTO `pos_shifts` (
                `company_id`,
                `branch_id`,
                `warehouse_id`,
                `user_id`,
                `shift_number`,
                `status`,
                `opening_cash`,
                `cash_sales`,
                `cash_in`,
                `cash_out`,
                `expected_cash`,
                `closing_cash`,
                `cash_variance`,
                `opened_at`,
                `closed_at`,
                `opened_by`,
                `closed_by`,
                `opening_notes`,
                `closing_notes`,
                `created_at`,
                `updated_at`
             ) VALUES (
                :company_id,
                :branch_id,
                :warehouse_id,
                :user_id,
                :shift_number,
                'open',
                :opening_cash,
                0,
                0,
                0,
                :expected_cash,
                NULL,
                NULL,
                UTC_TIMESTAMP(),
                NULL,
                :opened_by,
                NULL,
                :opening_notes,
                NULL,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )",
            [
                'company_id' =>
                    $data['company_id'],

                'branch_id' =>
                    $data['branch_id'],

                'warehouse_id' =>
                    $data['warehouse_id'],

                'user_id' =>
                    $data['user_id'],

                'shift_number' =>
                    $data['shift_number'],

                'opening_cash' =>
                    $data['opening_cash'],

                'expected_cash' =>
                    $data['opening_cash'],

                'opened_by' =>
                    $data['opened_by'],

                'opening_notes' =>
                    $data['opening_notes'],
            ]
        );

        $shiftId =
            (int) $this->connection()
                ->lastInsertId();

        $shift = $this->find(
            (int) $data['company_id'],
            $shiftId
        );

        if (!$shift instanceof PosShift) {
            throw new RuntimeException(
                'POS shift was created but could not be reloaded.'
            );
        }

        return $shift;
    }

    public function updateTotals(
        int $companyId,
        int $shiftId,
        float $cashSales,
        float $cashIn,
        float $cashOut,
        float $expectedCash
    ): ?PosShift {
        $this->execute(
            "UPDATE `pos_shifts`
             SET
                `cash_sales` = :cash_sales,
                `cash_in` = :cash_in,
                `cash_out` = :cash_out,
                `expected_cash` = :expected_cash,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :shift_id
               AND `status` = 'open'",
            [
                'cash_sales' => $cashSales,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expected_cash' => $expectedCash,
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );

        return $this->find(
            $companyId,
            $shiftId
        );
    }

    public function close(
        int $companyId,
        int $shiftId,
        float $cashSales,
        float $cashIn,
        float $cashOut,
        float $expectedCash,
        float $closingCash,
        float $cashVariance,
        int $closedBy,
        ?string $closingNotes
    ): ?PosShift {
        $this->execute(
            "UPDATE `pos_shifts`
             SET
                `status` = 'closed',
                `cash_sales` = :cash_sales,
                `cash_in` = :cash_in,
                `cash_out` = :cash_out,
                `expected_cash` = :expected_cash,
                `closing_cash` = :closing_cash,
                `cash_variance` = :cash_variance,
                `closed_at` = UTC_TIMESTAMP(),
                `closed_by` = :closed_by,
                `closing_notes` = :closing_notes,
                `updated_at` = UTC_TIMESTAMP()
             WHERE `company_id` = :company_id
               AND `id` = :shift_id
               AND `status` = 'open'",
            [
                'cash_sales' => $cashSales,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expected_cash' => $expectedCash,
                'closing_cash' => $closingCash,
                'cash_variance' => $cashVariance,
                'closed_by' => $closedBy,
                'closing_notes' => $closingNotes,
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );

        return $this->find(
            $companyId,
            $shiftId
        );
    }
}