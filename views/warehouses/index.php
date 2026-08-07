<?php

declare(strict_types=1);

use App\Models\Warehouse;
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
    <title>Warehouses — ZAY POS 2.0</title>

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
            <h1>Warehouses</h1>

            <p class="muted">
                Manage stock locations and warehouse policies.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
            && in_array(
                'warehouses.create',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/warehouses/create')) ?>"
            >
                Add warehouse
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
        action="<?= e(app_url('/warehouses')) ?>"
    >
        <input
            name="search"
            value="<?= e($filters['search']) ?>"
            maxlength="160"
            placeholder="Search name, code, location or manager"
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
                Current warehouses
            </option>

            <option
                value="1"
                <?= $filters['deleted'] ? 'selected' : '' ?>
            >
                Deleted warehouses
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
            href="<?= e(app_url('/warehouses')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Warehouse</th>
                    <th>Location</th>
                    <th>Manager</th>
                    <th>Default</th>
                    <th>Negative stock</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="actions-column">Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($warehouses === []): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">
                            No warehouses found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($warehouses as $warehouse): ?>
                    <?php
                    if (!$warehouse instanceof Warehouse) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($warehouse->name()) ?>
                            </strong>

                            <small>
                                Code:
                                <code>
                                    <?= e($warehouse->code()) ?>
                                </code>
                            </small>
                        </td>

                        <td>
                            <?= e(
                                $warehouse->locationName()
                                ?? $warehouse->address()
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $warehouse->managerName()
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?php if ($warehouse->isDefault()): ?>
                                <span class="status-badge status-active">
                                    Default
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= $warehouse->allowsNegativeStock()
                                ? 'Allowed'
                                : 'Not allowed' ?>
                        </td>

                        <td>
                            <span
                                class="status-badge <?= $warehouse->isActive()
                                    ? 'status-active'
                                    : 'status-inactive' ?>"
                            >
                                <?= e(
                                    ucfirst(
                                        $warehouse->status()
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(
                                $warehouse->updatedAt()?->format(
                                    'Y-m-d H:i'
                                ) ?? '—'
                            ) ?>
                        </td>

                        <td class="actions-column">
                            <?php if ($filters['deleted']): ?>

                                <?php if (
                                    in_array(
                                        'warehouses.restore',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/warehouses/restore')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Restore this warehouse?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $warehouse->id() ?>"
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
                                        'warehouses.update',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/warehouses/edit?id='
                                                . (int) $warehouse->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        'warehouses.delete',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/warehouses/delete')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Delete this warehouse?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $warehouse->id() ?>"
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
            aria-label="Warehouse pages"
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
                            '/warehouses?'
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