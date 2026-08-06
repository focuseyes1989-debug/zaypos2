<?php

declare(strict_types=1);

use App\Security\Csrf;

$isEdit = $mode === 'edit';
$selectedRoles = array_map('intval', (array) ($input['role_ids'] ?? []));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'Edit user' : 'Add user' ?> — ZAY POS 2.0</title><link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>"></head>
<body class="app-page"><?php require BASE_PATH . '/views/partials/admin_header.php'; ?>
<main class="form-shell">
    <div class="breadcrumb"><a href="<?= e(app_url('/users')) ?>">Users</a><span>/</span><span><?= $isEdit ? 'Edit' : 'Add user' ?></span></div>
    <section class="form-card"><div class="form-heading"><h1><?= $isEdit ? 'Edit user' : 'Add user' ?></h1><p class="muted"><?= $isEdit ? 'Update account details and assigned roles.' : 'Create a secure account for a staff member.' ?></p></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(app_url($isEdit ? '/users/update' : '/users')) ?>">
        <?= Csrf::input() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $input['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field full"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= e($input['full_name'] ?? '') ?>" maxlength="150" required></div>
            <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e($input['username'] ?? '') ?>" maxlength="80" required></div>
            <div class="field"><label for="email">Email (optional)</label><input id="email" name="email" type="email" value="<?= e($input['email'] ?? '') ?>" maxlength="190"></div>
            <div class="field"><label for="branch_id">Branch</label><select id="branch_id" name="branch_id"><option value="0">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) ($input['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="active" <?= ($input['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($input['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
            <?php if (!$isEdit): ?>
                <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="12" required></div>
                <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required></div>
            <?php endif; ?>
        </div>
        <fieldset class="checkbox-section"><legend>Roles</legend><div class="checkbox-grid"><?php foreach ($roles as $role): ?><label class="check-card"><input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $selectedRoles, true) ? 'checked' : '' ?>><span><strong><?= e($role['name']) ?></strong><small><?= e($role['description'] ?? $role['code']) ?></small></span></label><?php endforeach; ?></div></fieldset>
        <div class="form-actions"><a class="secondary-link" href="<?= e(app_url('/users')) ?>">Cancel</a><button class="primary-button compact" type="submit"><?= $isEdit ? 'Save changes' : 'Create user' ?></button></div>
    </form></section>

    <?php if ($isEdit && in_array('users.password_reset', $currentUser['permissions'], true)): ?>
        <section class="form-card danger-section"><div class="form-heading"><h2>Reset password</h2><p class="muted">This signs the user out from all existing sessions.</p></div>
        <form method="post" action="<?= e(app_url('/users/reset-password')) ?>"><?= Csrf::input() ?><input type="hidden" name="id" value="<?= (int) $input['id'] ?>"><div class="form-grid"><div class="field"><label for="reset_password">New password</label><input id="reset_password" name="password" type="password" minlength="12" required></div><div class="field"><label for="reset_confirmation">Confirm new password</label><input id="reset_confirmation" name="password_confirmation" type="password" minlength="12" required></div></div><div class="form-actions"><button class="danger-button" type="submit">Reset password</button></div></form></section>
    <?php endif; ?>
</main></body></html>
