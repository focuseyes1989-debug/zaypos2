<?php

declare(strict_types=1);

use App\Models\SalePayment;
use App\Security\Csrf;

$permissions =
    $currentUser['permissions'] ?? [];

$customerNames = [];

foreach ($customerOptions as $option) {
    $customerNames[(int) $option['id']] =
        (string) $option['name'];
}

$queryForPage = static function (
    int $pageNumber
) use ($filters): string {
    $query = [
        'search' =>
            $filters['search'] ?? '',

        'payment_method' =>
            $filters['payment_method'] ?? '',

        'sale_id' =>
            $filters['sale_id'] ?? '',

        'customer_id' =>
            $filters['customer_id'] ?? '',

        'deleted' =>
            !empty($filters['deleted'])
                ? '1'
                : '',

        'page' => $pageNumber,
    ];

    return http_build_query(
        array_filter(
            $query,
            static fn (
                mixed $value
            ): bool =>
                $value !== ''
                && $value !== null
        )
    );
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

    <title>Sale Payments — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
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
                SALE PAYMENTS
            </p>

            <h1>Customer Payment History</h1>

            <p class="muted">
                View and manage payments
                recorded against completed sales.
            </p>
        </div>

        <div class="page-actions">
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

    <form
        class="filter-bar"
        method="get"
        action="<?= e(
            app_url('/sale-payments')
        ) ?>"
    >
        <input
            type="search"
            name="search"
            maxlength="190"
            placeholder="Payment number, reference..."
            value="<?= e(
                $filters['search'] ?? ''
            ) ?>"
        >

        <select name="payment_method">
            <option value="">
                All payment methods
            </option>

            <?php foreach (
                $paymentMethodOptions
                as $method => $label
            ): ?>
                <option
                    value="<?= e($method) ?>"
                    <?= (
                        $filters[
                            'payment_method'
                        ] ?? ''
                    ) === $method
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="customer_id">
            <option value="">
                All customers
            </option>

            <?php foreach (
                $customerOptions
                as $option
            ): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) (
                        $filters['customer_id']
                        ?? 0
                    ) === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input
            type="number"
            min="1"
            name="sale_id"
            placeholder="Sale ID"
            value="<?= e(
                $filters['sale_id']
                ?? ''
            ) ?>"
        >

        <?php if (
            in_array(
                'sale_payments.restore',
                $permissions,
                true
            )
        ): ?>
            <label>
                <input
                    type="checkbox"
                    name="deleted"
                    value="1"
                    <?= !empty(
                        $filters['deleted']
                    )
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
            href="<?= e(
                app_url('/sale-payments')
            ) ?>"
        >
            Clear
        </a>
    </form>

    <section class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Payment</th>
                    <th>Sale</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th class="actions-column">
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if ($payments === []): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="empty-cell"
                        >
                            No sale payments found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach (
                    $payments as $payment
                ): ?>

                    <?php if (
                        !$payment
                            instanceof SalePayment
                    ) {
                        continue;
                    } ?>

                    <?php
                    $customerId =
                        $payment->customerId();

                    $customerName =
                        $customerId !== null
                            ? (
                                $customerNames[
                                    $customerId
                                ]
                                ?? 'Customer #'
                                    . $customerId
                            )
                            : 'Walk-in Customer';
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e(
                                    $payment
                                        ->paymentNumber()
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <a
                                class="table-link"
                                href="<?= e(
                                    app_url(
                                        '/sales/show?id='
                                        . $payment
                                            ->saleId()
                                    )
                                ) ?>"
                            >
                                Sale #
                                <?= (int) $payment
                                    ->saleId() ?>
                            </a>
                        </td>

                        <td>
                            <?= e($customerName) ?>
                        </td>

                        <td>
                            <?= e(
                                $payment
                                    ->paymentDate()
                                    ->format('Y-m-d')
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $payment
                                    ->paymentMethodLabel()
                            ) ?>
                        </td>

                        <td>
                            <?= $payment
                                ->referenceNumber()
                                !== null
                                ? e(
                                    $payment
                                        ->referenceNumber()
                                )
                                : '<span class="muted">—</span>' ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(
                                    number_format(
                                        $payment
                                            ->amount(),
                                        4
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?php if (
                                $payment->isDeleted()
                            ): ?>
                                <span
                                    class="status-badge inactive"
                                >
                                    Deleted
                                </span>
                            <?php else: ?>
                                <span
                                    class="status-badge active"
                                >
                                    Active
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="page-actions">

                                <a
                                    class="text-link"
                                    href="<?= e(
                                        app_url(
                                            '/sale-payments/history?sale_id='
                                            . $payment
                                                ->saleId()
                                        )
                                    ) ?>"
                                >
                                    History
                                </a>

                                <?php if (
                                    !$payment->isDeleted()
                                    && in_array(
                                        'sale_payments.delete',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/sale-payments/delete'
                                            )
                                        ) ?>"
                                        onsubmit="
                                            return confirm(
                                                'Delete this sale payment?'
                                            );
                                        "
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int)
                                                $payment->id()
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="sale_id"
                                            value="<?= (int)
                                                $payment
                                                    ->saleId()
                                            ?>"
                                        >

                                        <button
                                            class="secondary-button"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (
                                    $payment->isDeleted()
                                    && in_array(
                                        'sale_payments.restore',
                                        $permissions,
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/sale-payments/restore'
                                            )
                                        ) ?>"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int)
                                                $payment->id()
                                            ?>"
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

    <div class="page-heading">
        <p class="muted">
            Total records:
            <strong>
                <?= (int) $total ?>
            </strong>
        </p>
    </div>

    <?php if ($lastPage > 1): ?>

        <nav
            class="pagination"
            aria-label="Sale payment pages"
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
                            '/sale-payments?'
                            . $queryForPage(
                                $number
                            )
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