<?php

declare(strict_types=1);

use App\Security\Csrf;

$isEdit = $mode === 'edit';
$selectedPermissions = array_map('intval', (array) ($input['permission_ids'] ?? []));
$grouped = [];
foreach ($permissions as $permission) {
    $grouped[$permission['module']][] = $permission;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'Edit role' : 'Create role' ?> — ZAY POS 2.0</title><link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>"></head>
<body class="app-page"><?php require BASE_PATH . '/views/partials/admin_header.php'; ?>
<main class="form-shell wide-form">
    <div class="breadcrumb"><a href="<?= e(app_url('/roles')) ?>">Roles</a><span>/</span><span><?= $isEdit ? 'Edit' : 'Create' ?></span></div>
    <section class="form-card"><div class="form-heading"><h1><?= $isEdit ? 'Edit role' : 'Create custom role' ?></h1><p class="muted">Permissions are enforced on the server, not only hidden in the interface.</p></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(app_url($isEdit ? '/roles/update' : '/roles')) ?>"><?= Csrf::input() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $input['id'] ?>"><?php endif; ?>
        <div class="form-grid"><div class="field"><label for="name">Role name</label><input id="name" name="name" value="<?= e($input['name'] ?? '') ?>" maxlength="100" required></div><div class="field"><label for="code">Role code</label><input id="code" name="code" value="<?= e($input['code'] ?? '') ?>" maxlength="80" placeholder="example: inventory_staff" required></div><div class="field full"><label for="description">Description</label><input id="description" name="description" value="<?= e($input['description'] ?? '') ?>" maxlength="255"></div></div>
        <fieldset class="checkbox-section"><legend>Permissions</legend><?php foreach ($grouped as $module => $modulePermissions): ?><section class="permission-group"><h3><?= e(ucwords(str_replace('_', ' ', $module))) ?></h3><div class="checkbox-grid"><?php foreach ($modulePermissions as $permission): ?><label class="check-card"><input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>" <?= in_array((int) $permission['id'], $selectedPermissions, true) ? 'checked' : '' ?>><span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['code']) ?></small></span></label><?php endforeach; ?></div></section><?php endforeach; ?></fieldset>
        <div class="form-actions"><a class="secondary-link" href="<?= e(app_url('/roles')) ?>">Cancel</a><button class="primary-button compact" type="submit"><?= $isEdit ? 'Save role' : 'Create role' ?></button></div>
    </form></section>
</main></body></html>
