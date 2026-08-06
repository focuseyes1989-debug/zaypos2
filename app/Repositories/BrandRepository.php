<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Brand;
use PDO;
use RuntimeException;

final class BrandRepository
{
    /**
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
        string $search,
        string $status,
        int $page,
        int $perPage = 20,
        bool $onlyDeleted = false
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $conditions = ['company_id = :company_id'];
        $parameters = ['company_id' => $companyId];

        $conditions[] = $onlyDeleted
            ? 'deleted_at IS NOT NULL'
            : 'deleted_at IS NULL';

        if ($search !== '') {
            $conditions[] = '(
                name LIKE :search_name
                OR code LIKE :search_code
                OR description LIKE :search_description
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_name'] = $searchValue;
            $parameters['search_code'] = $searchValue;
            $parameters['search_description'] = $searchValue;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);

        $countStatement = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM brands
             WHERE {$where}"
        );

        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $statement = Database::connection()->prepare(
            "SELECT
                id,
                company_id,
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
             FROM brands
             WHERE {$where}
             ORDER BY
                sort_order ASC,
                name ASC,
                id ASC
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
                $items[] = new Brand($row);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(
                1,
                (int) ceil($total / $perPage)
            ),
        ];
    }

    public function find(
        int $companyId,
        int $brandId,
        bool $includeDeleted = false
    ): ?Brand {
        $sql = 'SELECT
                    id,
                    company_id,
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
                FROM brands
                WHERE id = :id
                  AND company_id = :company_id';

        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $sql .= ' LIMIT 1';

        $statement = Database::connection()->prepare($sql);

        $statement->execute([
            'id' => $brandId,
            'company_id' => $companyId,
        ]);

        $row = $statement->fetch();

        return is_array($row) ? new Brand($row) : null;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function activeOptions(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, name
             FROM brands
             WHERE company_id = :company_id
               AND status = 'active'
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC"
        );

        $statement->execute([
            'company_id' => $companyId,
        ]);

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
        ?int $exceptBrandId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM brands
                WHERE company_id = :company_id
                  AND code = :code';

        $parameters = [
            'company_id' => $companyId,
            'code' => $code,
        ];

        if ($exceptBrandId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptBrandId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function nameExists(
        int $companyId,
        string $name,
        ?int $exceptBrandId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM brands
                WHERE company_id = :company_id
                  AND name = :name
                  AND deleted_at IS NULL';

        $parameters = [
            'company_id' => $companyId,
            'name' => $name,
        ];

        if ($exceptBrandId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptBrandId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param array{
     *     company_id: int,
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string,
     *     created_by: int|null
     * } $data
     */
    public function create(array $data): Brand
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO brands (
                company_id,
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
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'created_by' => $data['created_by'],
            'updated_by' => $data['created_by'],
        ]);

        $brandId = (int) Database::connection()->lastInsertId();

        $brand = $this->find(
            $data['company_id'],
            $brandId
        );

        if (!$brand instanceof Brand) {
            throw new RuntimeException(
                'The brand was created but could not be reloaded.'
            );
        }

        return $brand;
    }

    /**
     * @param array{
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
        int $brandId,
        array $data
    ): ?Brand {
        $statement = Database::connection()->prepare(
            'UPDATE brands
             SET name = :name,
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
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
            'updated_by' => $data['updated_by'],
            'id' => $brandId,
            'company_id' => $companyId,
        ]);

        return $this->find($companyId, $brandId);
    }

    public function softDelete(
        int $companyId,
        int $brandId,
        int $userId
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE brands
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
            'id' => $brandId,
            'company_id' => $companyId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function restore(
        int $companyId,
        int $brandId,
        int $userId
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE brands
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
            'id' => $brandId,
            'company_id' => $companyId,
        ]);

        return $statement->rowCount() > 0;
    }
}