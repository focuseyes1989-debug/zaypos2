<?php

declare(strict_types=1);

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
        POS Register Reports — ZAY POS 2.0
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .report-filter {
            display: flex;
            gap: 12px;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .report-filter .field {
            min-width: 220px;
        }

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
            font-size: 22px;
            font-weight: 700;
        }

        .report-section {
            margin-top: 18px;
        }

        .report-section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .report-section-heading h2 {
            margin: 0;
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
            margin-top: 5px;
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

        .variance-positive {
            color: #166534;
            font-weight: 700;
        }

        .variance-negative {
            color: #b91c1c;
            font-weight: 700;
        }

        .variance-zero {
            opacity: .75;
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

        @media (max-width: 640px) {
            .report-grid,
            .payment-grid {
                grid-template-columns: 1fr;
            }

            .money-cell {
                text-align: left;
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
                POS REPORTING
            </p>

            <h1>
                POS Register Report
            </h1>

            <p class="muted">
                Daily POS sales, payments,
                register cash and shift summaries.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos')
                ) ?>"
            >
                POS Checkout
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos-shifts')
                ) ?>"
            >
                POS Shifts
            </a>
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

    <section class="form-card">
        <form
            method="get"
            action="<?= e(
                app_url('/pos-reports')
            ) ?>"
            class="report-filter"
        >
            <div class="field">
                <label for="date">
                    Report Date
                </label>

                <input
                    id="date"
                    name="date"
                    type="date"
                    value="<?= e(
                        (string) $date
                    ) ?>"
                    required
                >
            </div>

            <div class="form-actions">
                <button
                    type="submit"
                    class="primary-button"
                >
                    Load Report
                </button>
            </div>
        </form>
    </section>

    <section class="report-grid">

        <div class="report-card">
            <div class="report-label">
                POS Sales
            </div>

            <div class="report-value">
                <?= e(
                    (string) (
                        $salesSummary[
                            'sale_count'
                        ] ?? 0
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
                        $salesSummary[
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
                        $salesSummary[
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
                        $salesSummary[
                            'balance_due'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

    </section>

    <section class="report-grid">

        <div class="report-card">
            <div class="report-label">
                Subtotal
            </div>

            <div class="report-value">
                <?= e(
                    $money(
                        $salesSummary[
                            'subtotal'
                        ] ?? 0
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
                        $salesSummary[
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
                        $salesSummary[
                            'tax_amount'
                        ] ?? 0
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
                            $salesSummary[
                                'shipping_amount'
                            ] ?? 0
                        )
                        +
                        (
                            $salesSummary[
                                'other_amount'
                            ] ?? 0
                        )
                    )
                ) ?>
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
                    Payments received from POS sales
                    on the selected date.
                </p>
            </div>
        </div>

        <?php if (
            empty($paymentBreakdown)
        ): ?>
            <div class="form-card">
                <div class="empty-state">
                    No POS payments found.
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
                                    $payment[
                                        'amount'
                                    ] ?? 0
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

    <section class="report-section">

        <div class="report-section-heading">
            <div>
                <h2>
                    Shift Summary
                </h2>

                <p class="muted">
                    Cash register totals for shifts
                    opened on the selected date.
                </p>
            </div>
        </div>

        <div class="report-grid">

            <div class="report-card">
                <div class="report-label">
                    Shifts
                </div>

                <div class="report-value">
                    <?= e(
                        (string) (
                            $shiftSummary[
                                'shift_count'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Opening Cash
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $shiftSummary[
                                'opening_cash'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Cash Sales
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $shiftSummary[
                                'cash_sales'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Expected Cash
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $shiftSummary[
                                'expected_cash'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Cash In
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $shiftSummary[
                                'cash_in'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Cash Out
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $shiftSummary[
                                'cash_out'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Closing Cash
                </div>

                <div class="report-value">
                    <?= e(
                        $money(
                            $shiftSummary[
                                'closing_cash'
                            ] ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-label">
                    Cash Variance
                </div>

                <?php
                $totalVariance =
                    (float) (
                        $shiftSummary[
                            'cash_variance'
                        ] ?? 0
                    );

                $varianceClass =
                    $totalVariance > 0
                        ? 'variance-positive'
                        : (
                            $totalVariance < 0
                                ? 'variance-negative'
                                : 'variance-zero'
                        );
                ?>

                <div
                    class="report-value
                        <?= e(
                            $varianceClass
                        ) ?>"
                >
                    <?= e(
                        $money(
                            $totalVariance
                        )
                    ) ?>
                </div>
            </div>

        </div>
    </section>

    <section class="report-section table-card">

        <div class="report-section-heading">
            <div>
                <h2>
                    POS Shifts
                </h2>

                <p class="muted">
                    <?= e((string) $date) ?>
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                <tr>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Warehouse</th>
                    <th>Opened</th>
                    <th>Closed</th>
                    <th class="money-cell">
                        Cash Sales
                    </th>
                    <th class="money-cell">
                        Expected
                    </th>
                    <th class="money-cell">
                        Closing
                    </th>
                    <th class="money-cell">
                        Variance
                    </th>
                    <th>Report</th>
                </tr>
                </thead>

                <tbody>

                <?php if (empty($shifts)): ?>
                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                No POS shifts found
                                for this date.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach (
                        $shifts
                        as $shift
                    ): ?>

                        <?php
                        $status =
                            (string) (
                                $shift['status']
                                ?? ''
                            );

                        $variance =
                            (float) (
                                $shift[
                                    'cash_variance'
                                ] ?? 0
                            );

                        $rowVarianceClass =
                            $variance > 0
                                ? 'variance-positive'
                                : (
                                    $variance < 0
                                        ? 'variance-negative'
                                        : 'variance-zero'
                                );
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= e(
                                        (string) (
                                            $shift[
                                                'shift_number'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <span
                                    class="status-badge
                                        <?= e(
                                            $status === 'open'
                                                ? 'status-open'
                                                : 'status-closed'
                                        ) ?>"
                                >
                                    <?= e(
                                        ucfirst(
                                            $status
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                #<?= e(
                                    (string) (
                                        $shift[
                                            'user_id'
                                        ] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                #<?= e(
                                    (string) (
                                        $shift[
                                            'warehouse_id'
                                        ] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $shift[
                                            'opened_at'
                                        ] ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $shift[
                                            'closed_at'
                                        ] ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?= e(
                                    $money(
                                        $shift[
                                            'cash_sales'
                                        ] ?? 0
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?= e(
                                    $money(
                                        $shift[
                                            'expected_cash'
                                        ] ?? 0
                                    )
                                ) ?>
                            </td>

                            <td class="money-cell">
                                <?php if (
                                    $shift[
                                        'closing_cash'
                                    ] !== null
                                ): ?>
                                    <?= e(
                                        $money(
                                            $shift[
                                                'closing_cash'
                                            ]
                                        )
                                    ) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td
                                class="money-cell
                                    <?= e(
                                        $rowVarianceClass
                                    ) ?>"
                            >
                                <?= e(
                                    $money(
                                        $variance
                                    )
                                ) ?>
                            </td>

                            <td>
                                <a
                                    class="secondary-link"
                                    href="<?= e(
                                        app_url(
                                            '/pos-reports/shift?id='
                                            . (int) (
                                                $shift['id']
                                                ?? 0
                                            )
                                        )
                                    ) ?>"
                                >
                                    View
                                </a>
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