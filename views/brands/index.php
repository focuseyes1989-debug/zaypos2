<?php

declare(strict_types=1);

use App\Models\Brand;
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
    <title>Brands — ZAY POS 2.0</title>

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

            <h1>Brands</h1>

            <p class="muted">
                Manage product brands for searching, filtering and reporting.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
            && in_array(
                'brands.create',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/brands/create')) ?>"
            >
                Add brand
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
        action="<?= e(app_url('/brands')) ?>"
    >
        <input
            type="search"
            name="search"
            value="<?= e($filters['search']) ?>"
            maxlength="120"
            placeholder="Search name, code or description"
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
                Current brands
            </option>

            <option
                value="1"
                <?= $filters['deleted'] ? 'selected' : '' ?>
            >
                Deleted brands
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
            href="<?= e(app_url('/brands')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Brand</th>
                    <th>Code</th>
                    <th>Sort order</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="actions-column">
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>
                <?php if ($brands === []): ?>
                    <tr>
                        <td
                            colspan="6"
                            class="empty-cell"
                        >
                            No brands found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($brands as $brand): ?>
                    <?php
                    if (!$brand instanceof Brand) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($brand->name()) ?>
                            </strong>

                            <?php if (
                                $brand->description() !== null
                            ): ?>
                                <small>
                                    <?= e($brand->description()) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <code>
                                <?= e($brand->code()) ?>
                            </code>
                        </td>

                        <td>
                            <?= $brand->sortOrder() ?>
                        </td>

                        <td>
                            <span
                                class="status-badge <?= $brand->isActive()
                                    ? 'status-active'
                                    : 'status-inactive' ?>"
                            >
                                <?= e(ucfirst($brand->status())) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(
                                $brand->updatedAt()?->format(
                                    'Y-m-d H:i'
                                ) ?? '—'
                            ) ?>
                        </td>

                        <td class="actions-column">
                            <?php if ($filters['deleted']): ?>
                                <?php if (
                                    in_array(
                                        'brands.restore',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/brands/restore')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Restore this brand?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $brand->id() ?>"
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
                                        'brands.update',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/brands/edit?id='
                                                . (int) $brand->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        'brands.delete',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/brands/delete')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Delete this brand?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $brand->id() ?>"
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
            aria-label="Brand pages"
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
                            '/brands?'
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