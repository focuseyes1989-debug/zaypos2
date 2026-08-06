<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Category;
use App\Repositories\CategoryRepository;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $repository = new CategoryRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{
     *     items: list<Category>,
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
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
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
                'Invalid category status filter.'
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
        int $categoryId,
        bool $includeDeleted = false
    ): Category {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($categoryId, 'Category ID');

        $category = $this->repository->find(
            $companyId,
            $categoryId,
            $includeDeleted
        );

        if (!$category instanceof Category) {
            throw new ValidationException('Category not found.');
        }

        return $category;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function parentOptions(
        int $companyId,
        ?int $exceptCategoryId = null
    ): array {
        $this->validatePositiveId($companyId, 'Company ID');

        if ($exceptCategoryId !== null) {
            $this->validatePositiveId(
                $exceptCategoryId,
                'Category ID'
            );
        }

        return $this->repository->activeOptions(
            $companyId,
            $exceptCategoryId
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(
        int $companyId,
        int $userId,
        array $input
    ): Category {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($userId, 'User ID');

        $data = $this->validateCategory(
            $companyId,
            $input
        );

        $category = $this->repository->create([
            'company_id' => $companyId,
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'categories.created',
            'category',
            $category->id(),
            [
                'category' => $category->toArray(),
            ]
        );

        return $category;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $categoryId,
        int $userId,
        array $input
    ): Category {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($categoryId, 'Category ID');
        $this->validatePositiveId($userId, 'User ID');

        $existing = $this->find(
            $companyId,
            $categoryId
        );

        $data = $this->validateCategory(
            $companyId,
            $input,
            $categoryId
        );

        if (
            $this->repository->parentWouldCreateCycle(
                $companyId,
                $categoryId,
                $data['parent_id']
            )
        ) {
            throw new ValidationException(
                'The selected parent would create a category cycle.'
            );
        }

        $updated = $this->repository->update(
            $companyId,
            $categoryId,
            [
                'parent_id' => $data['parent_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'],
                'sort_order' => $data['sort_order'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Category) {
            throw new ValidationException(
                'Category could not be updated.'
            );
        }

        $this->audit->record(
            'categories.updated',
            'category',
            $categoryId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $categoryId,
        int $userId
    ): void {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($categoryId, 'Category ID');
        $this->validatePositiveId($userId, 'User ID');

        $category = $this->find(
            $companyId,
            $categoryId
        );

        if (
            $this->repository->hasChildren(
                $companyId,
                $categoryId
            )
        ) {
            throw new ValidationException(
                'Move or delete the child categories first.'
            );
        }

        $deleted = $this->repository->softDelete(
            $companyId,
            $categoryId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Category could not be deleted.'
            );
        }

        $this->audit->record(
            'categories.deleted',
            'category',
            $categoryId,
            [
                'category' => $category->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $categoryId,
        int $userId
    ): Category {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($categoryId, 'Category ID');
        $this->validatePositiveId($userId, 'User ID');

        $category = $this->find(
            $companyId,
            $categoryId,
            true
        );

        if (!$category->isDeleted()) {
            throw new ValidationException(
                'Category is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $category->code(),
                $categoryId
            )
        ) {
            throw new ValidationException(
                'Another category is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $category->name(),
                $categoryId
            )
        ) {
            throw new ValidationException(
                'Another category is already using this name.'
            );
        }

        $restored = $this->repository->restore(
            $companyId,
            $categoryId,
            $userId
        );

        if (!$restored) {
            throw new ValidationException(
                'Category could not be restored.'
            );
        }

        $category = $this->find(
            $companyId,
            $categoryId
        );

        $this->audit->record(
            'categories.restored',
            'category',
            $categoryId,
            [
                'category' => $category->toArray(),
            ]
        );

        return $category;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     parent_id: int|null,
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string
     * }
     */
    private function validateCategory(
        int $companyId,
        array $input,
        ?int $categoryId = null
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
        $parentIdValue = $input['parent_id'] ?? null;

        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(
                'Category name is required and must be 120 characters or fewer.'
            );
        }

        if (
            $code === ''
            || mb_strlen($code) > 80
            || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)
        ) {
            throw new ValidationException(
                'Category code must start with a letter or number and may contain lowercase letters, numbers, dashes and underscores.'
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
                'Category status must be active or inactive.'
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

        $parentId = null;

        if (
            $parentIdValue !== null
            && $parentIdValue !== ''
            && (int) $parentIdValue > 0
        ) {
            $parentId = (int) $parentIdValue;

            if (
                $categoryId !== null
                && $parentId === $categoryId
            ) {
                throw new ValidationException(
                    'A category cannot be its own parent.'
                );
            }

            $parent = $this->repository->find(
                $companyId,
                $parentId
            );

            if (!$parent instanceof Category) {
                throw new ValidationException(
                    'Selected parent category is invalid.'
                );
            }
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $code,
                $categoryId
            )
        ) {
            throw new ValidationException(
                'Category code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $categoryId
            )
        ) {
            throw new ValidationException(
                'Category name is already in use.'
            );
        }

        return [
            'parent_id' => $parentId,
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