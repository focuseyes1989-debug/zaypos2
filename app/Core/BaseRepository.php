<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use App\Models\BaseModel;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Shared low-level repository helpers.
 *
 * Business-specific SQL remains inside each module repository.
 *
 * @template TModel of BaseModel
 */
abstract class BaseRepository
{
    /**
     * Database table used by the repository.
     */
    protected string $table;

    /**
     * Fully-qualified model class name.
     *
     * @var class-string<TModel>
     */
    protected string $modelClass;

    /**
     * Database columns returned when hydrating the model.
     *
     * @var list<string>
     */
    protected array $selectColumns = [];

    /**
     * Columns allowed in INSERT statements.
     *
     * @var list<string>
     */
    protected array $createColumns = [];

    /**
     * Columns allowed in UPDATE statements.
     *
     * @var list<string>
     */
    protected array $updateColumns = [];

    /**
     * Primary-key column.
     */
    protected string $primaryKey = 'id';

    /**
     * Company ownership column.
     */
    protected string $companyColumn = 'company_id';

    /**
     * Soft-delete timestamp column.
     */
    protected string $deletedAtColumn = 'deleted_at';

    /**
     * Maximum allowed records per page.
     */
    protected int $maximumPerPage = 100;

    public function __construct()
    {
        $this->assertConfiguration();
    }

    protected function connection(): PDO
    {
        return Database::connection();
    }

    /**
     * Convert one database row into a model object.
     *
     * @param array<string, mixed> $row
     *
     * @return TModel
     */
    protected function hydrate(array $row): BaseModel
    {
        $modelClass = $this->modelClass;
        $model = new $modelClass($row);

        if (!$model instanceof BaseModel) {
            throw new RuntimeException(
                sprintf(
                    'Repository model "%s" must extend %s.',
                    $modelClass,
                    BaseModel::class
                )
            );
        }

        return $model;
    }

    /**
     * Convert database rows into model objects.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<TModel>
     */
    protected function hydrateMany(array $rows): array
    {
        $models = [];

        foreach ($rows as $row) {
            $models[] = $this->hydrate($row);
        }

        return $models;
    }

    /**
     * Execute a prepared statement.
     *
     * @param array<string, mixed> $parameters
     */
    protected function execute(
        string $sql,
        array $parameters = []
    ): PDOStatement {
        $statement = $this->connection()->prepare($sql);

        $this->bindValues($statement, $parameters);

        $statement->execute();

        return $statement;
    }

    /**
     * Return one associative database row.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>|null
     */
    protected function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        $row = $this->execute($sql, $parameters)->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Return multiple associative database rows.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        $rows = $this->execute($sql, $parameters)->fetchAll();

        return array_values(
            array_filter(
                $rows,
                static fn (mixed $row): bool => is_array($row)
            )
        );
    }

    /**
     * Return the first column from one database row.
     *
     * @param array<string, mixed> $parameters
     */
    protected function fetchValue(
        string $sql,
        array $parameters = []
    ): mixed {
        return $this->execute($sql, $parameters)->fetchColumn();
    }

    /**
     * Find a company-owned record by its primary key.
     *
     * @return TModel|null
     */
    public function findById(
        int $companyId,
        int $recordId,
        bool $includeDeleted = false
    ): ?BaseModel {
        $this->assertPositiveIdentifier($companyId, 'Company ID');
        $this->assertPositiveIdentifier($recordId, 'Record ID');

        $columns = $this->selectColumnList();

        $sql = sprintf(
            'SELECT %s
             FROM %s
             WHERE %s = :record_id
               AND %s = :company_id',
            $columns,
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->primaryKey),
            $this->quoteIdentifier($this->companyColumn)
        );

        if (!$includeDeleted) {
            $sql .= sprintf(
                ' AND %s IS NULL',
                $this->quoteIdentifier($this->deletedAtColumn)
            );
        }

        $sql .= ' LIMIT 1';

        $row = $this->fetchOne($sql, [
            'record_id' => $recordId,
            'company_id' => $companyId,
        ]);

        return $row === null
            ? null
            : $this->hydrate($row);
    }

    /**
     * Determine whether a matching company-owned value exists.
     */
    protected function valueExists(
        int $companyId,
        string $column,
        mixed $value,
        ?int $exceptRecordId = null,
        bool $excludeDeleted = false
    ): bool {
        $this->assertPositiveIdentifier($companyId, 'Company ID');

        $column = $this->quoteIdentifier($column);

        $sql = sprintf(
            'SELECT COUNT(*)
             FROM %s
             WHERE %s = :company_id
               AND %s = :value',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->companyColumn),
            $column
        );

        $parameters = [
            'company_id' => $companyId,
            'value' => $value,
        ];

        if ($exceptRecordId !== null) {
            $this->assertPositiveIdentifier(
                $exceptRecordId,
                'Excluded record ID'
            );

            $sql .= sprintf(
                ' AND %s <> :except_id',
                $this->quoteIdentifier($this->primaryKey)
            );

            $parameters['except_id'] = $exceptRecordId;
        }

        if ($excludeDeleted) {
            $sql .= sprintf(
                ' AND %s IS NULL',
                $this->quoteIdentifier($this->deletedAtColumn)
            );
        }

        return (int) $this->fetchValue($sql, $parameters) > 0;
    }

    /**
     * Soft-delete one company-owned record.
     */
    public function softDelete(
        int $companyId,
        int $recordId,
        int $userId
    ): bool {
        $this->assertPositiveIdentifier($companyId, 'Company ID');
        $this->assertPositiveIdentifier($recordId, 'Record ID');
        $this->assertPositiveIdentifier($userId, 'User ID');

        $sql = sprintf(
            'UPDATE %s
             SET deleted_at = UTC_TIMESTAMP(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = UTC_TIMESTAMP()
             WHERE %s = :record_id
               AND %s = :company_id
               AND %s IS NULL',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->primaryKey),
            $this->quoteIdentifier($this->companyColumn),
            $this->quoteIdentifier($this->deletedAtColumn)
        );

        $statement = $this->execute($sql, [
            'deleted_by' => $userId,
            'updated_by' => $userId,
            'record_id' => $recordId,
            'company_id' => $companyId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Restore one company-owned soft-deleted record.
     */
    public function restore(
        int $companyId,
        int $recordId,
        int $userId
    ): bool {
        $this->assertPositiveIdentifier($companyId, 'Company ID');
        $this->assertPositiveIdentifier($recordId, 'Record ID');
        $this->assertPositiveIdentifier($userId, 'User ID');

        $sql = sprintf(
            'UPDATE %s
             SET deleted_at = NULL,
                 deleted_by = NULL,
                 updated_by = :updated_by,
                 updated_at = UTC_TIMESTAMP()
             WHERE %s = :record_id
               AND %s = :company_id
               AND %s IS NOT NULL',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->primaryKey),
            $this->quoteIdentifier($this->companyColumn),
            $this->quoteIdentifier($this->deletedAtColumn)
        );

        $statement = $this->execute($sql, [
            'updated_by' => $userId,
            'record_id' => $recordId,
            'company_id' => $companyId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Normalize page and per-page values.
     *
     * @return array{
     *     page: int,
     *     per_page: int,
     *     offset: int
     * }
     */
    protected function pagination(
        int $page,
        int $perPage
    ): array {
        $page = max(1, $page);

        $perPage = min(
            $this->maximumPerPage,
            max(1, $perPage)
        );

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * Create standard pagination result metadata.
     *
     * @param list<TModel> $items
     *
     * @return array{
     *     items: list<TModel>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    protected function paginationResult(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return [
            'items' => $items,
            'total' => max(0, $total),
            'page' => max(1, $page),
            'per_page' => max(1, $perPage),
            'last_page' => max(
                1,
                (int) ceil($total / max(1, $perPage))
            ),
        ];
    }

    /**
     * Keep only columns explicitly allowed by a repository.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $allowedColumns
     *
     * @return array<string, mixed>
     */
    protected function onlyAllowedColumns(
        array $data,
        array $allowedColumns
    ): array {
        return array_intersect_key(
            $data,
            array_flip($allowedColumns)
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function bindValues(
        PDOStatement $statement,
        array $parameters
    ): void {
        foreach ($parameters as $name => $value) {
            $parameterName = str_starts_with($name, ':')
                ? $name
                : ':' . $name;

            $statement->bindValue(
                $parameterName,
                $value,
                $this->pdoType($value)
            );
        }
    }

    protected function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    protected function selectColumnList(): string
    {
        if ($this->selectColumns === []) {
            return '*';
        }

        return implode(
            ', ',
            array_map(
                fn (string $column): string => $this->quoteIdentifier(
                    $column
                ),
                $this->selectColumns
            )
        );
    }

    /**
     * Validate and quote a table or column identifier.
     *
     * Values must never be passed through this method.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        if (
            !preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $identifier
            )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid database identifier "%s".',
                    $identifier
                )
            );
        }

        return '`' . $identifier . '`';
    }

    protected function assertPositiveIdentifier(
        int $value,
        string $field
    ): void {
        if ($value < 1) {
            throw new InvalidArgumentException(
                "{$field} must be greater than zero."
            );
        }
    }

    private function assertConfiguration(): void
    {
        if (!isset($this->table) || $this->table === '') {
            throw new RuntimeException(
                static::class . ' must define the table property.'
            );
        }

        if (
            !isset($this->modelClass)
            || $this->modelClass === ''
            || !is_a(
                $this->modelClass,
                BaseModel::class,
                true
            )
        ) {
            throw new RuntimeException(
                static::class
                . ' must define a modelClass extending '
                . BaseModel::class
                . '.'
            );
        }

        $this->quoteIdentifier($this->table);
        $this->quoteIdentifier($this->primaryKey);
        $this->quoteIdentifier($this->companyColumn);
        $this->quoteIdentifier($this->deletedAtColumn);

        foreach (
            array_merge(
                $this->selectColumns,
                $this->createColumns,
                $this->updateColumns
            ) as $column
        ) {
            $this->quoteIdentifier($column);
        }
    }
}