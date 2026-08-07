<?php

declare(strict_types=1);

use App\Models\Supplier;
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
    <title>Suppliers — ZAY POS 2.0</title>

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

            <h1>Suppliers</h1>

            <p class="muted">
                Manage suppliers, contact details and purchasing terms.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
            && in_array(
                'suppliers.create',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/suppliers/create')) ?>"
            >
                Add supplier
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
        action="<?= e(app_url('/suppliers')) ?>"
    >
        <input
            name="search"
            value="<?= e($filters['search']) ?>"
            maxlength="160"
            placeholder="Search supplier, code, phone or email"
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
                <?= !$filters['deleted']
                    ? 'selected'
                    : '' ?>
            >
                Current suppliers
            </option>

            <option
                value="1"
                <?= $filters['deleted']
                    ? 'selected'
                    : '' ?>
            >
                Deleted suppliers
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
            href="<?= e(app_url('/suppliers')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Contact</th>
                    <th>Phone / Email</th>
                    <th>Payment terms</th>
                    <th>Credit limit</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="actions-column">
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>

                <?php if ($suppliers === []): ?>
                    <tr>
                        <td
                            colspan="8"
                            class="empty-cell"
                        >
                            No suppliers found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($suppliers as $supplier): ?>

                    <?php
                    if (!$supplier instanceof Supplier) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($supplier->name()) ?>
                            </strong>

                            <small>
                                Code:
                                <code>
                                    <?= e($supplier->code()) ?>
                                </code>
                            </small>
                        </td>

                        <td>
                            <?= e(
                                $supplier->contactPerson()
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?php if ($supplier->phone() !== null): ?>
                                <div>
                                    <?= e($supplier->phone()) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($supplier->email() !== null): ?>
                                <small>
                                    <?= e($supplier->email()) ?>
                                </small>
                            <?php endif; ?>

                            <?php if (
                                $supplier->phone() === null
                                && $supplier->email() === null
                            ): ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= (int) $supplier->paymentTermsDays() ?>
                            day<?= $supplier->paymentTermsDays() === 1
                                ? ''
                                : 's' ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $supplier->creditLimit(),
                                    2
                                )
                            ) ?>
                        </td>

                        <td>
                            <span
                                class="status-badge <?= $supplier->isActive()
                                    ? 'status-active'
                                    : 'status-inactive' ?>"
                            >
                                <?= e(
                                    ucfirst(
                                        $supplier->status()
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(
                                $supplier->updatedAt()?->format(
                                    'Y-m-d H:i'
                                ) ?? '—'
                            ) ?>
                        </td>

                        <td class="actions-column">

                            <?php if ($filters['deleted']): ?>

                                <?php if (
                                    in_array(
                                        'suppliers.restore',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/suppliers/restore'
                                            )
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Restore this supplier?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $supplier->id() ?>"
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
                                        'suppliers.update',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/suppliers/edit?id='
                                                . (int) $supplier->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        'suppliers.delete',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url(
                                                '/suppliers/delete'
                                            )
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Delete this supplier?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $supplier->id() ?>"
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
            aria-label="Supplier pages"
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
                            '/suppliers?'
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