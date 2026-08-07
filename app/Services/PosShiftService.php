<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use App\Repositories\PosCashMovementRepository;
use App\Repositories\PosShiftRepository;
use App\Repositories\SalePaymentRepository;
use PDO;
use Throwable;

final class PosShiftService
{
    public function __construct(
        private readonly PosShiftRepository $shiftRepository =
            new PosShiftRepository(),

        private readonly PosCashMovementRepository $movementRepository =
            new PosCashMovementRepository(),

        private readonly SalePaymentRepository $paymentRepository =
            new SalePaymentRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
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
        array $filters
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $search =
            trim(
                (string) (
                    $filters['search']
                    ?? ''
                )
            );

        if (mb_strlen($search) > 190) {
            throw new ValidationException(
                'Search text must be 190 characters or fewer.'
            );
        }

        $status =
            trim(
                strtolower(
                    (string) (
                        $filters['status']
                        ?? ''
                    )
                )
            );

        if (
            $status !== ''
            && !in_array(
                $status,
                PosShift::statuses(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid POS shift status.'
            );
        }

        $warehouseId =
            $this->nullableId(
                $filters['warehouse_id']
                    ?? null
            );

        $userId =
            $this->nullableId(
                $filters['user_id']
                    ?? null
            );

        $page =
            max(
                1,
                (int) (
                    $filters['page']
                    ?? 1
                )
            );

        $perPage =
            (int) (
                $filters['per_page']
                ?? 20
            );

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        return $this->shiftRepository
            ->paginate(
                $companyId,
                $search,
                $status,
                $warehouseId,
                $userId,
                $page,
                $perPage
            );
    }

    public function find(
        int $companyId,
        int $shiftId
    ): PosShift {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $shiftId,
            'Shift ID'
        );

        $shift =
            $this->shiftRepository
                ->find(
                    $companyId,
                    $shiftId
                );

        if (!$shift instanceof PosShift) {
            throw new ValidationException(
                'POS shift not found.'
            );
        }

        return $shift;
    }

    public function currentShift(
        int $companyId,
        int $userId
    ): ?PosShift {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        return $this->shiftRepository
            ->findOpenForUser(
                $companyId,
                $userId
            );
    }

    /**
     * @return list<PosCashMovement>
     */
    public function movements(
        int $companyId,
        int $shiftId
    ): array {
        $this->find(
            $companyId,
            $shiftId
        );

        return $this->movementRepository
            ->byShift(
                $companyId,
                $shiftId
            );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function openShift(
        int $companyId,
        int $userId,
        array $input
    ): PosShift {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $warehouseId =
            $this->requiredId(
                $input['warehouse_id']
                    ?? null,
                'Warehouse'
            );

        $branchId =
            $this->nullableId(
                $input['branch_id']
                    ?? null
            );

        $openingCash =
            $this->nonNegativeMoney(
                $input['opening_cash']
                    ?? 0,
                'Opening cash'
            );

        $openingNotes =
            $this->nullableString(
                $input['opening_notes']
                    ?? null
            );

        $requestedShiftNumber =
            trim(
                (string) (
                    $input['shift_number']
                    ?? ''
                )
            );

        if (
            $requestedShiftNumber !== ''
            && mb_strlen(
                $requestedShiftNumber
            ) > 100
        ) {
            throw new ValidationException(
                'Shift number must be 100 characters or fewer.'
            );
        }

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $existing =
                $this->shiftRepository
                    ->findOpenForUserForUpdate(
                        $companyId,
                        $userId
                    );

            if ($existing instanceof PosShift) {
                throw new ValidationException(
                    'This user already has an open POS shift.'
                );
            }

            $shiftNumber =
                $requestedShiftNumber;

            if ($shiftNumber === '') {
                $shiftNumber =
                    $this->generateShiftNumber(
                        $companyId
                    );
            }

            if (
                $this->shiftRepository
                    ->shiftNumberExists(
                        $companyId,
                        $shiftNumber
                    )
            ) {
                throw new ValidationException(
                    'POS shift number already exists.'
                );
            }

            $shift =
                $this->shiftRepository
                    ->create(
                        [
                            'company_id' =>
                                $companyId,

                            'branch_id' =>
                                $branchId,

                            'warehouse_id' =>
                                $warehouseId,

                            'user_id' =>
                                $userId,

                            'shift_number' =>
                                $shiftNumber,

                            'opening_cash' =>
                                $openingCash,

                            'opened_by' =>
                                $userId,

                            'opening_notes' =>
                                $openingNotes,
                        ]
                    );

            $database->commit();

            return $shift;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function cashIn(
        int $companyId,
        int $shiftId,
        int $userId,
        array $input
    ): PosCashMovement {
        return $this->createMovement(
            $companyId,
            $shiftId,
            $userId,
            'cash_in',
            $input
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function cashOut(
        int $companyId,
        int $shiftId,
        int $userId,
        array $input
    ): PosCashMovement {
        return $this->createMovement(
            $companyId,
            $shiftId,
            $userId,
            'cash_out',
            $input
        );
    }

    public function recalculate(
        int $companyId,
        int $shiftId
    ): PosShift {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $shiftId,
            'Shift ID'
        );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $shift =
                $this->shiftRepository
                    ->findForUpdate(
                        $companyId,
                        $shiftId
                    );

            if (!$shift instanceof PosShift) {
                throw new ValidationException(
                    'POS shift not found.'
                );
            }

            if (!$shift->isOpen()) {
                throw new ValidationException(
                    'Only an open POS shift can be recalculated.'
                );
            }

            $updated =
                $this->recalculateLocked(
                    $companyId,
                    $shift
                );

            $database->commit();

            return $updated;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }
    }

    /**
     * @return array{
     *     shift: PosShift,
     *     movements: list<PosCashMovement>,
     *     opening_cash: float,
     *     cash_sales: float,
     *     cash_in: float,
     *     cash_out: float,
     *     expected_cash: float,
     *     closing_cash: float|null,
     *     cash_variance: float|null,
     *     movement_count: int
     * }
     */
    public function summary(
        int $companyId,
        int $shiftId,
        bool $refreshOpenShift = true
    ): array {
        $shift =
            $this->find(
                $companyId,
                $shiftId
            );

        if (
            $refreshOpenShift
            && $shift->isOpen()
        ) {
            $shift =
                $this->recalculate(
                    $companyId,
                    $shiftId
                );
        }

        $movements =
            $this->movementRepository
                ->byShift(
                    $companyId,
                    $shiftId
                );

        return [
            'shift' =>
                $shift,

            'movements' =>
                $movements,

            'opening_cash' =>
                $shift->openingCash(),

            'cash_sales' =>
                $shift->cashSales(),

            'cash_in' =>
                $shift->cashIn(),

            'cash_out' =>
                $shift->cashOut(),

            'expected_cash' =>
                $shift->expectedCash(),

            'closing_cash' =>
                $shift->closingCash(),

            'cash_variance' =>
                $shift->cashVariance(),

            'movement_count' =>
                count($movements),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function closeShift(
        int $companyId,
        int $shiftId,
        int $userId,
        array $input
    ): PosShift {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $shiftId,
            'Shift ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $closingCash =
            $this->nonNegativeMoney(
                $input['closing_cash']
                    ?? null,
                'Closing cash'
            );

        $closingNotes =
            $this->nullableString(
                $input['closing_notes']
                    ?? null
            );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $shift =
                $this->shiftRepository
                    ->findForUpdate(
                        $companyId,
                        $shiftId
                    );

            if (!$shift instanceof PosShift) {
                throw new ValidationException(
                    'POS shift not found.'
                );
            }

            if (!$shift->isOpen()) {
                throw new ValidationException(
                    'POS shift is already closed.'
                );
            }

            /*
             * Recalculate directly from source data
             * before closing.
             */
            $cashSales =
                $this->money(
                    $this->paymentRepository
                        ->sumCashPaymentsByShift(
                            $companyId,
                            $shiftId
                        )
                );

            $cashIn =
                $this->money(
                    $this->movementRepository
                        ->sumCashIn(
                            $companyId,
                            $shiftId
                        )
                );

            $cashOut =
                $this->money(
                    $this->movementRepository
                        ->sumCashOut(
                            $companyId,
                            $shiftId
                        )
                );

            $expectedCash =
                $this->money(
                    $shift->openingCash()
                    + $cashSales
                    + $cashIn
                    - $cashOut
                );

            $cashVariance =
                $this->money(
                    $closingCash
                    - $expectedCash
                );

            $closed =
                $this->shiftRepository
                    ->close(
                        $companyId,
                        $shiftId,
                        $cashSales,
                        $cashIn,
                        $cashOut,
                        $expectedCash,
                        $closingCash,
                        $cashVariance,
                        $userId,
                        $closingNotes
                    );

            if (!$closed instanceof PosShift) {
                throw new ValidationException(
                    'POS shift could not be closed.'
                );
            }

            if (!$closed->isClosed()) {
                throw new ValidationException(
                    'POS shift could not be marked as closed.'
                );
            }

            $database->commit();

            return $closed;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }
    }

    /**
     * @return array<string, string>
     */
    public function movementTypeOptions(): array
    {
        return PosCashMovement
            ::movementTypeOptions();
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return PosShift::statusOptions();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createMovement(
        int $companyId,
        int $shiftId,
        int $userId,
        string $movementType,
        array $input
    ): PosCashMovement {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $shiftId,
            'Shift ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        if (
            !in_array(
                $movementType,
                PosCashMovement::movementTypes(),
                true
            )
        ) {
            throw new ValidationException(
                'Invalid POS cash movement type.'
            );
        }

        $amount =
            $this->positiveMoney(
                $input['amount']
                    ?? null,
                'Cash movement amount'
            );

        $referenceNumber =
            $this->nullableString(
                $input['reference_number']
                    ?? null,
                190
            );

        $notes =
            $this->nullableString(
                $input['notes']
                    ?? null
            );

        $database =
            Database::connection();

        try {
            $this->beginTransaction(
                $database
            );

            $shift =
                $this->shiftRepository
                    ->findForUpdate(
                        $companyId,
                        $shiftId
                    );

            if (!$shift instanceof PosShift) {
                throw new ValidationException(
                    'POS shift not found.'
                );
            }

            if (!$shift->isOpen()) {
                throw new ValidationException(
                    'Cash movements can only be recorded on an open POS shift.'
                );
            }

            $movement =
                $this->movementRepository
                    ->create(
                        [
                            'company_id' =>
                                $companyId,

                            'shift_id' =>
                                $shiftId,

                            'movement_type' =>
                                $movementType,

                            'amount' =>
                                $amount,

                            'reference_number' =>
                                $referenceNumber,

                            'notes' =>
                                $notes,

                            'created_by' =>
                                $userId,
                        ]
                    );

            /*
             * Recalculate immediately so the stored
             * shift summary remains synchronized.
             */
            $this->recalculateLocked(
                $companyId,
                $shift
            );

            $database->commit();

            return $movement;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded(
                $database
            );

            throw $exception;
        }
    }

    private function recalculateLocked(
        int $companyId,
        PosShift $shift
    ): PosShift {
        $shiftId =
            (int) $shift->id();

        if ($shiftId < 1) {
            throw new ValidationException(
                'POS shift ID is not available.'
            );
        }

        $cashSales =
            $this->money(
                $this->paymentRepository
                    ->sumCashPaymentsByShift(
                        $companyId,
                        $shiftId
                    )
            );

        $cashIn =
            $this->money(
                $this->movementRepository
                    ->sumCashIn(
                        $companyId,
                        $shiftId
                    )
            );

        $cashOut =
            $this->money(
                $this->movementRepository
                    ->sumCashOut(
                        $companyId,
                        $shiftId
                    )
            );

        $expectedCash =
            $this->money(
                $shift->openingCash()
                + $cashSales
                + $cashIn
                - $cashOut
            );

        $updated =
            $this->shiftRepository
                ->updateTotals(
                    $companyId,
                    $shiftId,
                    $cashSales,
                    $cashIn,
                    $cashOut,
                    $expectedCash
                );

        if (!$updated instanceof PosShift) {
            throw new ValidationException(
                'POS shift totals could not be updated.'
            );
        }

        return $updated;
    }

    private function generateShiftNumber(
        int $companyId
    ): string {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix =
                strtoupper(
                    substr(
                        bin2hex(
                            random_bytes(4)
                        ),
                        0,
                        6
                    )
                );

            $shiftNumber =
                'SHIFT-'
                . gmdate('Ymd')
                . '-'
                . $suffix;

            if (
                !$this->shiftRepository
                    ->shiftNumberExists(
                        $companyId,
                        $shiftNumber
                    )
            ) {
                return $shiftNumber;
            }
        }

        throw new ValidationException(
            'A unique POS shift number could not be generated.'
        );
    }

    private function requiredId(
        mixed $value,
        string $label
    ): int {
        $id =
            $this->nullableId(
                $value
            );

        if ($id === null) {
            throw new ValidationException(
                $label . ' is required.'
            );
        }

        return $id;
    }

    private function nullableId(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                'Expected a valid identifier.'
            );
        }

        $id =
            (int) $value;

        if ($id < 1) {
            throw new ValidationException(
                'Identifier must be greater than zero.'
            );
        }

        return $id;
    }

    private function validatePositiveId(
        int $value,
        string $label
    ): void {
        if ($value < 1) {
            throw new ValidationException(
                $label
                . ' must be greater than zero.'
            );
        }
    }

    private function positiveMoney(
        mixed $value,
        string $label
    ): float {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            throw new ValidationException(
                $label . ' is required.'
            );
        }

        $amount =
            $this->money(
                (float) $value
            );

        if ($amount <= 0) {
            throw new ValidationException(
                $label
                . ' must be greater than zero.'
            );
        }

        return $amount;
    }

    private function nonNegativeMoney(
        mixed $value,
        string $label
    ): float {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            throw new ValidationException(
                $label . ' is required.'
            );
        }

        $amount =
            $this->money(
                (float) $value
            );

        if ($amount < 0) {
            throw new ValidationException(
                $label
                . ' must be zero or greater.'
            );
        }

        return $amount;
    }

    private function money(
        float|int $value
    ): float {
        $amount =
            round(
                (float) $value,
                4
            );

        if (!is_finite($amount)) {
            throw new ValidationException(
                'Amount must be finite.'
            );
        }

        return $amount;
    }

    private function nullableString(
        mixed $value,
        ?int $maximumLength = null
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            throw new ValidationException(
                'Expected a valid string.'
            );
        }

        $value =
            trim($value);

        if ($value === '') {
            return null;
        }

        if (
            $maximumLength !== null
            && mb_strlen($value)
                > $maximumLength
        ) {
            throw new ValidationException(
                'Value must not exceed '
                . $maximumLength
                . ' characters.'
            );
        }

        return $value;
    }

    private function beginTransaction(
        PDO $database
    ): void {
        if ($database->inTransaction()) {
            throw new ValidationException(
                'A database transaction is already active.'
            );
        }

        if (!$database->beginTransaction()) {
            throw new ValidationException(
                'POS shift transaction could not be started.'
            );
        }
    }

    private function rollbackIfNeeded(
        PDO $database
    ): void {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
    }
}