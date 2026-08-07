<?php

declare(strict_types=1);

use App\Models\PurchasePayment;
use App\Security\Csrf;

$permissions = $currentUser['permissions'] ?? [];
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
        Payment History — <?= e($purchase->purchaseNumber()) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
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
                PURCHASE PAYMENT HISTORY
            </p>

            <h1>
                <?= e($purchase->purchaseNumber()) ?>
            </h1>

            <p class="muted">
                Complete payment ledger for this purchase.
            </p>
        </div>

        <div class="page-actions">

            <?php if (
                $purchase->isReceived()
                && (float) $summary['balance_due'] > 0
                && in_array(
                    'purchase_payments.create',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/purchase-payments/create?purchase_id='
                            . $purchase->id()
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
                        '/purchases/show?id='
                        . $purchase->id()
                    )
                ) ?>"
            >
                Purchase
            </a>

            <a
                class="secondary-link"
                href="<?= e(app_url('/purchase-payments')) ?>"
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
                    Grand Total
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float) $summary['grand_total'],
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
                            (float) $summary['paid_amount'],
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Balance Due
                </span>

                <strong>
                    <?= e(
                        number_format(
                            (float) $summary['balance_due'],
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Status
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            (string) $summary['payment_status']
                        )
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
                    <th>Payment #</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($payments === []): ?>
                    <tr>
                        <td
                            colspan="8"
                            class="empty-cell"
                        >
                            No payments have been recorded.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($payments as $payment): ?>
                    <?php
                    if (!$payment instanceof PurchasePayment) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($payment->paymentNumber()) ?>
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
                            <?= e($payment->paymentMethodLabel()) ?>
                        </td>

                        <td>
                            <?= e(
                                $payment->referenceNumber()
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $payment->amount(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $payment->notes()
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?= $payment->isDeleted()
                                ? 'Deleted'
                                : 'Active' ?>
                        </td>

                        <td>
                            <?php if (
                                !$payment->isDeleted()
                                && in_array(
                                    'purchase_payments.delete',
                                    $permissions,
                                    true
                                )
                            ): ?>

                                <form
                                    method="post"
                                    action="<?= e(
                                        app_url(
                                            '/purchase-payments/delete'
                                        )
                                    ) ?>"
                                    onsubmit="return confirm('Delete this payment? Purchase balance will be recalculated.');"
                                >
                                    <?= Csrf::input() ?>

                                    <input
                                        type="hidden"
                                        name="payment_id"
                                        value="<?= (int) $payment->id() ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="purchase_id"
                                        value="<?= (int) $purchase->id() ?>"
                                    >

                                    <button
                                        class="danger-button compact"
                                        type="submit"
                                    >
                                        Delete
                                    </button>
                                </form>

                            <?php elseif (
                                $payment->isDeleted()
                                && in_array(
                                    'purchase_payments.restore',
                                    $permissions,
                                    true
                                )
                            ): ?>

                                <form
                                    method="post"
                                    action="<?= e(
                                        app_url(
                                            '/purchase-payments/restore'
                                        )
                                    ) ?>"
                                >
                                    <?= Csrf::input() ?>

                                    <input
                                        type="hidden"
                                        name="payment_id"
                                        value="<?= (int) $payment->id() ?>"
                                    >

                                    <button
                                        class="secondary-button"
                                        type="submit"
                                    >
                                        Restore
                                    </button>
                                </form>

                            <?php else: ?>
                                —
                            <?php endif; ?>
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