<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Security\Csrf;
use App\Services\AuthService;

abstract class BaseAdminController
{
    /** @return array<string, mixed> */
    protected function requirePermission(string $permission): array
    {
        $auth = new AuthService();
        $user = $auth->user();
        if ($user === null) {
            flash('error', 'Please sign in to continue.');
            redirect('/login');
        }
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            flash('error', 'You must change your password before continuing.');
            redirect('/account/password');
        }
        if (!$auth->can($permission)) {
            http_response_code(403);
            View::render('errors.403');
            exit;
        }
        return $user;
    }

    protected function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors.419');
            exit;
        }
    }

    protected function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            http_response_code(404);
            View::render('errors.404');
            exit;
        }
        return (int) $id;
    }

    /** @param array<string, mixed> $source */
    protected function rememberOld(array $source): void
    {
        unset($source['_token'], $source['password'], $source['password_confirmation']);
        $_SESSION['_old'] = $source;
    }
}
