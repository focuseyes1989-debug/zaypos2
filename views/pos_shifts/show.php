<?php

declare(strict_types=1);

use App\Models\PosCashMovement;
use App\Models\PosShift;

if (!$shift instanceof PosShift) {
    throw new RuntimeException(
        'POS shift is not available.'
    );
}

$permissions =
    $currentUser['permissions'] ?? [];

$canCashMovement =
    in_array(
        'pos_shifts.cash_movement',
        $permissions,
        true
    );

$canClose =
    in_array(
        'pos_shifts.close',
        $permissions,
        true
    );

$money = static function (
    float|int|string|null $value
): string {
    return number_format(
        (float) ($value ?? 0),
        2
    );
};

$openedAt =
    $shift->openedAt()
        ->format('Y-m-d H:i:s');

$closedAt =
    $shift->closedAt() !== null
        ? $shift->closedAt()
            ->format('Y-m-d H:i:s')
        : null;

$variance =
    $shift->cashVariance();

$varianceClass =
    'shift-variance-zero';

if ($variance !== null) {
    if ($variance > 0) {
        $varianceClass =
            'shift-variance-positive';
    } elseif ($variance < 0) {
        $varianceClass =
            'shift-variance-negative';
    }
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
        <?= e($shift->shiftNumber()) ?>
        — POS Shift
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .shift-detail-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .shift-detail-card {
            background: var(--surface, #fff);
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 16px;
        }

        .shift-detail-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .65;
            margin-bottom: 7px;
        }

        .shift-detail-value {
            font-size: 20px;
            font-weight: 700;
        }

        .shift-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
        }

        .shift-status-open {
            background: #dcfce7;
            color: #166534;
        }

        .shift-status-closed {
            background: #e5e7eb;
            color: #374151;
        }

        .shift-info-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 10px 24px;
        }

        .shift-info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 9px 0;
            border-bottom: 1px solid
                var(--border-color, #e5e7eb);
        }

        .shift-info-row span:first-child {
            opacity: .7;
        }

        .shift-info-row strong {
            text-align: right;
        }

        .shift-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .movement-type {
            display: inline-flex;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .movement-in {
            background: #dcfce7;
            color: #166534;
        }

        .movement-out {
            background: #fee2e2;
            color: #991b1b;
        }

        .amount-positive {
            color: #166534;
            font-weight: 700;
        }

        .amount-negative {
            color: #b91c1c;
            font-weight: 700;
        }

        .shift-variance-positive {
            color: #166534;
        }

        .shift-variance-negative {
            color: #b91c1c;
        }

        .shift-variance-zero {
            color: inherit;
        }

        .notes-box {
            margin-top: 14px;
            padding: 14px;
            border-radius: 10px;
            background: #f8fafc;
            white-space: pre-wrap;
        }

        @media (max-width: 1000px) {
            .shift-detail-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .shift-detail-grid,
            .shift-info-grid {
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
                <?= e(
                    $shift->shiftNumber()
                ) ?>
            </h1>

            <p class="muted">
                Cashier shift summary and cash movement history.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos-shifts')
                ) ?>"
            >
                POS Shifts
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos')
                ) ?>"
            >
                POS Checkout
            </a>

            <?php if (
                $shift->isOpen()
                && $canCashMovement
            ): ?>
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/pos-shifts/cash-in?id='
                            . $shift->id()
                        )
                    ) ?>"
                >
                    Cash In
                </a>

                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/pos-shifts/cash-out?id='
                            . $shift->id()
                        )
                    ) ?>"
                >
                    Cash Out
                </a>
            <?php endif; ?>

            <?php if (
                $shift->isOpen()
                && $canClose
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/pos-shifts/close?id='
                            . $shift->id()
                        )
                    ) ?>"
                >
                    Close Shift
                </a>
            <?php endif; ?>
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

    <section class="shift-detail-grid">
        <div class="shift-detail-card">
            <div class="shift-detail-label">
                Status
            </div>

            <div class="shift-detail-value">
                <span
                    class="shift-status <?= e(
                        $shift->isOpen()
                            ? 'shift-status-open'
                            : 'shift-status-closed'
                    ) ?>"
                >
                    <?= e(
                        $shift->isOpen()
                            ? 'Open'
                            : 'Closed'
                    ) ?>
                </span>
            </div>
        </div>

        <div class="shift-detail-card">
            <div class="shift-detail-label">
                Opening Cash
            </div>

            <div class="shift-detail-value">
                <?= e(
                    $money(
                        $shift->openingCash()
                    )
                ) ?>
            </div>
        </div>

        <div class="shift-detail-card">
            <div class="shift-detail-label">
                Expected Cash
            </div>

            <div class="shift-detail-value">
                <?= e(
                    $money(
                        $shift->expectedCash()
                    )
                ) ?>
            </div>
        </div>

        <div class="shift-detail-card">
            <div class="shift-detail-label">
                Variance
            </div>

            <div
                class="shift-detail-value
                    <?= e($varianceClass) ?>"
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

    <section class="form-card">
        <div class="shift-info-grid">
            <div>
                <div class="shift-info-row">
                    <span>
                        Shift ID
                    </span>

                    <strong>
                        #<?= e(
                            (string) $shift->id()
                        ) ?>
                    </strong>
                </div>

                <div class="shift-info-row">
                    <span>
                        User
                    </span>

                    <strong>
                        #<?= e(
                            (string)
                            $shift->userId()
                        ) ?>
                    </strong>
                </div>

                <div class="shift-info-row">
                    <span>
                        Warehouse
                    </span>

                    <strong>
                        #<?= e(
                            (string)
                            $shift->warehouseId()
                        ) ?>
                    </strong>
                </div>

                <div class="shift-info-row">
                    <span>
                        Opened At
                    </span>

                    <strong>
                        <?= e($openedAt) ?>
                    </strong>
                </div>
            </div>

            <div>
                <div class="shift-info-row">
                    <span>
                        Cash Sales
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $shift->cashSales()
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="shift-info-row">
                    <span>
                        Cash In
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $shift->cashIn()
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="shift-info-row">
                    <span>
                        Cash Out
                    </span>

                    <strong>
                        <?= e(
                            $money(
                                $shift->cashOut()
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="shift-info-row">
                    <span>
                        Closing Cash
                    </span>

                    <strong>
                        <?php if (
                            $shift->closingCash()
                            !== null
                        ): ?>
                            <?= e(
                                $money(
                                    $shift->closingCash()
                                )
                            ) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </strong>
                </div>
            </div>
        </div>

        <?php if (
            $shift->openingNotes()
            !== null
        ): ?>
            <div class="notes-box">
                <strong>
                    Opening Notes
                </strong>

                <br>

                <?= e(
                    $shift->openingNotes()
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (
            $shift->closingNotes()
            !== null
        ): ?>
            <div class="notes-box">
                <strong>
                    Closing Notes
                </strong>

                <br>

                <?= e(
                    $shift->closingNotes()
                ) ?>
            </div>
        <?php endif; ?>

        <?php if ($closedAt !== null): ?>
            <div class="notes-box">
                <strong>
                    Closed At
                </strong>

                <br>

                <?= e($closedAt) ?>

                <?php if (
                    $shift->closedBy()
                    !== null
                ): ?>
                    by User
                    #<?= e(
                        (string)
                        $shift->closedBy()
                    ) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="table-card">
        <div class="page-heading">
            <div>
                <h2>
                    Cash Movements
                </h2>

                <p class="muted">
                    Cash added to or removed from the register.
                </p>
            </div>

            <div class="page-actions">
                <?php if (
                    $shift->isOpen()
                    && $canCashMovement
                ): ?>
                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url(
                                '/pos-shifts/cash-in?id='
                                . $shift->id()
                            )
                        ) ?>"
                    >
                        Cash In
                    </a>

                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url(
                                '/pos-shifts/cash-out?id='
                                . $shift->id()
                            )
                        ) ?>"
                    >
                        Cash Out
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th>User</th>
                    <th style="text-align:right">
                        Amount
                    </th>
                </tr>
                </thead>

                <tbody>
                <?php if (
                    empty($movements)
                ): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                No cash movements recorded.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach (
                        $movements as $movement
                    ): ?>
                        <?php
                        if (
                            !$movement
                            instanceof PosCashMovement
                        ) {
                            continue;
                        }

                        $isIn =
                            $movement->isCashIn();
                        ?>

                        <tr>
                            <td>
                                <?= e(
                                    $movement
                                        ->movementAt()
                                        ->format(
                                            'Y-m-d H:i:s'
                                        )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="movement-type
                                        <?= e(
                                            $isIn
                                                ? 'movement-in'
                                                : 'movement-out'
                                        ) ?>"
                                >
                                    <?= e(
                                        $movement
                                            ->movementTypeLabel()
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    $movement
                                        ->referenceNumber()
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $movement->notes()
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                #<?= e(
                                    (string)
                                    $movement->createdBy()
                                ) ?>
                            </td>

                            <td
                                style="text-align:right"
                                class="<?= e(
                                    $isIn
                                        ? 'amount-positive'
                                        : 'amount-negative'
                                ) ?>"
                            >
                                <?= e(
                                    $isIn
                                        ? '+'
                                        : '-'
                                ) ?>

                                <?= e(
                                    $money(
                                        $movement->amount()
                                    )
                                ) ?>
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
