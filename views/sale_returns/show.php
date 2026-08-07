<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnRefund;
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

$canEdit =
    $return->isDraft()
    && in_array(
        'sale_returns.create',
        $permissions,
        true
    );

$canComplete =
    $return->isDraft()
    && in_array(
        'sale_returns.complete',
        $permissions,
        true
    );

$canCancel =
    $return->isDraft()
    && in_array(
        'sale_returns.cancel',
        $permissions,
        true
    );

$canDelete =
    $return->isDraft()
    && in_array(
        'sale_returns.delete',
        $permissions,
        true
    );

$canCreateRefund =
    $return->isCompleted()
    && !$return->isDeleted()
    && in_array(
        'sale_returns.refunds_create',
        $permissions,
        true
    )
    && (float) (
        $refundSummary['refund_balance']
        ?? 0
    ) > 0.00005;

$canViewRefunds =
    in_array(
        'sale_returns.refunds_view',
        $permissions,
        true
    );

$canDeleteRefund =
    in_array(
        'sale_returns.refunds_delete',
        $permissions,
        true
    );

$statusLabel =
    ucfirst(
        $return->status()
    );

$refundStatusLabel =
    ucfirst(
        $return->refundStatus()
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
        <?= e($return->returnNumber()) ?>
        — Sale Return
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
                Return against sale
                <?= e(
                    $sale->saleNumber()
                ) ?>.
            </p>
        </div>

        <div class="page-actions">

            <?php if ($canEdit): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/sale-returns/edit?id='
                            . $return->id()
                        )
                    ) ?>"
                >
                    Edit Return
                </a>
            <?php endif; ?>

            <?php if ($canCreateRefund): ?>
                <a
                    class="primary-link"
                    href="<?= e(
                        app_url(
                            '/sale-returns/refund?sale_return_id='
                            . $return->id()
                        )
                    ) ?>"
                >
                    Add Refund
                </a>
            <?php endif; ?>

            <?php if ($canViewRefunds): ?>
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/sale-returns/refund-history?sale_return_id='
                            . $return->id()
                        )
                    ) ?>"
                >
                    Refund History
                </a>
            <?php endif; ?>

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
                    Return Information
                </h2>

                <p class="muted">
                    Return status and original sale information.
                </p>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <small>
                    Return number
                </small>

                <strong>
                    <?= e(
                        $return->returnNumber()
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Return date
                </small>

                <strong>
                    <?= e(
                        $return
                            ->returnDate()
                            ->format(
                                'Y-m-d'
                            )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Status
                </small>

                <strong>
                    <?= e(
                        $statusLabel
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Refund status
                </small>

                <strong>
                    <?= e(
                        $refundStatusLabel
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Original sale
                </small>

                <strong>
                    <a
                        class="table-link"
                        href="<?= e(
                            app_url(
                                '/sales/show?id='
                                . $sale->id()
                            )
                        ) ?>"
                    >
                        <?= e(
                            $sale->saleNumber()
                        ) ?>
                    </a>
                </strong>
            </div>

            <div>
                <small>
                    Original sale date
                </small>

                <strong>
                    <?= e(
                        $sale
                            ->saleDate()
                            ->format(
                                'Y-m-d'
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
                    <?= $return
                        ->customerId()
                        !== null
                        ? (int) $return
                            ->customerId()
                        : 'Walk-in / None'
                    ?>
                </strong>
            </div>
        </div>

        <?php if (
            $return->reason()
            !== null
            || $return->notes()
            !== null
        ): ?>

            <div
                class="detail-grid"
                style="margin-top:20px;"
            >
                <?php if (
                    $return->reason()
                    !== null
                ): ?>
                    <div>
                        <small>
                            Return reason
                        </small>

                        <strong>
                            <?= e(
                                $return->reason()
                            ) ?>
                        </strong>
                    </div>
                <?php endif; ?>

                <?php if (
                    $return->notes()
                    !== null
                ): ?>
                    <div>
                        <small>
                            Notes
                        </small>

                        <strong>
                            <?= nl2br(
                                e(
                                    $return
                                        ->notes()
                                )
                            ) ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </section>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Return Totals
                </h2>

                <p class="muted">
                    Financial value of the returned items.
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

            <div>
                <small>
                    Refunded
                </small>

                <strong>
                    <?= e(
                        number_format(
                            (float) (
                                $refundSummary[
                                    'refunded_amount'
                                ]
                                ?? 0
                            ),
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
                            (float) (
                                $refundSummary[
                                    'refund_balance'
                                ]
                                ?? 0
                            ),
                            2
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="table-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Returned Items
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
                        Unit
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
                </tr>
                </thead>

                <tbody>
                <?php if ($items === []): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="empty-cell"
                        >
                            No returned items.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach (
                        $items as $item
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
                                #
                                <?= (int)
                                    $item
                                        ->unitId()
                                ?>
                            </td>

                            <td>
                                <?= e(
                                    number_format(
                                        $item
                                            ->quantity(),
                                        4
                                    )
                                ) ?>
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
                                <?= e(
                                    $item->reason()
                                    ?? '-'
                                ) ?>

                                <?php if (
                                    $item->notes()
                                    !== null
                                ): ?>
                                    <small>
                                        <?= e(
                                            $item
                                                ->notes()
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (
        $return->isCompleted()
        || $refunds !== []
    ): ?>
        <section class="table-card">
            <div class="table-card-heading">
                <div>
                    <h2>
                        Refunds
                    </h2>

                    <p class="muted">
                        <?= count($refunds) ?>
                        refund record(s)
                    </p>
                </div>

                <div class="page-actions">
                    <?php if ($canCreateRefund): ?>
                        <a
                            class="primary-link"
                            href="<?= e(
                                app_url(
                                    '/sale-returns/refund?sale_return_id='
                                    . $return
                                        ->id()
                                )
                            ) ?>"
                        >
                            Add Refund
                        </a>
                    <?php endif; ?>

                    <?php if ($canViewRefunds): ?>
                        <a
                            class="secondary-link"
                            href="<?= e(
                                app_url(
                                    '/sale-returns/refund-history?sale_return_id='
                                    . $return
                                        ->id()
                                )
                            ) ?>"
                        >
                            Full History
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>
                            Refund
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Reference
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Notes
                        </th>

                        <th>
                            Actions
                        </th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if ($refunds === []): ?>
                        <tr>
                            <td
                                colspan="7"
                                class="empty-cell"
                            >
                                No refunds recorded.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach (
                            $refunds
                            as $refund
                        ): ?>

                            <?php if (
                                !$refund
                                instanceof SaleReturnRefund
                            ) {
                                continue;
                            } ?>

                            <tr>
                                <td>
                                    <?= e(
                                        $refund
                                            ->refundNumber()
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $refund
                                            ->refundDate()
                                            ->format(
                                                'Y-m-d'
                                            )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $refund
                                            ->refundMethodLabel()
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $refund
                                            ->referenceNumber()
                                        ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e(
                                            number_format(
                                                $refund
                                                    ->amount(),
                                                2
                                            )
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e(
                                        $refund->notes()
                                        ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?php if (
                                        $canDeleteRefund
                                        && !$refund
                                            ->isDeleted()
                                    ): ?>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url(
                                                    '/sale-returns/refunds/delete'
                                                )
                                            ) ?>"
                                            onsubmit="return confirm(
                                                'Delete this refund?'
                                            );"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="refund_id"
                                                value="<?= (int)
                                                    $refund
                                                        ->id()
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="sale_return_id"
                                                value="<?= (int)
                                                    $return
                                                        ->id()
                                                ?>"
                                            >

                                            <button
                                                class="secondary-button"
                                                type="submit"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (
        $return->isCompleted()
    ): ?>
        <section class="form-card">
            <div class="table-card-heading">
                <div>
                    <h2>
                        Completion
                    </h2>

                    <p class="muted">
                        Inventory was returned when this
                        return was completed.
                    </p>
                </div>
            </div>

            <div class="detail-grid">
                <div>
                    <small>
                        Completed at
                    </small>

                    <strong>
                        <?= $return
                            ->completedAt()
                            !== null
                            ? e(
                                $return
                                    ->completedAt()
                                    ->format(
                                        'Y-m-d H:i:s'
                                    )
                            )
                            : '-'
                        ?>
                    </strong>
                </div>

                <div>
                    <small>
                        Completed by
                    </small>

                    <strong>
                        <?= $return
                            ->completedBy()
                            !== null
                            ? (int)
                                $return
                                    ->completedBy()
                            : '-'
                        ?>
                    </strong>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (
        $return->isCancelled()
    ): ?>
        <section class="form-card">
            <div class="table-card-heading">
                <div>
                    <h2>
                        Cancellation
                    </h2>

                    <p class="muted">
                        This sale return was cancelled.
                    </p>
                </div>
            </div>

            <div class="detail-grid">
                <div>
                    <small>
                        Cancelled at
                    </small>

                    <strong>
                        <?= $return
                            ->cancelledAt()
                            !== null
                            ? e(
                                $return
                                    ->cancelledAt()
                                    ->format(
                                        'Y-m-d H:i:s'
                                    )
                            )
                            : '-'
                        ?>
                    </strong>
                </div>

                <div>
                    <small>
                        Cancelled by
                    </small>

                    <strong>
                        <?= $return
                            ->cancelledBy()
                            !== null
                            ? (int)
                                $return
                                    ->cancelledBy()
                            : '-'
                        ?>
                    </strong>
                </div>

                <div>
                    <small>
                        Reason
                    </small>

                    <strong>
                        <?= e(
                            $return
                                ->cancellationReason()
                            ?? '-'
                        ) ?>
                    </strong>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($return->isDraft()): ?>
        <section class="form-card">
            <div class="table-card-heading">
                <div>
                    <h2>
                        Draft Actions
                    </h2>

                    <p class="muted">
                        Complete, cancel or delete this
                        draft return.
                    </p>
                </div>
            </div>

            <div class="form-actions">

                <?php if ($canEdit): ?>
                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url(
                                '/sale-returns/edit?id='
                                . $return
                                    ->id()
                            )
                        ) ?>"
                    >
                        Edit Return
                    </a>
                <?php endif; ?>

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
                            'Complete this return and put stock back into inventory?'
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
                            value="Cancelled from sale return detail"
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
    <?php endif; ?>

</main>

</body>
</html>