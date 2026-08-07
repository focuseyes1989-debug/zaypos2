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

    <title>Create Purchase — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >
</head>

<body class="app-page">

<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="form-shell">
    <div class="breadcrumb">
        <a href="<?= e(app_url('/purchases')) ?>">
            Purchases
        </a>

        <span>/</span>

        <span>Create</span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>New Purchase</h1>

            <p class="muted">
                Create a draft supplier purchase. Products can be added after the draft is saved.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(app_url('/purchases')) ?>"
        >
            <?= Csrf::input() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="purchase_number">
                        Purchase number
                    </label>

                    <input
                        id="purchase_number"
                        name="purchase_number"
                        maxlength="100"
                        value="<?= e($input['purchase_number'] ?? '') ?>"
                        placeholder="Leave blank to generate automatically"
                    >
                </div>

                <div class="field">
                    <label for="supplier_invoice_number">
                        Supplier invoice number
                    </label>

                    <input
                        id="supplier_invoice_number"
                        name="supplier_invoice_number"
                        maxlength="100"
                        value="<?= e(
                            $input['supplier_invoice_number'] ?? ''
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="supplier_id">
                        Supplier
                    </label>

                    <select
                        id="supplier_id"
                        name="supplier_id"
                        required
                    >
                        <option value="">
                            Select supplier
                        </option>

                        <?php foreach ($supplierOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) ($input['supplier_id'] ?? 0)
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
                    <label for="purchase_date">
                        Purchase date
                    </label>

                    <input
                        id="purchase_date"
                        name="purchase_date"
                        type="date"
                        value="<?= e(
                            $input['purchase_date'] ?? date('Y-m-d')
                        ) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="due_date">
                        Due date
                    </label>

                    <input
                        id="due_date"
                        name="due_date"
                        type="date"
                        value="<?= e($input['due_date'] ?? '') ?>"
                    >
                </div>

                <div class="field">
                    <label for="shipping_amount">
                        Shipping amount
                    </label>

                    <input
                        id="shipping_amount"
                        name="shipping_amount"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e($input['shipping_amount'] ?? 0) ?>"
                    >
                </div>

                <div class="field">
                    <label for="other_amount">
                        Other amount
                    </label>

                    <input
                        id="other_amount"
                        name="other_amount"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e($input['other_amount'] ?? 0) ?>"
                    >
                </div>

                <div class="field">
                    <label for="paid_amount">
                        Paid amount
                    </label>

                    <input
                        id="paid_amount"
                        name="paid_amount"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e($input['paid_amount'] ?? 0) ?>"
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
                    href="<?= e(app_url('/purchases')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Create draft
                </button>
            </div>
        </form>
    </section>
</main>

</body>
</html>