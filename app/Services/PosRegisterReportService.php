<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\PosRegisterReportRepository;
use App\Repositories\PosShiftRepository;
use DateTimeImmutable;

final class PosRegisterReportService
{
    public function __construct(
        private readonly PosRegisterReportRepository $reportRepository =
            new PosRegisterReportRepository(),

        private readonly PosShiftRepository $shiftRepository =
            new PosShiftRepository()
    ) {
    }

    /**
     * Full report for a single POS shift.
     *
     * @return array<string, mixed>
     */
    public function shiftReport(
        int $companyId,
        int $shiftId
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $shiftId,
            'Shift ID'
        );

        $shift =
            $this->shiftRepository->find(
                $companyId,
                $shiftId
            );

        if ($shift === null) {
            throw new ValidationException(
                'POS shift not found.'
            );
        }

        $summary =
            $this->reportRepository
                ->shiftSummary(
                    $companyId,
                    $shiftId
                );

        if ($summary === null) {
            throw new ValidationException(
                'POS shift report could not be generated.'
            );
        }

        $payments =
            $this->reportRepository
                ->paymentBreakdown(
                    $companyId,
                    $shiftId
                );

        $cashSummary =
            $this->reportRepository
                ->cashMovementSummary(
                    $companyId,
                    $shiftId
                );

        $sales =
            $this->reportRepository
                ->salesByShift(
                    $companyId,
                    $shiftId
                );

        $cashMovements =
            $this->reportRepository
                ->cashMovements(
                    $companyId,
                    $shiftId
                );

        return [
            'shift' =>
                $shift,

            'summary' =>
                $this->normalizeSummary(
                    $summary
                ),

            'payment_breakdown' =>
                $this->normalizePaymentBreakdown(
                    $payments
                ),

            'cash_summary' =>
                $cashSummary,

            'sales' =>
                $sales,

            'cash_movements' =>
                $cashMovements,
        ];
    }

    /**
     * Daily POS dashboard/report.
     *
     * @return array<string, mixed>
     */
    public function dailyReport(
        int $companyId,
        string $date
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $date =
            $this->validateDate(
                $date
            );

        $summary =
            $this->reportRepository
                ->dailySummary(
                    $companyId,
                    $date
                );

        $payments =
            $this->reportRepository
                ->dailyPaymentBreakdown(
                    $companyId,
                    $date
                );

        $shifts =
            $this->reportRepository
                ->shiftsByDate(
                    $companyId,
                    $date
                );

        $openShiftCount = 0;
        $closedShiftCount = 0;
        $openingCash = 0.0;
        $cashSales = 0.0;
        $cashIn = 0.0;
        $cashOut = 0.0;
        $expectedCash = 0.0;
        $closingCash = 0.0;
        $cashVariance = 0.0;

        foreach ($shifts as $shift) {
            $status =
                (string) (
                    $shift['status']
                    ?? ''
                );

            if ($status === 'open') {
                ++$openShiftCount;
            }

            if ($status === 'closed') {
                ++$closedShiftCount;
            }

            $openingCash +=
                (float) (
                    $shift['opening_cash']
                    ?? 0
                );

            $cashSales +=
                (float) (
                    $shift['cash_sales']
                    ?? 0
                );

            $cashIn +=
                (float) (
                    $shift['cash_in']
                    ?? 0
                );

            $cashOut +=
                (float) (
                    $shift['cash_out']
                    ?? 0
                );

            $expectedCash +=
                (float) (
                    $shift['expected_cash']
                    ?? 0
                );

            $closingCash +=
                (float) (
                    $shift['closing_cash']
                    ?? 0
                );

            $cashVariance +=
                (float) (
                    $shift['cash_variance']
                    ?? 0
                );
        }

        return [
            'date' =>
                $date,

            'sales_summary' =>
                $this->normalizeDailySummary(
                    $summary
                ),

            'payment_breakdown' =>
                $this->normalizePaymentBreakdown(
                    $payments
                ),

            'shifts' =>
                $shifts,

            'shift_summary' => [
                'shift_count' =>
                    count($shifts),

                'open_shift_count' =>
                    $openShiftCount,

                'closed_shift_count' =>
                    $closedShiftCount,

                'opening_cash' =>
                    $this->money(
                        $openingCash
                    ),

                'cash_sales' =>
                    $this->money(
                        $cashSales
                    ),

                'cash_in' =>
                    $this->money(
                        $cashIn
                    ),

                'cash_out' =>
                    $this->money(
                        $cashOut
                    ),

                'expected_cash' =>
                    $this->money(
                        $expectedCash
                    ),

                'closing_cash' =>
                    $this->money(
                        $closingCash
                    ),

                'cash_variance' =>
                    $this->money(
                        $cashVariance
                    ),
            ],
        ];
    }

    /**
     * Dashboard aliases daily report.
     *
     * @return array<string, mixed>
     */
    public function dashboard(
        int $companyId,
        ?string $date = null
    ): array {
        $date =
            $date === null
                || trim($date) === ''
                ? gmdate('Y-m-d')
                : $date;

        return $this->dailyReport(
            $companyId,
            $date
        );
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    private function normalizeSummary(
        array $summary
    ): array {
        $moneyFields = [
            'opening_cash',
            'cash_sales',
            'cash_in',
            'cash_out',
            'expected_cash',
            'closing_cash',
            'cash_variance',
            'subtotal',
            'discount_amount',
            'tax_amount',
            'shipping_amount',
            'other_amount',
            'grand_total',
            'paid_amount',
            'balance_due',
        ];

        foreach ($moneyFields as $field) {
            if (
                !array_key_exists(
                    $field,
                    $summary
                )
            ) {
                continue;
            }

            if (
                $summary[$field] === null
            ) {
                continue;
            }

            $summary[$field] =
                $this->money(
                    (float) $summary[$field]
                );
        }

        $summary['sale_count'] =
            (int) (
                $summary['sale_count']
                ?? 0
            );

        return $summary;
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    private function normalizeDailySummary(
        array $summary
    ): array {
        $summary['sale_count'] =
            (int) (
                $summary['sale_count']
                ?? 0
            );

        foreach (
            [
                'subtotal',
                'discount_amount',
                'tax_amount',
                'shipping_amount',
                'other_amount',
                'grand_total',
                'paid_amount',
                'balance_due',
            ] as $field
        ) {
            $summary[$field] =
                $this->money(
                    (float) (
                        $summary[$field]
                        ?? 0
                    )
                );
        }

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $payments
     *
     * @return list<array<string, mixed>>
     */
    private function normalizePaymentBreakdown(
        array $payments
    ): array {
        $result = [];

        foreach ($payments as $payment) {
            $result[] = [
                'payment_method' =>
                    (string) (
                        $payment[
                            'payment_method'
                        ]
                        ?? ''
                    ),

                'payment_count' =>
                    (int) (
                        $payment[
                            'payment_count'
                        ]
                        ?? 0
                    ),

                'amount' =>
                    $this->money(
                        (float) (
                            $payment['amount']
                            ?? 0
                        )
                    ),
            ];
        }

        return $result;
    }

    private function validateDate(
        string $date
    ): string {
        $date =
            trim($date);

        $parsed =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );

        if (
            !$parsed instanceof DateTimeImmutable
            || $parsed->format('Y-m-d')
                !== $date
        ) {
            throw new ValidationException(
                'Report date must use YYYY-MM-DD format.'
            );
        }

        return $date;
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

    private function money(
        float|int $value
    ): float {
        return round(
            (float) $value,
            4
        );
    }
}