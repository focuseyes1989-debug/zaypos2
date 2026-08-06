<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Repositories\AdminRepository;
use App\Security\PasswordPolicy;
use PDO;
use Throwable;

final class UserManagementService
{
    public function __construct(
        private readonly AdminRepository $repository = new AdminRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /** @param array<string, mixed> $input */
    public function create(int $companyId, bool $canManageRoles, array $input): int
    {
        $data = $this->validateUser($companyId, $input, null, true, $canManageRoles);
        $database = Database::connection();

        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                'INSERT INTO users
                    (company_id, branch_id, username, email, full_name, password_hash, status,
                     password_changed_at, must_change_password, created_at, updated_at)
                 VALUES
                    (:company_id, :branch_id, :username, :email, :full_name, :password_hash, :status,
                     UTC_TIMESTAMP(), 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'username' => $data['username'],
                'email' => $data['email'],
                'full_name' => $data['full_name'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'status' => $data['status'],
            ]);
            $userId = (int) $database->lastInsertId();
            $this->syncRoles($database, $userId, $data['role_ids']);
            $database->commit();

            $this->audit->record('users.created', 'user', $userId, [
                'username' => $data['username'],
                'role_ids' => $data['role_ids'],
            ]);
            return $userId;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(
        int $companyId,
        int $userId,
        int $currentUserId,
        bool $canManageRoles,
        array $input
    ): void
    {
        $existing = $this->repository->findUser($companyId, $userId)
            ?? throw new ValidationException('User not found.');
        $targetIsSuperAdmin = $this->hasRoleCode($existing['role_ids'], 'super_admin');
        if ($targetIsSuperAdmin && !$canManageRoles) {
            throw new ValidationException('Only a Super Admin can modify a Super Admin account.');
        }
        $data = $this->validateUser($companyId, $input, $userId, false, $canManageRoles);

        if ($userId === $currentUserId && $data['status'] !== 'active') {
            throw new ValidationException('You cannot disable your own account.');
        }

        $removesSuperAdmin = $targetIsSuperAdmin
            && !$this->hasRoleCode($data['role_ids'], 'super_admin');
        if ($targetIsSuperAdmin
            && $existing['status'] === 'active'
            && ($removesSuperAdmin || $data['status'] !== 'active')
            && $this->countActiveSuperAdmins($companyId) <= 1) {
            throw new ValidationException('The last active Super Admin cannot be disabled or demoted.');
        }

        $database = Database::connection();
        try {
            $database->beginTransaction();
            $database->prepare(
                'UPDATE users
                 SET branch_id = :branch_id, username = :username, email = :email,
                     full_name = :full_name, status = :status, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id AND company_id = :company_id'
            )->execute([
                'branch_id' => $data['branch_id'],
                'username' => $data['username'],
                'email' => $data['email'],
                'full_name' => $data['full_name'],
                'status' => $data['status'],
                'id' => $userId,
                'company_id' => $companyId,
            ]);
            $this->syncRoles($database, $userId, $data['role_ids']);
            $database->commit();

            $this->audit->record('users.updated', 'user', $userId, [
                'role_ids' => $data['role_ids'],
                'status' => $data['status'],
            ]);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function toggleStatus(
        int $companyId,
        int $userId,
        int $currentUserId,
        bool $canManageRoles
    ): string
    {
        $user = $this->repository->findUser($companyId, $userId)
            ?? throw new ValidationException('User not found.');
        if ($userId === $currentUserId) {
            throw new ValidationException('You cannot disable your own account.');
        }
        if ($this->hasRoleCode($user['role_ids'], 'super_admin') && !$canManageRoles) {
            throw new ValidationException('Only a Super Admin can change a Super Admin account.');
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        if ($newStatus === 'inactive'
            && $this->hasRoleCode($user['role_ids'], 'super_admin')
            && $this->countActiveSuperAdmins($companyId) <= 1) {
            throw new ValidationException('The last active Super Admin cannot be disabled.');
        }

        Database::connection()->prepare(
            'UPDATE users SET status = :status, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND company_id = :company_id'
        )->execute(['status' => $newStatus, 'id' => $userId, 'company_id' => $companyId]);

        if ($newStatus === 'inactive') {
            Database::connection()->prepare('DELETE FROM user_sessions WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);
        }
        $this->audit->record('users.status_changed', 'user', $userId, ['status' => $newStatus]);
        return $newStatus;
    }

    public function resetPassword(
        int $companyId,
        int $userId,
        bool $canManageRoles,
        string $password,
        string $confirmation
    ): void
    {
        $user = $this->repository->findUser($companyId, $userId)
            ?? throw new ValidationException('User not found.');
        if ($this->hasRoleCode($user['role_ids'], 'super_admin') && !$canManageRoles) {
            throw new ValidationException('Only a Super Admin can reset a Super Admin password.');
        }
        PasswordPolicy::validate($password, $confirmation);

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $database->prepare(
                'UPDATE users
                 SET password_hash = :password_hash, password_changed_at = UTC_TIMESTAMP(),
                     must_change_password = 1, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id AND company_id = :company_id'
            )->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $userId,
                'company_id' => $companyId,
            ]);
            $database->prepare('DELETE FROM user_sessions WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);
            $database->commit();
            $this->audit->record('users.password_reset', 'user', $userId);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    private function validateUser(
        int $companyId,
        array $input,
        ?int $userId,
        bool $passwordRequired,
        bool $canManageRoles
    ): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $username = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $status = (string) ($input['status'] ?? 'active');
        $branchId = (int) ($input['branch_id'] ?? 0);
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($input['role_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        if ($fullName === '' || mb_strlen($fullName) > 150) {
            throw new ValidationException('Full name is required and must be 150 characters or fewer.');
        }
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            throw new ValidationException('Username must be 3-80 characters using a-z, 0-9, dot, underscore or dash.');
        }
        if ($this->repository->usernameExists($username, $userId)) {
            throw new ValidationException('That username is already in use.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Enter a valid email address.');
        }
        if ($email !== '' && $this->repository->emailExists($email, $userId)) {
            throw new ValidationException('That email address is already in use.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new ValidationException('Invalid account status.');
        }
        $validBranchIds = array_map(static fn (array $branch): int => (int) $branch['id'], $this->repository->branches($companyId));
        if ($branchId > 0 && !in_array($branchId, $validBranchIds, true)) {
            throw new ValidationException('Invalid branch selection.');
        }
        if ($roleIds === [] || count($this->repository->validRoleIds($companyId, $roleIds)) !== count($roleIds)) {
            throw new ValidationException('Select at least one valid role.');
        }
        if (!$canManageRoles && $this->hasRoleCode($roleIds, 'super_admin')) {
            throw new ValidationException('You are not allowed to assign the Super Admin role.');
        }
        if ($passwordRequired || $password !== '') {
            PasswordPolicy::validate($password, $confirmation);
        }

        return [
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email === '' ? null : $email,
            'status' => $status,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'role_ids' => $roleIds,
            'password' => $password,
        ];
    }

    /** @param list<int> $roleIds */
    private function syncRoles(PDO $database, int $userId, array $roleIds): void
    {
        $database->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $statement = $database->prepare(
            'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, UTC_TIMESTAMP())'
        );
        foreach ($roleIds as $roleId) {
            $statement->execute(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    /** @param list<int> $roleIds */
    private function hasRoleCode(array $roleIds, string $code): bool
    {
        if ($roleIds === []) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM roles WHERE code = ? AND id IN ({$placeholders})");
        $statement->execute([$code, ...$roleIds]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function countActiveSuperAdmins(int $companyId): int
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.company_id = :company_id AND u.status = 'active'
               AND u.deleted_at IS NULL AND r.code = 'super_admin'"
        );
        $statement->execute(['company_id' => $companyId]);
        return (int) $statement->fetchColumn();
    }
}
