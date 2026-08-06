<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class AdminRepository
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginateUsers(
        int $companyId,
        string $search,
        string $status,
        int $roleId,
        int $page,
        int $perPage = 20
    ): array {
        $conditions = ['u.company_id = :company_id', 'u.deleted_at IS NULL'];
        $parameters = ['company_id' => $companyId];

        if ($search !== '') {
            $conditions[] = '(u.username LIKE :search_username OR u.full_name LIKE :search_name OR u.email LIKE :search_email)';
            $parameters['search_username'] = '%' . $search . '%';
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_email'] = '%' . $search . '%';
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = 'u.status = :status';
            $parameters['status'] = $status;
        }
        if ($roleId > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM user_roles urf WHERE urf.user_id = u.id AND urf.role_id = :role_id)';
            $parameters['role_id'] = $roleId;
        }

        $where = implode(' AND ', $conditions);
        $count = Database::connection()->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $statement = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email, u.full_name, u.status, u.last_login_at,
                    b.name AS branch_name,
                    GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS role_names
             FROM users u
             LEFT JOIN branches b ON b.id = u.branch_id
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE {$where}
             GROUP BY u.id, u.username, u.email, u.full_name, u.status, u.last_login_at, b.name
             ORDER BY u.full_name, u.id
             LIMIT :limit OFFSET :offset"
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function findUser(int $companyId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_id, branch_id, username, email, full_name, status
             FROM users WHERE id = :id AND company_id = :company_id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $userId, 'company_id' => $companyId]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return null;
        }

        $roles = Database::connection()->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id');
        $roles->execute(['user_id' => $userId]);
        $user['role_ids'] = array_map('intval', $roles->fetchAll(PDO::FETCH_COLUMN));
        return $user;
    }

    /** @return list<array<string, mixed>> */
    public function branches(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, name FROM branches WHERE company_id = :company_id AND status = 'active' ORDER BY name"
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function roles(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.id, r.name, r.code, r.description, r.is_system, COUNT(ur.user_id) AS user_count
             FROM roles r
             LEFT JOIN user_roles ur ON ur.role_id = r.id
             WHERE r.company_id = :company_id
             GROUP BY r.id, r.name, r.code, r.description, r.is_system
             ORDER BY r.is_system DESC, r.name'
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function assignableRoles(int $companyId, bool $canManageRoles): array
    {
        $roles = $this->roles($companyId);
        if ($canManageRoles) {
            return $roles;
        }
        return array_values(array_filter(
            $roles,
            static fn (array $role): bool => $role['code'] !== 'super_admin'
        ));
    }

    /** @return list<array<string, mixed>> */
    public function permissions(): array
    {
        return Database::connection()->query(
            'SELECT id, name, code, module, description FROM permissions ORDER BY module, name'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findRole(int $companyId, int $roleId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_id, name, code, description, is_system
             FROM roles WHERE id = :id AND company_id = :company_id LIMIT 1'
        );
        $statement->execute(['id' => $roleId, 'company_id' => $companyId]);
        $role = $statement->fetch();
        if (!is_array($role)) {
            return null;
        }

        $permissions = Database::connection()->prepare(
            'SELECT permission_id FROM role_permissions WHERE role_id = :role_id'
        );
        $permissions->execute(['role_id' => $roleId]);
        $role['permission_ids'] = array_map('intval', $permissions->fetchAll(PDO::FETCH_COLUMN));
        return $role;
    }

    public function usernameExists(string $username, ?int $exceptUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $parameters = ['username' => $username];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptUserId;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    public function emailExists(string $email, ?int $exceptUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $parameters = ['email' => $email];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptUserId;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    public function roleCodeExists(int $companyId, string $code, ?int $exceptRoleId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM roles WHERE company_id = :company_id AND code = :code';
        $parameters = ['company_id' => $companyId, 'code' => $code];
        if ($exceptRoleId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptRoleId;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @param list<int> $ids */
    public function validRoleIds(int $companyId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "SELECT id FROM roles WHERE company_id = ? AND id IN ({$placeholders})"
        );
        $statement->execute([$companyId, ...$ids]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $ids */
    public function validPermissionIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "SELECT id FROM permissions WHERE id IN ({$placeholders})"
        );
        $statement->execute($ids);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function userHasRoleCode(int $userId, string $code): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.code = :code'
        );
        $statement->execute(['user_id' => $userId, 'code' => $code]);
        return (int) $statement->fetchColumn() > 0;
    }
}
