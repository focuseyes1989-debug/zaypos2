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
        <?= $isEdit ? 'Edit tax' : 'Add tax' ?>
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
        <a href="<?= e(app_url('/taxes')) ?>">
            Taxes
        </a>

        <span>/</span>

        <span>
            <?= $isEdit ? 'Edit' : 'Add tax' ?>
        </span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>
                <?= $isEdit ? 'Edit tax' : 'Add tax' ?>
            </h1>

            <p class="muted">
                <?= $isEdit
                    ? 'Update tax calculation and application rules.'
                    : 'Create a new sales or purchase tax.' ?>
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
                        ? '/taxes/update'
                        : '/taxes'
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
                        Tax name
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
                        Tax code
                    </label>

                    <input
                        id="code"
                        name="code"
                        value="<?= e($input['code'] ?? '') ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="ct-5"
                        required
                    >
                </div>

                <div class="field">
                    <label for="tax_type">
                        Tax type
                    </label>

                    <select
                        id="tax_type"
                        name="tax_type"
                    >
                        <option
                            value="percentage"
                            <?= ($input['tax_type'] ?? 'percentage')
                                === 'percentage'
                                ? 'selected'
                                : '' ?>
                        >
                            Percentage
                        </option>

                        <option
                            value="fixed"
                            <?= ($input['tax_type'] ?? '')
                                === 'fixed'
                                ? 'selected'
                                : '' ?>
                        >
                            Fixed amount
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label for="rate">
                        Rate / amount
                    </label>

                    <input
                        id="rate"
                        name="rate"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e($input['rate'] ?? 0) ?>"
                        required
                    >

                    <small>
                        Percentage taxes must not exceed 100.
                    </small>
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
                            name="price_includes_tax"
                            value="1"
                            <?= !empty(
                                $input['price_includes_tax']
                            )
                                ? 'checked'
                                : '' ?>
                        >
                        Price includes tax
                    </label>
                </div>

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="applies_to_sales"
                            value="1"
                            <?= !empty(
                                $input['applies_to_sales']
                            )
                                ? 'checked'
                                : '' ?>
                        >
                        Applies to sales
                    </label>
                </div>

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="applies_to_purchases"
                            value="1"
                            <?= !empty(
                                $input['applies_to_purchases']
                            )
                                ? 'checked'
                                : '' ?>
                        >
                        Applies to purchases
                    </label>
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
                        Default sales tax
                    </label>

                    <small>
                        Default tax must be active and apply to sales.
                    </small>
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
                    href="<?= e(app_url('/taxes')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create tax' ?>
                </button>
            </div>
        </form>
    </section>
</main>

</body>
</html>