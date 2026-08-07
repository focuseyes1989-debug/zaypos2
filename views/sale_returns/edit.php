<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Security\Csrf;

if (!$return instanceof SaleReturn) {
    throw new RuntimeException(
        'Sale return is required.'
    );
}

if (!$sale instanceof Sale) {
    throw new RuntimeException(
        'Original sale is required.'
    );
}

$permissions =
    $currentUser['permissions'] ?? [];

$canComplete =
    in_array(
        'sale_returns.complete',
        $permissions,
        true
    );

$canCancel =
    in_array(
        'sale_returns.cancel',
        $permissions,
        true
    );

$canDelete =
    in_array(
        'sale_returns.delete',
        $permissions,
        true
    );
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
        Edit Sale Return — ZAY POS 2.0
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url(
                '/assets/css/app.css'
            )
        ) ?>"
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
                SALES RETURN
            </p>

            <h1>
                <?= e(
                    $return->returnNumber()
                ) ?>
            </h1>

            <p class="muted">
                Draft return for sale
                <?= e(
                    $sale->saleNumber()
                ) ?>.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sale-returns/show?id='
                        . $return->id()
                    )
                ) ?>"
            >
                View
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sales/show?id='
                        . $sale->id()
                    )
                ) ?>"
            >
                Original Sale
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sale-returns'
                    )
                ) ?>"
            >
                Sale Returns
            </a>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="success-box">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Return Summary
                </h2>

                <p class="muted">
                    Original sale, return date
                    and current totals.
                </p>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <small>
                    Original sale
                </small>

                <strong>
                    <?= e(
                        $sale->saleNumber()
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Return status
                </small>

                <strong>
                    <?= e(
                        ucfirst(
                            $return->status()
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Warehouse ID
                </small>

                <strong>
                    <?= (int)
                        $return
                            ->warehouseId()
                    ?>
                </strong>
            </div>

            <div>
                <small>
                    Customer ID
                </small>

                <strong>
                    <?= $return->customerId()
                        !== null
                        ? (int)
                            $return
                                ->customerId()
                        : 'Walk-in / None'
                    ?>
                </strong>
            </div>

            <div>
                <small>
                    Grand total
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $return
                                ->grandTotal(),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Refund balance
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $return
                                ->refundBalance(),
                            2
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Return Details
                </h2>

                <p class="muted">
                    Update the draft return header.
                </p>
            </div>
        </div>

        <form
            method="post"
            action="<?= e(
                app_url(
                    '/sale-returns/update'
                )
            ) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="sale_return_id"
                value="<?= (int)
                    $return->id()
                ?>"
            >

            <div class="form-grid">
                <div class="field">
                    <label>
                        Return number
                    </label>

                    <input
                        type="text"
                        value="<?= e(
                            $return
                                ->returnNumber()
                        ) ?>"
                        disabled
                    >
                </div>

                <div class="field">
                    <label for="return_date">
                        Return date
                    </label>

                    <input
                        id="return_date"
                        name="return_date"
                        type="date"
                        required
                        value="<?= e(
                            $input[
                                'return_date'
                            ]
                            ?? $return
                                ->returnDate()
                                ->format(
                                    'Y-m-d'
                                )
                        ) ?>"
                    >
                </div>

                <div class="field full">
                    <label for="reason">
                        Return reason
                    </label>

                    <input
                        id="reason"
                        name="reason"
                        type="text"
                        maxlength="500"
                        value="<?= e(
                            $input['reason']
                            ?? $return
                                ->reason()
                            ?? ''
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
                        ?? $return
                            ->notes()
                        ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button
                    class="primary-button"
                    type="submit"
                >
                    Save Return
                </button>
            </div>
        </form>
    </section>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Add Return Item
                </h2>

                <p class="muted">
                    Choose an item from the original
                    completed sale.
                </p>
            </div>
        </div>

        <?php if ($saleItems === []): ?>
            <div class="error-box">
                Original sale has no items
                available for return.
            </div>
        <?php else: ?>

            <form
                method="post"
                action="<?= e(
                    app_url(
                        '/sale-returns/items/add'
                    )
                ) ?>"
            >
                <?= Csrf::input() ?>

                <input
                    type="hidden"
                    name="sale_return_id"
                    value="<?= (int)
                        $return->id()
                    ?>"
                >

                <div class="form-grid">
                    <div class="field full">
                        <label for="sale_item_id">
                            Original sale item
                        </label>

                        <select
                            id="sale_item_id"
                            name="sale_item_id"
                            required
                        >
                            <option value="">
                                Select sale item
                            </option>

                            <?php foreach (
                                $saleItems
                                as $saleItem
                            ): ?>

                                <?php if (
                                    !$saleItem
                                    instanceof SaleItem
                                ) {
                                    continue;
                                } ?>

                                <option
                                    value="<?= (int)
                                        $saleItem
                                            ->id()
                                    ?>"
                                    <?= (
                                        (string) (
                                            $input[
                                                'sale_item_id'
                                            ]
                                            ?? ''
                                        )
                                        ===
                                        (string)
                                            $saleItem
                                                ->id()
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Item #
                                    <?= (int)
                                        $saleItem
                                            ->id()
                                    ?>

                                    —
                                    Product #
                                    <?= (int)
                                        $saleItem
                                            ->productId()
                                    ?>

                                    —
                                    Sold:
                                    <?= e(
                                        number_format(
                                            $saleItem
                                                ->quantity(),
                                            4
                                        )
                                    ) ?>

                                    —
                                    Fulfilled:
                                    <?= e(
                                        number_format(
                                            $saleItem
                                                ->fulfilledQuantity(),
                                            4
                                        )
                                    ) ?>

                                    —
                                    Price:
                                    <?= e(
                                        number_format(
                                            $saleItem
                                                ->unitPrice(),
                                            2
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small>
                            Return quantity is validated
                            against the original fulfilled
                            quantity and previous completed
                            returns.
                        </small>
                    </div>

                    <div class="field">
                        <label for="quantity">
                            Return quantity
                        </label>

                        <input
                            id="quantity"
                            name="quantity"
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            required
                            value="<?= e(
                                $input[
                                    'quantity'
                                ] ?? '1'
                            ) ?>"
                        >
                    </div>

                    <div class="field">
                        <label for="item_reason">
                            Item reason
                        </label>

                        <input
                            id="item_reason"
                            name="reason"
                            type="text"
                            maxlength="500"
                            value="<?= e(
                                $input[
                                    'reason'
                                ] ?? ''
                            ) ?>"
                            placeholder="Damaged, wrong item..."
                        >
                    </div>

                    <div class="field full">
                        <label for="item_notes">
                            Item notes
                        </label>

                        <textarea
                            id="item_notes"
                            name="notes"
                            rows="3"
                            maxlength="5000"
                        ><?= e(
                            $input[
                                'notes'
                            ] ?? ''
                        ) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Add Return Item
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </section>

    <section class="table-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Return Items
                </h2>

                <p class="muted">
                    <?= count($items) ?>
                    item(s)
                </p>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>
                        Original Item
                    </th>

                    <th>
                        Product
                    </th>

                    <th>
                        Quantity
                    </th>

                    <th>
                        Unit Price
                    </th>

                    <th>
                        Discount
                    </th>

                    <th>
                        Tax
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Reason
                    </th>

                    <th>
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if ($items === []): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="empty-cell"
                        >
                            No return items added yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $items
                        as $item
                    ): ?>

                        <?php if (
                            !$item
                            instanceof SaleReturnItem
                        ) {
                            continue;
                        } ?>

                        <tr>
                            <td>
                                #
                                <?= (int)
                                    $item
                                        ->saleItemId()
                                ?>
                            </td>

                            <td>
                                #
                                <?= (int)
                                    $item
                                        ->productId()
                                ?>
                            </td>

                            <td>
                                <form
                                    method="post"
                                    action="<?= e(
                                        app_url(
                                            '/sale-returns/items/update'
                                        )
                                    ) ?>"
                                >
                                    <?= Csrf::input() ?>

                                    <input
                                        type="hidden"
                                        name="sale_return_id"
                                        value="<?= (int)
                                            $return
                                                ->id()
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
                                        type="hidden"
                                        name="sale_item_id"
                                        value="<?= (int)
                                            $item
                                                ->saleItemId()
                                        ?>"
                                    >

                                    <input
                                        name="quantity"
                                        type="number"
                                        min="0.0001"
                                        step="0.0001"
                                        required
                                        value="<?= e(
                                            number_format(
                                                $item
                                                    ->quantity(),
                                                4,
                                                '.',
                                                ''
                                            )
                                        ) ?>"
                                        style="min-width:110px;"
                                    >
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
                                <?= e(
                                    number_format(
                                        $item
                                            ->discountAmount(),
                                        2
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    number_format(
                                        $item
                                            ->taxAmount(),
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
                                <input
                                    name="reason"
                                    type="text"
                                    maxlength="500"
                                    value="<?= e(
                                        $item
                                            ->reason()
                                        ?? ''
                                    ) ?>"
                                    style="min-width:160px;"
                                >

                                <input
                                    name="notes"
                                    type="hidden"
                                    value="<?= e(
                                        $item
                                            ->notes()
                                        ?? ''
                                    ) ?>"
                                >
                            </td>

                            <td class="actions-column">
                                <button
                                    class="table-button"
                                    type="submit"
                                >
                                    Update
                                </button>
                                </form>

                                <form
                                    method="post"
                                    action="<?= e(
                                        app_url(
                                            '/sale-returns/items/delete'
                                        )
                                    ) ?>"
                                    onsubmit="return confirm(
                                        'Delete this return item?'
                                    );"
                                >
                                    <?= Csrf::input() ?>

                                    <input
                                        type="hidden"
                                        name="sale_return_id"
                                        value="<?= (int)
                                            $return
                                                ->id()
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
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Draft Actions
                </h2>

                <p class="muted">
                    Completing the return will put
                    tracked stock back into inventory.
                </p>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <small>
                    Subtotal
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $return
                                ->subtotal(),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Discount
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $return
                                ->discountAmount(),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Tax
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $return
                                ->taxAmount(),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Grand total
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $return
                                ->grandTotal(),
                            2
                        )
                    ) ?>
                </strong>
            </div>
        </div>

        <div class="form-actions">

            <?php if (
                $canComplete
                && $items !== []
            ): ?>
                <form
                    method="post"
                    action="<?= e(
                        app_url(
                            '/sale-returns/complete'
                        )
                    ) ?>"
                    onsubmit="return confirm(
                        'Complete this sale return and return stock to inventory?'
                    );"
                >
                    <?= Csrf::input() ?>

                    <input
                        type="hidden"
                        name="sale_return_id"
                        value="<?= (int)
                            $return->id()
                        ?>"
                    >

                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Complete Return
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canCancel): ?>
                <form
                    method="post"
                    action="<?= e(
                        app_url(
                            '/sale-returns/cancel'
                        )
                    ) ?>"
                    onsubmit="return confirm(
                        'Cancel this sale return?'
                    );"
                >
                    <?= Csrf::input() ?>

                    <input
                        type="hidden"
                        name="sale_return_id"
                        value="<?= (int)
                            $return->id()
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="reason"
                        value="Cancelled from return edit screen"
                    >

                    <button
                        class="secondary-button"
                        type="submit"
                    >
                        Cancel Return
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canDelete): ?>
                <form
                    method="post"
                    action="<?= e(
                        app_url(
                            '/sale-returns/delete'
                        )
                    ) ?>"
                    onsubmit="return confirm(
                        'Delete this draft sale return?'
                    );"
                >
                    <?= Csrf::input() ?>

                    <input
                        type="hidden"
                        name="sale_return_id"
                        value="<?= (int)
                            $return->id()
                        ?>"
                    >

                    <button
                        class="secondary-button"
                        type="submit"
                    >
                        Delete Draft
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </section>

</main>

</body>
</html>