<?php

declare(strict_types=1);

use App\Models\PosShift;

$permissions =
    $currentUser['permissions'] ?? [];

$canOpen =
    in_array(
        'pos_shifts.open',
        $permissions,
        true
    );

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

$items =
    $shifts
    ?? [];

$total =
    (int) ($total ?? 0);

$page =
    max(
        1,
        (int) ($page ?? 1)
    );

$perPage =
    max(
        1,
        (int) ($perPage ?? 20)
    );

$totalPages =
    max(
        1,
        (int) (
            $lastPage
            ?? ceil(
                $total / $perPage
            )
        )
    );

$queryForPage = static function (
    int $targetPage
) use ($filters): string {
    return http_build_query([
        'search' =>
            $filters['search']
            ?? '',

        'status' =>
            $filters['status']
            ?? '',

        'user_id' =>
            $filters['user_id']
            ?? '',

        'warehouse_id' =>
            $filters['warehouse_id']
            ?? '',

        'page' =>
            $targetPage,
    ]);
};

$money = static function (
    float|int|string|null $value
): string {
    return number_format(
        (float) ($value ?? 0),
        2
    );
};

$statusLabel = static function (
    string $status
): string {
    return match ($status) {
        'open' => 'Open',
        'closed' => 'Closed',
        default => ucfirst($status),
    };
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

    <title>POS Shifts — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .shift-summary-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .shift-summary-card {
            background: var(--surface, #fff);
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 16px;
        }

        .shift-summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .7;
            margin-bottom: 7px;
        }

        .shift-summary-value {
            font-size: 22px;
            font-weight: 700;
        }

        .shift-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
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

        .shift-actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .shift-number {
            font-weight: 700;
        }

        .shift-money {
            text-align: right;
            white-space: nowrap;
        }

        .shift-variance-positive {
            color: #166534;
            font-weight: 700;
        }

        .shift-variance-negative {
            color: #b91c1c;
            font-weight: 700;
        }

        .shift-variance-zero {
            opacity: .7;
        }

        .shift-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .shift-pagination-links {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 1000px) {
            .shift-summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .shift-summary-grid {
                grid-template-columns: 1fr;
            }

            .shift-money {
                text-align: left;
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
                POS MANAGEMENT
            </p>

            <h1>
                POS Shifts
            </h1>

            <p class="muted">
                Review cashier shifts, opening cash,
                cash movements and closing balances.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(app_url('/pos')) ?>"
            >
                POS Checkout
            </a>

            <?php if ($canOpen): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url('/pos-shifts/open')
                    ) ?>"
                >
                    Open Shift
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

    <?php
    $openCount = 0;
    $closedCount = 0;
    $expectedTotal = 0.0;
    $varianceTotal = 0.0;

    foreach ($items as $shift) {
        if (!$shift instanceof PosShift) {
            continue;
        }

        if ($shift->isOpen()) {
            ++$openCount;
        }

        if ($shift->isClosed()) {
            ++$closedCount;
        }

        $expectedTotal +=
            $shift->expectedCash();

        $varianceTotal +=
            $shift->cashVariance()
            ?? 0.0;
    }
    ?>

    <section class="shift-summary-grid">
        <div class="shift-summary-card">
            <div class="shift-summary-label">
                Total Records
            </div>

            <div class="shift-summary-value">
                <?= e((string) $total) ?>
            </div>
        </div>

        <div class="shift-summary-card">
            <div class="shift-summary-label">
                Open On This Page
            </div>

            <div class="shift-summary-value">
                <?= e((string) $openCount) ?>
            </div>
        </div>

        <div class="shift-summary-card">
            <div class="shift-summary-label">
                Closed On This Page
            </div>

            <div class="shift-summary-value">
                <?= e((string) $closedCount) ?>
            </div>
        </div>

        <div class="shift-summary-card">
            <div class="shift-summary-label">
                Expected Cash
            </div>

            <div class="shift-summary-value">
                <?= e($money($expectedTotal)) ?>
            </div>
        </div>
    </section>

    <section class="form-card">
        <form
            method="get"
            action="<?= e(
                app_url('/pos-shifts')
            ) ?>"
        >
            <div class="filter-bar">
                <div class="field">
                    <label for="search">
                        Search
                    </label>

                    <input
                        id="search"
                        name="search"
                        type="search"
                        maxlength="190"
                        value="<?= e(
                            $filters['search']
                            ?? ''
                        ) ?>"
                        placeholder="Shift number..."
                    >
                </div>

                <div class="field">
                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            value="open"
                            <?= (
                                $filters['status']
                                ?? ''
                            ) === 'open'
                                ? 'selected'
                                : '' ?>
                        >
                            Open
                        </option>

                        <option
                            value="closed"
                            <?= (
                                $filters['status']
                                ?? ''
                            ) === 'closed'
                                ? 'selected'
                                : '' ?>
                        >
                            Closed
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label for="user_id">
                        User ID
                    </label>

                    <input
                        id="user_id"
                        name="user_id"
                        type="number"
                        min="1"
                        value="<?= e(
                            $filters['user_id']
                            ?? ''
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="warehouse_id">
                        Warehouse ID
                    </label>

                    <input
                        id="warehouse_id"
                        name="warehouse_id"
                        type="number"
                        min="1"
                        value="<?= e(
                            $filters['warehouse_id']
                            ?? ''
                        ) ?>"
                    >
                </div>
            </div>

            <div class="form-actions">
                <button
                    class="primary-button"
                    type="submit"
                >
                    Filter
                </button>

                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url('/pos-shifts')
                    ) ?>"
                >
                    Reset
                </a>
            </div>
        </form>
    </section>

    <section class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                <tr>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Warehouse</th>
                    <th>Opened</th>
                    <th class="shift-money">
                        Opening
                    </th>
                    <th class="shift-money">
                        Cash Sales
                    </th>
                    <th class="shift-money">
                        Cash In
                    </th>
                    <th class="shift-money">
                        Cash Out
                    </th>
                    <th class="shift-money">
                        Expected
                    </th>
                    <th class="shift-money">
                        Closing
                    </th>
                    <th class="shift-money">
                        Variance
                    </th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="13">
                            <div class="empty-state">
                                No POS shifts found.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $shift): ?>
                        <?php
                        if (!$shift instanceof PosShift) {
                            continue;
                        }

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

                        <tr>
                            <td>
                                <a
                                    class="shift-number"
                                    href="<?= e(
                                        app_url(
                                            '/pos-shifts/show?id='
                                            . $shift->id()
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        $shift->shiftNumber()
                                    ) ?>
                                </a>
                            </td>

                            <td>
                                <span
                                    class="shift-status <?= e(
                                        $shift->isOpen()
                                            ? 'shift-status-open'
                                            : 'shift-status-closed'
                                    ) ?>"
                                >
                                    <?= e(
                                        $statusLabel(
                                            $shift->status()
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                #<?= e(
                                    (string) $shift->userId()
                                ) ?>
                            </td>

                            <td>
                                #<?= e(
                                    (string)
                                    $shift->warehouseId()
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $shift->openedAt()->format(
                                        'Y-m-d H:i:s'
                                    )
                                ) ?>
                            </td>

                            <td class="shift-money">
                                <?= e(
                                    $money(
                                        $shift->openingCash()
                                    )
                                ) ?>
                            </td>

                            <td class="shift-money">
                                <?= e(
                                    $money(
                                        $shift->cashSales()
                                    )
                                ) ?>
                            </td>

                            <td class="shift-money">
                                <?= e(
                                    $money(
                                        $shift->cashIn()
                                    )
                                ) ?>
                            </td>

                            <td class="shift-money">
                                <?= e(
                                    $money(
                                        $shift->cashOut()
                                    )
                                ) ?>
                            </td>

                            <td class="shift-money">
                                <?= e(
                                    $money(
                                        $shift->expectedCash()
                                    )
                                ) ?>
                            </td>

                            <td class="shift-money">
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
                            </td>

                            <td
                                class="shift-money
                                    <?= e(
                                        $varianceClass
                                    ) ?>"
                            >
                                <?php if (
                                    $variance !== null
                                ): ?>
                                    <?= e(
                                        $money($variance)
                                    ) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="shift-actions">
                                    <a
                                        class="secondary-link"
                                        href="<?= e(
                                            app_url(
                                                '/pos-shifts/show?id='
                                                . $shift->id()
                                            )
                                        ) ?>"
                                    >
                                        View
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
                                            class="secondary-link"
                                            href="<?= e(
                                                app_url(
                                                    '/pos-shifts/close?id='
                                                    . $shift->id()
                                                )
                                            ) ?>"
                                        >
                                            Close
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="shift-pagination">
            <div class="muted">
                Page <?= e((string) $page) ?>
                of <?= e((string) $totalPages) ?>
                · <?= e((string) $total) ?>
                record(s)
            </div>

            <div class="shift-pagination-links">
                <?php if ($page > 1): ?>
                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url('/pos-shifts?')
                            . $queryForPage(
                                $page - 1
                            )
                        ) ?>"
                    >
                        Previous
                    </a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url('/pos-shifts?')
                            . $queryForPage(
                                $page + 1
                            )
                        ) ?>"
                    >
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

</body>
</html>
