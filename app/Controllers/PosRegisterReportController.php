<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\PosRegisterReportService;
use Throwable;

final class PosRegisterReportController extends BaseAdminController
{
    private PosRegisterReportService $service;

    public function __construct()
    {
        $this->service =
            new PosRegisterReportService();
    }

    /**
     * Daily POS register dashboard.
     */
    public function index(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_reports.view'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $date =
            trim(
                (string) (
                    $_GET['date']
                    ?? ''
                )
            );

        try {
            $report =
                $this->service->dashboard(
                    $companyId,
                    $date !== ''
                        ? $date
                        : null
                );

            View::render(
                'pos_reports.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'report' =>
                        $report,

                    'date' =>
                        $report['date'],

                    'salesSummary' =>
                        $report['sales_summary'],

                    'paymentBreakdown' =>
                        $report[
                            'payment_breakdown'
                        ],

                    'shifts' =>
                        $report['shifts'],

                    'shiftSummary' =>
                        $report[
                            'shift_summary'
                        ],

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            View::render(
                'pos_reports.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'report' =>
                        null,

                    'date' =>
                        $date,

                    'salesSummary' => [
                        'sale_count' => 0,
                        'subtotal' => 0,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'shipping_amount' => 0,
                        'other_amount' => 0,
                        'grand_total' => 0,
                        'paid_amount' => 0,
                        'balance_due' => 0,
                    ],

                    'paymentBreakdown' =>
                        [],

                    'shifts' =>
                        [],

                    'shiftSummary' => [
                        'shift_count' => 0,
                        'open_shift_count' => 0,
                        'closed_shift_count' => 0,
                        'opening_cash' => 0,
                        'cash_sales' => 0,
                        'cash_in' => 0,
                        'cash_out' => 0,
                        'expected_cash' => 0,
                        'closing_cash' => 0,
                        'cash_variance' => 0,
                    ],

                    'success' =>
                        flash('success'),

                    'error' =>
                        $exception->getMessage(),
                ]
            );
        }
    }

    /**
     * Detailed report for one POS shift.
     */
    public function shift(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_reports.view'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $shiftId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'POS shift'
            );

        try {
            $report =
                $this->service->shiftReport(
                    $companyId,
                    $shiftId
                );

            View::render(
                'pos_reports.shift',
                [
                    'currentUser' =>
                        $currentUser,

                    'report' =>
                        $report,

                    'shift' =>
                        $report['shift'],

                    'summary' =>
                        $report['summary'],

                    'paymentBreakdown' =>
                        $report[
                            'payment_breakdown'
                        ],

                    'cashSummary' =>
                        $report[
                            'cash_summary'
                        ],

                    'sales' =>
                        $report['sales'],

                    'cashMovements' =>
                        $report[
                            'cash_movements'
                        ],

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

            redirect('/pos-reports');
        }
    }

    /**
     * Printable shift report.
     */
    public function printShift(): void
    {
        $currentUser =
            $this->requirePermission(
                'pos_reports.print'
            );

        $companyId =
            (int) $currentUser['company_id'];

        $shiftId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'POS shift'
            );

        try {
            $report =
                $this->service->shiftReport(
                    $companyId,
                    $shiftId
                );

            View::render(
                'pos_reports.shift_print',
                [
                    'currentUser' =>
                        $currentUser,

                    'report' =>
                        $report,

                    'shift' =>
                        $report['shift'],

                    'summary' =>
                        $report['summary'],

                    'paymentBreakdown' =>
                        $report[
                            'payment_breakdown'
                        ],

                    'cashSummary' =>
                        $report[
                            'cash_summary'
                        ],

                    'sales' =>
                        $report['sales'],

                    'cashMovements' =>
                        $report[
                            'cash_movements'
                        ],
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/pos-reports/shift?id='
                . $shiftId
            );
        }
    }

    /**
     * Convert a required request value
     * to a positive integer.
     */
    private function requiredInteger(
        mixed $value,
        string $label
    ): int {
        if ($value === null) {
            throw new ValidationException(
                $label . ' is required.'
            );
        }

        $value =
            trim(
                (string) $value
            );

        if (
            $value === ''
            || !ctype_digit($value)
        ) {
            throw new ValidationException(
                $label . ' is invalid.'
            );
        }

        $integer =
            (int) $value;

        if ($integer < 1) {
            throw new ValidationException(
                $label
                . ' must be greater than zero.'
            );
        }

        return $integer;
    }
}