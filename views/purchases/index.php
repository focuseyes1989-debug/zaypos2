<?php

declare(strict_types=1);

use App\Models\Purchase;
use App\Security\Csrf;

$permissions = $currentUser['permissions'] ?? [];

$supplierNames = [];
foreach ($supplierOptions as $option) {
    $supplierNames[(int) $option['id']] =
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

        'supplier_id' =>
            $filters['supplier_id'] ?? '',

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

    <title>Purchases — ZAY POS 2.0</title>

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
                PURCHASES
            </p>

            <h1>
                Purchase Management
            </h1>

            <p class="muted">
                Manage supplier purchases,
                receiving and payment balances.
            </p>
        </div>

        <div class="page-actions">
            <?php if (
                in_array(
                    'purchases.create',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/purchases/create'
                        )
                    ) ?>"
                >
                    New Purchase
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
        action="<?= e(app_url('/purchases')) ?>"
    >
        <input
            type="search"
            name="search"
            maxlength="190"
            placeholder="Purchase number, invoice..."
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
                value="received"
                <?= ($filters['status'] ?? '')
                    === 'received'
                    ? 'selected'
                    : '' ?>
            >
                Received
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

        <select name="supplier_id">
            <option value="">
                All suppliers
            </option>

            <?php foreach (
                $supplierOptions as $option
            ): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) (
                        $filters['supplier_id']
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
                'purchases.restore',
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
                app_url('/purchases')
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
                    <th>
                        Purchase #
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Supplier
                    </th>

                    <th>
                        Warehouse
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Payment
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Paid
                    </th>

                    <th>
                        Balance
                    </th>

                    <th>
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if ($purchases === []): ?>
                    <tr>
                        <td
                            colspan="10"
                            class="empty-cell"
                        >
                            No purchase records found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach (
                    $purchases as $purchase
                ): ?>

                    <?php
                    if (
                        !$purchase
                        instanceof Purchase
                    ) {
                        continue;
                    }

                    $supplierName =
                        $supplierNames[
                            $purchase->supplierId()
                        ]
                        ?? (
                            'Supplier #'
                            . $purchase
                                ->supplierId()
                        );

                    $warehouseName =
                        $warehouseNames[
                            $purchase
                                ->warehouseId()
                        ]
                        ?? (
                            'Warehouse #'
                            . $purchase
                                ->warehouseId()
                        );
                    ?>

                    <tr>

                        <td>
                            <a
                                class="text-link"
                                href="<?= e(
                                    app_url(
                                        '/purchases/show?id='
                                        . $purchase->id()
                                    )
                                ) ?>"
                            >
                                <strong>
                                    <?= e(
                                        $purchase
                                            ->purchaseNumber()
                                    ) ?>
                                </strong>
                            </a>

                            <?php if (
                                $purchase
                                    ->supplierInvoiceNumber()
                                !== null
                            ): ?>
                                <small>
                                    Invoice:
                                    <?= e(
                                        $purchase
                                            ->supplierInvoiceNumber()
                                    ) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e(
                                $purchase
                                    ->purchaseDate()
                                    ->format(
                                        'Y-m-d'
                                    )
                            ) ?>
                        </td>

                        <td>
                            <?= e($supplierName) ?>
                        </td>

                        <td>
                            <?= e($warehouseName) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    ucfirst(
                                        $purchase
                                            ->status()
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                ucfirst(
                                    $purchase
                                        ->paymentStatus()
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $purchase
                                        ->grandTotal(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $purchase
                                        ->paidAmount(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $purchase
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
                                            '/purchases/show?id='
                                            . $purchase->id()
                                        )
                                    ) ?>"
                                >
                                    View
                                </a>

                                <?php if (
                                    $purchase->isDraft()
                                    && !$purchase
                                        ->isDeleted()
                                    && in_array(
                                        'purchases.update',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="text-link"
                                        href="<?= e(
                                            app_url(
                                                '/purchases/edit?id='
                                                . $purchase->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    $purchase->isDeleted()
                                    && in_array(
                                        'purchases.restore',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/purchases/restore'
                                            )
                                        ) ?>"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="purchase_id"
                                            value="<?= (int) $purchase->id() ?>"
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
            aria-label="Purchase pages"
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
                            '/purchases?'
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