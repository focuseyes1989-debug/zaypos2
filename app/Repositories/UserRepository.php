<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;

final class UserRepository
{
    /** @return array<string, mixed>|null */
    public function findByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_id, branch_id, username, email, full_name, password_hash, status, must_change_password
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );
        $statement->execute([
            'username' => $login,
            'email' => $login,
        ]);

        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_id, branch_id, username, email, full_name, status, last_login_at, must_change_password
             FROM users
             WHERE id = :id AND status = \'active\'
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        $user['roles'] = $this->rolesForUser($id);
        $user['permissions'] = $this->permissionsForUser($id);
        return $user;
    }

    /** @return array{password_hash: string}|null */
    public function findPasswordRecord(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT password_hash FROM users WHERE id = :id AND status = \'active\' LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        return is_array($record) ? $record : null;
    }

    /** @return list<string> */
    private function rolesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.code
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.code'
        );
        $statement->execute(['user_id' => $userId]);
        return array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    private function permissionsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT p.code
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id
             ORDER BY p.code'
        );
        $statement->execute(['user_id' => $userId]);
        return array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }
}
