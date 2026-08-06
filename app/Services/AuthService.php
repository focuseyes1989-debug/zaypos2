<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Repositories\UserRepository;
use App\Security\Csrf;
use App\Security\PasswordPolicy;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class AuthService
{
    private static ?array $currentUser = null;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /** @return array{success: bool, message: string, requires_password_change?: bool} */
    public function attempt(string $login, string $password): array
    {
        $login = mb_strtolower(trim($login));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($login === '' || $password === '') {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }
        if ($this->isLockedOut($login, $ipAddress)) {
            $this->audit->record('auth.login_blocked', 'user', null, ['login' => $login]);
            return ['success' => false, 'message' => 'Too many login attempts. Please wait and try again.'];
        }

        $user = $this->users->findByLogin($login);
        $valid = is_array($user)
            && ($user['status'] ?? null) === 'active'
            && password_verify($password, (string) $user['password_hash']);

        $this->recordAttempt($login, $ipAddress, $valid, $valid ? (int) $user['id'] : null);
        if (!$valid) {
            $this->audit->record('auth.login_failed', 'user', is_array($user) ? (int) $user['id'] : null, [
                'login' => $login,
            ]);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->rehashPassword((int) $user['id'], $password);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity_at'] = time();
        $_SESSION['session_started_at'] = time();
        $_SESSION['session_rotated_at'] = time();
        Csrf::regenerate();

        $this->createSessionRecord((int) $user['id']);
        $database = Database::connection();
        $database->prepare(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute(['id' => (int) $user['id']]);
        $database->prepare(
            'DELETE FROM login_attempts WHERE login_identifier = :login AND successful = 0'
        )->execute(['login' => $login]);

        self::$currentUser = null;
        $this->audit->record('auth.login_succeeded', 'user', (int) $user['id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'requires_password_change' => (int) ($user['must_change_password'] ?? 0) === 1,
        ];
    }

    public function logout(): void
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        if ($userId !== null) {
            $this->audit->record('auth.logout', 'user', $userId);
        }

        $this->revokeCurrentSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$currentUser = null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        if (!$this->sessionRecordIsValid((int) $_SESSION['user_id'])) {
            $this->logout();
            return null;
        }

        $idleLifetime = max(1, (int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 480)) * 60;
        $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);
        if ($lastActivity <= 0 || time() - $lastActivity > $idleLifetime) {
            $this->logout();
            return null;
        }

        $absoluteLifetime = max(1, (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME_MINUTES'] ?? 720)) * 60;
        $startedAt = (int) ($_SESSION['session_started_at'] ?? 0);
        if ($startedAt <= 0 || time() - $startedAt > $absoluteLifetime) {
            $this->logout();
            return null;
        }

        $user = $this->users->findActiveById((int) $_SESSION['user_id']);
        if ($user === null) {
            $this->logout();
            return null;
        }

        $this->rotateSessionIfDue((int) $user['id']);
        $_SESSION['last_activity_at'] = time();
        $this->touchSessionRecord();
        self::$currentUser = $user;
        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        return $user !== null && in_array($permission, $user['permissions'] ?? [], true);
    }

    public function changeOwnPassword(int $userId, string $currentPassword, string $password, string $confirmation): void
    {
        $record = $this->users->findPasswordRecord($userId)
            ?? throw new ValidationException('User account was not found.');
        if (!password_verify($currentPassword, (string) $record['password_hash'])) {
            throw new ValidationException('Current password is incorrect.');
        }
        PasswordPolicy::validate($password, $confirmation);
        if (password_verify($password, (string) $record['password_hash'])) {
            throw new ValidationException('New password must be different from the current password.');
        }

        $database = Database::connection();
        try {
            $database->beginTransaction();
            $database->prepare(
                'UPDATE users
                 SET password_hash = :password_hash, password_changed_at = UTC_TIMESTAMP(),
                     must_change_password = 0, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            )->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);
            $database->prepare(
                'UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP()
                 WHERE user_id = :user_id AND session_token_hash <> :current_hash AND revoked_at IS NULL'
            )->execute([
                'user_id' => $userId,
                'current_hash' => hash('sha256', session_id()),
            ]);
            $database->commit();
            self::$currentUser = null;
            $this->audit->record('auth.password_changed', 'user', $userId);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function isLockedOut(string $login, string $ipAddress): bool
    {
        $minutes = max(1, (int) ($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15));
        $maximum = max(1, (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("-{$minutes} minutes")->format('Y-m-d H:i:s');
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0
               AND (login_identifier = :login OR ip_address = :ip_address)
               AND attempted_at >= :cutoff'
        );
        $statement->execute(['login' => $login, 'ip_address' => $ipAddress, 'cutoff' => $cutoff]);
        return (int) $statement->fetchColumn() >= $maximum;
    }

    private function recordAttempt(string $login, string $ipAddress, bool $successful, ?int $userId): void
    {
        Database::connection()->prepare(
            'INSERT INTO login_attempts
             (user_id, login_identifier, ip_address, successful, attempted_at)
             VALUES (:user_id, :login, :ip_address, :successful, UTC_TIMESTAMP())'
        )->execute([
            'user_id' => $userId,
            'login' => $login,
            'ip_address' => $ipAddress,
            'successful' => $successful ? 1 : 0,
        ]);
    }

    private function createSessionRecord(int $userId): void
    {
        $idleMinutes = max(1, (int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 480));
        $absoluteMinutes = max($idleMinutes, (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME_MINUTES'] ?? 720));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $statement = Database::connection()->prepare(
            'INSERT INTO user_sessions
             (user_id, session_token_hash, ip_address, user_agent, user_agent_hash,
              last_activity_at, expires_at, absolute_expires_at, created_at)
             VALUES
             (:user_id, :token_hash, :ip_address, :user_agent, :user_agent_hash,
              UTC_TIMESTAMP(), :expires_at, :absolute_expires_at, UTC_TIMESTAMP())'
        );
        $userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => hash('sha256', session_id()),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $userAgent,
            'user_agent_hash' => hash('sha256', $userAgent),
            'expires_at' => $now->modify("+{$idleMinutes} minutes")->format('Y-m-d H:i:s'),
            'absolute_expires_at' => $now->modify("+{$absoluteMinutes} minutes")->format('Y-m-d H:i:s'),
        ]);
    }

    private function rehashPassword(int $userId, string $password): void
    {
        Database::connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
    }

    private function sessionRecordIsValid(int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT user_agent_hash FROM user_sessions
             WHERE user_id = :user_id AND session_token_hash = :token_hash
               AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()
               AND absolute_expires_at > UTC_TIMESTAMP() LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'token_hash' => hash('sha256', session_id())]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return false;
        }
        $bindUserAgent = filter_var($_ENV['SESSION_BIND_USER_AGENT'] ?? true, FILTER_VALIDATE_BOOL);
        if (!$bindUserAgent) {
            return true;
        }
        $currentHash = hash('sha256', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));
        return hash_equals((string) ($row['user_agent_hash'] ?? ''), $currentHash);
    }

    private function touchSessionRecord(): void
    {
        $minutes = max(1, (int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 480));
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$minutes} minutes")->format('Y-m-d H:i:s');
        Database::connection()->prepare(
            'UPDATE user_sessions SET last_activity_at = UTC_TIMESTAMP(), expires_at = :expires_at
             WHERE session_token_hash = :token_hash AND revoked_at IS NULL'
        )->execute(['expires_at' => $expiresAt, 'token_hash' => hash('sha256', session_id())]);
    }

    private function rotateSessionIfDue(int $userId): void
    {
        $minutes = max(5, (int) ($_ENV['SESSION_ROTATE_MINUTES'] ?? 30));
        $rotatedAt = (int) ($_SESSION['session_rotated_at'] ?? 0);
        if ($rotatedAt > 0 && time() - $rotatedAt < $minutes * 60) {
            return;
        }
        $oldHash = hash('sha256', session_id());
        session_regenerate_id(true);
        $newHash = hash('sha256', session_id());
        Database::connection()->prepare(
            'UPDATE user_sessions SET session_token_hash = :new_hash
             WHERE user_id = :user_id AND session_token_hash = :old_hash AND revoked_at IS NULL'
        )->execute(['new_hash' => $newHash, 'user_id' => $userId, 'old_hash' => $oldHash]);
        $_SESSION['session_rotated_at'] = time();
        Csrf::regenerate();
    }

    private function revokeCurrentSession(): void
    {
        Database::connection()->prepare(
            'UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP()
             WHERE session_token_hash = :token_hash AND revoked_at IS NULL'
        )->execute(['token_hash' => hash('sha256', session_id())]);
    }
}
