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

    <title>Stock Transfer — ZAY POS 2.0</title>

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

        <span>Transfer</span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>Warehouse Transfer</h1>

            <p class="muted">
                Transfer stock from one warehouse to another.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(app_url('/inventory/transfer')) ?>"
        >
            <?= Csrf::input() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="from_warehouse_id">
                        From warehouse
                    </label>

                    <select
                        id="from_warehouse_id"
                        name="from_warehouse_id"
                        required
                    >
                        <option value="">
                            Select source warehouse
                        </option>

                        <?php foreach ($warehouseOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['from_warehouse_id']
                                    ?? 0
                                ) === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="to_warehouse_id">
                        To warehouse
                    </label>

                    <select
                        id="to_warehouse_id"
                        name="to_warehouse_id"
                        required
                    >
                        <option value="">
                            Select destination warehouse
                        </option>

                        <?php foreach ($warehouseOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['to_warehouse_id']
                                    ?? 0
                                ) === (int) $option['id']
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
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="quantity">
                        Quantity
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
                    <label for="reference_number">
                        Reference number
                    </label>

                    <input
                        id="reference_number"
                        name="reference_number"
                        maxlength="100"
                        value="<?= e(
                            $input['reference_number'] ?? ''
                        ) ?>"
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
                    Transfer stock
                </button>
            </div>
        </form>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fromWarehouse = document.getElementById(
        'from_warehouse_id'
    );

    const toWarehouse = document.getElementById(
        'to_warehouse_id'
    );

    if (!fromWarehouse || !toWarehouse) {
        return;
    }

    function validateWarehouses() {
        if (
            fromWarehouse.value !== ''
            && toWarehouse.value !== ''
            && fromWarehouse.value === toWarehouse.value
        ) {
            toWarehouse.setCustomValidity(
                'Source and destination warehouses must be different.'
            );
        } else {
            toWarehouse.setCustomValidity('');
        }
    }

    fromWarehouse.addEventListener(
        'change',
        validateWarehouses
    );

    toWarehouse.addEventListener(
        'change',
        validateWarehouses
    );
});
</script>

</body>
</html>