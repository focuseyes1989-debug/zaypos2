<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\SaleReturn;
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

$canCreate =
    $return->isCompleted()
    && !$return->isDeleted()
    && in_array(
        'sale_returns.refunds_create',
        $permissions,
        true
    )
    && (float) (
        $summary['refund_balance']
        ?? 0
    ) > 0.00005;

$canDelete =
    in_array(
        'sale_returns.refunds_delete',
        $permissions,
        true
    );

$canRestore =
    in_array(
        'sale_returns.refunds_restore',
        $permissions,
        true
    );

$grandTotal =
    (float) (
        $summary['grand_total']
        ?? $return->grandTotal()
    );

$refundedAmount =
    (float) (
        $summary['refunded_amount']
        ?? 0
    );

$refundBalance =
    (float) (
        $summary['refund_balance']
        ?? 0
    );

$refundStatus =
    (string) (
        $summary['refund_status']
        ?? 'none'
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
        Refund History — ZAY POS 2.0
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
                SALE RETURN REFUNDS
            </p>

            <h1>
                Refund History
            </h1>

            <p class="muted">
                Refund ledger for
                <?= e(
                    $return->returnNumber()
                ) ?>.
            </p>
        </div>

        <div class="page-actions">
            <?php if ($canCreate): ?>
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

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sale-returns/show?id='
                        . $return->id()
                    )
                ) ?>"
            >
                Return Details
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
                    Refund Summary
                </h2>

                <p class="muted">
                    Active refund amounts only.
                </p>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <small>
                    Return
                </small>

                <strong>
                    <?= e(
                        $return->returnNumber()
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
                    Return total
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $grandTotal,
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
                            $refundedAmount,
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Balance
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $refundBalance,
                            2
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
                        ucfirst(
                            $refundStatus
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
                    Refund Ledger
                </h2>

                <p class="muted">
                    <?= count($refunds) ?>
                    record(s), including deleted records.
                </p>
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
                        State
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
                            colspan="8"
                            class="empty-cell"
                        >
                            No refund records found.
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
                                <strong>
                                    <?= e(
                                        $refund
                                            ->refundNumber()
                                    ) ?>
                                </strong>
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
                                    $refund
                                        ->isDeleted()
                                ): ?>
                                    <span
                                        class="status-badge"
                                    >
                                        Deleted
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="status-badge"
                                    >
                                        Active
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="actions-column">
                                <?php if (
                                    $refund
                                        ->isDeleted()
                                ): ?>

                                    <?php if (
                                        $canRestore
                                    ): ?>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url(
                                                    '/sale-returns/refunds/restore'
                                                )
                                            ) ?>"
                                            onsubmit="return confirm(
                                                'Restore this refund?'
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

                                            <button
                                                class="table-button"
                                                type="submit"
                                            >
                                                Restore
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>

                                <?php else: ?>

                                    <?php if (
                                        $canDelete
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

                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if (
                            $refund->isDeleted()
                        ): ?>
                            <tr>
                                <td
                                    colspan="8"
                                >
                                    <small class="muted">
                                        Deleted by user #
                                        <?= $refund
                                            ->deletedBy()
                                            !== null
                                            ? (int)
                                                $refund
                                                    ->deletedBy()
                                            : '-'
                                        ?>

                                        <?php if (
                                            $refund
                                                ->deletedAt()
                                            !== null
                                        ): ?>
                                            at
                                            <?= e(
                                                $refund
                                                    ->deletedAt()
                                                    ->format(
                                                        'Y-m-d H:i:s'
                                                    )
                                            ) ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>