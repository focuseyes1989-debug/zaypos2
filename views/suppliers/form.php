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
            ? 'Edit supplier'
            : 'Add supplier' ?>
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
        <a href="<?= e(app_url('/suppliers')) ?>">
            Suppliers
        </a>

        <span>/</span>

        <span>
            <?= $isEdit
                ? 'Edit'
                : 'Add supplier' ?>
        </span>
    </div>

    <section class="form-card">

        <div class="form-heading">
            <h1>
                <?= $isEdit
                    ? 'Edit supplier'
                    : 'Add supplier' ?>
            </h1>

            <p class="muted">
                <?= $isEdit
                    ? 'Update supplier details and purchasing terms.'
                    : 'Create a new supplier for purchasing and inventory.' ?>
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
                        ? '/suppliers/update'
                        : '/suppliers'
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
                        Supplier name
                    </label>

                    <input
                        id="name"
                        name="name"
                        value="<?= e(
                            $input['name'] ?? ''
                        ) ?>"
                        maxlength="160"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="code">
                        Supplier code
                    </label>

                    <input
                        id="code"
                        name="code"
                        value="<?= e(
                            $input['code'] ?? ''
                        ) ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="supplier-code"
                        required
                    >

                    <small>
                        Lowercase letters, numbers,
                        dashes and underscores.
                    </small>
                </div>

                <div class="field">
                    <label for="contact_person">
                        Contact person
                    </label>

                    <input
                        id="contact_person"
                        name="contact_person"
                        value="<?= e(
                            $input['contact_person']
                            ?? ''
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
                        value="<?= e(
                            $input['phone'] ?? ''
                        ) ?>"
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
                        value="<?= e(
                            $input['email'] ?? ''
                        ) ?>"
                        maxlength="190"
                        placeholder="supplier@example.com"
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
                            $input['tax_number']
                            ?? ''
                        ) ?>"
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="payment_terms_days">
                        Payment terms (days)
                    </label>

                    <input
                        id="payment_terms_days"
                        name="payment_terms_days"
                        type="number"
                        min="0"
                        max="65535"
                        step="1"
                        value="<?= e(
                            $input['payment_terms_days']
                            ?? 0
                        ) ?>"
                        required
                    >

                    <small>
                        Use 0 for immediate payment.
                    </small>
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
                            $input['credit_limit']
                            ?? 0
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
                            $input['opening_balance']
                            ?? 0
                        ) ?>"
                        required
                    >

                    <small>
                        Existing supplier balance when
                        starting the system.
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

                <div class="field full">
                    <label for="address">
                        Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        maxlength="500"
                        rows="4"
                    ><?= e(
                        $input['address'] ?? ''
                    ) ?></textarea>
                </div>

                <div class="field full">
                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="5"
                    ><?= e(
                        $input['notes'] ?? ''
                    ) ?></textarea>
                </div>

            </div>

            <div class="form-actions">

                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url('/suppliers')
                    ) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create supplier' ?>
                </button>

            </div>

        </form>

    </section>

</main>

</body>
</html>