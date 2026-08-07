<?php

declare(strict_types=1);

use App\Models\PurchasePayment;
use App\Security\Csrf;

$permissions = $currentUser['permissions'] ?? [];

$supplierNames = [];

foreach ($supplierOptions as $option) {
    $supplierNames[(int) $option['id']] =
        (string) $option['name'];
}

$queryForPage = static function (
    int $targetPage
) use ($filters): string {
    return http_build_query([
        'search' =>
            $filters['search'] ?? '',

        'payment_method' =>
            $filters['payment_method'] ?? '',

        'purchase_id' =>
            $filters['purchase_id'] ?? '',

        'supplier_id' =>
            $filters['supplier_id'] ?? '',

        'deleted' =>
            !empty($filters['deleted'])
                ? '1'
                : '',

        'page' =>
            $targetPage,
    ]);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Purchase Payments — ZAY POS 2.0</title>

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
                PURCHASE PAYMENTS
            </p>

            <h1>
                Supplier Payment History
            </h1>

            <p class="muted">
                Track payments made against received purchases.
            </p>
        </div>

        <div class="page-actions">
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

    <form
        class="filter-bar"
        method="get"
        action="<?= e(app_url('/purchase-payments')) ?>"
    >
        <input
            type="search"
            name="search"
            maxlength="190"
            placeholder="Payment number, reference..."
            value="<?= e($filters['search'] ?? '') ?>"
        >

        <select name="payment_method">
            <option value="">
                All payment methods
            </option>

            <?php foreach (
                $paymentMethodOptions as $value => $label
            ): ?>
                <option
                    value="<?= e($value) ?>"
                    <?= ($filters['payment_method'] ?? '') === $value
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="supplier_id">
            <option value="">
                All suppliers
            </option>

            <?php foreach ($supplierOptions as $option): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) ($filters['supplier_id'] ?? 0)
                        === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input
            type="number"
            name="purchase_id"
            min="1"
            placeholder="Purchase ID"
            value="<?= e(
                (string) ($filters['purchase_id'] ?? '')
            ) ?>"
        >

        <?php if (
            in_array(
                'purchase_payments.restore',
                $permissions,
                true
            )
        ): ?>
            <label>
                <input
                    type="checkbox"
                    name="deleted"
                    value="1"
                    <?= !empty($filters['deleted'])
                        ? 'checked'
                        : '' ?>
                >
                Deleted
            </label>
        <?php endif; ?>

        <button
            class="secondary-button"
            type="submit"
        >
            Filter
        </button>

        <a
            class="text-link"
            href="<?= e(app_url('/purchase-payments')) ?>"
        >
            Clear
        </a>
    </form>

    <section class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Date</th>
                    <th>Purchase</th>
                    <th>Supplier</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($payments === []): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="empty-cell"
                        >
                            No purchase payments found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($payments as $payment): ?>
                    <?php
                    if (!$payment instanceof PurchasePayment) {
                        continue;
                    }

                    $supplierName =
                        $supplierNames[$payment->supplierId()]
                        ?? (
                            'Supplier #'
                            . $payment->supplierId()
                        );
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($payment->paymentNumber()) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $payment
                                    ->paymentDate()
                                    ->format('Y-m-d')
                            ) ?>
                        </td>

                        <td>
                            <a
                                class="text-link"
                                href="<?= e(
                                    app_url(
                                        '/purchases/show?id='
                                        . $payment->purchaseId()
                                    )
                                ) ?>"
                            >
                                Purchase #<?= (int) $payment->purchaseId() ?>
                            </a>
                        </td>

                        <td>
                            <?= e($supplierName) ?>
                        </td>

                        <td>
                            <?= e($payment->paymentMethodLabel()) ?>
                        </td>

                        <td>
                            <?= e(
                                $payment->referenceNumber()
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $payment->amount(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?php if ($payment->isDeleted()): ?>
                                Deleted
                            <?php else: ?>
                                Active
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="page-actions">

                                <a
                                    class="text-link"
                                    href="<?= e(
                                        app_url(
                                            '/purchase-payments/history?purchase_id='
                                            . $payment->purchaseId()
                                        )
                                    ) ?>"
                                >
                                    History
                                </a>

                                <?php if (
                                    !$payment->isDeleted()
                                    && in_array(
                                        'purchase_payments.delete',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/purchase-payments/delete'
                                            )
                                        ) ?>"
                                        onsubmit="return confirm('Delete this payment?');"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int) $payment->id() ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="purchase_id"
                                            value="<?= (int) $payment->purchaseId() ?>"
                                        >

                                        <button
                                            class="danger-button compact"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (
                                    $payment->isDeleted()
                                    && in_array(
                                        'purchase_payments.restore',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/purchase-payments/restore'
                                            )
                                        ) ?>"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int) $payment->id() ?>"
                                        >

                                        <button
                                            class="secondary-button"
                                            type="submit"
                                        >
                                            Restore
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </section>

    <p class="muted">
        Total records:
        <strong><?= (int) $total ?></strong>
    </p>

    <?php if ($lastPage > 1): ?>
        <nav
            class="pagination"
            aria-label="Purchase payment pages"
        >
            <?php for (
                $number = 1;
                $number <= $lastPage;
                $number++
            ): ?>
                <a
                    class="<?= $number === $page
                        ? 'current'
                        : '' ?>"
                    href="<?= e(
                        app_url(
                            '/purchase-payments?'
                            . $queryForPage($number)
                        )
                    ) ?>"
                >
                    <?= $number ?>
                </a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

</main>

</body>
</html>