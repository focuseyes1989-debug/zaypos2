<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Security\Csrf;

if (!$return instanceof SaleReturn) {
    throw new RuntimeException(
        'Sale return is required.'
    );
}

if (!$sale instanceof Sale) {
    throw new RuntimeException(
        'Original sale is required.'
    );
}

$grandTotal =
    (float) (
        $summary['grand_total']
        ?? $return->grandTotal()
    );

$refundedAmount =
    (float) (
        $summary['refunded_amount']
        ?? 0
    );

$refundBalance =
    (float) (
        $summary['refund_balance']
        ?? 0
    );

$refundStatus =
    (string) (
        $summary['refund_status']
        ?? 'none'
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
        Record Refund — ZAY POS 2.0
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url(
                '/assets/css/app.css'
            )
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
                SALE RETURN REFUND
            </p>

            <h1>
                Record Refund
            </h1>

            <p class="muted">
                Refund for return
                <?= e(
                    $return->returnNumber()
                ) ?>
                against sale
                <?= e(
                    $sale->saleNumber()
                ) ?>.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sale-returns/show?id='
                        . $return->id()
                    )
                ) ?>"
            >
                Return Details
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sale-returns/refund-history?sale_return_id='
                        . $return->id()
                    )
                ) ?>"
            >
                Refund History
            </a>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Return Summary
                </h2>

                <p class="muted">
                    Current refundable balance.
                </p>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <small>
                    Return number
                </small>

                <strong>
                    <?= e(
                        $return->returnNumber()
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Original sale
                </small>

                <strong>
                    <?= e(
                        $sale->saleNumber()
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Return date
                </small>

                <strong>
                    <?= e(
                        $return
                            ->returnDate()
                            ->format(
                                'Y-m-d'
                            )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Refund status
                </small>

                <strong>
                    <?= e(
                        ucfirst(
                            $refundStatus
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Return total
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $grandTotal,
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Already refunded
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $refundedAmount,
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Refund balance
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $refundBalance,
                            2
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Refund Details
                </h2>

                <p class="muted">
                    Record a full or partial refund.
                </p>
            </div>
        </div>

        <form
            method="post"
            action="<?= e(
                app_url(
                    '/sale-returns/refund'
                )
            ) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="sale_return_id"
                value="<?= (int)
                    $return->id()
                ?>"
            >

            <div class="form-grid">
                <div class="field">
                    <label for="refund_number">
                        Refund number
                    </label>

                    <input
                        id="refund_number"
                        name="refund_number"
                        type="text"
                        maxlength="100"
                        value="<?= e(
                            $input[
                                'refund_number'
                            ] ?? ''
                        ) ?>"
                        placeholder="Leave blank to auto-generate"
                    >

                    <small>
                        Leave blank to generate automatically.
                    </small>
                </div>

                <div class="field">
                    <label for="refund_date">
                        Refund date
                    </label>

                    <input
                        id="refund_date"
                        name="refund_date"
                        type="date"
                        required
                        value="<?= e(
                            $input[
                                'refund_date'
                            ]
                            ?? date(
                                'Y-m-d'
                            )
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="amount">
                        Amount
                    </label>

                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        min="0.0001"
                        max="<?= e(
                            number_format(
                                $refundBalance,
                                4,
                                '.',
                                ''
                            )
                        ) ?>"
                        step="0.0001"
                        required
                        value="<?= e(
                            number_format(
                                (float) (
                                    $input[
                                        'amount'
                                    ]
                                    ?? $refundBalance
                                ),
                                4,
                                '.',
                                ''
                            )
                        ) ?>"
                    >

                    <small>
                        Maximum:
                        <?= e(
                            number_format(
                                $refundBalance,
                                2
                            )
                        ) ?>
                    </small>
                </div>

                <div class="field">
                    <label for="refund_method">
                        Refund method
                    </label>

                    <select
                        id="refund_method"
                        name="refund_method"
                        required
                    >
                        <?php foreach (
                            $refundMethodOptions
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= (
                                    (
                                        $input[
                                            'refund_method'
                                        ]
                                        ?? 'cash'
                                    )
                                    === $value
                                )
                                    ? 'selected'
                                    : ''
                                ?>
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
                        type="text"
                        maxlength="190"
                        value="<?= e(
                            $input[
                                'reference_number'
                            ] ?? ''
                        ) ?>"
                        placeholder="Bank, card, mobile payment reference..."
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
                        placeholder="Optional refund notes"
                    ><?= e(
                        $input[
                            'notes'
                        ] ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/sale-returns/show?id='
                            . $return->id()
                        )
                    ) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button"
                    type="submit"
                    onclick="return confirm(
                        'Record this refund?'
                    );"
                >
                    Record Refund
                </button>
            </div>
        </form>
    </section>

</main>

</body>
</html>