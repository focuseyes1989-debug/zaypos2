<?php

declare(strict_types=1);

use App\Models\SaleItem;
use App\Security\Csrf;

$permissions = $currentUser['permissions'] ?? [];
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
        Edit <?= e($sale->saleNumber()) ?> — ZAY POS 2.0
    </title>

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
                SALE
            </p>

            <h1>
                <?= e($sale->saleNumber()) ?>
            </h1>

            <p class="muted">
                Edit draft sale header
                and sale line items.
            </p>
        </div>

        <div class="page-actions">

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sales/show?id='
                        . $sale->id()
                    )
                ) ?>"
            >
                View details
            </a>

            <a
                class="secondary-link"
                href="<?= e(app_url('/sales')) ?>"
            >
                Sales
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

    <!-- Sale Header -->
    <section class="form-card">

        <div class="form-heading">
            <h2>
                Sale information
            </h2>
        </div>

        <form
            method="post"
            action="<?= e(app_url('/sales/update')) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="sale_id"
                value="<?= (int) $sale->id() ?>"
            >

            <div class="form-grid">

                <div class="field">
                    <label>
                        Sale number
                    </label>

                    <input
                        value="<?= e(
                            $sale->saleNumber()
                        ) ?>"
                        disabled
                    >
                </div>

                <div class="field">
                    <label for="customer_reference">
                        Customer reference
                    </label>

                    <input
                        id="customer_reference"
                        name="customer_reference"
                        maxlength="100"
                        value="<?= e(
                            $input['customer_reference']
                            ?? $sale->customerReference()
                            ?? ''
                        ) ?>"
                        placeholder="Optional reference"
                    >
                </div>

                <div class="field">
                    <label for="customer_id">
                        Customer
                    </label>

                    <select
                        id="customer_id"
                        name="customer_id"
                    >
                        <option value="">
                            Walk-in Customer
                        </option>

                        <?php foreach (
                            $customerOptions as $option
                        ): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['customer_id']
                                    ?? $sale->customerId()
                                    ?? 0
                                ) === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <small class="muted">
                        Leave blank for walk-in customer.
                    </small>
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

                        <?php foreach (
                            $warehouseOptions as $option
                        ): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['warehouse_id']
                                    ?? $sale->warehouseId()
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
                    <label for="sale_date">
                        Sale date
                    </label>

                    <input
                        id="sale_date"
                        name="sale_date"
                        type="date"
                        value="<?= e(
                            $input['sale_date']
                            ?? $sale
                                ->saleDate()
                                ->format('Y-m-d')
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
                        value="<?= e(
                            $input['due_date']
                            ?? $sale
                                ->dueDate()
                                ?->format('Y-m-d')
                            ?? ''
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="shipping_amount">
                        Shipping
                    </label>

                    <input
                        id="shipping_amount"
                        name="shipping_amount"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['shipping_amount']
                            ?? $sale->shippingAmount()
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="other_amount">
                        Other
                    </label>

                    <input
                        id="other_amount"
                        name="other_amount"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['other_amount']
                            ?? $sale->otherAmount()
                        ) ?>"
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
                        value="<?= e(
                            $input['paid_amount']
                            ?? $sale->paidAmount()
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
                        rows="4"
                        maxlength="5000"
                    ><?= e(
                        $input['notes']
                        ?? $sale->notes()
                        ?? ''
                    ) ?></textarea>
                </div>

            </div>

            <div class="form-actions">

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Save sale
                </button>

            </div>

        </form>

    </section>

    <!-- Add Sale Item -->
    <section class="form-card">

        <div class="form-heading">
            <h2>
                Add sale item
            </h2>

            <p class="muted">
                Select the product's configured
                sale unit and selling price.
            </p>
        </div>

        <form
            method="post"
            action="<?= e(
                app_url('/sales/items/add')
            ) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="sale_id"
                value="<?= (int) $sale->id() ?>"
            >

            <div class="form-grid">

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

                        <?php foreach (
                            $productOptions as $option
                        ): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['product_id']
                                    ?? 0
                                ) === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>

                                <?php if (
                                    !empty($option['sku'])
                                ): ?>
                                    — <?= e($option['sku']) ?>
                                <?php endif; ?>

                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="field">
                    <label for="unit_id">
                        Unit
                    </label>

                    <select
                        id="unit_id"
                        name="unit_id"
                        required
                    >
                        <option value="">
                            Select unit
                        </option>

                        <?php foreach (
                            $unitOptions as $option
                        ): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['unit_id']
                                    ?? 0
                                ) === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>

                                <?php if (
                                    !empty($option['code'])
                                ): ?>
                                    (<?= e($option['code']) ?>)
                                <?php endif; ?>
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
                        value="<?= e(
                            $input['quantity']
                            ?? ''
                        ) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="unit_price">
                        Unit price
                    </label>

                    <input
                        id="unit_price"
                        name="unit_price"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['unit_price']
                            ?? ''
                        ) ?>"
                        placeholder="Uses sale price if blank"
                    >
                </div>

                <div class="field">
                    <label for="discount_type">
                        Discount type
                    </label>

                    <select
                        id="discount_type"
                        name="discount_type"
                    >
                        <option value="">
                            No discount
                        </option>

                        <option
                            value="percentage"
                            <?= (
                                $input['discount_type']
                                ?? ''
                            ) === 'percentage'
                                ? 'selected'
                                : '' ?>
                        >
                            Percentage
                        </option>

                        <option
                            value="fixed"
                            <?= (
                                $input['discount_type']
                                ?? ''
                            ) === 'fixed'
                                ? 'selected'
                                : '' ?>
                        >
                            Fixed amount
                        </option>

                    </select>
                </div>

                <div class="field">
                    <label for="discount_value">
                        Discount value
                    </label>

                    <input
                        id="discount_value"
                        name="discount_value"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['discount_value']
                            ?? 0
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="tax_id">
                        Tax
                    </label>

                    <select
                        id="tax_id"
                        name="tax_id"
                    >
                        <option value="">
                            No tax
                        </option>

                        <?php foreach (
                            $taxOptions as $option
                        ): ?>

                            <?php
                            if (
                                isset(
                                    $option[
                                        'applies_to_sales'
                                    ]
                                )
                                && !$option[
                                    'applies_to_sales'
                                ]
                            ) {
                                continue;
                            }
                            ?>

                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['tax_id']
                                    ?? 0
                                ) === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>

                                <?php if (
                                    isset($option['rate'])
                                ): ?>
                                    — <?= e(
                                        (string) $option['rate']
                                    ) ?>
                                <?php endif; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="field full">
                    <label for="item_notes">
                        Item notes
                    </label>

                    <input
                        id="item_notes"
                        name="notes"
                        maxlength="500"
                        value="<?= e(
                            $input['notes']
                            ?? ''
                        ) ?>"
                    >
                </div>

            </div>

            <div class="form-actions">
                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Add item
                </button>
            </div>

        </form>

    </section>

    <!-- Sale Items -->
    <section class="table-card">

        <div class="table-scroll">
            <table>

                <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Qty</th>
                    <th>Fulfilled</th>
                    <th>Price</th>
                    <th>Discount</th>
                    <th>Tax</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($items === []): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="empty-cell"
                        >
                            No sale items yet.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach (
                    $items as $item
                ): ?>

                    <?php
                    if (
                        !$item instanceof SaleItem
                    ) {
                        continue;
                    }
                    ?>

                    <tr>

                        <td>
                            Product #
                            <?= (int) $item->productId() ?>
                        </td>

                        <td>
                            Unit #
                            <?= (int) $item->unitId() ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item->quantity(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item
                                        ->fulfilledQuantity(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item->unitPrice(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item
                                        ->discountAmount(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item->taxAmount(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $item->lineTotal(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>

                            <?php if (
                                in_array(
                                    'sales.update',
                                    $permissions,
                                    true
                                )
                            ): ?>

                                <form
                                    method="post"
                                    action="<?= e(
                                        app_url(
                                            '/sales/items/delete'
                                        )
                                    ) ?>"
                                    onsubmit="return confirm('Delete this sale item?');"
                                >
                                    <?= Csrf::input() ?>

                                    <input
                                        type="hidden"
                                        name="sale_id"
                                        value="<?= (int) $sale->id() ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int) $item->id() ?>"
                                    >

                                    <button
                                        class="danger-button compact"
                                        type="submit"
                                    >
                                        Delete
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>
        </div>

    </section>

    <!-- Totals -->
    <section class="form-card">

        <div class="form-heading">
            <h2>
                Totals
            </h2>
        </div>

        <div class="summary-grid">

            <div>
                <span class="muted">
                    Subtotal
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->subtotal(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Discount
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale
                                ->discountAmount(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Tax
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->taxAmount(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Shipping
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale
                                ->shippingAmount(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Other
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->otherAmount(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Grand total
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->grandTotal(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Paid
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->paidAmount(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Balance due
                </span>

                <strong>
                    <?= e(
                        number_format(
                            $sale->balanceDue(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Payment status
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            $sale->paymentStatus()
                        )
                    ) ?>
                </strong>
            </div>

        </div>

    </section>

</main>

</body>
</html>