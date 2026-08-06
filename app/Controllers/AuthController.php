<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Security\Csrf;
use App\Services\AuthService;

final class AuthController
{
    public function showLogin(): void
    {
        $auth = new AuthService();
        if ($auth->check()) {
            redirect('/dashboard');
        }

        View::render('auth.login', [
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors.419');
            return;
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $_SESSION['_old']['login'] = $login;

        $result = (new AuthService())->attempt(
            $login,
            (string) ($_POST['password'] ?? '')
        );

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect('/login');
        }

        unset($_SESSION['_old']);
        redirect(($result['requires_password_change'] ?? false) ? '/account/password' : '/dashboard');
    }

    public function showPassword(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        if ($user === null) {
            flash('error', 'Please sign in to continue.');
            redirect('/login');
        }

        View::render('auth.password', [
            'currentUser' => $user,
            'required' => (int) ($user['must_change_password'] ?? 0) === 1,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function changePassword(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors.419');
            return;
        }

        $auth = new AuthService();
        $user = $auth->user();
        if ($user === null) {
            flash('error', 'Please sign in to continue.');
            redirect('/login');
        }

        try {
            $auth->changeOwnPassword(
                (int) $user['id'],
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirmation'] ?? '')
            );
            flash('success', 'Password changed successfully. Other sessions were signed out.');
            redirect('/dashboard');
        } catch (\App\Exceptions\ValidationException $exception) {
            flash('error', $exception->getMessage());
            redirect('/account/password');
        }
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors.419');
            return;
        }

        (new AuthService())->logout();
        redirect('/login');
    }
}
