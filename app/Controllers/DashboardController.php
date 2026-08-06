<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\AuthService;

final class DashboardController
{
    public function index(): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            flash('error', 'Please sign in to continue.');
            redirect('/login');
        }

        if (!$auth->can('dashboard.view')) {
            http_response_code(403);
            View::render('errors.403');
            return;
        }

        View::render('dashboard.index', [
            'user' => $user,
        ]);
    }
}
