<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Brand;
use App\Repositories\BrandRepository;

final class BrandService
{
    public function __construct(
        private readonly BrandRepository $repository = new BrandRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Brand>,
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
                'Invalid brand status filter.'
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
        int $brandId,
        bool $includeDeleted = false
    ): Brand {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($brandId, 'Brand ID');

        $brand = $this->repository->find(
            $companyId,
            $brandId,
            $includeDeleted
        );

        if (!$brand instanceof Brand) {
            throw new ValidationException('Brand not found.');
        }

        return $brand;
    }

    /**
     * @return list<array{id: int, name: string}>
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
    ): Brand {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($userId, 'User ID');

        $data = $this->validateBrand(
            $companyId,
            $input
        );

        $brand = $this->repository->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'brands.created',
            'brand',
            $brand->id(),
            [
                'brand' => $brand->toArray(),
            ]
        );

        return $brand;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $brandId,
        int $userId,
        array $input
    ): Brand {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($brandId, 'Brand ID');
        $this->validatePositiveId($userId, 'User ID');

        $existing = $this->find(
            $companyId,
            $brandId
        );

        $data = $this->validateBrand(
            $companyId,
            $input,
            $brandId
        );

        $updated = $this->repository->update(
            $companyId,
            $brandId,
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'],
                'sort_order' => $data['sort_order'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Brand) {
            throw new ValidationException(
                'Brand could not be updated.'
            );
        }

        $this->audit->record(
            'brands.updated',
            'brand',
            $brandId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $brandId,
        int $userId
    ): void {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($brandId, 'Brand ID');
        $this->validatePositiveId($userId, 'User ID');

        $brand = $this->find(
            $companyId,
            $brandId
        );

        $deleted = $this->repository->softDelete(
            $companyId,
            $brandId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Brand could not be deleted.'
            );
        }

        $this->audit->record(
            'brands.deleted',
            'brand',
            $brandId,
            [
                'brand' => $brand->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $brandId,
        int $userId
    ): Brand {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($brandId, 'Brand ID');
        $this->validatePositiveId($userId, 'User ID');

        $brand = $this->find(
            $companyId,
            $brandId,
            true
        );

        if (!$brand->isDeleted()) {
            throw new ValidationException(
                'Brand is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $brand->code(),
                $brandId
            )
        ) {
            throw new ValidationException(
                'Another brand is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $brand->name(),
                $brandId
            )
        ) {
            throw new ValidationException(
                'Another brand is already using this name.'
            );
        }

        $restored = $this->repository->restore(
            $companyId,
            $brandId,
            $userId
        );

        if (!$restored) {
            throw new ValidationException(
                'Brand could not be restored.'
            );
        }

        $brand = $this->find(
            $companyId,
            $brandId
        );

        $this->audit->record(
            'brands.restored',
            'brand',
            $brandId,
            [
                'brand' => $brand->toArray(),
            ]
        );

        return $brand;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string
     * }
     */
    private function validateBrand(
        int $companyId,
        array $input,
        ?int $brandId = null
    ): array {
        $name = trim((string) ($input['name'] ?? ''));

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
        );

        $description = trim(
            (string) ($input['description'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        $sortOrderValue = $input['sort_order'] ?? 0;

        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(
                'Brand name is required and must be 120 characters or fewer.'
            );
        }

        if (
            $code === ''
            || mb_strlen($code) > 80
            || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)
        ) {
            throw new ValidationException(
                'Brand code must start with a letter or number and may contain lowercase letters, numbers, dashes and underscores.'
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
                'Brand status must be active or inactive.'
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
                $brandId
            )
        ) {
            throw new ValidationException(
                'Brand code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $brandId
            )
        ) {
            throw new ValidationException(
                'Brand name is already in use.'
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'description' => $description === ''
                ? null
                : $description,
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