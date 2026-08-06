<?php

declare(strict_types=1);

use App\Security\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — ZAY POS 2.0</title>
    <link rel="stylesheet" href="<?= e(app_url('/assets/css/app.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-brand-panel">
        <div class="brand-mark">ZAY</div>
        <p class="eyebrow">ACCOUNT SECURITY</p>
        <h1>Protect your POS account</h1>
        <p class="auth-copy">Use a unique password with uppercase, lowercase, number, and special characters.</p>
    </section>
    <section class="auth-form-panel">
        <div class="auth-form-wrap">
            <h2><?= $required ? 'Password change required' : 'Change password' ?></h2>
            <p class="muted">
                <?= $required
                    ? 'Your administrator requires you to set a new password before continuing.'
                    : 'Changing your password signs out your other active sessions.' ?>
            </p>

            <?php if (is_string($success) && $success !== ''): ?>
                <div class="alert alert-success" role="alert"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(app_url('/account/password')) ?>" autocomplete="off">
                <?= Csrf::input() ?>
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password"
                       autocomplete="current-password" required autofocus>

                <label for="password">New password</label>
                <input id="password" name="password" type="password"
                       autocomplete="new-password" minlength="12" required>

                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       autocomplete="new-password" minlength="12" required>

                <button class="primary-button" type="submit">Update password</button>
            </form>
            <?php if (!$required): ?>
                <p><a href="<?= e(app_url('/dashboard')) ?>">Return to dashboard</a></p>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
