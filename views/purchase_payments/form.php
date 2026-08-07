<?php

declare(strict_types=1);

use App\Security\Csrf;
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
        Record Purchase Payment — ZAY POS 2.0
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

<main class="form-shell">

    <div class="breadcrumb">
        <a href="<?= e(app_url('/purchases')) ?>">
            Purchases
        </a>

        <span>/</span>

        <a
            href="<?= e(
                app_url(
                    '/purchases/show?id='
                    . $purchase->id()
                )
            ) ?>"
        >
            <?= e($purchase->purchaseNumber()) ?>
        </a>

        <span>/</span>

        <span>Record Payment</span>
    </div>

    <section class="form-card">

        <div class="form-heading">
            <h1>Record Purchase Payment</h1>

            <p class="muted">
                <?= e($purchase->purchaseNumber()) ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

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
                    Payment Status
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

        <form
            method="post"
            action="<?= e(app_url('/purchase-payments')) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="purchase_id"
                value="<?= (int) $purchase->id() ?>"
            >

            <div class="form-grid">

                <div class="field">
                    <label for="payment_number">
                        Payment number
                    </label>

                    <input
                        id="payment_number"
                        name="payment_number"
                        maxlength="100"
                        value="<?= e(
                            $input['payment_number']
                            ?? ''
                        ) ?>"
                        placeholder="Leave blank to generate"
                    >
                </div>

                <div class="field">
                    <label for="payment_date">
                        Payment date
                    </label>

                    <input
                        id="payment_date"
                        name="payment_date"
                        type="date"
                        required
                        value="<?= e(
                            $input['payment_date']
                            ?? date('Y-m-d')
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="amount">
                        Payment amount
                    </label>

                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        min="0.0001"
                        step="0.0001"
                        max="<?= e(
                            (string) $summary['balance_due']
                        ) ?>"
                        required
                        value="<?= e(
                            (string) (
                                $input['amount']
                                ?? $summary['balance_due']
                            )
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="payment_method">
                        Payment method
                    </label>

                    <select
                        id="payment_method"
                        name="payment_method"
                        required
                    >
                        <?php foreach (
                            $paymentMethodOptions
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= (
                                    $input['payment_method']
                                    ?? 'cash'
                                ) === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="reference_number">
                        Reference number
                    </label>

                    <input
                        id="reference_number"
                        name="reference_number"
                        maxlength="190"
                        value="<?= e(
                            $input['reference_number']
                            ?? ''
                        ) ?>"
                        placeholder="Bank reference, receipt, cheque number..."
                    >
                </div>

                <div class="field full">
                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        maxlength="5000"
                    ><?= e(
                        $input['notes']
                        ?? ''
                    ) ?></textarea>
                </div>

            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/purchases/show?id='
                            . $purchase->id()
                        )
                    ) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Record Payment
                </button>
            </div>

        </form>

    </section>

</main>

</body>
</html>