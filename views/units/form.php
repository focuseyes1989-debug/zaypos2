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
        <?= $isEdit ? 'Edit unit' : 'Add unit' ?>
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
        <a href="<?= e(app_url('/units')) ?>">
            Units
        </a>
        <span>/</span>
        <span>
            <?= $isEdit ? 'Edit' : 'Add unit' ?>
        </span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>
                <?= $isEdit
                    ? 'Edit unit'
                    : 'Add unit' ?>
            </h1>

            <p class="muted">
                <?= $isEdit
                    ? 'Update unit details.'
                    : 'Create a new unit of measurement.' ?>
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
                        ? '/units/update'
                        : '/units'
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
                    <label for="name">Unit name</label>
                    <input
                        id="name"
                        name="name"
                        value="<?= e($input['name'] ?? '') ?>"
                        maxlength="120"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="code">Unit code</label>
                    <input
                        id="code"
                        name="code"
                        value="<?= e($input['code'] ?? '') ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="unit-code"
                        required
                    >
                    <small>
                        Lowercase letters, numbers, dashes and underscores.
                    </small>
                </div>

                <div class="field">
                    <label for="symbol">Symbol</label>
                    <input
                        id="symbol"
                        name="symbol"
                        value="<?= e($input['symbol'] ?? '') ?>"
                        maxlength="8"
                    >
                </div>

                <div class="field">
                    <label for="decimal_places">Decimal places</label>
                    <input
                        id="decimal_places"
                        name="decimal_places"
                        type="number"
                        min="0"
                        max="8"
                        value="<?= e($input['decimal_places'] ?? 0) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="parent_id">Parent unit</label>
                    <select id="parent_id" name="parent_id">
                        <option value="">No parent</option>

                        <?php foreach ($parents as $parent): ?>
                            <option
                                value="<?= (int) $parent['id'] ?>"
                                <?= (int) (
                                    $input['parent_id'] ?? 0
                                ) === (int) $parent['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($parent['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="sort_order">Sort order</label>
                    <input
                        id="sort_order"
                        name="sort_order"
                        type="number"
                        min="0"
                        max="999999"
                        value="<?= e($input['sort_order'] ?? 0) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
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
                    ><?= e($input['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(app_url('/units')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create unit' ?>
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>