<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Exceptions\ValidationException;
use App\Repositories\AdminRepository;
use PDO;
use Throwable;

final class RoleManagementService
{
    public function __construct(
        private readonly AdminRepository $repository = new AdminRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /** @param array<string, mixed> $input */
    public function create(int $companyId, array $input): int
    {
        $data = $this->validate($companyId, $input, null);
        $database = Database::connection();
        try {
            $database->beginTransaction();
            $database->prepare(
                'INSERT INTO roles (company_id, name, code, description, is_system, created_at, updated_at)
                 VALUES (:company_id, :name, :code, :description, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            )->execute([
                'company_id' => $companyId,
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'],
            ]);
            $roleId = (int) $database->lastInsertId();
            $this->syncPermissions($database, $roleId, $data['permission_ids']);
            $database->commit();
            $this->audit->record('roles.created', 'role', $roleId, ['code' => $data['code']]);
            return $roleId;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(int $companyId, int $roleId, array $input): void
    {
        $role = $this->repository->findRole($companyId, $roleId)
            ?? throw new ValidationException('Role not found.');
        if ((int) $role['is_system'] === 1) {
            throw new ValidationException('Built-in system roles cannot be modified.');
        }
        $data = $this->validate($companyId, $input, $roleId);
        $database = Database::connection();
        try {
            $database->beginTransaction();
            $database->prepare(
                'UPDATE roles SET name = :name, code = :code, description = :description, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id AND company_id = :company_id'
            )->execute([
                'name' => $data['name'], 'code' => $data['code'], 'description' => $data['description'],
                'id' => $roleId, 'company_id' => $companyId,
            ]);
            $this->syncPermissions($database, $roleId, $data['permission_ids']);
            $database->commit();
            $this->audit->record('roles.updated', 'role', $roleId, ['code' => $data['code']]);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    private function validate(int $companyId, array $input, ?int $roleId): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $code = mb_strtolower(trim((string) ($input['code'] ?? '')));
        $description = trim((string) ($input['description'] ?? ''));
        $permissionIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($input['permission_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));

        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationException('Role name is required and must be 100 characters or fewer.');
        }
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $code)) {
            throw new ValidationException('Role code must be 3-80 characters using a-z, 0-9, dot, underscore or dash.');
        }
        if ($this->repository->roleCodeExists($companyId, $code, $roleId)) {
            throw new ValidationException('That role code is already in use.');
        }
        if (mb_strlen($description) > 255) {
            throw new ValidationException('Description must be 255 characters or fewer.');
        }
        if ($permissionIds === [] || count($this->repository->validPermissionIds($permissionIds)) !== count($permissionIds)) {
            throw new ValidationException('Select at least one valid permission.');
        }

        return [
            'name' => $name,
            'code' => $code,
            'description' => $description === '' ? null : $description,
            'permission_ids' => $permissionIds,
        ];
    }

    /** @param list<int> $permissionIds */
    private function syncPermissions(PDO $database, int $roleId, array $permissionIds): void
    {
        $database->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')->execute(['role_id' => $roleId]);
        $statement = $database->prepare(
            'INSERT INTO role_permissions (role_id, permission_id, created_at)
             VALUES (:role_id, :permission_id, UTC_TIMESTAMP())'
        );
        foreach ($permissionIds as $permissionId) {
            $statement->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }
}
