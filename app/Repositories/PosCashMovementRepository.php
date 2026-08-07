<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\PosCashMovement;
use RuntimeException;

/**
 * @extends BaseRepository<PosCashMovement>
 */
final class PosCashMovementRepository extends BaseRepository
{
    protected string $table = 'pos_cash_movements';

    protected string $modelClass = PosCashMovement::class;

    protected array $selectColumns = [
        'id',
        'company_id',
        'shift_id',
        'movement_type',
        'amount',
        'reference_number',
        'notes',
        'movement_at',
        'created_by',
        'created_at',
    ];

    /**
     * @return array{
     *     items: list<PosCashMovement>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        ?int $shiftId,
        string $movementType,
        string $search,
        int $page,
        int $perPage = 20
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

        if ($shiftId !== null) {
            $conditions[] =
                '`shift_id` = :shift_id';

            $parameters['shift_id'] =
                $shiftId;
        }

        if (
            in_array(
                $movementType,
                ['cash_in', 'cash_out'],
                true
            )
        ) {
            $conditions[] =
                '`movement_type` = :movement_type';

            $parameters['movement_type'] =
                $movementType;
        }

        if ($search !== '') {
            $conditions[] = '(
                `reference_number` LIKE :reference_number
                OR `notes` LIKE :notes
            )';

            $searchValue =
                '%' . $search . '%';

            $parameters['reference_number'] =
                $searchValue;

            $parameters['notes'] =
                $searchValue;
        }

        $where =
            implode(
                ' AND ',
                $conditions
            );

        $total =
            (int) $this->fetchValue(
                "SELECT COUNT(*)
                 FROM `pos_cash_movements`
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
                 FROM `pos_cash_movements`
                 WHERE {$where}
                 ORDER BY
                    `movement_at` DESC,
                    `id` DESC
                 LIMIT :limit OFFSET :offset",
                $listParameters
            );

        /** @var list<PosCashMovement> $items */
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
        int $movementId
    ): ?PosCashMovement {
        $row =
            $this->fetchOne(
                "SELECT {$this->selectColumnList()}
                 FROM `pos_cash_movements`
                 WHERE `company_id` = :company_id
                   AND `id` = :movement_id
                 LIMIT 1",
                [
                    'company_id' =>
                        $companyId,

                    'movement_id' =>
                        $movementId,
                ]
            );

        if ($row === null) {
            return null;
        }

        $movement =
            $this->hydrate($row);

        return $movement
            instanceof PosCashMovement
                ? $movement
                : null;
    }

    /**
     * @return list<PosCashMovement>
     */
    public function byShift(
        int $companyId,
        int $shiftId
    ): array {
        $rows =
            $this->fetchAll(
                "SELECT {$this->selectColumnList()}
                 FROM `pos_cash_movements`
                 WHERE `company_id` = :company_id
                   AND `shift_id` = :shift_id
                 ORDER BY
                    `movement_at` ASC,
                    `id` ASC",
                [
                    'company_id' =>
                        $companyId,

                    'shift_id' =>
                        $shiftId,
                ]
            );

        /** @var list<PosCashMovement> $items */
        $items =
            $this->hydrateMany(
                $rows
            );

        return $items;
    }

    /**
     * @param array{
     *     company_id: int,
     *     shift_id: int,
     *     movement_type: string,
     *     amount: float|int|string,
     *     reference_number: string|null,
     *     notes: string|null,
     *     created_by: int
     * } $data
     */
    public function create(
        array $data
    ): PosCashMovement {
        $this->execute(
            'INSERT INTO `pos_cash_movements` (
                `company_id`,
                `shift_id`,
                `movement_type`,
                `amount`,
                `reference_number`,
                `notes`,
                `movement_at`,
                `created_by`,
                `created_at`
             ) VALUES (
                :company_id,
                :shift_id,
                :movement_type,
                :amount,
                :reference_number,
                :notes,
                UTC_TIMESTAMP(),
                :created_by,
                UTC_TIMESTAMP()
             )',
            [
                'company_id' =>
                    $data['company_id'],

                'shift_id' =>
                    $data['shift_id'],

                'movement_type' =>
                    $data['movement_type'],

                'amount' =>
                    $data['amount'],

                'reference_number' =>
                    $data['reference_number'],

                'notes' =>
                    $data['notes'],

                'created_by' =>
                    $data['created_by'],
            ]
        );

        $movementId =
            (int) $this->connection()
                ->lastInsertId();

        $movement =
            $this->find(
                (int) $data['company_id'],
                $movementId
            );

        if (
            !$movement
            instanceof PosCashMovement
        ) {
            throw new RuntimeException(
                'POS cash movement was created but could not be reloaded.'
            );
        }

        return $movement;
    }

    public function sumCashIn(
        int $companyId,
        int $shiftId
    ): float {
        return (float) $this->fetchValue(
            "SELECT COALESCE(
                SUM(`amount`),
                0
             )
             FROM `pos_cash_movements`
             WHERE `company_id` = :company_id
               AND `shift_id` = :shift_id
               AND `movement_type` = 'cash_in'",
            [
                'company_id' =>
                    $companyId,

                'shift_id' =>
                    $shiftId,
            ]
        );
    }

    public function sumCashOut(
        int $companyId,
        int $shiftId
    ): float {
        return (float) $this->fetchValue(
            "SELECT COALESCE(
                SUM(`amount`),
                0
             )
             FROM `pos_cash_movements`
             WHERE `company_id` = :company_id
               AND `shift_id` = :shift_id
               AND `movement_type` = 'cash_out'",
            [
                'company_id' =>
                    $companyId,

                'shift_id' =>
                    $shiftId,
            ]
        );
    }

    public function countByShift(
        int $companyId,
        int $shiftId
    ): int {
        return (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM `pos_cash_movements`
             WHERE `company_id` = :company_id
               AND `shift_id` = :shift_id',
            [
                'company_id' =>
                    $companyId,

                'shift_id' =>
                    $shiftId,
            ]
        );
    }
}