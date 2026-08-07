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

$cashMovements =
    $cashMovements
    ?? (
        $report['cash_movements']
        ?? []
    );

$variance =
    $summary['cash_variance']
    ?? null;
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
        — Shift Report
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f8;
            color: #111827;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 13px;
        }

        .toolbar {
            max-width: 900px;
            margin: 20px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }

        .toolbar a {
            background: #e8eef8;
            color: #12315d;
        }

        .toolbar button {
            background: #2456d8;
            color: #fff;
        }

        .report {
            width: 900px;
            max-width: calc(100% - 32px);
            margin: 16px auto 32px;
            background: #fff;
            border: 1px solid #dfe3e8;
            border-radius: 12px;
            padding: 30px;
        }

        .report-header {
            text-align: center;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }

        .brand {
            font-size: 12px;
            letter-spacing: .12em;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .report-header h1 {
            margin: 0 0 7px;
            font-size: 27px;
        }

        .shift-number {
            color: #64748b;
            font-size: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 0 30px;
            margin-bottom: 22px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row span {
            color: #64748b;
        }

        .info-row strong {
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 22px;
        }

        .summary-card {
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            padding: 12px;
        }

        .summary-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: .04em;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 18px;
            font-weight: 700;
        }

        h2 {
            margin: 24px 0 10px;
            font-size: 17px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 9px 7px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: .04em;
        }

        .money {
            text-align: right;
            white-space: nowrap;
        }

        .positive {
            color: #166534;
        }

        .negative {
            color: #b91c1c;
        }

        .payment-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .payment-card {
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            padding: 12px;
        }

        .payment-card strong {
            display: block;
            font-size: 18px;
            margin: 5px 0;
        }

        .footer {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            color: #64748b;
            font-size: 11px;
        }

        @media print {
            @page {
                size: A4;
                margin: 7mm;
            }

            body {
                background: #fff;
                font-size: 10px;
            }

            .toolbar {
                display: none;
            }

            .report {
                width: 100%;
                max-width: none;
                margin: 0;
                border: 0;
                border-radius: 0;
                padding: 0;
            }

            .report-header {
                padding-bottom: 10px;
                margin-bottom: 10px;
            }

            .report-header h1 {
                font-size: 21px;
                margin-bottom: 3px;
            }

            .info-grid {
                gap: 0 18px;
                margin-bottom: 10px;
            }

            .info-row {
                padding: 4px 0;
            }

            .summary-grid {
                gap: 5px;
                margin-bottom: 10px;
            }

            .summary-card {
                padding: 7px;
            }

            .summary-label {
                font-size: 8px;
                margin-bottom: 2px;
            }

            .summary-value {
                font-size: 13px;
            }

            h2 {
                margin: 10px 0 5px;
                font-size: 13px;
            }

            th,
            td {
                padding: 4px 5px;
                font-size: 9px;
            }

            .payment-grid {
                gap: 5px;
            }

            .payment-card {
                padding: 7px;
            }

            .payment-card strong {
                font-size: 13px;
                margin: 2px 0;
            }

            .footer {
                margin-top: 10px;
                padding-top: 8px;
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            tr,
            .summary-card,
            .payment-card {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

<div class="toolbar">
    <a
        href="<?= e(
            app_url(
                '/pos-reports/shift?id='
                . $shift->id()
            )
        ) ?>"
    >
        Back
    </a>

    <button
        type="button"
        onclick="window.print()"
    >
        Print
    </button>
</div>

<main class="report">

    <header class="report-header">
        <div class="brand">
            ZAY POS 2.0
        </div>

        <h1>
            POS Shift Report
        </h1>

        <div class="shift-number">
            <?= e(
                $shift->shiftNumber()
            ) ?>
        </div>
    </header>

    <section class="info-grid">
        <div>
            <div class="info-row">
                <span>Status</span>

                <strong>
                    <?= e(
                        $shift->isOpen()
                            ? 'Open'
                            : 'Closed'
                    ) ?>
                </strong>
            </div>

            <div class="info-row">
                <span>Cashier</span>

                <strong>
                    #<?= e(
                        (string)
                        $shift->userId()
                    ) ?>
                </strong>
            </div>

            <div class="info-row">
                <span>Warehouse</span>

                <strong>
                    #<?= e(
                        (string)
                        $shift->warehouseId()
                    ) ?>
                </strong>
            </div>
        </div>

        <div>
            <div class="info-row">
                <span>Opened</span>

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
                <span>Closed</span>

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

            <div class="info-row">
                <span>POS Sales</span>

                <strong>
                    <?= e(
                        (string) (
                            $summary[
                                'sale_count'
                            ] ?? 0
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="summary-grid">

        <div class="summary-card">
            <div class="summary-label">
                Opening Cash
            </div>

            <div class="summary-value">
                <?= e(
                    $money(
                        $summary[
                            'opening_cash'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Cash Sales
            </div>

            <div class="summary-value">
                <?= e(
                    $money(
                        $summary[
                            'cash_sales'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Expected Cash
            </div>

            <div class="summary-value">
                <?= e(
                    $money(
                        $summary[
                            'expected_cash'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Closing Cash
            </div>

            <div class="summary-value">
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
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Cash In
            </div>

            <div class="summary-value">
                <?= e(
                    $money(
                        $summary[
                            'cash_in'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Cash Out
            </div>

            <div class="summary-value">
                <?= e(
                    $money(
                        $summary[
                            'cash_out'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Grand Total
            </div>

            <div class="summary-value">
                <?= e(
                    $money(
                        $summary[
                            'grand_total'
                        ] ?? 0
                    )
                ) ?>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-label">
                Variance
            </div>

            <div
                class="summary-value
                    <?= e(
                        (float) ($variance ?? 0) < 0
                            ? 'negative'
                            : (
                                (float) ($variance ?? 0) > 0
                                    ? 'positive'
                                    : ''
                            )
                    ) ?>"
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

    <h2>Sales Totals</h2>

    <table>
        <tbody>
        <tr>
            <td>Subtotal</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'subtotal'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>

        <tr>
            <td>Discount</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'discount_amount'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>

        <tr>
            <td>Tax</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'tax_amount'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>

        <tr>
            <td>Shipping</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'shipping_amount'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>

        <tr>
            <td>Other</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'other_amount'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>

        <tr>
            <td><strong>Grand Total</strong></td>
            <td class="money">
                <strong>
                    <?= e(
                        $money(
                            $summary[
                                'grand_total'
                            ] ?? 0
                        )
                    ) ?>
                </strong>
            </td>
        </tr>

        <tr>
            <td>Paid</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'paid_amount'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>

        <tr>
            <td>Balance Due</td>
            <td class="money">
                <?= e(
                    $money(
                        $summary[
                            'balance_due'
                        ] ?? 0
                    )
                ) ?>
            </td>
        </tr>
        </tbody>
    </table>

    <h2>Payment Breakdown</h2>

    <?php if (
        empty($paymentBreakdown)
    ): ?>

        <p>No payments recorded.</p>

    <?php else: ?>

        <div class="payment-grid">

            <?php foreach (
                $paymentBreakdown
                as $payment
            ): ?>

                <div class="payment-card">
                    <span>
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

                    <small>
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

    <h2>Sales</h2>

    <table>
        <thead>
        <tr>
            <th>Sale</th>
            <th>Date / Time</th>
            <th>Payment</th>
            <th class="money">Total</th>
            <th class="money">Paid</th>
            <th class="money">Balance</th>
        </tr>
        </thead>

        <tbody>

        <?php if (empty($sales)): ?>
            <tr>
                <td colspan="6">
                    No completed sales.
                </td>
            </tr>
        <?php else: ?>

            <?php foreach (
                $sales
                as $sale
            ): ?>
                <tr>
                    <td>
                        <?= e(
                            (string) (
                                $sale[
                                    'sale_number'
                                ] ?? ''
                            )
                        ) ?>
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

                    <td class="money">
                        <?= e(
                            $money(
                                $sale[
                                    'grand_total'
                                ] ?? 0
                            )
                        ) ?>
                    </td>

                    <td class="money">
                        <?= e(
                            $money(
                                $sale[
                                    'paid_amount'
                                ] ?? 0
                            )
                        ) ?>
                    </td>

                    <td class="money">
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

    <h2>Cash Movements</h2>

    <table>
        <thead>
        <tr>
            <th>Date / Time</th>
            <th>Type</th>
            <th>Reference</th>
            <th>Notes</th>
            <th class="money">Amount</th>
        </tr>
        </thead>

        <tbody>

        <?php if (
            empty($cashMovements)
        ): ?>

            <tr>
                <td colspan="5">
                    No cash movements.
                </td>
            </tr>

        <?php else: ?>

            <?php foreach (
                $cashMovements
                as $movement
            ): ?>

                <?php
                $isCashIn =
                    (
                        $movement[
                            'movement_type'
                        ] ?? ''
                    ) === 'cash_in';
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
                        <?= e(
                            $isCashIn
                                ? 'Cash In'
                                : 'Cash Out'
                        ) ?>
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

                    <td
                        class="money
                            <?= e(
                                $isCashIn
                                    ? 'positive'
                                    : 'negative'
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

    <footer class="footer">
        ZAY POS 2.0 — POS Shift Report
        <br>
        <?= e(
            $shift->shiftNumber()
        ) ?>
    </footer>

</main>

</body>
</html>