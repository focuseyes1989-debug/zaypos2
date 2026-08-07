<?php

declare(strict_types=1);

use App\Models\PosShift;
use App\Security\Csrf;

if (!$shift instanceof PosShift) {
    throw new RuntimeException(
        'POS shift is not available.'
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Close POS Shift — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .close-shell {
            max-width: 900px;
            margin: 0 auto;
        }

        .close-summary {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .close-summary-card {
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 12px;
            padding: 14px;
            background: #fff;
        }

        .close-summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .65;
            margin-bottom: 6px;
        }

        .close-summary-value {
            font-size: 20px;
            font-weight: 700;
        }

        .close-form-card {
            background: #fff;
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 22px;
        }

        .close-warning {
            margin-bottom: 18px;
            padding: 14px;
            border-radius: 10px;
            background: #fff7ed;
            color: #9a3412;
        }

        .variance-preview {
            margin-top: 16px;
            padding: 14px;
            border-radius: 10px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        @media (max-width: 800px) {
            .close-summary {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .close-summary {
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
                POS SHIFT
            </p>

            <h1>
                Close Shift
            </h1>

            <p class="muted">
                <?= e(
                    $shift->shiftNumber()
                ) ?>
            </p>
        </div>

        <div class="page-actions">
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
        </div>
    </section>

    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="close-shell">
        <section class="close-summary">
            <div class="close-summary-card">
                <div class="close-summary-label">
                    Opening Cash
                </div>

                <div class="close-summary-value">
                    <?= e(
                        $money(
                            $summary['opening_cash']
                            ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="close-summary-card">
                <div class="close-summary-label">
                    Cash Sales
                </div>

                <div class="close-summary-value">
                    <?= e(
                        $money(
                            $summary['cash_sales']
                            ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="close-summary-card">
                <div class="close-summary-label">
                    Cash In / Out
                </div>

                <div class="close-summary-value">
                    +<?= e(
                        $money(
                            $summary['cash_in']
                            ?? 0
                        )
                    ) ?>
                    /
                    -<?= e(
                        $money(
                            $summary['cash_out']
                            ?? 0
                        )
                    ) ?>
                </div>
            </div>

            <div class="close-summary-card">
                <div class="close-summary-label">
                    Expected Cash
                </div>

                <div class="close-summary-value">
                    <?= e(
                        $money(
                            $summary['expected_cash']
                            ?? 0
                        )
                    ) ?>
                </div>
            </div>
        </section>

        <section class="close-form-card">
            <div class="close-warning">
                Closing this shift will prevent any further
                cash movements or POS sales from being linked
                to it.
            </div>

            <form
                method="post"
                action="<?= e(
                    app_url('/pos-shifts/close')
                ) ?>"
            >
                <?= Csrf::input() ?>

                <input
                    type="hidden"
                    name="shift_id"
                    value="<?= e(
                        (string) $shift->id()
                    ) ?>"
                >

                <div class="field">
                    <label for="closing_cash">
                        Closing Cash
                    </label>

                    <input
                        id="closing_cash"
                        name="closing_cash"
                        type="number"
                        min="0"
                        step="0.0001"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="closing_notes">
                        Closing Notes
                    </label>

                    <textarea
                        id="closing_notes"
                        name="closing_notes"
                        rows="4"
                        placeholder="Optional notes..."
                    ></textarea>
                </div>

                <div class="variance-preview">
                    <span>
                        Expected Cash
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $summary['expected_cash']
                                ?? 0
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="form-actions">
                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Close Shift
                    </button>

                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url(
                                '/pos-shifts/show?id='
                                . $shift->id()
                            )
                        ) ?>"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </section>
    </div>
</main>

</body>
</html>