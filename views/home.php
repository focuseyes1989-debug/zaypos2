<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<main class="status-card">
    <div class="brand">ZAY</div>
    <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="subtitle">Secure PHP MVC foundation</p>

    <dl class="status-list">
        <div>
            <dt>PHP</dt>
            <dd class="success"><?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div>
            <dt>Database</dt>
            <dd class="<?= $databaseError === null ? 'success' : 'error' ?>">
                <?= htmlspecialchars($databaseStatus, ENT_QUOTES, 'UTF-8') ?>
            </dd>
        </div>
        <div>
            <dt>MySQL</dt>
            <dd><?= htmlspecialchars($databaseVersion, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
    </dl>

    <?php if ($databaseError !== null): ?>
        <div class="message error-box">
            <?= htmlspecialchars($databaseError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php else: ?>
        <div class="message success-box">
            ZAY POS 2.0 foundation is ready.
        </div>
    <?php endif; ?>
</main>
</body>
</html>
