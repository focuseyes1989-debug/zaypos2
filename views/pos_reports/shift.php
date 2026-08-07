<?php

declare(strict_types=1);

use App\Models\PosShift;

if (!$shift instanceof PosShift) {
    throw new RuntimeException(
        'POS shift report is not available.'
    );
}

$money = static function (
    float|int|string|null $value
): string {
    return number_format(
        (float) ($value ?? 0),
        2
    );
};

$paymentLabel = static function (
    string $method
): string {
    return match ($method) {
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
        'transfer' => 'Transfer',
        'mobile' => 'Mobile Payment',
        default => ucwords(
            str_replace(
                '_',
                ' ',
                $method
            )
        ),
    };
};

$canPrint =
    in_array(
        'pos_reports.print',
        $currentUser['permissions'] ?? [],
        true
    );

$variance =
    $summary['cash_variance']
    ?? null;

$varianceClass =
    'variance-zero';

if ($variance !== null) {
    if ((float) $variance > 0) {
        $varianceClass =
            'variance-positive';
    } elseif ((float) $variance < 0) {
        $varianceClass =
            'variance-negative';
    }
}

$cashMovements =
    $cashMovements
    ?? (
        $report['cash_movements']
        ?? []
    );
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= e($shift->shiftNumber()) ?>
        — POS Shift Report
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .report-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .report-card {
            background: #fff;
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 16px;
        }

        .report-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .65;
            margin-bottom: 7px;
        }

        .report-value {
            font-size: 21px;
            font-weight: 700;
        }

        .report-section {
            margin-top: 18px;
        }

        .report-section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }

        .report-section-heading h2 {
            margin: 0;
        }

        .shift-info-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 0 28px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid
                var(--border-color, #e5e7eb);
        }

        .info-row span {
            opacity: .7;
        }

        .info-row strong {
            text-align: right;
        }

        .status-badge {
            display: inline-flex;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-open {
            background: #dcfce7;
            color: #166534;
        }

        .status-closed {
            background: #e5e7eb;
            color: #374151;
        }

        .payment-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .payment-card {
            background: #fff;
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 12px;
            padding: 14px;
        }

        .payment-card strong {
            display: block;
            font-size: 20px;
            margin: 5px 0;
        }

        .variance-positive {
            color: #166534;
        }

        .variance-negative {
            color: #b91c1c;
        }

        .variance-zero {
            color: inherit;
        }

        .movement-in {
            color: #166534;
            font-weight: 700;
        }

        .movement-out {
            color: #b91c1c;
            font-weight: 700;
        }

        .money-cell {
            text-align: right;
            white-space: nowrap;
        }

        @media (max-width: 1000px) {
            .report-grid,
            .payment-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .report-grid,
            .payment-grid,
            .shift-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="app-page">

<?php
require BASE_PATH
    . '/views/partials/admin_header.php';
?>

<main class="dashboard-shell">

    <section class="page-heading">
        <div>
            <p class="eyebrow">
                POS SHIFT REPORT
            </p>

            <h1>
                <?= e(
                    $shift->shiftNumber()
                ) ?>
            </h1>

            <p class="muted">
                Sales, payments and register cash
                reconciliation for this POS shift.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos-reports')
                ) ?>"
            >
                POS Reports
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/pos-shifts/show?id='
                        . $shift->id()
                    )
                ) ?>"
            >
                Shift Detail
            </a>

            <?php if ($canPrint): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/pos-reports/shift/print?id='
                            . $shift->id()
                        )
                    ) ?>"
                    target="_blank"
                >
                    Print Report
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($success)): ?>
        <div class="success-box">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="report-grid">

        <div class="report-card">
            <div class="report-label">
                Status
            </div>

            <div class="report-value">
                <span
                    class="status-badge
                        <?= e(
                            $shift->isOpen()
                                ? 'status-open'
                                : 'status-closed'
                        ) ?>"
                >
                    <?= e(
                        $shift->isOpen()
                            ? 'Open'
                            : 'Closed'
                    ) ?>
                </span>
            </div>
        </div>

        <div class="report-card">
            <div class="report-label">
                POS Sales
            </div>

            <div class="report-value">
                <?= e(
                    (string) (
                        $summary['sale_count']
                        ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="report-card">
            <div class="report-label">
                Grand Total
            </div>

            <div class="report-value">
                <?= e(
                    $money(
                        $summary['grand_total']
                        ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="report-card">
            <div class="report-label">
                Cash Variance
            </div>

            <div
                class="report-value
                    <?= e($varianceClass) ?>"
            >
                <?php if ($variance !== null): ?>
                    <?= e(
                        $money($variance)
                    ) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>
        </div>

    </section>

    <section class="form-card">
        <div class="shift-info-grid">

            <div>
                <div class="info-row">
                    <span>
                        Shift ID
                    </span>

                    <strong>
                        #<?= e(
                            (string) $shift->id()
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Cashier
                    </span>

                    <strong>
                        #<?= e(
                            (string) $shift->userId()
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Warehouse
                    </span>

                    <strong>
                        #<?= e(
                            (string)
                            $shift->warehouseId()
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Opened
                    </span>

                    <strong>
                        <?= e(
                            $shift->openedAt()
                                ->format(
                                    'Y-m-d H:i:s'
                                )
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Closed
                    </span>

                    <strong>
                        <?php if (
                            $shift->closedAt()
                            !== null
                        ): ?>
                            <?= e(
                                $shift->closedAt()
                                    ->format(
                                        'Y-m-d H:i:s'
                                    )
                            ) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </strong>
                </div>
            </div>

            <div>
                <div class="info-row">
                    <span>
                        Opening Cash
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $summary[
                                    'opening_cash'
                                ] ?? 0
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Cash Sales
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $summary[
                                    'cash_sales'
                                ] ?? 0
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Cash In
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $summary[
                                    'cash_in'
                                ] ?? 0
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Cash Out
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $summary[
                                    'cash_out'
                                ] ?? 0
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Expected Cash
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $summary[
                                    'expected_cash'
                                ] ?? 0
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>
                        Closing Cash
                    </span>

                    <strong>
                        <?php if (
                            ($summary[
                                'closing_cash'
                            ] ?? null) !== null
                        ): ?>
                            <?= e(
                                $money(
                                    $summary[
                                        'closing_cash'
                                    ]
                                )
                            ) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </strong>
                </div>
            </div>

        </div>
    </section>

    <section class="report-section">

        <div class="report-section-heading">
            <div>
                <h2>
                    Sales Summary
                </h2>

                <p class="muted">
                    Completed POS sales linked
                    to this shift.
                </p>
            </div>
        </div>

        <div class="report-grid">

            <div class="report-card">
                <div class="report-label">
                    Subtotal
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $summary['subtotal']
                            ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Discount
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $summary[
                                'discount_amount'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Tax
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $summary['tax_amount']
                            ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Shipping / Other
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            (
                                $summary[
                                    'shipping_amount'
                                ] ?? 0
                            )
                            +
                            (
                                $summary[
                                    'other_amount'
                                ] ?? 0
                            )
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Grand Total
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $summary[
                                'grand_total'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Paid Amount
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $summary[
                                'paid_amount'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Balance Due
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $summary[
                                'balance_due'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

        </div>
    </section>

    <section class="report-section">

        <div class="report-section-heading">
            <div>
                <h2>
                    Payment Breakdown
                </h2>

                <p class="muted">
                    Payments received during
                    this shift.
                </p>
            </div>
        </div>

        <?php if (
            empty($paymentBreakdown)
        ): ?>
            <div class="form-card">
                <div class="empty-state">
                    No payments recorded.
                </div>
            </div>
        <?php else: ?>

            <div class="payment-grid">

                <?php foreach (
                    $paymentBreakdown
                    as $payment
                ): ?>

                    <div class="payment-card">
                        <span class="muted">
                            <?= e(
                                $paymentLabel(
                                    (string) (
                                        $payment[
                                            'payment_method'
                                        ] ?? ''
                                    )
                                )
                            ) ?>
                        </span>

                        <strong>
                            <?= e(
                                $money(
                                    $payment['amount']
                                    ?? 0
                                )
                            ) ?>
                        </strong>

                        <small class="muted">
                            <?= e(
                                (string) (
                                    $payment[
                                        'payment_count'
                                    ] ?? 0
                                )
                            ) ?>
                            payment(s)
                        </small>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <section class="report-section table-card">

        <div class="report-section-heading">
            <div>
                <h2>
                    Sales
                </h2>

                <p class="muted">
                    <?= e(
                        (string) count(
                            $sales ?? []
                        )
                    ) ?>
                    completed sale(s)
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                <tr>
                    <th>Sale</th>
                    <th>Date</th>
                    <th>Payment</th>
                    <th class="money-cell">
                        Subtotal
                    </th>
                    <th class="money-cell">
                        Total
                    </th>
                    <th class="money-cell">
                        Paid
                    </th>
                    <th class="money-cell">
                        Balance
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                No completed sales
                                found for this shift.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach (
                        $sales
                        as $sale
                    ): ?>
                        <tr>
                            <td>
                                <a
                                    href="<?= e(
                                        app_url(
                                            '/sales/show?id='
                                            . (int) (
                                                $sale['id']
                                                ?? 0
                                            )
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        (string) (
                                            $sale[
                                                'sale_number'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </a>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $sale[
                                            'completed_at'
                                        ]
                                        ?? $sale[
                                            'sale_date'
                                        ]
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    ucfirst(
                                        (string) (
                                            $sale[
                                                'payment_status'
                                            ] ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?= e(
                                    $money(
                                        $sale['subtotal']
                                        ?? 0
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?= e(
                                    $money(
                                        $sale[
                                            'grand_total'
                                        ] ?? 0
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?= e(
                                    $money(
                                        $sale[
                                            'paid_amount'
                                        ] ?? 0
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?= e(
                                    $money(
                                        $sale[
                                            'balance_due'
                                        ] ?? 0
                                    )
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

    <section class="report-section table-card">

        <div class="report-section-heading">
            <div>
                <h2>
                    Cash Movements
                </h2>

                <p class="muted">
                    <?= e(
                        (string) (
                            $cashSummary[
                                'movement_count'
                            ] ?? 0
                        )
                    ) ?>
                    movement(s)
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th>User</th>
                    <th class="money-cell">
                        Amount
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if (
                    empty($cashMovements)
                ): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                No cash movements recorded.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach (
                        $cashMovements
                        as $movement
                    ): ?>

                        <?php
                        $movementType =
                            (string) (
                                $movement[
                                    'movement_type'
                                ] ?? ''
                            );

                        $isCashIn =
                            $movementType
                            === 'cash_in';
                        ?>

                        <tr>
                            <td>
                                <?= e(
                                    (string) (
                                        $movement[
                                            'movement_at'
                                        ] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="<?= e(
                                        $isCashIn
                                            ? 'movement-in'
                                            : 'movement-out'
                                    ) ?>"
                                >
                                    <?= e(
                                        $isCashIn
                                            ? 'Cash In'
                                            : 'Cash Out'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $movement[
                                            'reference_number'
                                        ] ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $movement[
                                            'notes'
                                        ] ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                #<?= e(
                                    (string) (
                                        $movement[
                                            'created_by'
                                        ] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td
                                class="money-cell
                                    <?= e(
                                        $isCashIn
                                            ? 'movement-in'
                                            : 'movement-out'
                                    ) ?>"
                            >
                                <?= e(
                                    $isCashIn
                                        ? '+'
                                        : '-'
                                ) ?>

                                <?= e(
                                    $money(
                                        $movement[
                                            'amount'
                                        ] ?? 0
                                    )
                                ) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>