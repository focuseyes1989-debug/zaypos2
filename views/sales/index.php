<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Security\Csrf;

$permissions = $currentUser['permissions'] ?? [];

$customerNames = [];
foreach ($customerOptions as $option) {
    $customerNames[(int) $option['id']] =
        (string) $option['name'];
}

$warehouseNames = [];
foreach ($warehouseOptions as $option) {
    $warehouseNames[(int) $option['id']] =
        (string) $option['name'];
}

$queryForPage = static function (
    int $targetPage
) use ($filters): string {
    return http_build_query([
        'search' =>
            $filters['search'] ?? '',

        'status' =>
            $filters['status'] ?? '',

        'payment_status' =>
            $filters['payment_status'] ?? '',

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sales — ZAY POS 2.0</title>

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
                SALES
            </p>

            <h1>
                Sales Management
            </h1>

            <p class="muted">
                Manage customer sales,
                completion and payment balances.
            </p>
        </div>

        <div class="page-actions">
            <?php if (
                in_array(
                    'sales.create',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/sales/create'
                        )
                    ) ?>"
                >
                    New Sale
                </a>
            <?php endif; ?>
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

    <form
        class="filter-bar"
        method="get"
        action="<?= e(app_url('/sales')) ?>"
    >
        <input
            type="search"
            name="search"
            maxlength="190"
            placeholder="Sale number, reference..."
            value="<?= e(
                $filters['search'] ?? ''
            ) ?>"
        >

        <select name="status">
            <option value="">
                All statuses
            </option>

            <option
                value="draft"
                <?= ($filters['status'] ?? '')
                    === 'draft'
                    ? 'selected'
                    : '' ?>
            >
                Draft
            </option>

            <option
                value="completed"
                <?= ($filters['status'] ?? '')
                    === 'completed'
                    ? 'selected'
                    : '' ?>
            >
                Completed
            </option>

            <option
                value="cancelled"
                <?= ($filters['status'] ?? '')
                    === 'cancelled'
                    ? 'selected'
                    : '' ?>
            >
                Cancelled
            </option>
        </select>

        <select name="payment_status">
            <option value="">
                All payments
            </option>

            <option
                value="unpaid"
                <?= (
                    $filters['payment_status']
                    ?? ''
                ) === 'unpaid'
                    ? 'selected'
                    : '' ?>
            >
                Unpaid
            </option>

            <option
                value="partial"
                <?= (
                    $filters['payment_status']
                    ?? ''
                ) === 'partial'
                    ? 'selected'
                    : '' ?>
            >
                Partial
            </option>

            <option
                value="paid"
                <?= (
                    $filters['payment_status']
                    ?? ''
                ) === 'paid'
                    ? 'selected'
                    : '' ?>
            >
                Paid
            </option>
        </select>

        <select name="customer_id">
            <option value="">
                All customers
            </option>

            <?php foreach (
                $customerOptions as $option
            ): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) (
                        $filters['customer_id']
                        ?? 0
                    ) === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="warehouse_id">
            <option value="">
                All warehouses
            </option>

            <?php foreach (
                $warehouseOptions as $option
            ): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) (
                        $filters['warehouse_id']
                        ?? 0
                    ) === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (
            in_array(
                'sales.restore',
                $permissions,
                true
            )
        ): ?>
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
        <?php endif; ?>

        <button
            class="secondary-button"
            type="submit"
        >
            Filter
        </button>

        <a
            class="text-link"
            href="<?= e(
                app_url('/sales')
            ) ?>"
        >
            Clear
        </a>
    </form>

    <section class="table-card">

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Sale #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Warehouse</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($sales === []): ?>
                    <tr>
                        <td
                            colspan="10"
                            class="empty-cell"
                        >
                            No sale records found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach (
                    $sales as $sale
                ): ?>

                    <?php
                    if (!$sale instanceof Sale) {
                        continue;
                    }

                    $customerId =
                        $sale->customerId();

                    if ($customerId === null) {
                        $customerName =
                            'Walk-in Customer';
                    } else {
                        $customerName =
                            $customerNames[
                                $customerId
                            ]
                            ?? (
                                'Customer #'
                                . $customerId
                            );
                    }

                    $warehouseName =
                        $warehouseNames[
                            $sale->warehouseId()
                        ]
                        ?? (
                            'Warehouse #'
                            . $sale->warehouseId()
                        );
                    ?>

                    <tr>

                        <td>
                            <a
                                class="text-link"
                                href="<?= e(
                                    app_url(
                                        '/sales/show?id='
                                        . $sale->id()
                                    )
                                ) ?>"
                            >
                                <strong>
                                    <?= e(
                                        $sale
                                            ->saleNumber()
                                    ) ?>
                                </strong>
                            </a>

                            <?php if (
                                $sale
                                    ->customerReference()
                                !== null
                            ): ?>
                                <small>
                                    Ref:
                                    <?= e(
                                        $sale
                                            ->customerReference()
                                    ) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e(
                                $sale
                                    ->saleDate()
                                    ->format(
                                        'Y-m-d'
                                    )
                            ) ?>
                        </td>

                        <td>
                            <?= e($customerName) ?>
                        </td>

                        <td>
                            <?= e($warehouseName) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    ucfirst(
                                        $sale->status()
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                ucfirst(
                                    $sale
                                        ->paymentStatus()
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $sale
                                        ->grandTotal(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $sale
                                        ->paidAmount(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $sale
                                            ->balanceDue(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <div class="page-actions">

                                <a
                                    class="text-link"
                                    href="<?= e(
                                        app_url(
                                            '/sales/show?id='
                                            . $sale->id()
                                        )
                                    ) ?>"
                                >
                                    View
                                </a>

                                <?php if (
                                    $sale->isDraft()
                                    && !$sale
                                        ->isDeleted()
                                    && in_array(
                                        'sales.update',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="text-link"
                                        href="<?= e(
                                            app_url(
                                                '/sales/edit?id='
                                                . $sale->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    $sale->isDeleted()
                                    && in_array(
                                        'sales.restore',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/sales/restore'
                                            )
                                        ) ?>"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="sale_id"
                                            value="<?= (int) $sale->id() ?>"
                                        >

                                        <button
                                            class="secondary-button"
                                            type="submit"
                                        >
                                            Restore
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    </section>

    <div class="page-heading">
        <p class="muted">
            Total records:
            <strong>
                <?= (int) $total ?>
            </strong>
        </p>
    </div>

    <?php if ($lastPage > 1): ?>

        <nav
            class="pagination"
            aria-label="Sale pages"
        >

            <?php for (
                $number = 1;
                $number <= $lastPage;
                $number++
            ): ?>

                <a
                    class="<?= $number === $page
                        ? 'current'
                        : '' ?>"
                    href="<?= e(
                        app_url(
                            '/sales?'
                            . $queryForPage(
                                $number
                            )
                        )
                    ) ?>"
                >
                    <?= $number ?>
                </a>

            <?php endfor; ?>

        </nav>

    <?php endif; ?>

</main>

</body>
</html>