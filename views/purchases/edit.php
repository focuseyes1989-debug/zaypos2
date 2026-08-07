<?php

declare(strict_types=1);

use App\Models\PurchaseItem;
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

    <title>
        Edit <?= e($purchase->purchaseNumber()) ?> — ZAY POS 2.0
    </title>

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
            <p class="eyebrow">PURCHASE</p>

            <h1>
                <?= e($purchase->purchaseNumber()) ?>
            </h1>

            <p class="muted">
                Edit draft purchase header and line items.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/purchases/show?id='
                        . $purchase->id()
                    )
                ) ?>"
            >
                View details
            </a>

            <a
                class="secondary-link"
                href="<?= e(app_url('/purchases')) ?>"
            >
                Purchases
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

    <section class="form-card">
        <div class="form-heading">
            <h2>Purchase information</h2>
        </div>

        <form
            method="post"
            action="<?= e(app_url('/purchases/update')) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="purchase_id"
                value="<?= (int) $purchase->id() ?>"
            >

            <div class="form-grid">
                <div class="field">
                    <label>Purchase number</label>

                    <input
                        value="<?= e($purchase->purchaseNumber()) ?>"
                        disabled
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
                            $input['supplier_invoice_number']
                            ?? $purchase->supplierInvoiceNumber()
                            ?? ''
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
                        <?php foreach ($supplierOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['supplier_id']
                                    ?? $purchase->supplierId()
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
                    <label for="warehouse_id">
                        Warehouse
                    </label>

                    <select
                        id="warehouse_id"
                        name="warehouse_id"
                        required
                    >
                        <?php foreach ($warehouseOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) (
                                    $input['warehouse_id']
                                    ?? $purchase->warehouseId()
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
                    <label for="purchase_date">
                        Purchase date
                    </label>

                    <input
                        id="purchase_date"
                        name="purchase_date"
                        type="date"
                        value="<?= e(
                            $input['purchase_date']
                            ?? $purchase->purchaseDate()->format('Y-m-d')
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
                            ?? $purchase->dueDate()?->format('Y-m-d')
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
                            ?? $purchase->shippingAmount()
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
                            ?? $purchase->otherAmount()
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
                            ?? $purchase->paidAmount()
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
                        ?? $purchase->notes()
                        ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Save purchase
                </button>
            </div>
        </form>
    </section>

    <section class="form-card">
        <div class="form-heading">
            <h2>Add purchase item</h2>

            <p class="muted">
                Select the product's configured purchase unit.
            </p>
        </div>

        <form
            method="post"
            action="<?= e(app_url('/purchases/items/add')) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="purchase_id"
                value="<?= (int) $purchase->id() ?>"
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

                        <?php foreach ($productOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
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

                        <?php foreach ($unitOptions as $option): ?>
                            <option value="<?= (int) $option['id'] ?>">
                                <?= e($option['name']) ?>
                                (<?= e($option['code']) ?>)
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
                        required
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

                        <option value="percentage">
                            Percentage
                        </option>

                        <option value="fixed">
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
                        value="0"
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

                        <?php foreach ($taxOptions as $option): ?>
                            <?php
                            if (
                                isset($option['applies_to_purchases'])
                                && !$option['applies_to_purchases']
                            ) {
                                continue;
                            }
                            ?>

                            <option value="<?= (int) $option['id'] ?>">
                                <?= e($option['name']) ?>
                                — <?= e((string) $option['rate']) ?>
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

    <section class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Qty</th>
                    <th>Received</th>
                    <th>Cost</th>
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
                            No purchase items yet.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <?php
                    if (!$item instanceof PurchaseItem) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            Product #<?= (int) $item->productId() ?>
                        </td>

                        <td>
                            Unit #<?= (int) $item->unitId() ?>
                        </td>

                        <td>
                            <?= e(number_format($item->quantity(), 4)) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item->receivedQuantity(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(number_format($item->unitCost(), 4)) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $item->discountAmount(),
                                    4
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(number_format($item->taxAmount(), 4)) ?>
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
                            <form
                                method="post"
                                action="<?= e(
                                    app_url('/purchases/items/delete')
                                ) ?>"
                                onsubmit="return confirm('Delete this purchase item?');"
                            >
                                <?= Csrf::input() ?>

                                <input
                                    type="hidden"
                                    name="purchase_id"
                                    value="<?= (int) $purchase->id() ?>"
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
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="form-card">
        <div class="form-heading">
            <h2>Totals</h2>
        </div>

        <div class="summary-grid">
            <div>
                <span class="muted">Subtotal</span>
                <strong>
                    <?= e(number_format($purchase->subtotal(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Discount</span>
                <strong>
                    <?= e(
                        number_format(
                            $purchase->discountAmount(),
                            4
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Tax</span>
                <strong>
                    <?= e(number_format($purchase->taxAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Grand total</span>
                <strong>
                    <?= e(number_format($purchase->grandTotal(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Paid</span>
                <strong>
                    <?= e(number_format($purchase->paidAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Balance due</span>
                <strong>
                    <?= e(number_format($purchase->balanceDue(), 4)) ?>
                </strong>
            </div>
        </div>
    </section>
</main>

</body>
</html>