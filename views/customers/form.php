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
        <?= $isEdit ? 'Edit customer' : 'Add customer' ?>
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
        <a href="<?= e(app_url('/customers')) ?>">
            Customers
        </a>

        <span>/</span>

        <span>
            <?= $isEdit ? 'Edit' : 'Add customer' ?>
        </span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>
                <?= $isEdit
                    ? 'Edit customer'
                    : 'Add customer' ?>
            </h1>

            <p class="muted">
                <?= $isEdit
                    ? 'Update customer details and credit settings.'
                    : 'Create a new sales customer.' ?>
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
                        ? '/customers/update'
                        : '/customers'
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
                        Customer name
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
                        Customer code
                    </label>

                    <input
                        id="code"
                        name="code"
                        value="<?= e($input['code'] ?? '') ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="customer-code"
                        required
                    >

                    <small>
                        Lowercase letters, numbers,
                        dashes and underscores.
                    </small>
                </div>

                <div class="field">
                    <label for="customer_group">
                        Customer group
                    </label>

                    <input
                        id="customer_group"
                        name="customer_group"
                        value="<?= e(
                            $input['customer_group'] ?? ''
                        ) ?>"
                        maxlength="100"
                        placeholder="Retail, Wholesale, VIP"
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
                        placeholder="09xxxxxxxxx"
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
                        placeholder="customer@example.com"
                    >
                </div>

                <div class="field">
                    <label for="tax_number">
                        Tax number
                    </label>

                    <input
                        id="tax_number"
                        name="tax_number"
                        value="<?= e(
                            $input['tax_number'] ?? ''
                        ) ?>"
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="credit_limit">
                        Credit limit
                    </label>

                    <input
                        id="credit_limit"
                        name="credit_limit"
                        type="number"
                        min="0"
                        step="0.01"
                        value="<?= e(
                            $input['credit_limit'] ?? 0
                        ) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="opening_balance">
                        Opening balance
                    </label>

                    <input
                        id="opening_balance"
                        name="opening_balance"
                        type="number"
                        step="0.01"
                        value="<?= e(
                            $input['opening_balance'] ?? 0
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
                    href="<?= e(app_url('/customers')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create customer' ?>
                </button>
            </div>
        </form>
    </section>
</main>

</body>
</html>