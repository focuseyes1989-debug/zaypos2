<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WarehouseStock;
use App\Security\Csrf;

$sale =
    $cart['sale']
    ?? null;

$items =
    $cart['items']
    ?? [];

$isDraft =
    $sale instanceof Sale
    && $sale->isDraft();

$grandTotal =
    $sale instanceof Sale
        ? $sale->grandTotal()
        : 0.0;

$balanceDue =
    $sale instanceof Sale
        ? $sale->balanceDue()
        : 0.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>POS Checkout — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url(
                '/assets/css/app.css'
            )
        ) ?>"
    >

    <style>
        .pos-shell {
            width: min(1600px, calc(100% - 24px));
            margin: 20px auto 40px;
        }

        .pos-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .pos-heading h1 {
            margin: 0;
        }

        .pos-heading-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pos-layout {
            display: grid;
            grid-template-columns:
                minmax(0, 1.5fr)
                minmax(360px, 0.9fr);
            gap: 18px;
            align-items: start;
        }

        .pos-panel {
            background: var(--surface, #fff);
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 18px;
        }

        .pos-panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .pos-panel-heading h2 {
            margin: 0;
        }

        .pos-start-card {
            max-width: 800px;
            margin: 30px auto;
        }

        .pos-start-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .pos-start-grid .full {
            grid-column: 1 / -1;
        }

        .pos-scan-row {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                100px
                auto;
            gap: 10px;
            margin-bottom: 14px;
        }

        .pos-scan-row input {
            min-width: 0;
        }

        .pos-product-search {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }

        .pos-product-search input {
            flex: 1;
            min-width: 0;
        }

        .pos-product-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(190px, 1fr)
                );
            gap: 12px;
        }

        .pos-product-card {
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 9px;
            min-height: 190px;
        }

        .pos-product-card h3 {
            margin: 0;
            font-size: 16px;
        }

        .pos-product-meta {
            font-size: 12px;
            opacity: .75;
            line-height: 1.5;
        }

        .pos-product-price {
            font-size: 20px;
            font-weight: 700;
            margin-top: auto;
        }

        .pos-product-stock {
            font-size: 13px;
        }

        .pos-product-actions {
            margin-top: 4px;
        }

        .pos-product-actions form {
            display: flex;
            gap: 8px;
        }

        .pos-product-actions input {
            width: 72px;
        }

        .pos-out-of-stock {
            opacity: .55;
        }

        .pos-cart-panel {
            position: sticky;
            top: 16px;
        }

        .pos-cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pos-cart-table th,
        .pos-cart-table td {
            padding: 10px 7px;
            border-bottom: 1px solid
                var(--border-color, #e6e8eb);
            vertical-align: middle;
        }

        .pos-cart-table th {
            text-align: left;
            font-size: 12px;
            opacity: .75;
        }

        .pos-cart-name {
            min-width: 125px;
        }

        .pos-cart-name strong {
            display: block;
        }

        .pos-cart-name small {
            display: block;
            margin-top: 3px;
        }

        .pos-qty-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pos-qty-form input {
            width: 78px;
        }

        .pos-cart-actions {
            display: flex;
            gap: 5px;
        }

        .pos-cart-summary {
            margin-top: 18px;
            border-top: 2px solid
                var(--border-color, #dfe3e8);
            padding-top: 14px;
        }

        .pos-summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
        }

        .pos-summary-total {
            font-size: 22px;
            font-weight: 700;
            padding-top: 10px;
        }

        .pos-payment-box {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid
                var(--border-color, #dfe3e8);
        }

        .pos-payment-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .pos-payment-grid .full {
            grid-column: 1 / -1;
        }

        .pos-change-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            background: rgba(0, 0, 0, .04);
            margin-top: 12px;
        }

        .pos-change-value {
            font-size: 22px;
            font-weight: 700;
        }

        .pos-checkout-button {
            width: 100%;
            margin-top: 14px;
            min-height: 52px;
            font-size: 17px;
        }

        .pos-empty-cart {
            text-align: center;
            padding: 35px 10px;
            opacity: .7;
        }

        @media (max-width: 1050px) {
            .pos-layout {
                grid-template-columns: 1fr;
            }

            .pos-cart-panel {
                position: static;
            }
        }

        @media (max-width: 700px) {
            .pos-shell {
                width: min(
                    100% - 14px,
                    1600px
                );
                margin-top: 10px;
            }

            .pos-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .pos-start-grid,
            .pos-payment-grid {
                grid-template-columns: 1fr;
            }

            .pos-start-grid .full,
            .pos-payment-grid .full {
                grid-column: auto;
            }

            .pos-scan-row {
                grid-template-columns:
                    minmax(0, 1fr)
                    80px;
            }

            .pos-scan-row button {
                grid-column: 1 / -1;
            }

            .pos-product-grid {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

            .pos-cart-table {
                min-width: 620px;
            }
        }

        @media (max-width: 480px) {
            .pos-product-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="app-page">

<?php
require BASE_PATH
    . '/views/partials/admin_header.php';
?>

<main class="pos-shell">

    <section class="pos-heading">
        <div>
            <p class="eyebrow">
                CASHIER
            </p>

            <h1>
                POS Checkout
            </h1>

            <?php if ($sale instanceof Sale): ?>
                <p class="muted">
                    <?= e(
                        $sale->saleNumber()
                    ) ?>
                    · Warehouse #
                    <?= (int)
                        $sale->warehouseId()
                    ?>
                </p>
            <?php else: ?>
                <p class="muted">
                    Start a sale to begin checkout.
                </p>
            <?php endif; ?>
        </div>

        <div class="pos-heading-actions">
            <?php if ($sale instanceof Sale): ?>
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/sales/show?id='
                            . $sale->id()
                        )
                    ) ?>"
                >
                    Sale Detail
                </a>

                <a
                    class="primary-link"
                    href="<?= e(
                        app_url('/pos')
                    ) ?>"
                >
                    New Sale
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

    <?php if (!$sale instanceof Sale): ?>

        <section
            class="pos-panel pos-start-card"
        >
            <div class="pos-panel-heading">
                <div>
                    <h2>
                        Start New Sale
                    </h2>

                    <p class="muted">
                        Select warehouse and
                        optional customer.
                    </p>
                </div>
            </div>

            <form
                method="post"
                action="<?= e(
                    app_url(
                        '/pos/start'
                    )
                ) ?>"
            >
                <?= Csrf::input() ?>

                <div class="pos-start-grid">
                    <div class="field">
                        <label for="warehouse_id">
                            Warehouse
                        </label>

                        <select
                            id="warehouse_id"
                            name="warehouse_id"
                            required
                            autofocus
                        >
                            <option value="">
                                Select warehouse
                            </option>

                            <?php foreach (
                                $warehouseOptions
                                as $option
                            ): ?>
                                <option
                                    value="<?= (int)
                                        $option['id']
                                    ?>"
                                    <?= (
                                        (int)
                                            $selectedWarehouseId
                                        ===
                                        (int)
                                            $option['id']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= e(
                                        $option['name']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                                $customerOptions
                                as $option
                            ): ?>
                                <option
                                    value="<?= (int)
                                        $option['id']
                                    ?>"
                                >
                                    <?= e(
                                        $option['name']
                                    ) ?>
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
                                date('Y-m-d')
                            ) ?>"
                            required
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
                            placeholder="Optional"
                        >
                    </div>

                    <div class="full">
                        <button
                            class="primary-button"
                            type="submit"
                        >
                            Start Sale
                        </button>
                    </div>
                </div>
            </form>
        </section>

    <?php else: ?>

        <div class="pos-layout">

            <section class="pos-panel">

                <div class="pos-panel-heading">
                    <div>
                        <h2>
                            Products
                        </h2>

                        <p class="muted">
                            <?= (int)
                                $productTotal
                            ?>
                            active product(s)
                        </p>
                    </div>
                </div>

                <form
                    class="pos-scan-row"
                    method="post"
                    action="<?= e(
                        app_url(
                            '/pos/scan'
                        )
                    ) ?>"
                >
                    <?= Csrf::input() ?>

                    <input
                        type="hidden"
                        name="sale_id"
                        value="<?= (int)
                            $sale->id()
                        ?>"
                    >

                    <input
                        id="pos-barcode"
                        name="barcode"
                        type="text"
                        maxlength="190"
                        placeholder="Scan barcode..."
                        autocomplete="off"
                        required
                        autofocus
                    >

                    <input
                        name="quantity"
                        type="number"
                        min="0.0001"
                        step="0.0001"
                        value="1"
                        aria-label="Quantity"
                    >

                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Add
                    </button>
                </form>

                <form
                    class="pos-product-search"
                    method="get"
                    action="<?= e(
                        app_url('/pos')
                    ) ?>"
                >
                    <input
                        type="hidden"
                        name="sale_id"
                        value="<?= (int)
                            $sale->id()
                        ?>"
                    >

                    <input
                        name="search"
                        maxlength="190"
                        value="<?= e(
                            $search
                        ) ?>"
                        placeholder="Search product, SKU or barcode"
                    >

                    <button
                        class="secondary-button"
                        type="submit"
                    >
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a
                            class="secondary-link"
                            href="<?= e(
                                app_url(
                                    '/pos?sale_id='
                                    . $sale->id()
                                )
                            ) ?>"
                        >
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <div class="pos-product-grid">

                    <?php if ($products === []): ?>

                        <div class="pos-empty-cart">
                            No products found.
                        </div>

                    <?php else: ?>

                        <?php foreach (
                            $products
                            as $entry
                        ): ?>

                            <?php
                            $product =
                                $entry[
                                    'product'
                                ]
                                ?? null;

                            $availability =
                                $entry[
                                    'availability'
                                ]
                                ?? null;

                            if (
                                !$product
                                instanceof Product
                            ) {
                                continue;
                            }

                            $canSell =
                                $availability === null
                                || !empty(
                                    $availability[
                                        'can_sell'
                                    ]
                                );

                            $availableQuantity =
                                $availability[
                                    'available_quantity'
                                ]
                                ?? null;

                            $stock =
                                $availability[
                                    'stock'
                                ]
                                ?? null;
                            ?>

                            <article
                                class="pos-product-card <?= !$canSell
                                    ? 'pos-out-of-stock'
                                    : ''
                                ?>"
                            >
                                <div>
                                    <h3>
                                        <?= e(
                                            $product->name()
                                        ) ?>
                                    </h3>

                                    <div
                                        class="pos-product-meta"
                                    >
                                        Code:
                                        <?= e(
                                            $product->code()
                                        ) ?>

                                        <?php if (
                                            $product->sku()
                                            !== null
                                        ): ?>
                                            <br>
                                            SKU:
                                            <?= e(
                                                $product->sku()
                                            ) ?>
                                        <?php endif; ?>

                                        <?php if (
                                            $product->barcode()
                                            !== null
                                        ): ?>
                                            <br>
                                            Barcode:
                                            <?= e(
                                                $product
                                                    ->barcode()
                                            ) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div
                                    class="pos-product-stock"
                                >
                                    <?php if (
                                        $availableQuantity
                                        !== null
                                    ): ?>
                                        Stock:
                                        <strong>
                                            <?= e(
                                                number_format(
                                                    (float)
                                                        $availableQuantity,
                                                    4
                                                )
                                            ) ?>
                                        </strong>
                                    <?php elseif (
                                        !$product
                                            ->tracksStock()
                                        || $product
                                            ->isServiceProduct()
                                    ): ?>
                                        Non-stock item
                                    <?php elseif (
                                        $stock
                                        instanceof WarehouseStock
                                    ): ?>
                                        Stock:
                                        <?= e(
                                            number_format(
                                                $stock
                                                    ->availableQuantity(),
                                                4
                                            )
                                        ) ?>
                                    <?php else: ?>
                                        Stock unavailable
                                    <?php endif; ?>
                                </div>

                                <div
                                    class="pos-product-price"
                                >
                                    <?= e(
                                        number_format(
                                            $product
                                                ->salePrice(),
                                            2
                                        )
                                    ) ?>
                                </div>

                                <div
                                    class="pos-product-actions"
                                >
                                    <?php if ($canSell): ?>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url(
                                                    '/pos/add-product'
                                                )
                                            ) ?>"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="sale_id"
                                                value="<?= (int)
                                                    $sale->id()
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= (int)
                                                    $product
                                                        ->id()
                                                ?>"
                                            >

                                            <input
                                                name="quantity"
                                                type="number"
                                                min="0.0001"
                                                step="0.0001"
                                                value="1"
                                                aria-label="Quantity"
                                            >

                                            <button
                                                class="primary-button compact"
                                                type="submit"
                                            >
                                                Add
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button
                                            class="secondary-button"
                                            type="button"
                                            disabled
                                        >
                                            Out of stock
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>
            </section>

            <aside
                class="pos-panel pos-cart-panel"
            >
                <div class="pos-panel-heading">
                    <div>
                        <h2>
                            Cart
                        </h2>

                        <p class="muted">
                            <?= count($items) ?>
                            item row(s)
                        </p>
                    </div>
                </div>

                <?php if ($items === []): ?>

                    <div class="pos-empty-cart">
                        Scan or add a product
                        to begin.
                    </div>

                <?php else: ?>

                    <div class="table-scroll">
                        <table class="pos-cart-table">
                            <thead>
                            <tr>
                                <th>
                                    Item
                                </th>

                                <th>
                                    Qty
                                </th>

                                <th>
                                    Price
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                </th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach (
                                $items as $item
                            ): ?>

                                <?php if (
                                    !$item
                                    instanceof SaleItem
                                ) {
                                    continue;
                                } ?>

                                <?php
                                $productName =
                                    'Product #'
                                    . $item
                                        ->productId();

                                foreach (
                                    $products
                                    as $productEntry
                                ) {
                                    $candidate =
                                        $productEntry[
                                            'product'
                                        ]
                                        ?? null;

                                    if (
                                        $candidate
                                        instanceof Product
                                        && (int)
                                            $candidate
                                                ->id()
                                            ===
                                            $item
                                                ->productId()
                                    ) {
                                        $productName =
                                            $candidate
                                                ->name();

                                        break;
                                    }
                                }
                                ?>

                                <tr>
                                    <td
                                        class="pos-cart-name"
                                    >
                                        <strong>
                                            <?= e(
                                                $productName
                                            ) ?>
                                        </strong>

                                        <small>
                                            #
                                            <?= (int)
                                                $item
                                                    ->productId()
                                            ?>
                                        </small>
                                    </td>

                                    <td>
                                        <form
                                            class="pos-qty-form"
                                            method="post"
                                            action="<?= e(
                                                app_url(
                                                    '/pos/item/update'
                                                )
                                            ) ?>"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="sale_id"
                                                value="<?= (int)
                                                    $sale->id()
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int)
                                                    $item->id()
                                                ?>"
                                            >

                                            <input
                                                name="quantity"
                                                type="number"
                                                min="0.0001"
                                                step="0.0001"
                                                value="<?= e(
                                                    number_format(
                                                        $item
                                                            ->quantity(),
                                                        4,
                                                        '.',
                                                        ''
                                                    )
                                                ) ?>"
                                            >

                                            <button
                                                class="table-button"
                                                type="submit"
                                            >
                                                ✓
                                            </button>
                                        </form>
                                    </td>

                                    <td>
                                        <?= e(
                                            number_format(
                                                $item
                                                    ->unitPrice(),
                                                2
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e(
                                                number_format(
                                                    $item
                                                        ->lineTotal(),
                                                    2
                                                )
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url(
                                                    '/pos/item/remove'
                                                )
                                            ) ?>"
                                            onsubmit="return confirm(
                                                'Remove this item?'
                                            );"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="sale_id"
                                                value="<?= (int)
                                                    $sale->id()
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int)
                                                    $item->id()
                                                ?>"
                                            >

                                            <button
                                                class="secondary-button"
                                                type="submit"
                                            >
                                                ×
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

                <div class="pos-cart-summary">

                    <div class="pos-summary-row">
                        <span>
                            Subtotal
                        </span>

                        <strong>
                            <?= e(
                                number_format(
                                    $sale->subtotal(),
                                    2
                                )
                            ) ?>
                        </strong>
                    </div>

                    <?php if (
                        $sale->discountAmount()
                        > 0.00005
                    ): ?>
                        <div class="pos-summary-row">
                            <span>
                                Discount
                            </span>

                            <strong>
                                -
                                <?= e(
                                    number_format(
                                        $sale
                                            ->discountAmount(),
                                        2
                                    )
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $sale->taxAmount()
                        > 0.00005
                    ): ?>
                        <div class="pos-summary-row">
                            <span>
                                Tax
                            </span>

                            <strong>
                                <?= e(
                                    number_format(
                                        $sale
                                            ->taxAmount(),
                                        2
                                    )
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $sale->shippingAmount()
                        > 0.00005
                    ): ?>
                        <div class="pos-summary-row">
                            <span>
                                Shipping
                            </span>

                            <strong>
                                <?= e(
                                    number_format(
                                        $sale
                                            ->shippingAmount(),
                                        2
                                    )
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $sale->otherAmount()
                        > 0.00005
                    ): ?>
                        <div class="pos-summary-row">
                            <span>
                                Other
                            </span>

                            <strong>
                                <?= e(
                                    number_format(
                                        $sale
                                            ->otherAmount(),
                                        2
                                    )
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <div
                        class="pos-summary-row pos-summary-total"
                    >
                        <span>
                            Total
                        </span>

                        <span>
                            <?= e(
                                number_format(
                                    $grandTotal,
                                    2
                                )
                            ) ?>
                        </span>
                    </div>

                    <div class="pos-summary-row">
                        <span>
                            Balance
                        </span>

                        <strong>
                            <?= e(
                                number_format(
                                    $balanceDue,
                                    2
                                )
                            ) ?>
                        </strong>
                    </div>
                </div>

                <form
                    id="pos-checkout-form"
                    class="pos-payment-box"
                    method="post"
                    action="<?= e(
                        app_url(
                            '/pos/checkout'
                        )
                    ) ?>"
                    onsubmit="return confirm(
                        'Complete this sale?'
                    );"
                >
                    <?= Csrf::input() ?>

                    <input
                        type="hidden"
                        name="sale_id"
                        value="<?= (int)
                            $sale->id()
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="payment_date"
                        value="<?= e(
                            date('Y-m-d')
                        ) ?>"
                    >

                    <div class="pos-payment-grid">

                        <div class="field">
                            <label for="payment_method">
                                Payment method
                            </label>

                            <select
                                id="payment_method"
                                name="payment_method"
                            >
                                <?php foreach (
                                    $paymentMethodOptions
                                    as $value => $label
                                ): ?>
                                    <option
                                        value="<?= e(
                                            $value
                                        ) ?>"
                                    >
                                        <?= e(
                                            $label
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div
                            class="field"
                            id="cash-received-field"
                        >
                            <label for="cash_received">
                                Cash received
                            </label>

                            <input
                                id="cash_received"
                                name="cash_received"
                                type="number"
                                min="0"
                                step="0.0001"
                                value="<?= e(
                                    number_format(
                                        $grandTotal,
                                        4,
                                        '.',
                                        ''
                                    )
                                ) ?>"
                            >
                        </div>

                        <div
                            class="field"
                            id="payment-amount-field"
                            hidden
                        >
                            <label for="payment_amount">
                                Payment amount
                            </label>

                            <input
                                id="payment_amount"
                                name="payment_amount"
                                type="number"
                                min="0"
                                step="0.0001"
                                max="<?= e(
                                    number_format(
                                        $grandTotal,
                                        4,
                                        '.',
                                        ''
                                    )
                                ) ?>"
                                value="<?= e(
                                    number_format(
                                        $grandTotal,
                                        4,
                                        '.',
                                        ''
                                    )
                                ) ?>"
                            >
                        </div>

                        <div class="field full">
                            <label for="reference_number">
                                Reference
                            </label>

                            <input
                                id="reference_number"
                                name="reference_number"
                                maxlength="190"
                                placeholder="Optional"
                            >
                        </div>
                    </div>

                    <div
                        class="pos-change-box"
                        id="change-box"
                    >
                        <span>
                            Change
                        </span>

                        <span
                            class="pos-change-value"
                            id="change-value"
                        >
                            0.00
                        </span>
                    </div>

                    <button
                        class="primary-button pos-checkout-button"
                        type="submit"
                        <?= $items === []
                            ? 'disabled'
                            : ''
                        ?>
                    >
                        Complete Sale
                    </button>
                </form>

            </aside>
        </div>

    <?php endif; ?>

</main>

<?php if ($sale instanceof Sale): ?>
<script>
(function () {
    const paymentMethod =
        document.getElementById(
            'payment_method'
        );

    const cashField =
        document.getElementById(
            'cash-received-field'
        );

    const amountField =
        document.getElementById(
            'payment-amount-field'
        );

    const cashInput =
        document.getElementById(
            'cash_received'
        );

    const paymentInput =
        document.getElementById(
            'payment_amount'
        );

    const changeBox =
        document.getElementById(
            'change-box'
        );

    const changeValue =
        document.getElementById(
            'change-value'
        );

    const total =
        <?= json_encode(
            round(
                $grandTotal,
                4
            )
        ) ?>;

    function calculateChange() {
        if (
            !paymentMethod
            || paymentMethod.value !== 'cash'
        ) {
            if (changeValue) {
                changeValue.textContent =
                    '0.00';
            }

            return;
        }

        const received =
            parseFloat(
                cashInput?.value
                || '0'
            );

        const change =
            Math.max(
                0,
                received - total
            );

        if (changeValue) {
            changeValue.textContent =
                change.toFixed(2);
        }
    }

    function switchPaymentFields() {
        if (!paymentMethod) {
            return;
        }

        const isCash =
            paymentMethod.value
            === 'cash';

        if (cashField) {
            cashField.hidden =
                !isCash;
        }

        if (amountField) {
            amountField.hidden =
                isCash;
        }

        if (changeBox) {
            changeBox.hidden =
                !isCash;
        }

        if (
            !isCash
            && paymentInput
            && (
                paymentInput.value === ''
                || parseFloat(
                    paymentInput.value
                ) <= 0
            )
        ) {
            paymentInput.value =
                total.toFixed(4);
        }

        calculateChange();
    }

    paymentMethod?.addEventListener(
        'change',
        switchPaymentFields
    );

    cashInput?.addEventListener(
        'input',
        calculateChange
    );

    switchPaymentFields();

    /*
     * Barcode scanners normally behave
     * like keyboards followed by Enter.
     * Keep focus on barcode field after
     * the page loads.
     */
    const barcodeInput =
        document.getElementById(
            'pos-barcode'
        );

    if (barcodeInput) {
        window.setTimeout(
            function () {
                barcodeInput.focus();
                barcodeInput.select();
            },
            50
        );
    }
})();
</script>
<?php endif; ?>

</body>
</html>