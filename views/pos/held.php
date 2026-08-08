<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Security\Csrf;

/**
 * @var array<string, mixed> $currentUser
 * @var array<string, mixed> $result
 * @var list<Sale> $items
 * @var bool $mineOnly
 * @var string|null $success
 * @var string|null $error
 */

$total =
    (int) (
        $result['total']
        ?? count($items)
    );

$page =
    max(
        1,
        (int) (
            $result['page']
            ?? 1
        )
    );

$perPage =
    max(
        1,
        (int) (
            $result['per_page']
            ?? 20
        )
    );

$lastPage =
    max(
        1,
        (int) (
            $result['last_page']
            ?? (
                (int) ceil(
                    $total / $perPage
                )
            )
        )
    );

$from =
    $total > 0
        ? (($page - 1) * $perPage) + 1
        : 0;

$to =
    $total > 0
        ? min(
            $total,
            $from + count($items) - 1
        )
        : 0;

/**
 * Build pagination URL while preserving
 * the "mine" filter.
 */
$paginationUrl =
    static function (
        int $targetPage,
        bool $mineOnly
    ): string {
        $query = [
            'page' =>
                max(
                    1,
                    $targetPage
                ),
        ];

        if ($mineOnly) {
            $query['mine'] = '1';
        }

        return app_url(
            '/pos/held?'
            . http_build_query(
                $query
            )
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
        Held POS Sales — ZAY POS 2.0
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
        .held-shell {
            width: min(
                1400px,
                calc(100% - 24px)
            );
            margin: 20px auto 40px;
        }

        .held-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .held-heading h1 {
            margin: 0;
        }

        .held-heading-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .held-panel {
            background:
                var(--surface, #fff);
            border: 1px solid
                var(
                    --border-color,
                    #dfe3e8
                );
            border-radius: 14px;
            overflow: hidden;
        }

        .held-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 16px 18px;
            border-bottom: 1px solid
                var(
                    --border-color,
                    #e6e8eb
                );
        }

        .held-toolbar-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .held-toolbar-info strong {
            font-size: 16px;
        }

        .held-toolbar-actions {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
        }

        .held-filter-active {
            font-weight: 700;
        }

        .held-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .held-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        .held-table th,
        .held-table td {
            padding: 13px 14px;
            border-bottom: 1px solid
                var(
                    --border-color,
                    #e6e8eb
                );
            text-align: left;
            vertical-align: middle;
        }

        .held-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .7;
            background:
                rgba(
                    0,
                    0,
                    0,
                    .025
                );
        }

        .held-table tbody tr:hover {
            background:
                rgba(
                    0,
                    0,
                    0,
                    .02
                );
        }

        .held-sale-number {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .held-sale-number strong {
            white-space: nowrap;
        }

        .held-sale-number small {
            opacity: .65;
        }

        .held-money {
            white-space: nowrap;
            font-weight: 700;
            text-align: right;
        }

        .held-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            background:
                rgba(
                    245,
                    158,
                    11,
                    .12
                );
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .held-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #f59e0b;
        }

        .held-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 7px;
            flex-wrap: wrap;
        }

        .held-actions form {
            margin: 0;
        }

        .held-resume-button,
        .held-cancel-button {
            min-height: 36px;
            padding: 7px 12px;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
        }

        .held-resume-button {
            border: 1px solid
                var(
                    --primary-color,
                    #2563eb
                );
            background:
                var(
                    --primary-color,
                    #2563eb
                );
            color: #fff;
        }

        .held-cancel-button {
            border: 1px solid
                #dc2626;
            background: transparent;
            color: #dc2626;
        }

        .held-empty {
            padding: 55px 20px;
            text-align: center;
        }

        .held-empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .held-empty h2 {
            margin:
                0
                0
                8px;
        }

        .held-empty p {
            margin:
                0
                auto
                18px;
            max-width: 500px;
        }

        .held-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            flex-wrap: wrap;
        }

        .held-pagination-info {
            font-size: 13px;
            opacity: .72;
        }

        .held-pagination-links {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .held-page-link {
            min-width: 36px;
            min-height: 36px;
            padding: 7px 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid
                var(
                    --border-color,
                    #dfe3e8
                );
            border-radius: 8px;
            text-decoration: none;
        }

        .held-page-link.active {
            font-weight: 700;
            border-color:
                var(
                    --primary-color,
                    #2563eb
                );
        }

        .held-page-link.disabled {
            opacity: .4;
            pointer-events: none;
        }

        @media (max-width: 760px) {
            .held-shell {
                width: min(
                    100% - 14px,
                    1400px
                );
                margin-top: 10px;
            }

            .held-heading {
                flex-direction: column;
            }

            .held-heading-actions,
            .held-toolbar-actions {
                width: 100%;
            }

            .held-heading-actions a,
            .held-toolbar-actions a {
                flex: 1;
                text-align: center;
            }

            .held-pagination {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
</head>

<body class="app-page">

<?php
require BASE_PATH
    . '/views/partials/admin_header.php';
?>

<main class="held-shell">

    <section class="held-heading">
        <div>
            <p class="eyebrow">
                CASHIER
            </p>

            <h1>
                Held POS Sales
            </h1>

            <p class="muted">
                Resume or cancel POS carts
                that were temporarily held.
            </p>
        </div>

        <div class="held-heading-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos')
                ) ?>"
            >
                Back to POS
            </a>

            <a
                class="primary-link"
                href="<?= e(
                    app_url('/pos')
                ) ?>"
            >
                New Sale
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

    <section class="held-panel">

        <div class="held-toolbar">

            <div class="held-toolbar-info">
                <strong>
                    <?= number_format(
                        $total
                    ) ?>
                    held sale<?= $total === 1
                        ? ''
                        : 's'
                    ?>
                </strong>

                <span class="muted">
                    <?php if ($mineOnly): ?>
                        Showing sales held by you.
                    <?php else: ?>
                        Showing all held POS sales.
                    <?php endif; ?>
                </span>
            </div>

            <div class="held-toolbar-actions">

                <a
                    class="<?= !$mineOnly
                        ? 'primary-link held-filter-active'
                        : 'secondary-link'
                    ?>"
                    href="<?= e(
                        app_url('/pos/held')
                    ) ?>"
                >
                    All Held Sales
                </a>

                <a
                    class="<?= $mineOnly
                        ? 'primary-link held-filter-active'
                        : 'secondary-link'
                    ?>"
                    href="<?= e(
                        app_url(
                            '/pos/held?mine=1'
                        )
                    ) ?>"
                >
                    My Held Sales
                </a>

            </div>
        </div>

        <?php if ($items === []): ?>

            <div class="held-empty">
                <div class="held-empty-icon">
                    ⏸
                </div>

                <h2>
                    No held sales
                </h2>

                <p class="muted">
                    There are currently no POS
                    transactions waiting to be resumed.
                </p>

                <a
                    class="primary-link"
                    href="<?= e(
                        app_url('/pos')
                    ) ?>"
                >
                    Go to POS
                </a>
            </div>

        <?php else: ?>

            <div class="held-table-wrap">

                <table class="held-table">
                    <thead>
                    <tr>
                        <th>
                            Sale
                        </th>

                        <th>
                            Warehouse
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Held By
                        </th>

                        <th>
                            Held At
                        </th>

                        <th style="text-align:right;">
                            Total
                        </th>

                        <th style="text-align:right;">
                            Actions
                        </th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach (
                        $items
                        as $sale
                    ): ?>

                        <?php if (
                            !$sale instanceof Sale
                        ): ?>
                            <?php continue; ?>
                        <?php endif; ?>

                        <tr>

                            <td>
                                <div
                                    class="held-sale-number"
                                >
                                    <strong>
                                        <?= e(
                                            $sale
                                                ->saleNumber()
                                        ) ?>
                                    </strong>

                                    <small>
                                        Sale #
                                        <?= (int)
                                            $sale->id()
                                        ?>
                                        · Shift #
                                        <?= e(
                                            (string) (
                                                $sale
                                                    ->posShiftId()
                                                ?? '—'
                                            )
                                        ) ?>
                                    </small>
                                </div>
                            </td>

                            <td>
                                Warehouse #
                                <?= (int)
                                    $sale
                                        ->warehouseId()
                                ?>
                            </td>

                            <td>
                                <?php if (
                                    $sale->customerId()
                                    !== null
                                ): ?>
                                    Customer #
                                    <?= (int)
                                        $sale
                                            ->customerId()
                                    ?>
                                <?php else: ?>
                                    <span class="muted">
                                        Walk-in
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span
                                    class="held-status"
                                >
                                    <span
                                        class="held-status-dot"
                                    ></span>

                                    Held
                                </span>
                            </td>

                            <td>
                                <?php if (
                                    $sale->heldBy()
                                    !== null
                                ): ?>
                                    User #
                                    <?= (int)
                                        $sale
                                            ->heldBy()
                                    ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e(
                                    $sale
                                        ->heldAt()
                                        ?->format(
                                            'Y-m-d H:i:s'
                                        )
                                    ?? '—'
                                ) ?>
                            </td>

                            <td class="held-money">
                                <?= e(
                                    number_format(
                                        $sale
                                            ->grandTotal(),
                                        2
                                    )
                                ) ?>
                            </td>

                            <td>
                                <div
                                    class="held-actions"
                                >

                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/pos/resume'
                                            )
                                        ) ?>"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="sale_id"
                                            value="<?= (int)
                                                $sale
                                                    ->id()
                                            ?>"
                                        >

                                        <button
                                            class="held-resume-button"
                                            type="submit"
                                        >
                                            Resume
                                        </button>
                                    </form>

                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/pos/held/cancel'
                                            )
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Cancel this held sale? This action cannot be undone.'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="sale_id"
                                            value="<?= (int)
                                                $sale
                                                    ->id()
                                            ?>"
                                        >

                                        <button
                                            class="held-cancel-button"
                                            type="submit"
                                        >
                                            Cancel
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <div class="held-pagination">

                <div class="held-pagination-info">
                    Showing
                    <?= number_format($from) ?>
                    –
                    <?= number_format($to) ?>
                    of
                    <?= number_format($total) ?>
                </div>

                <?php if ($lastPage > 1): ?>

                    <nav
                        class="held-pagination-links"
                        aria-label="Held sales pagination"
                    >

                        <a
                            class="held-page-link <?= $page <= 1
                                ? 'disabled'
                                : ''
                            ?>"
                            href="<?= e(
                                $paginationUrl(
                                    max(
                                        1,
                                        $page - 1
                                    ),
                                    $mineOnly
                                )
                            ) ?>"
                        >
                            ‹
                        </a>

                        <?php
                        $startPage =
                            max(
                                1,
                                $page - 2
                            );

                        $endPage =
                            min(
                                $lastPage,
                                $page + 2
                            );
                        ?>

                        <?php for (
                            $number = $startPage;
                            $number <= $endPage;
                            $number++
                        ): ?>

                            <a
                                class="held-page-link <?= $number === $page
                                    ? 'active'
                                    : ''
                                ?>"
                                href="<?= e(
                                    $paginationUrl(
                                        $number,
                                        $mineOnly
                                    )
                                ) ?>"
                            >
                                <?= (int)
                                    $number
                                ?>
                            </a>

                        <?php endfor; ?>

                        <a
                            class="held-page-link <?= $page >= $lastPage
                                ? 'disabled'
                                : ''
                            ?>"
                            href="<?= e(
                                $paginationUrl(
                                    min(
                                        $lastPage,
                                        $page + 1
                                    ),
                                    $mineOnly
                                )
                            ) ?>"
                        >
                            ›
                        </a>

                    </nav>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>