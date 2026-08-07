<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\WarehouseRepository;
use App\Services\PosShiftService;
use Throwable;

final class PosShiftController extends BaseAdminController
{
    private PosShiftService $service;

    private WarehouseRepository $warehouseRepository;

    public function __construct()
    {
        $this->service =
            new PosShiftService();

        $this->warehouseRepository =
            new WarehouseRepository();
    }

    /**
     * POS shift list.
     */
    public function index(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.view'
            );

        $companyId =
            (int) $currentUser['company_id'];

        try {
            $result =
                $this->service->paginate(
                    $companyId,
                    [
                        'search' =>
                            $_GET['search']
                            ?? '',

                        'status' =>
                            $_GET['status']
                            ?? '',

                        'user_id' =>
                            $_GET['user_id']
                            ?? null,

                        'warehouse_id' =>
                            $_GET['warehouse_id']
                            ?? null,

                        'page' =>
                            $_GET['page']
                            ?? 1,

                        'per_page' =>
                            20,
                    ]
                );

            $currentShift =
                $this->service->currentShift(
                    $companyId,
                    (int) $currentUser['id']
                );

            View::render(
                'pos_shifts.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'shifts' =>
                        $result['items'],

                    'total' =>
                        $result['total'],

                    'page' =>
                        $result['page'],

                    'perPage' =>
                        $result['per_page'],

                    'lastPage' =>
                        $result['last_page'],

                    'filters' => [
                        'search' =>
                            trim(
                                (string) (
                                    $_GET['search']
                                    ?? ''
                                )
                            ),

                        'status' =>
                            trim(
                                (string) (
                                    $_GET['status']
                                    ?? ''
                                )
                            ),

                        'user_id' =>
                            $this->nullableInteger(
                                $_GET['user_id']
                                ?? null
                            ),

                        'warehouse_id' =>
                            $this->nullableInteger(
                                $_GET['warehouse_id']
                                ?? null
                            ),
                    ],

                    'statusOptions' =>
                        $this->service
                            ->statusOptions(),

                    'warehouseOptions' =>
                        $this->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'currentShift' =>
                        $currentShift,

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            View::render(
                'pos_shifts.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'shifts' =>
                        [],

                    'total' =>
                        0,

                    'page' =>
                        1,

                    'perPage' =>
                        20,

                    'lastPage' =>
                        1,

                    'filters' => [
                        'search' => '',
                        'status' => '',
                        'user_id' => null,
                        'warehouse_id' => null,
                    ],

                    'statusOptions' =>
                        $this->service
                            ->statusOptions(),

                    'warehouseOptions' =>
                        $this->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'currentShift' =>
                        null,

                    'success' =>
                        flash('success'),

                    'error' =>
                        $exception->getMessage(),
                ]
            );
        }
    }

    /**
     * Show a POS shift and its summary.
     */
    public function show(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.view'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $shiftId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'POS shift'
            );

        try {
            $summary =
                $this->service->summary(
                    $companyId,
                    $shiftId
                );

            View::render(
                'pos_shifts.show',
                [
                    'currentUser' =>
                        $currentUser,

                    'shift' =>
                        $summary['shift'],

                    'summary' =>
                        $summary,

                    'movements' =>
                        $summary['movements']
                        ?? $this->service
                            ->movements(
                                $companyId,
                                $shiftId
                            ),

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/pos-shifts');
        }
    }

    /**
     * Display open-shift form.
     */
    public function create(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.open'
            );

        $companyId =
            (int) $currentUser['company_id'];

        try {
            $currentShift =
                $this->service->currentShift(
                    $companyId,
                    (int) $currentUser['id']
                );

            if ($currentShift !== null) {
                redirect(
                    '/pos-shifts/show?id='
                    . $currentShift->id()
                );
            }

            View::render(
                'pos_shifts.open',
                [
                    'currentUser' =>
                        $currentUser,

                    'warehouseOptions' =>
                        $this->warehouseRepository
                            ->activeOptions(
                                $companyId
                            ),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/pos-shifts');
        }
    }

    /**
     * Open a cashier shift.
     */
    public function store(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.open'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $userId =
            (int) $currentUser['id'];

        try {
            $shift =
                $this->service->openShift(
                    $companyId,
                    $userId,
                    [
                        'warehouse_id' =>
                            $_POST['warehouse_id']
                            ?? null,

                        'opening_cash' =>
                            $_POST['opening_cash']
                            ?? 0,

                        'opening_notes' =>
                            $_POST['opening_notes']
                            ?? null,
                    ]
                );

            flash(
                'success',
                'POS shift opened successfully.'
            );

            redirect(
                '/pos-shifts/show?id='
                . $shift->id()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/pos-shifts/open');
        }
    }

    /**
     * Display cash-in form.
     */
    public function cashInForm(): void
    {
        $this->cashMovementForm(
            'cash_in'
        );
    }

    /**
     * Display cash-out form.
     */
    public function cashOutForm(): void
    {
        $this->cashMovementForm(
            'cash_out'
        );
    }

    /**
     * Record cash-in.
     */
    public function cashIn(): void
    {
        $this->storeCashMovement(
            'cash_in'
        );
    }

    /**
     * Record cash-out.
     */
    public function cashOut(): void
    {
        $this->storeCashMovement(
            'cash_out'
        );
    }

    /**
     * Display close-shift form.
     */
    public function closeForm(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.close'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $shiftId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'POS shift'
            );

        try {
            $summary =
                $this->service->summary(
                    $companyId,
                    $shiftId
                );

            $shift =
                $summary['shift'];

            if (!$shift->isOpen()) {
                throw new ValidationException(
                    'Only an open POS shift can be closed.'
                );
            }

            View::render(
                'pos_shifts.close',
                [
                    'currentUser' =>
                        $currentUser,

                    'shift' =>
                        $shift,

                    'summary' =>
                        $summary,

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/pos-shifts/show?id='
                . $shiftId
            );
        }
    }

    /**
     * Close a POS shift.
     */
    public function close(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.close'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $userId =
            (int) $currentUser['id'];

        $shiftId =
            $this->requiredInteger(
                $_POST['shift_id']
                ?? null,
                'POS shift'
            );

        try {
            $shift =
                $this->service->closeShift(
                    $companyId,
                    $shiftId,
                    $userId,
                    [
                        'closing_cash' =>
                            $_POST['closing_cash']
                            ?? null,

                        'closing_notes' =>
                            $_POST['closing_notes']
                            ?? null,
                    ]
                );

            flash(
                'success',
                'POS shift closed successfully.'
            );

            redirect(
                '/pos-shifts/show?id='
                . $shift->id()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/pos-shifts/close?id='
                . $shiftId
            );
        }
    }

    /**
     * Shared cash movement form.
     */
    private function cashMovementForm(
        string $movementType
    ): void {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.cash_movement'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $shiftId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'POS shift'
            );

        try {
            $shift =
                $this->service->find(
                    $companyId,
                    $shiftId
                );

            if (!$shift->isOpen()) {
                throw new ValidationException(
                    'Cash movements can only be recorded on an open POS shift.'
                );
            }

            View::render(
                'pos_shifts.cash_movement',
                [
                    'currentUser' =>
                        $currentUser,

                    'shift' =>
                        $shift,

                    'movementType' =>
                        $movementType,

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/pos-shifts/show?id='
                . $shiftId
            );
        }
    }

    /**
     * Shared cash movement store action.
     */
    private function storeCashMovement(
        string $movementType
    ): void {
        $currentUser =
            $this->requirePermission(
                'pos_shifts.cash_movement'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $userId =
            (int) $currentUser['id'];

        $shiftId =
            $this->requiredInteger(
                $_POST['shift_id']
                ?? null,
                'POS shift'
            );

        $input = [
            'amount' =>
                $_POST['amount']
                ?? null,

            'reference_number' =>
                $_POST['reference_number']
                ?? null,

            'notes' =>
                $_POST['notes']
                ?? null,
        ];

        try {
            if ($movementType === 'cash_in') {
                $this->service->cashIn(
                    $companyId,
                    $shiftId,
                    $userId,
                    $input
                );
            } elseif (
                $movementType === 'cash_out'
            ) {
                $this->service->cashOut(
                    $companyId,
                    $shiftId,
                    $userId,
                    $input
                );
            } else {
                throw new ValidationException(
                    'Invalid POS cash movement type.'
                );
            }

            flash(
                'success',
                $movementType === 'cash_in'
                    ? 'Cash in recorded successfully.'
                    : 'Cash out recorded successfully.'
            );

            redirect(
                '/pos-shifts/show?id='
                . $shiftId
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            $path =
                $movementType === 'cash_in'
                    ? '/pos-shifts/cash-in'
                    : '/pos-shifts/cash-out';

            redirect(
                $path
                . '?id='
                . $shiftId
            );
        }
    }

    /**
     * Convert an optional request value
     * to a positive integer.
     */
    private function nullableInteger(
        mixed $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            return null;
        }

        $integer =
            (int) $value;

        return $integer > 0
            ? $integer
            : null;
    }

    /**
     * Convert a required request value
     * to a positive integer.
     */
    private function requiredInteger(
        mixed $value,
        string $label
    ): int {
        $integer =
            $this->nullableInteger(
                $value
            );

        if ($integer === null) {
            throw new ValidationException(
                $label . ' is required.'
            );
        }

        return $integer;
    }
}