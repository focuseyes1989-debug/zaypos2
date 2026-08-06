<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\View;
use App\Services\AuthService;
use Throwable;

final class HomeController
{
    public function index(): void
    {
        redirect((new AuthService())->check() ? '/dashboard' : '/login');
    }

    public function systemCheck(): void
    {
        $databaseStatus = 'Connected';
        $databaseVersion = 'Unknown';
        $databaseError = null;

        try {
            $connection = Database::connection();
            $result = $connection->query('SELECT VERSION() AS version')->fetch();
            $databaseVersion = (string) ($result['version'] ?? 'Unknown');
        } catch (Throwable $exception) {
            $databaseStatus = 'Connection failed';
            $databaseError = $exception->getMessage();
        }

        View::render('home', [
            'appName' => $_ENV['APP_NAME'] ?? 'ZAY POS System 2.0',
            'phpVersion' => PHP_VERSION,
            'databaseStatus' => $databaseStatus,
            'databaseVersion' => $databaseVersion,
            'databaseError' => $databaseError,
        ]);
    }
}
