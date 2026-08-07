<?php

declare(strict_types=1);

use App\Models\PurchaseItem;
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
        <?= e($purchase->purchaseNumber()) ?> — ZAY POS 2.0
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
                Supplier invoice:
                <?= e(
                    $purchase->supplierInvoiceNumber()
                    ?? '—'
                ) ?>
            </p>
        </div>

        <div class="page-actions">
            <?php if (
                $purchase->isDraft()
                && in_array(
                    'purchases.update',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="secondary-link"
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
                $purchase->isReceived()
                && !$purchase->isDeleted()
                && $purchase->balanceDue() > 0
                && in_array(
                    'purchase_payments.create',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/purchase-payments/create?purchase_id='
                            . $purchase->id()
                        )
                    ) ?>"
                >
                    Record Payment
                </a>
            <?php endif; ?>

            <?php if (
                in_array(
                    'purchase_payments.view',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/purchase-payments/history?purchase_id='
                            . $purchase->id()
                        )
                    ) ?>"
                >
                    Payment History
                </a>
            <?php endif; ?>

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
        <div class="summary-grid">
            <div>
                <span class="muted">Status</span>
                <strong><?= e(ucfirst($purchase->status())) ?></strong>
            </div>

            <div>
                <span class="muted">Payment</span>
                <strong>
                    <?= e(ucfirst($purchase->paymentStatus())) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Supplier ID</span>
                <strong><?= (int) $purchase->supplierId() ?></strong>
            </div>

            <div>
                <span class="muted">Warehouse ID</span>
                <strong><?= (int) $purchase->warehouseId() ?></strong>
            </div>

            <div>
                <span class="muted">Purchase date</span>
                <strong>
                    <?= e($purchase->purchaseDate()->format('Y-m-d')) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Due date</span>
                <strong>
                    <?= $purchase->dueDate()
                        ? e($purchase->dueDate()->format('Y-m-d'))
                        : '—' ?>
                </strong>
            </div>
        </div>
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
                    <th>Unit Cost</th>
                    <th>Discount</th>
                    <th>Tax</th>
                    <th>Total</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    if (!$item instanceof PurchaseItem) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            #<?= (int) $item->productId() ?>
                        </td>

                        <td>
                            #<?= (int) $item->unitId() ?>
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
                                <?= e(number_format($item->lineTotal(), 4)) ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="form-card">
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
                    <?= e(number_format($purchase->discountAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Tax</span>
                <strong>
                    <?= e(number_format($purchase->taxAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Shipping</span>
                <strong>
                    <?= e(number_format($purchase->shippingAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Other</span>
                <strong>
                    <?= e(number_format($purchase->otherAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Grand total</span>
                <strong>
                    <?= e(number_format($purchase->grandTotal(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Paid amount</span>
                <strong>
                    <?= e(number_format($purchase->paidAmount(), 4)) ?>
                </strong>
            </div>

            <div>
                <span class="muted">Payment status</span>
                <strong>
                    <?= e(ucfirst($purchase->paymentStatus())) ?>
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

    <?php if ($purchase->notes() !== null): ?>
        <section class="form-card">
            <h2>Notes</h2>

            <p>
                <?= nl2br(e($purchase->notes())) ?>
            </p>
        </section>
    <?php endif; ?>

    <?php if ($purchase->isDraft()): ?>
        <section class="form-card">
            <div class="page-actions">
                <?php if (
                    in_array(
                        'purchases.receive',
                        $permissions,
                        true
                    )
                ): ?>
                    <form
                        method="post"
                        action="<?= e(app_url('/purchases/receive')) ?>"
                        onsubmit="return confirm('Receive this purchase into inventory?');"
                    >
                        <?= Csrf::input() ?>

                        <input
                            type="hidden"
                            name="purchase_id"
                            value="<?= (int) $purchase->id() ?>"
                        >

                        <button
                            class="primary-button compact"
                            type="submit"
                        >
                            Receive purchase
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (
                    in_array(
                        'purchases.cancel',
                        $permissions,
                        true
                    )
                ): ?>
                    <form
                        method="post"
                        action="<?= e(app_url('/purchases/cancel')) ?>"
                    >
                        <?= Csrf::input() ?>

                        <input
                            type="hidden"
                            name="purchase_id"
                            value="<?= (int) $purchase->id() ?>"
                        >

                        <input
                            name="reason"
                            maxlength="500"
                            placeholder="Cancellation reason"
                        >

                        <button
                            class="secondary-button"
                            type="submit"
                        >
                            Cancel purchase
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (
                    in_array(
                        'purchases.delete',
                        $permissions,
                        true
                    )
                ): ?>
                    <form
                        method="post"
                        action="<?= e(app_url('/purchases/delete')) ?>"
                        onsubmit="return confirm('Delete this draft purchase?');"
                    >
                        <?= Csrf::input() ?>

                        <input
                            type="hidden"
                            name="purchase_id"
                            value="<?= (int) $purchase->id() ?>"
                        >

                        <button
                            class="danger-button compact"
                            type="submit"
                        >
                            Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (
        $purchase->isDeleted()
        && in_array(
            'purchases.restore',
            $permissions,
            true
        )
    ): ?>
        <section class="form-card">
            <form
                method="post"
                action="<?= e(app_url('/purchases/restore')) ?>"
            >
                <?= Csrf::input() ?>

                <input
                    type="hidden"
                    name="purchase_id"
                    value="<?= (int) $purchase->id() ?>"
                >

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    Restore purchase
                </button>
            </form>
        </section>
    <?php endif; ?>
</main>

</body>
</html>