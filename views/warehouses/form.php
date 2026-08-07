<?php

declare(strict_types=1);

use App\Security\Csrf;

$isEdit = $mode === 'edit';
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
        <?= $isEdit
            ? 'Edit warehouse'
            : 'Add warehouse' ?>
        — ZAY POS 2.0
    </title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >
</head>
<body class="app-page">

<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="form-shell">
    <div class="breadcrumb">
        <a href="<?= e(app_url('/warehouses')) ?>">
            Warehouses
        </a>

        <span>/</span>

        <span>
            <?= $isEdit
                ? 'Edit'
                : 'Add warehouse' ?>
        </span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>
                <?= $isEdit
                    ? 'Edit warehouse'
                    : 'Add warehouse' ?>
            </h1>

            <p class="muted">
                <?= $isEdit
                    ? 'Update warehouse details and stock rules.'
                    : 'Create a new stock location.' ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(
                app_url(
                    $isEdit
                        ? '/warehouses/update'
                        : '/warehouses'
                )
            ) ?>"
        >
            <?= Csrf::input() ?>

            <?php if ($isEdit): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) ($input['id'] ?? 0) ?>"
                >
            <?php endif; ?>

            <div class="form-grid">
                <div class="field full">
                    <label for="name">
                        Warehouse name
                    </label>

                    <input
                        id="name"
                        name="name"
                        value="<?= e($input['name'] ?? '') ?>"
                        maxlength="160"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="code">
                        Warehouse code
                    </label>

                    <input
                        id="code"
                        name="code"
                        value="<?= e($input['code'] ?? '') ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="main-warehouse"
                        required
                    >

                    <small>
                        Lowercase letters, numbers,
                        dashes and underscores.
                    </small>
                </div>

                <div class="field">
                    <label for="location_name">
                        Location name
                    </label>

                    <input
                        id="location_name"
                        name="location_name"
                        value="<?= e(
                            $input['location_name'] ?? ''
                        ) ?>"
                        maxlength="160"
                        placeholder="Yangon Main Branch"
                    >
                </div>

                <div class="field">
                    <label for="manager_name">
                        Manager name
                    </label>

                    <input
                        id="manager_name"
                        name="manager_name"
                        value="<?= e(
                            $input['manager_name'] ?? ''
                        ) ?>"
                        maxlength="160"
                    >
                </div>

                <div class="field">
                    <label for="phone">
                        Phone
                    </label>

                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        value="<?= e($input['phone'] ?? '') ?>"
                        maxlength="50"
                    >
                </div>

                <div class="field">
                    <label for="email">
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?= e($input['email'] ?? '') ?>"
                        maxlength="190"
                    >
                </div>

                <div class="field">
                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >
                        <option
                            value="active"
                            <?= ($input['status'] ?? 'active')
                                === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= ($input['status'] ?? '')
                                === 'inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="is_default"
                            value="1"
                            <?= !empty($input['is_default'])
                                ? 'checked'
                                : '' ?>
                        >
                        Default warehouse
                    </label>

                    <small>
                        The default warehouse must remain active.
                    </small>
                </div>

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="allow_negative_stock"
                            value="1"
                            <?= !empty(
                                $input['allow_negative_stock']
                            )
                                ? 'checked'
                                : '' ?>
                        >
                        Allow negative stock
                    </label>

                    <small>
                        Enable only when operationally required.
                    </small>
                </div>

                <div class="field full">
                    <label for="address">
                        Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        maxlength="500"
                        rows="4"
                    ><?= e($input['address'] ?? '') ?></textarea>
                </div>

                <div class="field full">
                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="5"
                    ><?= e($input['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(app_url('/warehouses')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create warehouse' ?>
                </button>
            </div>
        </form>
    </section>
</main>

</body>
</html>