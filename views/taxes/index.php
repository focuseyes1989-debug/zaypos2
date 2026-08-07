<?php

declare(strict_types=1);

use App\Models\Tax;
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
    <title>Taxes — ZAY POS 2.0</title>

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
            <h1>Taxes</h1>

            <p class="muted">
                Manage sales and purchase tax rules.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
            && in_array(
                'taxes.create',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/taxes/create')) ?>"
            >
                Add tax
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
        action="<?= e(app_url('/taxes')) ?>"
    >
        <input
            name="search"
            value="<?= e($filters['search']) ?>"
            maxlength="160"
            placeholder="Search name, code, type or description"
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
                Current taxes
            </option>

            <option
                value="1"
                <?= $filters['deleted'] ? 'selected' : '' ?>
            >
                Deleted taxes
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
            href="<?= e(app_url('/taxes')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Tax</th>
                    <th>Type</th>
                    <th>Rate</th>
                    <th>Applies to</th>
                    <th>Included</th>
                    <th>Default</th>
                    <th>Status</th>
                    <th class="actions-column">Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($taxes === []): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">
                            No taxes found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($taxes as $tax): ?>
                    <?php
                    if (!$tax instanceof Tax) {
                        continue;
                    }
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($tax->name()) ?>
                            </strong>

                            <small>
                                Code:
                                <code>
                                    <?= e($tax->code()) ?>
                                </code>
                            </small>
                        </td>

                        <td>
                            <?= e(ucfirst($tax->taxType())) ?>
                        </td>

                        <td>
                            <?php if ($tax->isPercentage()): ?>
                                <?= e(
                                    number_format(
                                        $tax->rate(),
                                        4
                                    )
                                ) ?>%
                            <?php else: ?>
                                <?= e(
                                    number_format(
                                        $tax->rate(),
                                        4
                                    )
                                ) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php
                            $scopes = [];

                            if ($tax->appliesToSales()) {
                                $scopes[] = 'Sales';
                            }

                            if ($tax->appliesToPurchases()) {
                                $scopes[] = 'Purchases';
                            }
                            ?>

                            <?= e(
                                $scopes === []
                                    ? '—'
                                    : implode(', ', $scopes)
                            ) ?>
                        </td>

                        <td>
                            <?= $tax->priceIncludesTax()
                                ? 'Yes'
                                : 'No' ?>
                        </td>

                        <td>
                            <?php if ($tax->isDefault()): ?>
                                <span class="status-badge status-active">
                                    Default
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <span
                                class="status-badge <?= $tax->isActive()
                                    ? 'status-active'
                                    : 'status-inactive' ?>"
                            >
                                <?= e(
                                    ucfirst(
                                        $tax->status()
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td class="actions-column">
                            <?php if ($filters['deleted']): ?>

                                <?php if (
                                    in_array(
                                        'taxes.restore',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/taxes/restore')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Restore this tax?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $tax->id() ?>"
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
                                        'taxes.update',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/taxes/edit?id='
                                                . (int) $tax->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        'taxes.delete',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/taxes/delete')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Delete this tax?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $tax->id() ?>"
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
            aria-label="Tax pages"
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
                            '/taxes?'
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