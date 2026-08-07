<?php

declare(strict_types=1);

use App\Models\StockMovement;

$queryForPage = static function (int $targetPage) use ($filters): string {
    return http_build_query([
        'warehouse_id' => $filters['warehouse_id'] ?? '',
        'product_id' => $filters['product_id'] ?? '',
        'movement_type' => $filters['movement_type'] ?? '',
        'search' => $filters['search'] ?? '',
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

    <title>Stock Movements — ZAY POS 2.0</title>

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

            <h1>Stock Movements</h1>

            <p class="muted">
                Review opening stock, adjustments, transfers and other inventory movements.
            </p>
        </div>

        <a
            class="secondary-link"
            href="<?= e(app_url('/inventory')) ?>"
        >
            Back to inventory
        </a>
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
        action="<?= e(app_url('/inventory/movements')) ?>"
    >
        <input
            name="search"
            value="<?= e($filters['search'] ?? '') ?>"
            maxlength="190"
            placeholder="Reference or notes"
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
                </option>
            <?php endforeach; ?>
        </select>

        <select name="movement_type">
            <option value="">
                All movement types
            </option>

            <?php foreach ($movementTypes as $type): ?>
                <option
                    value="<?= e($type) ?>"
                    <?= ($filters['movement_type'] ?? '') === $type
                        ? 'selected'
                        : '' ?>
                >
                    <?= e(
                        ucwords(
                            str_replace('_', ' ', $type)
                        )
                    ) ?>
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
            href="<?= e(app_url('/inventory/movements')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Warehouse</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Unit Cost</th>
                    <th>Reference</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($movements === []): ?>
                    <tr>
                        <td
                            colspan="10"
                            class="empty-cell"
                        >
                            No stock movements found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($movements as $movement): ?>
                    <?php
                    if (!$movement instanceof StockMovement) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <?= e(
                                $movement->movementAt()->format(
                                    'Y-m-d H:i:s'
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= (int) $movement->warehouseId() ?>
                        </td>

                        <td>
                            <?= (int) $movement->productId() ?>
                        </td>

                        <td>
                            <?= e(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $movement->movementType()
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $movement->quantityIn(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $movement->quantityOut(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $movement->quantityBefore(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $movement->quantityAfter(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $movement->unitCost(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?php if ($movement->referenceNumber() !== null): ?>
                                <strong>
                                    <?= e($movement->referenceNumber()) ?>
                                </strong>
                            <?php else: ?>
                                —
                            <?php endif; ?>

                            <?php if ($movement->notes() !== null): ?>
                                <small>
                                    <?= e($movement->notes()) ?>
                                </small>
                            <?php endif; ?>
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
            aria-label="Stock movement pages"
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
                            '/inventory/movements?'
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