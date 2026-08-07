<?php

declare(strict_types=1);

use App\Models\WarehouseStock;

$queryForPage = static function (int $targetPage) use ($filters): string {
    return http_build_query([
        'warehouse_id' => $filters['warehouse_id'] ?? '',
        'product_id' => $filters['product_id'] ?? '',
        'page' => $targetPage,
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

    <title>Inventory — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >
</head>

<body class="app-page">

<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="dashboard-shell">
    <section class="page-heading">
        <div>
            <p class="eyebrow">INVENTORY</p>

            <h1>Warehouse Stock</h1>

            <p class="muted">
                View current product balances across warehouses.
            </p>
        </div>

        <div class="page-actions">
            <?php if (
                in_array(
                    'inventory.adjust',
                    $currentUser['permissions'] ?? [],
                    true
                )
            ): ?>
                <a
                    class="secondary-link"
                    href="<?= e(app_url('/inventory/opening')) ?>"
                >
                    Opening stock
                </a>

                <a
                    class="secondary-link"
                    href="<?= e(app_url('/inventory/adjustment')) ?>"
                >
                    Adjustment
                </a>
            <?php endif; ?>

            <?php if (
                in_array(
                    'inventory.transfer',
                    $currentUser['permissions'] ?? [],
                    true
                )
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(app_url('/inventory/transfer')) ?>"
                >
                    Transfer stock
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
        action="<?= e(app_url('/inventory')) ?>"
    >
        <select name="warehouse_id">
            <option value="">
                All warehouses
            </option>

            <?php foreach ($warehouseOptions as $option): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) ($filters['warehouse_id'] ?? 0)
                        === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="product_id">
            <option value="">
                All products
            </option>

            <?php foreach ($productOptions as $option): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) ($filters['product_id'] ?? 0)
                        === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>

                    <?php if (!empty($option['sku'])): ?>
                        — <?= e($option['sku']) ?>
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button
            class="secondary-button"
            type="submit"
        >
            Filter
        </button>

        <a
            class="text-link"
            href="<?= e(app_url('/inventory')) ?>"
        >
            Clear
        </a>

        <?php if (
            in_array(
                'inventory.movements.view',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="text-link"
                href="<?= e(app_url('/inventory/movements')) ?>"
            >
                Movement history
            </a>
        <?php endif; ?>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Warehouse ID</th>
                    <th>Product ID</th>
                    <th>Quantity</th>
                    <th>Reserved</th>
                    <th>Available</th>
                    <th>Average Cost</th>
                    <th>Last Movement</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($stocks === []): ?>
                    <tr>
                        <td
                            colspan="7"
                            class="empty-cell"
                        >
                            No warehouse stock records found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($stocks as $stock): ?>
                    <?php
                    if (!$stock instanceof WarehouseStock) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <?= (int) $stock->warehouseId() ?>
                        </td>

                        <td>
                            <?= (int) $stock->productId() ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $stock->quantity(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $stock->reservedQuantity(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $stock->availableQuantity(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $stock->averageCost(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= $stock->lastMovementAt()
                                ? e(
                                    $stock->lastMovementAt()
                                        ->format('Y-m-d H:i:s')
                                )
                                : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($lastPage > 1): ?>
        <nav
            class="pagination"
            aria-label="Inventory pages"
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
                            '/inventory?'
                            . $queryForPage($number)
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