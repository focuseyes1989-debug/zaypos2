<?php

declare(strict_types=1);

$currentUser = $user;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — ZAY POS 2.0</title>
    <link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>">
</head>
<body class="app-page">
<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="dashboard-shell">
    <section class="welcome-card">
        <div>
            <p class="eyebrow">ADMIN DASHBOARD</p>
            <h1>Hello, <?= e($user['full_name']) ?></h1>
            <p class="muted">Authentication, role permissions and audit logging are active.</p>
        </div>
        <span class="ready-badge">System ready</span>
    </section>

    <section class="module-grid">
        <article class="module-card active-card"><span class="module-icon">01</span><h2>Users & Roles</h2><p>Secure foundation installed. Management screens are the next milestone.</p></article>
        <article class="module-card"><span class="module-icon">02</span><h2>Products</h2><p>Products, categories, units, barcodes and branch pricing.</p></article>
        <article class="module-card"><span class="module-icon">03</span><h2>Inventory</h2><p>Stock ledger, receiving, adjustment, transfer and stock count.</p></article>
        <article class="module-card"><span class="module-icon">04</span><h2>Cashier</h2><p>Fast barcode sales, payments, receipts and cashier shifts.</p></article>
    </section>
</main>
</body>
</html>
