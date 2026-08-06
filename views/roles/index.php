<?php declare(strict_types=1); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Roles — ZAY POS 2.0</title><link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>"></head>
<body class="app-page"><?php require BASE_PATH . '/views/partials/admin_header.php'; ?>
<main class="dashboard-shell">
    <section class="page-heading"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Roles & permissions</h1><p class="muted">Use built-in roles or create a custom permission set.</p></div><a class="primary-link" href="<?= e(app_url('/roles/create')) ?>">Create role</a></section>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <section class="role-grid"><?php foreach ($roles as $role): ?><article class="role-card"><div class="role-card-top"><span class="module-icon"><?= e(strtoupper(substr($role['name'], 0, 2))) ?></span><?php if ((int) $role['is_system'] === 1): ?><span class="system-badge">Built-in</span><?php endif; ?></div><h2><?= e($role['name']) ?></h2><code><?= e($role['code']) ?></code><p><?= e($role['description'] ?? 'Custom access role') ?></p><div class="role-footer"><span><?= (int) $role['user_count'] ?> user(s)</span><?php if ((int) $role['is_system'] !== 1): ?><a class="table-link" href="<?= e(app_url('/roles/edit?id=' . (int) $role['id'])) ?>">Edit permissions</a><?php endif; ?></div></article><?php endforeach; ?></section>
</main></body></html>
