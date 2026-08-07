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
        <?= e($sale->saleNumber()) ?> — ZAY POS 2.0
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
                Customer reference:
                <?= e(
                    $sale->customerReference()
                    ?? '—'
                ) ?>
            </p>
        </div>

        <div class="page-actions">

            <?php if (
                $sale->isDraft()
                && !$sale->isDeleted()
                && in_array(
                    'sales.update',
                    $permissions,
                    true
                )
            ): ?>
                <a
                    class="secondary-link"
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

    <!-- Header summary -->
    <section class="form-card">

        <div class="summary-grid">

            <div>
                <span class="muted">
                    Status
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            $sale->status()
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Payment
                </span>

                <strong>
                    <?= e(
                        ucfirst(
                            $sale->paymentStatus()
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Customer
                </span>

                <strong>
                    <?= $sale->customerId() !== null
                        ? 'Customer #'
                            . (int) $sale->customerId()
                        : 'Walk-in Customer' ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Warehouse ID
                </span>

                <strong>
                    <?= (int) $sale->warehouseId() ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Sale date
                </span>

                <strong>
                    <?= e(
                        $sale
                            ->saleDate()
                            ->format('Y-m-d')
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="muted">
                    Due date
                </span>

                <strong>
                    <?= $sale->dueDate()
                        ? e(
                            $sale
                                ->dueDate()
                                ->format('Y-m-d')
                        )
                        : '—' ?>
                </strong>
            </div>

        </div>

    </section>

    <!-- Items -->
    <section class="table-card">

        <div class="table-scroll">
            <table>

                <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Qty</th>
                    <th>Fulfilled</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <th>Tax</th>
                    <th>Total</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($items === []): ?>
                    <tr>
                        <td
                            colspan="8"
                            class="empty-cell"
                        >
                            No sale items.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach (
                    $items as $item
                ): ?>

                    <?php
                    if (!$item instanceof SaleItem) {
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

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>
        </div>

    </section>

    <!-- Totals -->
    <section class="form-card">

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
                            $sale->discountAmount(),
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
                            $sale->shippingAmount(),
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
                    Paid amount
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

        </div>

    </section>

    <?php if ($sale->notes() !== null): ?>

        <section class="form-card">

            <h2>
                Notes
            </h2>

            <p>
                <?= nl2br(
                    e($sale->notes())
                ) ?>
            </p>

        </section>

    <?php endif; ?>

    <!-- Draft actions -->
    <?php if (
        $sale->isDraft()
        && !$sale->isDeleted()
    ): ?>

        <section class="form-card">

            <div class="page-actions">

                <?php if (
                    in_array(
                        'sales.complete',
                        $permissions,
                        true
                    )
                ): ?>

                    <form
                        method="post"
                        action="<?= e(
                            app_url(
                                '/sales/complete'
                            )
                        ) ?>"
                        onsubmit="return confirm('Complete this sale and deduct inventory?');"
                    >
                        <?= Csrf::input() ?>

                        <input
                            type="hidden"
                            name="sale_id"
                            value="<?= (int) $sale->id() ?>"
                        >

                        <button
                            class="primary-button compact"
                            type="submit"
                        >
                            Complete sale
                        </button>

                    </form>

                <?php endif; ?>

                <?php if (
                    in_array(
                        'sales.cancel',
                        $permissions,
                        true
                    )
                ): ?>

                    <form
                        method="post"
                        action="<?= e(
                            app_url(
                                '/sales/cancel'
                            )
                        ) ?>"
                    >
                        <?= Csrf::input() ?>

                        <input
                            type="hidden"
                            name="sale_id"
                            value="<?= (int) $sale->id() ?>"
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
                            Cancel sale
                        </button>

                    </form>

                <?php endif; ?>

                <?php if (
                    in_array(
                        'sales.delete',
                        $permissions,
                        true
                    )
                ): ?>

                    <form
                        method="post"
                        action="<?= e(
                            app_url(
                                '/sales/delete'
                            )
                        ) ?>"
                        onsubmit="return confirm('Delete this draft sale?');"
                    >
                        <?= Csrf::input() ?>

                        <input
                            type="hidden"
                            name="sale_id"
                            value="<?= (int) $sale->id() ?>"
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

    <!-- Restore -->
    <?php if (
        $sale->isDeleted()
        && in_array(
            'sales.restore',
            $permissions,
            true
        )
    ): ?>

        <section class="form-card">

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
                    class="primary-button compact"
                    type="submit"
                >
                    Restore sale
                </button>

            </form>

        </section>

    <?php endif; ?>

</main>

</body>
</html>