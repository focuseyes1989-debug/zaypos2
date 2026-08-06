<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Category;
use PDO;

final class CategoryRepository
{
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
        string $search,
        string $status,
        int $page,
        int $perPage = 20,
        bool $onlyDeleted = false
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $conditions = ['c.company_id = :company_id'];
        $parameters = ['company_id' => $companyId];

        if ($onlyDeleted) {
            $conditions[] = 'c.deleted_at IS NOT NULL';
        } else {
            $conditions[] = 'c.deleted_at IS NULL';
        }

        if ($search !== '') {
            $conditions[] = '(
                c.name LIKE :search_name
                OR c.code LIKE :search_code
                OR c.description LIKE :search_description
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_description'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = 'c.status = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $countStatement = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM categories c
             WHERE {$where}"
        );

        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $statement = Database::connection()->prepare(
            "SELECT
                c.id,
                c.company_id,
                c.parent_id,
                c.name,
                c.code,
                c.description,
                c.sort_order,
                c.status,
                c.created_by,
                c.updated_by,
                c.deleted_by,
                c.created_at,
                c.updated_at,
                c.deleted_at,
                parent.name AS parent_name
             FROM categories c
             LEFT JOIN categories parent
                ON parent.id = c.parent_id
                AND parent.company_id = c.company_id
             WHERE {$where}
             ORDER BY
                c.sort_order ASC,
                c.name ASC,
                c.id ASC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($parameters as $key => $value) {
            $statement->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = [];

        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new Category($row);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(
        int $companyId,
        int $categoryId,
        bool $includeDeleted = false
    ): ?Category {
        $sql = 'SELECT
                    id,
                    company_id,
                    parent_id,
                    name,
                    code,
                    description,
                    sort_order,
                    status,
                    created_by,
                    updated_by,
                    deleted_by,
                    created_at,
                    updated_at,
                    deleted_at
                FROM categories
                WHERE id = :id
                  AND company_id = :company_id';

        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $sql .= ' LIMIT 1';

        $statement = Database::connection()->prepare($sql);

        $statement->execute([
            'id' => $categoryId,
            'company_id' => $companyId,
        ]);

        $row = $statement->fetch();

        return is_array($row) ? new Category($row) : null;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function activeOptions(
        int $companyId,
        ?int $exceptCategoryId = null
    ): array {
        $sql = "SELECT id, name
                FROM categories
                WHERE company_id = :company_id
                  AND status = 'active'
                  AND deleted_at IS NULL";

        $parameters = ['company_id' => $companyId];

        if ($exceptCategoryId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptCategoryId;
        }

        $sql .= ' ORDER BY sort_order, name';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        $options = [];

        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $options[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
        }

        return $options;
    }

    public function codeExists(
        int $companyId,
        string $code,
        ?int $exceptCategoryId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM categories
                WHERE company_id = :company_id
                  AND code = :code';

        $parameters = [
            'company_id' => $companyId,
            'code' => $code,
        ];

        if ($exceptCategoryId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptCategoryId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptCategoryId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM categories
                WHERE company_id = :company_id
                  AND name = :name
                  AND deleted_at IS NULL';

        $parameters = [
            'company_id' => $companyId,
            'name' => $name,
        ];

        if ($exceptCategoryId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptCategoryId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param array{
     *     company_id: int,
     *     parent_id: int|null,
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(array $data): Category
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO categories (
                company_id,
                parent_id,
                name,
                code,
                description,
                sort_order,
                status,
                created_by,
                updated_by,
                created_at,
                updated_at
             ) VALUES (
                :company_id,
                :parent_id,
                :name,
                :code,
                :description,
                :sort_order,
                :status,
                :created_by,
                :updated_by,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );

        $statement->execute([
            'company_id' => $data['company_id'],
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'created_by' => $data['created_by'],
            'updated_by' => $data['created_by'],
        ]);

        $categoryId = (int) Database::connection()->lastInsertId();

        $category = $this->find($data['company_id'], $categoryId);

        if (!$category instanceof Category) {
            throw new \RuntimeException(
                'The category was created but could not be reloaded.'
            );
        }

        return $category;
    }

    /**
     * @param array{
     *     parent_id: int|null,
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string,
     *     updated_by: int|null
     * } $data
     */
    public function update(
        int $companyId,
        int $categoryId,
        array $data
    ): ?Category {
        $statement = Database::connection()->prepare(
            'UPDATE categories
             SET parent_id = :parent_id,
                 name = :name,
                 code = :code,
                 description = :description,
                 sort_order = :sort_order,
                 status = :status,
                 updated_by = :updated_by,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND company_id = :company_id
               AND deleted_at IS NULL'
        );

        $statement->execute([
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'updated_by' => $data['updated_by'],
            'id' => $categoryId,
            'company_id' => $companyId,
        ]);

        return $this->find($companyId, $categoryId);
    }

    public function softDelete(
        int $companyId,
        int $categoryId,
        int $userId
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE categories
             SET deleted_at = UTC_TIMESTAMP(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND company_id = :company_id
               AND deleted_at IS NULL'
        );

        $statement->execute([
            'deleted_by' => $userId,
            'updated_by' => $userId,
            'id' => $categoryId,
            'company_id' => $companyId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function restore(
        int $companyId,
        int $categoryId,
        int $userId
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE categories
             SET deleted_at = NULL,
                 deleted_by = NULL,
                 updated_by = :updated_by,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND company_id = :company_id
               AND deleted_at IS NOT NULL'
        );

        $statement->execute([
            'updated_by' => $userId,
            'id' => $categoryId,
            'company_id' => $companyId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function hasChildren(
        int $companyId,
        int $categoryId
    ): bool {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM categories
             WHERE company_id = :company_id
               AND parent_id = :parent_id
               AND deleted_at IS NULL'
        );

        $statement->execute([
            'company_id' => $companyId,
            'parent_id' => $categoryId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function parentWouldCreateCycle(
        int $companyId,
        int $categoryId,
        ?int $parentId
    ): bool {
        if ($parentId === null) {
            return false;
        }

        if ($categoryId === $parentId) {
            return true;
        }

        $visited = [];
        $currentParentId = $parentId;

        while ($currentParentId !== null) {
            if (isset($visited[$currentParentId])) {
                return true;
            }

            $visited[$currentParentId] = true;

            if ($currentParentId === $categoryId) {
                return true;
            }

            $statement = Database::connection()->prepare(
                'SELECT parent_id
                 FROM categories
                 WHERE id = :id
                   AND company_id = :company_id
                   AND deleted_at IS NULL
                 LIMIT 1'
            );

            $statement->execute([
                'id' => $currentParentId,
                'company_id' => $companyId,
            ]);

            $value = $statement->fetchColumn();

            if ($value === false || $value === null) {
                $currentParentId = null;
            } else {
                $currentParentId = (int) $value;
            }
        }

        return false;
    }
}