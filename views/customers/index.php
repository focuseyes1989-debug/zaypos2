<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Security\Csrf;

$queryForPage = static function (int $targetPage) use ($filters): string {
    return http_build_query([
        'search' => $filters['search'],
        'status' => $filters['status'],
        'deleted' => $filters['deleted'] ? '1' : '',
        'page' => $targetPage,
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
    <title>Customers — ZAY POS 2.0</title>

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
            <p class="eyebrow">MASTER DATA</p>
            <h1>Customers</h1>
            <p class="muted">
                Manage customers, credit limits and opening balances.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
            && in_array(
                'customers.create',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/customers/create')) ?>"
            >
                Add customer
            </a>
        <?php endif; ?>
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
        action="<?= e(app_url('/customers')) ?>"
    >
        <input
            name="search"
            value="<?= e($filters['search']) ?>"
            maxlength="160"
            placeholder="Search name, code, phone, email or group"
        >

        <select name="status">
            <option value="">All statuses</option>

            <option
                value="active"
                <?= $filters['status'] === 'active'
                    ? 'selected'
                    : '' ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= $filters['status'] === 'inactive'
                    ? 'selected'
                    : '' ?>
            >
                Inactive
            </option>
        </select>

        <select name="deleted">
            <option
                value=""
                <?= !$filters['deleted'] ? 'selected' : '' ?>
            >
                Current customers
            </option>

            <option
                value="1"
                <?= $filters['deleted'] ? 'selected' : '' ?>
            >
                Deleted customers
            </option>
        </select>

        <button
            class="secondary-button"
            type="submit"
        >
            Filter
        </button>

        <a
            class="text-link"
            href="<?= e(app_url('/customers')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Group</th>
                    <th>Credit limit</th>
                    <th>Opening balance</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="actions-column">Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($customers === []): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">
                            No customers found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($customers as $customer): ?>
                    <?php
                    if (!$customer instanceof Customer) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($customer->name()) ?>
                            </strong>

                            <small>
                                Code:
                                <code>
                                    <?= e($customer->code()) ?>
                                </code>
                            </small>
                        </td>

                        <td>
                            <?php if ($customer->phone() !== null): ?>
                                <div>
                                    <?= e($customer->phone()) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($customer->email() !== null): ?>
                                <small>
                                    <?= e($customer->email()) ?>
                                </small>
                            <?php endif; ?>

                            <?php if (
                                $customer->phone() === null
                                && $customer->email() === null
                            ): ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($customer->customerGroup() ?? '—') ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $customer->creditLimit(),
                                    2
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $customer->openingBalance(),
                                    2
                                )
                            ) ?>
                        </td>

                        <td>
                            <span
                                class="status-badge <?= $customer->isActive()
                                    ? 'status-active'
                                    : 'status-inactive' ?>"
                            >
                                <?= e(
                                    ucfirst(
                                        $customer->status()
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(
                                $customer->updatedAt()?->format(
                                    'Y-m-d H:i'
                                ) ?? '—'
                            ) ?>
                        </td>

                        <td class="actions-column">
                            <?php if ($filters['deleted']): ?>
                                <?php if (
                                    in_array(
                                        'customers.restore',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/customers/restore')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Restore this customer?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $customer->id() ?>"
                                        >

                                        <button
                                            class="table-button"
                                            type="submit"
                                        >
                                            Restore
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (
                                    in_array(
                                        'customers.update',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/customers/edit?id='
                                                . (int) $customer->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        'customers.delete',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/customers/delete')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Delete this customer?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $customer->id() ?>"
                                        >

                                        <button
                                            class="table-button"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($lastPage > 1): ?>
        <nav
            class="pagination"
            aria-label="Customer pages"
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
                            '/customers?'
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