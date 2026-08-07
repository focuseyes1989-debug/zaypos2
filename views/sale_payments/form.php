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

    <title>Record Sale Payment — ZAY POS 2.0</title>

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

<main class="form-shell">

    <div class="breadcrumb">
        <a href="<?= e(app_url('/sales')) ?>">
            Sales
        </a>

        <span>/</span>

        <a
            href="<?= e(
                app_url(
                    '/sales/show?id='
                    . $sale->id()
                )
            ) ?>"
        >
            <?= e($sale->saleNumber()) ?>
        </a>

        <span>/</span>

        <span>Record payment</span>
    </div>

    <section class="form-card">

        <div class="form-heading">
            <h1>Record Sale Payment</h1>

            <p class="muted">
                Record money received against
                <?= e($sale->saleNumber()) ?>.
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
                    Sale
                </span>

                <strong>
                    <?= e(
                        $sale->saleNumber()
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
                            $sale->status()
                        )
                    ) ?>
                </strong>
            </div>

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

        </div>

        <form
            method="post"
            action="<?= e(
                app_url('/sale-payments')
            ) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="sale_id"
                value="<?= (int) $sale->id() ?>"
            >

            <div class="form-grid">

                <label class="field">
                    <span>Payment number</span>

                    <input
                        type="text"
                        name="payment_number"
                        maxlength="100"
                        placeholder="Auto-generated if blank"
                        value="<?= e(
                            $input[
                                'payment_number'
                            ] ?? ''
                        ) ?>"
                    >

                    <small class="muted">
                        Leave blank to generate
                        automatically.
                    </small>
                </label>

                <label class="field">
                    <span>
                        Payment date
                        <strong>*</strong>
                    </span>

                    <input
                        type="date"
                        name="payment_date"
                        required
                        value="<?= e(
                            $input[
                                'payment_date'
                            ]
                            ?? date('Y-m-d')
                        ) ?>"
                    >
                </label>

                <label class="field">
                    <span>
                        Amount
                        <strong>*</strong>
                    </span>

                    <input
                        type="number"
                        name="amount"
                        min="0.0001"
                        step="0.0001"
                        required
                        value="<?= e(
                            $input['amount']
                            ?? ''
                        ) ?>"
                    >

                    <small class="muted">
                        Maximum available balance:
                        <?= e(
                            number_format(
                                (float)
                                $summary[
                                    'balance_due'
                                ],
                                4
                            )
                        ) ?>
                    </small>
                </label>

                <label class="field">
                    <span>
                        Payment method
                        <strong>*</strong>
                    </span>

                    <select
                        name="payment_method"
                        required
                    >
                        <?php foreach (
                            $paymentMethodOptions
                            as $method => $label
                        ): ?>
                            <option
                                value="<?= e(
                                    $method
                                ) ?>"
                                <?= (
                                    $input[
                                        'payment_method'
                                    ]
                                    ?? 'cash'
                                ) === $method
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field full">
                    <span>
                        Reference number
                    </span>

                    <input
                        type="text"
                        name="reference_number"
                        maxlength="190"
                        placeholder="Bank reference, receipt, transaction ID..."
                        value="<?= e(
                            $input[
                                'reference_number'
                            ] ?? ''
                        ) ?>"
                    >
                </label>

                <label class="field full">
                    <span>Notes</span>

                    <textarea
                        name="notes"
                        rows="4"
                        maxlength="5000"
                        placeholder="Optional payment notes"
                    ><?= e(
                        $input['notes'] ?? ''
                    ) ?></textarea>
                </label>

            </div>

            <div class="form-actions">

                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/sales/show?id='
                            . $sale->id()
                        )
                    ) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button"
                    type="submit"
                >
                    Record payment
                </button>

            </div>
        </form>

    </section>

</main>

</body>
</html>