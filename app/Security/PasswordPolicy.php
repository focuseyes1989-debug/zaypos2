<?php

declare(strict_types=1);

namespace App\Security;

use App\Exceptions\ValidationException;

final class PasswordPolicy
{
    public static function validate(string $password, string $confirmation = ''): void
    {
        $minimumLength = max(10, (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 12));

        if (mb_strlen($password) < $minimumLength) {
            throw new ValidationException("Password must be at least {$minimumLength} characters.");
        }
        if ($confirmation !== '' && !hash_equals($password, $confirmation)) {
            throw new ValidationException('Passwords do not match.');
        }
        if (!preg_match('/[a-z]/', $password)
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/\d/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new ValidationException(
                'Password must contain uppercase, lowercase, number, and special characters.'
            );
        }
        if (preg_match('/(.)\1{3,}/u', $password)) {
            throw new ValidationException('Password contains too many repeated characters.');
        }
    }
}
