<?php

declare(strict_types=1);

use App\Models\SalePayment;
use App\Security\Csrf;

$permissions =
    $currentUser['permissions'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sale Payment History — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >
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
                SALE PAYMENT HISTORY
            </p>

            <h1>
                <?= e($sale->saleNumber()) ?>
            </h1>

            <p class="muted">
                Complete active and deleted
                payment history for this sale.
            </p>
        </div>

        <div class="page-actions">

            <?php if (
                $sale->status() === 'completed'
                && (
                    (float)
                    $summary['balance_due']
                    > 0.00005
                )
                && in_array(
                    'sale_payments.create',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/sale-payments/create?sale_id='
                            . $sale->id()
                        )
                    ) ?>"
                >
                    Record Payment
                </a>
            <?php endif; ?>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sales/show?id='
                        . $sale->id()
                    )
                ) ?>"
            >
                Sale Details
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/sale-payments')
                ) ?>"
            >
                All Payments
            </a>

        </div>
    </section>

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

    <section class="form-card">

        <div class="summary-grid">

            <div>
                <span class="muted">
                    Grand total
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float)
                            $summary[
                                'grand_total'
                            ],
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Paid
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float)
                            $summary[
                                'paid_amount'
                            ],
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Balance due
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float)
                            $summary[
                                'balance_due'
                            ],
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Payment status
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            (string)
                            $summary[
                                'payment_status'
                            ]
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Sale status
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            $sale->status()
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Sale date
                </span>

                <strong>
                    <?= e(
                        $sale
                            ->saleDate()
                            ->format('Y-m-d')
                    ) ?>
                </strong>
            </div>

        </div>

    </section>

    <section class="table-card">

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th class="actions-column">
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if ($payments === []): ?>
                    <tr>
                        <td
                            colspan="8"
                            class="empty-cell"
                        >
                            No payments recorded
                            for this sale.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach (
                    $payments as $payment
                ): ?>

                    <?php if (
                        !$payment
                            instanceof SalePayment
                    ) {
                        continue;
                    } ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e(
                                    $payment
                                        ->paymentNumber()
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $payment
                                    ->paymentDate()
                                    ->format('Y-m-d')
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $payment
                                    ->paymentMethodLabel()
                            ) ?>
                        </td>

                        <td>
                            <?= $payment
                                ->referenceNumber()
                                !== null
                                ? e(
                                    $payment
                                        ->referenceNumber()
                                )
                                : '<span class="muted">—</span>' ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $payment
                                            ->amount(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= $payment->notes()
                                !== null
                                ? nl2br(
                                    e(
                                        $payment
                                            ->notes()
                                    )
                                )
                                : '<span class="muted">—</span>' ?>
                        </td>

                        <td>
                            <?php if (
                                $payment->isDeleted()
                            ): ?>
                                <span
                                    class="status-badge inactive"
                                >
                                    Deleted
                                </span>
                            <?php else: ?>
                                <span
                                    class="status-badge active"
                                >
                                    Active
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="page-actions">

                                <?php if (
                                    !$payment->isDeleted()
                                    && in_array(
                                        'sale_payments.delete',
                                        $permissions,
                                        true
                                    )
                                ): ?>

                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/sale-payments/delete'
                                            )
                                        ) ?>"
                                        onsubmit="
                                            return confirm(
                                                'Delete this sale payment?'
                                            );
                                        "
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int)
                                                $payment->id()
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="sale_id"
                                            value="<?= (int)
                                                $sale->id()
                                            ?>"
                                        >

                                        <button
                                            class="secondary-button"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>

                                <?php endif; ?>

                                <?php if (
                                    $payment->isDeleted()
                                    && in_array(
                                        'sale_payments.restore',
                                        $permissions,
                                        true
                                    )
                                ): ?>

                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/sale-payments/restore'
                                            )
                                        ) ?>"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int)
                                                $payment->id()
                                            ?>"
                                        >

                                        <button
                                            class="secondary-button"
                                            type="submit"
                                        >
                                            Restore
                                        </button>
                                    </form>

                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    </section>

</main>

</body>
</html>