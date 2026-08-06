<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Unit;
use App\Repositories\UnitRepository;

final class UnitService
{
    public function __construct(
        private readonly UnitRepository $repository = new UnitRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Unit>,
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
        $this->validatePositiveId($companyId, 'Company ID');

        $search = trim((string) ($filters['search'] ?? ''));

        $status = strtolower(
            trim((string) ($filters['status'] ?? ''))
        );

        $page = max(1, (int) ($filters['page'] ?? 1));

        $perPage = (int) ($filters['per_page'] ?? 20);

        $onlyDeleted = filter_var(
            $filters['deleted'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (mb_strlen($search) > 120) {
            throw new ValidationException(
                'Search text must be 120 characters or fewer.'
            );
        }

        if (
            $status !== ''
            && !in_array($status, ['active', 'inactive'], true)
        ) {
            throw new ValidationException(
                'Invalid unit status filter.'
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
        int $unitId,
        bool $includeDeleted = false
    ): Unit {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($unitId, 'Unit ID');

        $unit = $this->repository->find(
            $companyId,
            $unitId,
            $includeDeleted
        );

        if (!$unit instanceof Unit) {
            throw new ValidationException('Unit not found.');
        }

        return $unit;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     decimal_places: int
     * }>
     */
    public function activeOptions(int $companyId): array
    {
        $this->validatePositiveId($companyId, 'Company ID');

        return $this->repository->activeOptions($companyId);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): Unit {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($userId, 'User ID');

        $data = $this->validateUnit(
            $companyId,
            $input
        );

        $unit = $this->repository->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => $data['code'],
            'symbol' => $data['symbol'],
            'description' => $data['description'],
            'decimal_places' => $data['decimal_places'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'units.created',
            'unit',
            $unit->id(),
            [
                'unit' => $unit->toArray(),
            ]
        );

        return $unit;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $unitId,
        int $userId,
        array $input
    ): Unit {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($unitId, 'Unit ID');
        $this->validatePositiveId($userId, 'User ID');

        $existing = $this->find(
            $companyId,
            $unitId
        );

        $data = $this->validateUnit(
            $companyId,
            $input,
            $unitId
        );

        $updated = $this->repository->update(
            $companyId,
            $unitId,
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'symbol' => $data['symbol'],
                'description' => $data['description'],
                'decimal_places' => $data['decimal_places'],
                'sort_order' => $data['sort_order'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Unit) {
            throw new ValidationException(
                'Unit could not be updated.'
            );
        }

        $this->audit->record(
            'units.updated',
            'unit',
            $unitId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $unitId,
        int $userId
    ): void {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($unitId, 'Unit ID');
        $this->validatePositiveId($userId, 'User ID');

        $unit = $this->find(
            $companyId,
            $unitId
        );

        $deleted = $this->repository->softDelete(
            $companyId,
            $unitId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Unit could not be deleted.'
            );
        }

        $this->audit->record(
            'units.deleted',
            'unit',
            $unitId,
            [
                'unit' => $unit->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $unitId,
        int $userId
    ): Unit {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($unitId, 'Unit ID');
        $this->validatePositiveId($userId, 'User ID');

        $unit = $this->find(
            $companyId,
            $unitId,
            true
        );

        if (!$unit->isDeleted()) {
            throw new ValidationException(
                'Unit is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $unit->code(),
                $unitId
            )
        ) {
            throw new ValidationException(
                'Another unit is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $unit->name(),
                $unitId
            )
        ) {
            throw new ValidationException(
                'Another unit is already using this name.'
            );
        }

        $restored = $this->repository->restore(
            $companyId,
            $unitId,
            $userId
        );

        if (!$restored) {
            throw new ValidationException(
                'Unit could not be restored.'
            );
        }

        $unit = $this->find(
            $companyId,
            $unitId
        );

        $this->audit->record(
            'units.restored',
            'unit',
            $unitId,
            [
                'unit' => $unit->toArray(),
            ]
        );

        return $unit;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     description: string|null,
     *     decimal_places: int,
     *     sort_order: int,
     *     status: string
     * }
     */
    private function validateUnit(
        int $companyId,
        array $input,
        ?int $unitId = null
    ): array {
        $name = trim((string) ($input['name'] ?? ''));

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
        );

        $symbol = trim(
            (string) ($input['symbol'] ?? '')
        );

        $description = trim(
            (string) ($input['description'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        $decimalPlacesValue = $input['decimal_places'] ?? 0;
        $sortOrderValue = $input['sort_order'] ?? 0;

        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(
                'Unit name is required and must be 120 characters or fewer.'
            );
        }

        if (
            $code === ''
            || mb_strlen($code) > 30
            || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)
        ) {
            throw new ValidationException(
                'Unit code must start with a letter or number and may contain lowercase letters, numbers, dashes and underscores.'
            );
        }

        if ($symbol !== '' && mb_strlen($symbol) > 20) {
            throw new ValidationException(
                'Unit symbol must be 20 characters or fewer.'
            );
        }

        if (
            $description !== ''
            && mb_strlen($description) > 500
        ) {
            throw new ValidationException(
                'Description must be 500 characters or fewer.'
            );
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new ValidationException(
                'Unit status must be active or inactive.'
            );
        }

        if (
            filter_var(
                $decimalPlacesValue,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new ValidationException(
                'Decimal places must be a whole number.'
            );
        }

        $decimalPlaces = (int) $decimalPlacesValue;

        if ($decimalPlaces < 0 || $decimalPlaces > 6) {
            throw new ValidationException(
                'Decimal places must be between 0 and 6.'
            );
        }

        if (
            filter_var(
                $sortOrderValue,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new ValidationException(
                'Sort order must be a whole number.'
            );
        }

        $sortOrder = (int) $sortOrderValue;

        if ($sortOrder < 0 || $sortOrder > 999999) {
            throw new ValidationException(
                'Sort order must be between 0 and 999999.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $code,
                $unitId
            )
        ) {
            throw new ValidationException(
                'Unit code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $unitId
            )
        ) {
            throw new ValidationException(
                'Unit name is already in use.'
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'symbol' => $symbol === '' ? null : $symbol,
            'description' => $description === ''
                ? null
                : $description,
            'decimal_places' => $decimalPlaces,
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
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