<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Warehouse;
use App\Repositories\WarehouseRepository;

final class WarehouseService
{
    public function __construct(
        private readonly WarehouseRepository $repository =
            new WarehouseRepository(),
        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Warehouse>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(
        int $companyId,
        array $filters
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        $status = strtolower(
            trim((string) ($filters['status'] ?? ''))
        );

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = (int) ($filters['per_page'] ?? 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $onlyDeleted = filter_var(
            $filters['deleted'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (mb_strlen($search) > 160) {
            throw new ValidationException(
                'Search text must be 160 characters or fewer.'
            );
        }

        if (
            $status !== ''
            && !in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {
            throw new ValidationException(
                'Invalid warehouse status filter.'
            );
        }

        return $this->repository->paginate(
            $companyId,
            $search,
            $status,
            $page,
            $perPage,
            $onlyDeleted
        );
    }

    public function find(
        int $companyId,
        int $warehouseId,
        bool $includeDeleted = false
    ): Warehouse {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        $warehouse = $this->repository->find(
            $companyId,
            $warehouseId,
            $includeDeleted
        );

        if (!$warehouse instanceof Warehouse) {
            throw new ValidationException(
                'Warehouse not found.'
            );
        }

        return $warehouse;
    }

    public function findDefault(
        int $companyId
    ): ?Warehouse {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        return $this->repository->findDefault(
            $companyId
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     location_name: string|null,
     *     is_default: bool,
     *     allow_negative_stock: bool
     * }>
     */
    public function activeOptions(
        int $companyId
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        return $this->repository->activeOptions(
            $companyId
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): Warehouse {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $data = $this->validateWarehouse(
            $companyId,
            $input
        );

        /*
         * The first active warehouse for a company must
         * automatically become its default warehouse.
         */
        $hasActiveWarehouse = $this->repository->countActive(
            $companyId
        ) > 0;

        if (!$hasActiveWarehouse) {
            $data['is_default'] = true;
            $data['status'] = 'active';
        }

        if (
            $data['is_default']
            && $data['status'] !== 'active'
        ) {
            throw new ValidationException(
                'The default warehouse must be active.'
            );
        }

        $warehouse = $this->repository->transaction(
            function (
                WarehouseRepository $repository
            ) use (
                $companyId,
                $userId,
                $data
            ): Warehouse {
                if ($data['is_default']) {
                    $repository->clearDefault(
                        $companyId,
                        $userId
                    );
                }

                return $repository->create([
                    'company_id' => $companyId,
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'location_name' => $data['location_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'address' => $data['address'],
                    'manager_name' => $data['manager_name'],
                    'is_default' => $data['is_default'],
                    'allow_negative_stock' =>
                        $data['allow_negative_stock'],
                    'notes' => $data['notes'],
                    'status' => $data['status'],
                    'created_by' => $userId,
                ]);
            }
        );

        $this->audit->record(
            'warehouses.created',
            'warehouse',
            $warehouse->id(),
            [
                'warehouse' => $warehouse->toArray(),
            ]
        );

        return $warehouse;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $warehouseId,
        int $userId,
        array $input
    ): Warehouse {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $existing = $this->find(
            $companyId,
            $warehouseId
        );

        $data = $this->validateWarehouse(
            $companyId,
            $input,
            $warehouseId
        );

        if (
            $existing->isDefault()
            && !$data['is_default']
        ) {
            throw new ValidationException(
                'The default warehouse cannot be unset directly. '
                . 'Set another warehouse as default first.'
            );
        }

        if (
            $existing->isDefault()
            && $data['status'] !== 'active'
        ) {
            throw new ValidationException(
                'The default warehouse cannot be inactive.'
            );
        }

        if (
            $data['is_default']
            && $data['status'] !== 'active'
        ) {
            throw new ValidationException(
                'The default warehouse must be active.'
            );
        }

        if (
            $existing->isActive()
            && $data['status'] === 'inactive'
            && $this->repository->countActive(
                $companyId,
                $warehouseId
            ) < 1
        ) {
            throw new ValidationException(
                'At least one active warehouse is required.'
            );
        }

        $updated = $this->repository->transaction(
            function (
                WarehouseRepository $repository
            ) use (
                $companyId,
                $warehouseId,
                $userId,
                $data
            ): Warehouse {
                if ($data['is_default']) {
                    $repository->clearDefault(
                        $companyId,
                        $userId,
                        $warehouseId
                    );
                }

                $warehouse = $repository->update(
                    $companyId,
                    $warehouseId,
                    [
                        'name' => $data['name'],
                        'code' => $data['code'],
                        'location_name' =>
                            $data['location_name'],
                        'phone' => $data['phone'],
                        'email' => $data['email'],
                        'address' => $data['address'],
                        'manager_name' =>
                            $data['manager_name'],
                        'is_default' =>
                            $data['is_default'],
                        'allow_negative_stock' =>
                            $data['allow_negative_stock'],
                        'notes' => $data['notes'],
                        'status' => $data['status'],
                        'updated_by' => $userId,
                    ]
                );

                if (!$warehouse instanceof Warehouse) {
                    throw new ValidationException(
                        'Warehouse could not be updated.'
                    );
                }

                return $warehouse;
            }
        );

        $this->audit->record(
            'warehouses.updated',
            'warehouse',
            $warehouseId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $warehouseId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $warehouse = $this->find(
            $companyId,
            $warehouseId
        );

        if ($warehouse->isDefault()) {
            throw new ValidationException(
                'The default warehouse cannot be deleted. '
                . 'Set another warehouse as default first.'
            );
        }

        if (
            $warehouse->isActive()
            && $this->repository->countActive(
                $companyId,
                $warehouseId
            ) < 1
        ) {
            throw new ValidationException(
                'The last active warehouse cannot be deleted.'
            );
        }

        $deleted = $this->repository->softDelete(
            $companyId,
            $warehouseId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Warehouse could not be deleted.'
            );
        }

        $this->audit->record(
            'warehouses.deleted',
            'warehouse',
            $warehouseId,
            [
                'warehouse' => $warehouse->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $warehouseId,
        int $userId
    ): Warehouse {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $warehouseId,
            'Warehouse ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $warehouse = $this->find(
            $companyId,
            $warehouseId,
            true
        );

        if (!$warehouse->isDeleted()) {
            throw new ValidationException(
                'Warehouse is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $warehouse->code(),
                $warehouseId
            )
        ) {
            throw new ValidationException(
                'Another warehouse is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $warehouse->name(),
                $warehouseId
            )
        ) {
            throw new ValidationException(
                'Another warehouse is already using this name.'
            );
        }

        $restored = $this->repository->transaction(
            function (
                WarehouseRepository $repository
            ) use (
                $companyId,
                $warehouseId,
                $userId,
                $warehouse
            ): Warehouse {
                $success = $repository->restore(
                    $companyId,
                    $warehouseId,
                    $userId
                );

                if (!$success) {
                    throw new ValidationException(
                        'Warehouse could not be restored.'
                    );
                }

                $restoredWarehouse = $repository->find(
                    $companyId,
                    $warehouseId
                );

                if (!$restoredWarehouse instanceof Warehouse) {
                    throw new ValidationException(
                        'Restored warehouse could not be reloaded.'
                    );
                }

                /*
                 * If the company currently has no default warehouse,
                 * the restored active warehouse becomes the default.
                 */
                if (
                    $restoredWarehouse->isActive()
                    && $repository->findDefault(
                        $companyId
                    ) === null
                ) {
                    $restoredWarehouse = $repository->update(
                        $companyId,
                        $warehouseId,
                        [
                            'name' =>
                                $restoredWarehouse->name(),
                            'code' =>
                                $restoredWarehouse->code(),
                            'location_name' =>
                                $restoredWarehouse->locationName(),
                            'phone' =>
                                $restoredWarehouse->phone(),
                            'email' =>
                                $restoredWarehouse->email(),
                            'address' =>
                                $restoredWarehouse->address(),
                            'manager_name' =>
                                $restoredWarehouse->managerName(),
                            'is_default' => true,
                            'allow_negative_stock' =>
                                $restoredWarehouse
                                    ->allowsNegativeStock(),
                            'notes' =>
                                $restoredWarehouse->notes(),
                            'status' =>
                                $restoredWarehouse->status(),
                            'updated_by' => $userId,
                        ]
                    );

                    if (
                        !$restoredWarehouse
                        instanceof Warehouse
                    ) {
                        throw new ValidationException(
                            'Warehouse default status '
                            . 'could not be restored.'
                        );
                    }
                }

                return $restoredWarehouse;
            }
        );

        $this->audit->record(
            'warehouses.restored',
            'warehouse',
            $warehouseId,
            [
                'warehouse' => $restored->toArray(),
            ]
        );

        return $restored;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     location_name: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     manager_name: string|null,
     *     is_default: bool,
     *     allow_negative_stock: bool,
     *     notes: string|null,
     *     status: string
     * }
     */
    private function validateWarehouse(
        int $companyId,
        array $input,
        ?int $warehouseId = null
    ): array {
        $name = trim(
            (string) ($input['name'] ?? '')
        );

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
        );

        $locationName = trim(
            (string) ($input['location_name'] ?? '')
        );

        $phone = trim(
            (string) ($input['phone'] ?? '')
        );

        $email = mb_strtolower(
            trim((string) ($input['email'] ?? ''))
        );

        $address = trim(
            (string) ($input['address'] ?? '')
        );

        $managerName = trim(
            (string) ($input['manager_name'] ?? '')
        );

        $notes = trim(
            (string) ($input['notes'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        $isDefault = $this->booleanInput(
            $input['is_default'] ?? false
        );

        $allowNegativeStock = $this->booleanInput(
            $input['allow_negative_stock'] ?? false
        );

        if ($name === '') {
            throw new ValidationException(
                'Warehouse name is required.'
            );
        }

        if (mb_strlen($name) > 160) {
            throw new ValidationException(
                'Warehouse name must be 160 characters or fewer.'
            );
        }

        if (
            $code === ''
            || mb_strlen($code) > 80
            || !preg_match(
                '/^[a-z0-9][a-z0-9_-]*$/',
                $code
            )
        ) {
            throw new ValidationException(
                'Warehouse code must start with a letter or number '
                . 'and may contain lowercase letters, numbers, '
                . 'dashes and underscores.'
            );
        }

        if (
            $locationName !== ''
            && mb_strlen($locationName) > 160
        ) {
            throw new ValidationException(
                'Location name must be 160 characters or fewer.'
            );
        }

        if (
            $managerName !== ''
            && mb_strlen($managerName) > 160
        ) {
            throw new ValidationException(
                'Manager name must be 160 characters or fewer.'
            );
        }

        if (
            $phone !== ''
            && mb_strlen($phone) > 50
        ) {
            throw new ValidationException(
                'Phone number must be 50 characters or fewer.'
            );
        }

        if (
            $phone !== ''
            && !preg_match(
                '/^[0-9+().\-\s]+$/',
                $phone
            )
        ) {
            throw new ValidationException(
                'Phone number contains invalid characters.'
            );
        }

        if ($email !== '') {
            if (mb_strlen($email) > 190) {
                throw new ValidationException(
                    'Email address must be 190 characters or fewer.'
                );
            }

            if (
                filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new ValidationException(
                    'Warehouse email address is invalid.'
                );
            }
        }

        if (
            $address !== ''
            && mb_strlen($address) > 500
        ) {
            throw new ValidationException(
                'Address must be 500 characters or fewer.'
            );
        }

        if (
            !in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {
            throw new ValidationException(
                'Warehouse status must be active or inactive.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $code,
                $warehouseId
            )
        ) {
            throw new ValidationException(
                'Warehouse code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $warehouseId
            )
        ) {
            throw new ValidationException(
                'Warehouse name is already in use.'
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'location_name' => $locationName === ''
                ? null
                : $locationName,
            'phone' => $phone === ''
                ? null
                : $phone,
            'email' => $email === ''
                ? null
                : $email,
            'address' => $address === ''
                ? null
                : $address,
            'manager_name' => $managerName === ''
                ? null
                : $managerName,
            'is_default' => $isDefault,
            'allow_negative_stock' => $allowNegativeStock,
            'notes' => $notes === ''
                ? null
                : $notes,
            'status' => $status,
        ];
    }

    private function booleanInput(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                ['1', 'true', 'yes', 'on'],
                true
            );
        }

        return false;
    }

    private function validatePositiveId(
        int $value,
        string $field
    ): void {
        if ($value < 1) {
            throw new ValidationException(
                "{$field} must be greater than zero."
            );
        }
    }
}