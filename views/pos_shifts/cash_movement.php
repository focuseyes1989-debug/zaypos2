<?php

declare(strict_types=1);

use App\Models\PosShift;
use App\Security\Csrf;

if (!$shift instanceof PosShift) {
    throw new RuntimeException(
        'POS shift is not available.'
    );
}

$isCashIn =
    $movementType === 'cash_in';

$isCashOut =
    $movementType === 'cash_out';

if (!$isCashIn && !$isCashOut) {
    throw new RuntimeException(
        'Invalid POS cash movement type.'
    );
}

$pageTitle =
    $isCashIn
        ? 'Cash In'
        : 'Cash Out';

$formAction =
    $isCashIn
        ? '/pos-shifts/cash-in'
        : '/pos-shifts/cash-out';

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

    <title>
        <?= e($pageTitle) ?>
        — POS Shift
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .movement-shell {
            max-width: 820px;
            margin: 0 auto;
        }

        .movement-card {
            background: var(--surface, #fff);
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 22px;
        }

        .movement-summary {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .movement-summary-card {
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 12px;
            padding: 14px;
            background: #f8fafc;
        }

        .movement-summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .65;
            margin-bottom: 6px;
        }

        .movement-summary-value {
            font-size: 20px;
            font-weight: 700;
        }

        .movement-type-box {
            margin-bottom: 20px;
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
        }

        .movement-type-in {
            background: #dcfce7;
            color: #166534;
        }

        .movement-type-out {
            background: #fee2e2;
            color: #991b1b;
        }

        .movement-form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .movement-form-grid .full {
            grid-column: 1 / -1;
        }

        @media (max-width: 700px) {
            .movement-summary,
            .movement-form-grid {
                grid-template-columns: 1fr;
            }

            .movement-form-grid .full {
                grid-column: auto;
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
                <?= e($pageTitle) ?>
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

    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="movement-shell">
        <section class="movement-card">
            <div class="movement-summary">
                <div class="movement-summary-card">
                    <div class="movement-summary-label">
                        Opening Cash
                    </div>

                    <div class="movement-summary-value">
                        <?= e(
                            $money(
                                $shift->openingCash()
                            )
                        ) ?>
                    </div>
                </div>

                <div class="movement-summary-card">
                    <div class="movement-summary-label">
                        Cash In
                    </div>

                    <div class="movement-summary-value">
                        <?= e(
                            $money(
                                $shift->cashIn()
                            )
                        ) ?>
                    </div>
                </div>

                <div class="movement-summary-card">
                    <div class="movement-summary-label">
                        Cash Out
                    </div>

                    <div class="movement-summary-value">
                        <?= e(
                            $money(
                                $shift->cashOut()
                            )
                        ) ?>
                    </div>
                </div>
            </div>

            <div
                class="movement-type-box
                    <?= e(
                        $isCashIn
                            ? 'movement-type-in'
                            : 'movement-type-out'
                    ) ?>"
            >
                <?= e(
                    $isCashIn
                        ? 'Cash In — Add cash to the register'
                        : 'Cash Out — Remove cash from the register'
                ) ?>
            </div>

            <form
                method="post"
                action="<?= e(
                    app_url($formAction)
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

                <div class="movement-form-grid">
                    <div class="field">
                        <label for="amount">
                            Amount
                        </label>

                        <input
                            id="amount"
                            name="amount"
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            required
                            autofocus
                        >
                    </div>

                    <div class="field">
                        <label for="reference_number">
                            Reference Number
                        </label>

                        <input
                            id="reference_number"
                            name="reference_number"
                            type="text"
                            maxlength="190"
                            placeholder="Optional"
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
                            placeholder="Optional notes..."
                        ></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button
                        class="primary-button"
                        type="submit"
                    >
                        <?= e(
                            $isCashIn
                                ? 'Record Cash In'
                                : 'Record Cash Out'
                        ) ?>
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
