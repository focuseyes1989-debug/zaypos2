<?php

declare(strict_types=1);

use App\Security\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Opening Stock — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >
</head>

<body class="app-page">

<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="form-shell">
    <div class="breadcrumb">
        <a href="<?= e(app_url('/inventory')) ?>">
            Inventory
        </a>

        <span>/</span>

        <span>Opening stock</span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>Opening Stock</h1>

            <p class="muted">
                Enter the initial stock balance for a product and warehouse.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(app_url('/inventory/opening')) ?>"
        >
            <?= Csrf::input() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="warehouse_id">
                        Warehouse
                    </label>

                    <select
                        id="warehouse_id"
                        name="warehouse_id"
                        required
                    >
                        <option value="">
                            Select warehouse
                        </option>

                        <?php foreach ($warehouseOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) ($input['warehouse_id'] ?? 0)
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="product_id">
                        Product
                    </label>

                    <select
                        id="product_id"
                        name="product_id"
                        required
                    >
                        <option value="">
                            Select product
                        </option>

                        <?php foreach ($productOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) ($input['product_id'] ?? 0)
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
                </div>

                <div class="field">
                    <label for="quantity">
                        Opening quantity
                    </label>

                    <input
                        id="quantity"
                        name="quantity"
                        type="number"
                        min="0.0001"
                        step="0.0001"
                        value="<?= e($input['quantity'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="unit_cost">
                        Unit cost
                    </label>

                    <input
                        id="unit_cost"
                        name="unit_cost"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e($input['unit_cost'] ?? 0) ?>"
                        required
                    >
                </div>

                <div class="field full">
                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="5"
                        maxlength="5000"
                    ><?= e($input['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(app_url('/inventory')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Save opening stock
                </button>
            </div>
        </form>
    </section>
</main>

</body>
</html>