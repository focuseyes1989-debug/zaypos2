<?php

declare(strict_types=1);

use App\Security\Csrf;

$pages = max(1, (int) ceil($total / $perPage));
$queryForPage = static function (int $targetPage) use ($filters): string {
    return http_build_query([
        'search' => $filters['search'],
        'status' => $filters['status'],
        'role_id' => $filters['roleId'],
        'page' => $targetPage,
    ]);
};
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users — ZAY POS 2.0</title><link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>"></head>
<body class="app-page">
<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>
<main class="dashboard-shell">
    <section class="page-heading">
        <div><p class="eyebrow">ACCESS CONTROL</p><h1>Users</h1><p class="muted">Manage staff accounts, branches, roles and access status.</p></div>
        <?php if (in_array('users.create', $currentUser['permissions'], true)): ?>
            <a class="primary-link" href="<?= e(app_url('/users/create')) ?>">Add user</a>
        <?php endif; ?>
    </section>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="filter-bar" method="get" action="<?= e(app_url('/users')) ?>">
        <input name="search" value="<?= e($filters['search']) ?>" placeholder="Search name, username or email">
        <select name="status"><option value="">All statuses</option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
        <select name="role_id"><option value="0">All roles</option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= (int) $filters['roleId'] === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select>
        <button class="secondary-button" type="submit">Filter</button>
        <a class="text-link" href="<?= e(app_url('/users')) ?>">Clear</a>
    </form>

    <div class="table-card">
        <div class="table-scroll"><table><thead><tr><th>User</th><th>Branch</th><th>Roles</th><th>Status</th><th>Last login</th><th class="actions-column">Actions</th></tr></thead><tbody>
        <?php if ($users === []): ?><tr><td colspan="6" class="empty-cell">No users found.</td></tr><?php endif; ?>
        <?php foreach ($users as $account): ?>
            <tr>
                <td><strong><?= e($account['full_name']) ?></strong><small>@<?= e($account['username']) ?><?= $account['email'] ? ' · ' . e($account['email']) : '' ?></small></td>
                <td><?= e($account['branch_name'] ?? 'All branches') ?></td><td><?= e($account['role_names'] ?? 'No role') ?></td>
                <td><span class="status-badge <?= $account['status'] === 'active' ? 'status-active' : 'status-inactive' ?>"><?= e(ucfirst($account['status'])) ?></span></td>
                <td><?= e($account['last_login_at'] ?? 'Never') ?></td>
                <td class="actions-column">
                    <?php $protectedSuperAdmin = str_contains((string) ($account['role_names'] ?? ''), 'Super Admin') && !in_array('super_admin', $currentUser['roles'], true); ?>
                    <?php if (in_array('users.update', $currentUser['permissions'], true) && !$protectedSuperAdmin): ?><a class="table-link" href="<?= e(app_url('/users/edit?id=' . (int) $account['id'])) ?>">Edit</a><?php endif; ?>
                    <?php if (in_array('users.disable', $currentUser['permissions'], true) && (int) $account['id'] !== (int) $currentUser['id'] && !$protectedSuperAdmin): ?>
                        <form method="post" action="<?= e(app_url('/users/toggle-status')) ?>" onsubmit="return confirm('Change this user status?');"><?= Csrf::input() ?><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><button class="table-button" type="submit"><?= $account['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>

    <?php if ($pages > 1): ?><nav class="pagination"><?php for ($number = 1; $number <= $pages; $number++): ?><a class="<?= $number === $page ? 'current' : '' ?>" href="<?= e(app_url('/users?' . $queryForPage($number))) ?>"><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
</main></body></html>
