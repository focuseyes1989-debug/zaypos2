<?php

declare(strict_types=1);

use App\Models\Unit;
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
    <title>Units — ZAY POS 2.0</title>
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
            <h1>Units</h1>
            <p class="muted">
                Manage measurement units used across products.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
                && in_array(
                'units.create',
                $currentUser['permissions'],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/units/create')) ?>"
            >
                Add unit
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
        action="<?= e(app_url('/units')) ?>"
    >
        <input
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
                Current units
            </option>
            <option
                value="1"
                <?= $filters['deleted'] ? 'selected' : '' ?>
            >
                Deleted units
            </option>
        </select>

        <button class="secondary-button" type="submit">
            Filter
        </button>

        <a
            class="text-link"
            href="<?= e(app_url('/units')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Unit</th>
                    <th>Code</th>
                    <th>Sort order</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="actions-column">Actions</th>
                </tr>
                </thead>

                <tbody>
                    <?php if ($units === []): ?>
                    <tr>
                        <td colspan="6" class="empty-cell">
                                No units found.
                        </td>
                    </tr>
                <?php endif; ?>
                    <?php foreach ($units as $unit): ?>
                        <?php if (!$unit instanceof Unit) {
                            continue;
                        } ?>

                        <tr>
                            <td>
                                <strong><?= e($unit->name()) ?></strong>

                                <?php if ($unit->description() !== null): ?>
                                    <small>
                                        <?= e($unit->description()) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <code><?= e($unit->code()) ?></code>
                            </td>

                            <td><?= $unit->sortOrder() ?></td>

                            <td>
                                <span
                                    class="status-badge <?= $unit->isActive()
                                        ? 'status-active'
                                        : 'status-inactive' ?>"
                                >
                                    <?= e(ucfirst($unit->status())) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    $unit->updatedAt()?->format(
                                        'Y-m-d H:i'
                                    ) ?? '—'
                                ) ?>
                            </td>

                            <td class="actions-column">
                                <?php if ($filters['deleted']): ?>
                                    <?php if (
                                        in_array(
                                            'units.restore',
                                            $currentUser['permissions'],
                                            true
                                        )
                                    ): ?>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url('/units/restore')
                                            ) ?>"
                                            onsubmit="return confirm(
                                                'Restore this unit?'
                                            );"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $unit->id() ?>"
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
                                            'units.update',
                                            $currentUser['permissions'],
                                            true
                                        )
                                    ): ?>
                                        <a
                                            class="table-link"
                                            href="<?= e(
                                                app_url(
                                                    '/units/edit?id='
                                                    . (int) $unit->id()
                                                )
                                            ) ?>"
                                        >
                                            Edit
                                        </a>
                                    <?php endif; ?>

                                    <?php if (
                                        in_array(
                                            'units.delete',
                                            $currentUser['permissions'],
                                            true
                                        )
                                    ): ?>
                                        <form
                                            method="post"
                                            action="<?= e(
                                                app_url('/units/delete')
                                            ) ?>"
                                            onsubmit="return confirm(
                                                'Delete this unit?'
                                            );"
                                        >
                                            <?= Csrf::input() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $unit->id() ?>"
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
        <nav class="pagination" aria-label="Unit pages">
            <?php for (
                $number = 1;
                $number <= $lastPage;
                $number++
            ): ?>
                <a
                    class="<?= $number === $page ? 'current' : '' ?>"
                    href="<?= e(
                        app_url(
                            '/units?'
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