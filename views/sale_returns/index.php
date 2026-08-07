<?php

declare(strict_types=1);

use App\Models\SaleReturn;
use App\Security\Csrf;

$queryForPage = static function (
    int $targetPage
) use ($filters): string {
    return http_build_query([
        'search' =>
            $filters['search'] ?? '',

        'status' =>
            $filters['status'] ?? '',

        'refund_status' =>
            $filters['refund_status'] ?? '',

        'sale_id' =>
            $filters['sale_id'] ?? '',

        'customer_id' =>
            $filters['customer_id'] ?? '',

        'warehouse_id' =>
            $filters['warehouse_id'] ?? '',

        'deleted' =>
            !empty($filters['deleted'])
                ? '1'
                : '',

        'page' =>
            $targetPage,
    ]);
};

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

    <title>Sale Returns — ZAY POS 2.0</title>

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
                SALES RETURN
            </p>

            <h1>
                Sale Returns
            </h1>

            <p class="muted">
                Review customer returns, inventory returns,
                and refund status.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(app_url('/sales')) ?>"
            >
                Sales
            </a>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="success-box">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="form-card">
        <form
            method="get"
            action="<?= e(app_url('/sale-returns')) ?>"
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
                        placeholder="Return number, reason..."
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

                        <?php foreach (
                            [
                                'draft' =>
                                    'Draft',

                                'completed' =>
                                    'Completed',

                                'cancelled' =>
                                    'Cancelled',
                            ]
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= (
                                    $filters['status']
                                    ?? ''
                                ) === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="refund_status">
                        Refund status
                    </label>

                    <select
                        id="refund_status"
                        name="refund_status"
                    >
                        <option value="">
                            All refund statuses
                        </option>

                        <?php foreach (
                            [
                                'none' =>
                                    'None',

                                'partial' =>
                                    'Partial',

                                'refunded' =>
                                    'Refunded',
                            ]
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= (
                                    $filters[
                                        'refund_status'
                                    ]
                                    ?? ''
                                ) === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="sale_id">
                        Sale ID
                    </label>

                    <input
                        id="sale_id"
                        name="sale_id"
                        type="number"
                        min="1"
                        value="<?= e(
                            $filters['sale_id']
                            ?? ''
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="customer_id">
                        Customer ID
                    </label>

                    <input
                        id="customer_id"
                        name="customer_id"
                        type="number"
                        min="1"
                        value="<?= e(
                            $filters['customer_id']
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

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="deleted"
                            value="1"
                            <?= !empty(
                                $filters['deleted']
                            )
                                ? 'checked'
                                : '' ?>
                        >

                        Deleted
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/sale-returns'
                        )
                    ) ?>"
                >
                    Clear
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Filter
                </button>
            </div>
        </form>
    </section>

    <section class="table-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Returns
                </h2>

                <p class="muted">
                    <?= (int) $total ?>
                    record(s)
                </p>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>
                        Return
                    </th>

                    <th>
                        Sale
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Refunded
                    </th>

                    <th>
                        Balance
                    </th>

                    <th>
                        Refund status
                    </th>

                    <th>
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>
                <?php if ($returns === []): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="empty-cell"
                        >
                            No sale returns found.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach (
                        $returns as $return
                    ): ?>
                        <?php
                        if (
                            !$return
                            instanceof SaleReturn
                        ) {
                            continue;
                        }
                        ?>

                        <tr>
                            <td>
                                <a
                                    class="table-link"
                                    href="<?= e(
                                        app_url(
                                            '/sale-returns/show?id='
                                            . $return->id()
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        $return
                                            ->returnNumber()
                                    ) ?>
                                </a>

                                <?php if (
                                    $return->reason()
                                    !== null
                                ): ?>
                                    <small>
                                        <?= e(
                                            $return
                                                ->reason()
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a
                                    class="table-link"
                                    href="<?= e(
                                        app_url(
                                            '/sales/show?id='
                                            . $return
                                                ->saleId()
                                        )
                                    ) ?>"
                                >
                                    Sale #
                                    <?= (int)
                                        $return
                                            ->saleId()
                                    ?>
                                </a>
                            </td>

                            <td>
                                <?= e(
                                    $return
                                        ->returnDate()
                                        ->format(
                                            'Y-m-d'
                                        )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="status-badge"
                                >
                                    <?= e(
                                        ucfirst(
                                            $return
                                                ->status()
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    number_format(
                                        $return
                                            ->grandTotal(),
                                        2
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    number_format(
                                        $return
                                            ->refundedAmount(),
                                        2
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    number_format(
                                        $return
                                            ->refundBalance(),
                                        2
                                    )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="status-badge"
                                >
                                    <?= e(
                                        ucfirst(
                                            $return
                                                ->refundStatus()
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td class="actions-column">
                                <?php if (
                                    !empty(
                                        $filters[
                                            'deleted'
                                        ]
                                    )
                                ): ?>

                                    <?php if (
                                        in_array(
                                            'sale_returns.restore',
                                            $permissions,
                                            true
                                        )
                                    ): ?>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url(
                                                    '/sale-returns/restore'
                                                )
                                            ) ?>"
                                            onsubmit="return confirm(
                                                'Restore this sale return?'
                                            );"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="sale_return_id"
                                                value="<?= (int)
                                                    $return
                                                        ->id()
                                                ?>"
                                            >

                                            <button
                                                class="table-button"
                                                type="submit"
                                            >
                                                Restore
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/sale-returns/show?id='
                                                . $return
                                                    ->id()
                                            )
                                        ) ?>"
                                    >
                                        View
                                    </a>

                                    <?php if (
                                        $return->isDraft()
                                        && in_array(
                                            'sale_returns.create',
                                            $permissions,
                                            true
                                        )
                                    ): ?>
                                        <a
                                            class="table-link"
                                            href="<?= e(
                                                app_url(
                                                    '/sale-returns/edit?id='
                                                    . $return
                                                        ->id()
                                                )
                                            ) ?>"
                                        >
                                            Edit
                                        </a>
                                    <?php endif; ?>

                                    <?php if (
                                        $return
                                            ->isCompleted()
                                        && in_array(
                                            'sale_returns.refunds_view',
                                            $permissions,
                                            true
                                        )
                                    ): ?>
                                        <a
                                            class="table-link"
                                            href="<?= e(
                                                app_url(
                                                    '/sale-returns/refund-history?sale_return_id='
                                                    . $return
                                                        ->id()
                                                )
                                            ) ?>"
                                        >
                                            Refunds
                                        </a>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url(
                                '/sale-returns?'
                                . $queryForPage(
                                    $page - 1
                                )
                            )
                        ) ?>"
                    >
                        Previous
                    </a>
                <?php endif; ?>

                <span>
                    Page
                    <?= (int) $page ?>
                    of
                    <?= (int) $lastPage ?>
                </span>

                <?php if (
                    $page < $lastPage
                ): ?>
                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url(
                                '/sale-returns?'
                                . $queryForPage(
                                    $page + 1
                                )
                            )
                        ) ?>"
                    >
                        Next
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>