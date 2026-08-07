<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;

if (!$sale instanceof Sale) {
    throw new RuntimeException(
        'Sale is required.'
    );
}
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
        Receipt <?= e($sale->saleNumber()) ?>
        — ZAY POS 2.0
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url(
                '/assets/css/app.css'
            )
        ) ?>"
    >

    <style>
        .receipt-shell {
            width: min(760px, calc(100% - 24px));
            margin: 24px auto 50px;
        }

        .receipt-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .receipt-action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .receipt-card {
            background: #fff;
            border: 1px solid #dfe3e8;
            border-radius: 14px;
            padding: 24px;
        }

        .receipt-heading {
            text-align: center;
            margin-bottom: 24px;
        }

        .receipt-heading h1 {
            margin: 0 0 6px;
        }

        .receipt-heading p {
            margin: 4px 0;
        }

        .receipt-meta {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 10px 18px;
            padding: 16px 0;
            border-top: 1px dashed #ccd2d9;
            border-bottom: 1px dashed #ccd2d9;
            margin-bottom: 18px;
        }

        .receipt-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 10px 6px;
            border-bottom: 1px solid #e8ebef;
        }

        .receipt-table th {
            text-align: left;
            font-size: 12px;
            opacity: .7;
        }

        .receipt-right {
            text-align: right;
        }

        .receipt-totals {
            width: min(360px, 100%);
            margin-left: auto;
            margin-top: 18px;
        }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 5px 0;
        }

        .receipt-grand-total {
            font-size: 22px;
            font-weight: 700;
            padding-top: 10px;
            margin-top: 6px;
            border-top: 2px solid #dfe3e8;
        }

        .receipt-payment {
            margin-top: 24px;
            border-top: 1px dashed #ccd2d9;
            padding-top: 18px;
        }

        .receipt-payment h2 {
            font-size: 17px;
            margin: 0 0 12px;
        }

        .receipt-payment-row {
            display: grid;
            grid-template-columns:
                1.2fr 1fr 1fr;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #eceff2;
        }

        .receipt-change {
            margin-top: 18px;
            padding: 14px;
            border-radius: 10px;
            background: #f2f5f9;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 18px;
            font-weight: 700;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px dashed #ccd2d9;
        }

        @media print {
            body {
                background: #fff !important;
            }

            header,
            .receipt-actions,
            .alert {
                display: none !important;
            }

            .receipt-shell {
                width: 100%;
                margin: 0;
            }

            .receipt-card {
                border: 0;
                box-shadow: none;
                padding: 0;
            }
        }

        @media (max-width: 600px) {
            .receipt-meta {
                grid-template-columns: 1fr;
            }

            .receipt-payment-row {
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

<main class="receipt-shell">

    <div class="receipt-actions">
        <a
            class="primary-link"
            href="<?= e(
                app_url('/pos')
            ) ?>"
        >
            New Sale
        </a>

        <div class="receipt-action-group">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sales/show?id='
                        . $sale->id()
                    )
                ) ?>"
            >
                Sale Detail
            </a>

            <button
                class="secondary-button"
                type="button"
                onclick="window.print();"
            >
                Print Receipt
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="receipt-card">

        <div class="receipt-heading">
            <p class="eyebrow">
                ZAY POS 2.0
            </p>

            <h1>
                Sales Receipt
            </h1>

            <p>
                <?= e(
                    $sale->saleNumber()
                ) ?>
            </p>
        </div>

        <div class="receipt-meta">
            <div class="receipt-meta-row">
                <span>
                    Sale date
                </span>

                <strong>
                    <?= e(
                        $sale
                            ->saleDate()
                            ->format(
                                'Y-m-d'
                            )
                    ) ?>
                </strong>
            </div>

            <div class="receipt-meta-row">
                <span>
                    Warehouse
                </span>

                <strong>
                    #
                    <?= (int)
                        $sale->warehouseId()
                    ?>
                </strong>
            </div>

            <div class="receipt-meta-row">
                <span>
                    Customer
                </span>

                <strong>
                    <?= $sale->customerId()
                        !== null
                        ? '#'
                            . (int)
                                $sale
                                    ->customerId()
                        : 'Walk-in Customer'
                    ?>
                </strong>
            </div>

            <div class="receipt-meta-row">
                <span>
                    Status
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            $sale->status()
                        )
                    ) ?>
                </strong>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
            <tr>
                <th>
                    Item
                </th>

                <th class="receipt-right">
                    Qty
                </th>

                <th class="receipt-right">
                    Price
                </th>

                <th class="receipt-right">
                    Total
                </th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($items as $item): ?>

                <?php if (
                    !$item
                    instanceof SaleItem
                ) {
                    continue;
                } ?>

                <tr>
                    <td>
                        Product #
                        <?= (int)
                            $item->productId()
                        ?>
                    </td>

                    <td class="receipt-right">
                        <?= e(
                            number_format(
                                $item
                                    ->quantity(),
                                4
                            )
                        ) ?>
                    </td>

                    <td class="receipt-right">
                        <?= e(
                            number_format(
                                $item
                                    ->unitPrice(),
                                2
                            )
                        ) ?>
                    </td>

                    <td class="receipt-right">
                        <strong>
                            <?= e(
                                number_format(
                                    $item
                                        ->lineTotal(),
                                    2
                                )
                            ) ?>
                        </strong>
                    </td>
                </tr>

            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-totals">

            <div class="receipt-total-row">
                <span>
                    Subtotal
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->subtotal(),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <?php if (
                $sale->discountAmount()
                > 0.00005
            ): ?>
                <div class="receipt-total-row">
                    <span>
                        Discount
                    </span>

                    <strong>
                        -
                        <?= e(
                            number_format(
                                $sale
                                    ->discountAmount(),
                                2
                            )
                        ) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if (
                $sale->taxAmount()
                > 0.00005
            ): ?>
                <div class="receipt-total-row">
                    <span>
                        Tax
                    </span>

                    <strong>
                        <?= e(
                            number_format(
                                $sale
                                    ->taxAmount(),
                                2
                            )
                        ) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if (
                $sale->shippingAmount()
                > 0.00005
            ): ?>
                <div class="receipt-total-row">
                    <span>
                        Shipping
                    </span>

                    <strong>
                        <?= e(
                            number_format(
                                $sale
                                    ->shippingAmount(),
                                2
                            )
                        ) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if (
                $sale->otherAmount()
                > 0.00005
            ): ?>
                <div class="receipt-total-row">
                    <span>
                        Other
                    </span>

                    <strong>
                        <?= e(
                            number_format(
                                $sale
                                    ->otherAmount(),
                                2
                            )
                        ) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <div
                class="receipt-total-row receipt-grand-total"
            >
                <span>
                    Total
                </span>

                <span>
                    <?= e(
                        number_format(
                            $sale
                                ->grandTotal(),
                            2
                        )
                    ) ?>
                </span>
            </div>

            <div class="receipt-total-row">
                <span>
                    Paid
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float) (
                                $paymentSummary[
                                    'paid_amount'
                                ]
                                ?? 0
                            ),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div class="receipt-total-row">
                <span>
                    Balance
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float) (
                                $paymentSummary[
                                    'balance_due'
                                ]
                                ?? 0
                            ),
                            2
                        )
                    ) ?>
                </strong>
            </div>
        </div>

        <section class="receipt-payment">
            <h2>
                Payment
            </h2>

            <?php if ($payments === []): ?>

                <p class="muted">
                    No payment recorded.
                </p>

            <?php else: ?>

                <?php foreach (
                    $payments as $payment
                ): ?>

                    <?php if (
                        !$payment
                        instanceof SalePayment
                    ) {
                        continue;
                    } ?>

                    <div class="receipt-payment-row">
                        <strong>
                            <?= e(
                                $payment
                                    ->paymentMethodLabel()
                            ) ?>
                        </strong>

                        <span>
                            <?= e(
                                $payment
                                    ->paymentNumber()
                            ) ?>
                        </span>

                        <strong
                            class="receipt-right"
                        >
                            <?= e(
                                number_format(
                                    $payment
                                        ->amount(),
                                    2
                                )
                            ) ?>
                        </strong>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>
        </section>

        <?php if (
            $change !== null
            && $change !== ''
            && (float) $change
                > 0.00005
        ): ?>
            <div class="receipt-change">
                <span>
                    Change
                </span>

                <span>
                    <?= e(
                        number_format(
                            (float) $change,
                            2
                        )
                    ) ?>
                </span>
            </div>
        <?php endif; ?>

        <footer class="receipt-footer">
            <strong>
                Thank you.
            </strong>

            <p class="muted">
                <?= e(
                    $sale->saleNumber()
                ) ?>
            </p>
        </footer>

    </section>

</main>

</body>
</html>