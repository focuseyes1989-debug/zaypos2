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
        <?= $isEdit ? 'Edit brand' : 'Add brand' ?>
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
        <a href="<?= e(app_url('/brands')) ?>">
            Brands
        </a>

        <span>/</span>

        <span>
            <?= $isEdit ? 'Edit' : 'Add brand' ?>
        </span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>
                <?= $isEdit
                    ? 'Edit brand'
                    : 'Add brand' ?>
            </h1>

            <p class="muted">
                <?= $isEdit
                    ? 'Update the selected brand details.'
                    : 'Create a new product brand.' ?>
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
                        ? '/brands/update'
                        : '/brands'
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
                        Brand name
                    </label>

                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="<?= e($input['name'] ?? '') ?>"
                        maxlength="120"
                        autocomplete="off"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="code">
                        Brand code
                    </label>

                    <input
                        id="code"
                        name="code"
                        type="text"
                        value="<?= e($input['code'] ?? '') ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="samsung"
                        autocomplete="off"
                        required
                    >

                    <small>
                        Lowercase letters, numbers, dashes and underscores.
                    </small>
                </div>

                <div class="field">
                    <label for="sort_order">
                        Sort order
                    </label>

                    <input
                        id="sort_order"
                        name="sort_order"
                        type="number"
                        min="0"
                        max="999999"
                        value="<?= e(
                            $input['sort_order'] ?? 0
                        ) ?>"
                        required
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

                <div class="field full">
                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        maxlength="500"
                        rows="5"
                    ><?= e(
                        $input['description'] ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(app_url('/brands')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create brand' ?>
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>