<?php

declare(strict_types=1);

use App\Security\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — ZAY POS 2.0</title>
    <link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-brand-panel">
        <div class="brand-mark">ZAY</div>
        <p class="eyebrow">POINT OF SALE</p>
        <h1>Welcome to ZAY POS 2.0</h1>
        <p class="auth-copy">Secure access for your shop administrators and cashier team.</p>
        <div class="server-pill"><span class="status-dot"></span>Local POS server connected</div>
    </section>

    <section class="auth-form-panel">
        <div class="auth-form-wrap">
            <h2>Sign in</h2>
            <p class="muted">Enter your username or email to continue.</p>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(app_url('/login')) ?>" autocomplete="on">
                <?= Csrf::input() ?>
                <label for="login">Username or email</label>
                <input id="login" name="login" type="text" value="<?= e(old('login')) ?>"
                       autocomplete="username" maxlength="190" required autofocus>

                <label for="password">Password</label>
                <input id="password" name="password" type="password"
                       autocomplete="current-password" required>

                <button class="primary-button" type="submit">Sign in</button>
            </form>
            <p class="security-note">Authorized staff only. Login activity is recorded.</p>
        </div>
    </section>
</main>
</body>
</html>
