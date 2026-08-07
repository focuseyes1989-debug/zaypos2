<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Tax;
use App\Repositories\TaxRepository;

final class TaxService
{
    public function __construct(
        private readonly TaxRepository $repository =
            new TaxRepository(),
        private readonly AuditService $audit =
            new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Tax>,
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
                'Invalid tax status filter.'
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
        int $taxId,
        bool $includeDeleted = false
    ): Tax {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $taxId,
            'Tax ID'
        );

        $tax = $this->repository->find(
            $companyId,
            $taxId,
            $includeDeleted
        );

        if (!$tax instanceof Tax) {
            throw new ValidationException(
                'Tax not found.'
            );
        }

        return $tax;
    }

    public function findDefault(
        int $companyId
    ): ?Tax {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        return $this->repository->findDefault(
            $companyId
        );
    }

    /**
     * @return list<array<string, mixed>>
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
     * @return list<array<string, mixed>>
     */
    public function activeSalesOptions(
        int $companyId
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        return $this->repository->activeSalesOptions(
            $companyId
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activePurchaseOptions(
        int $companyId
    ): array {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        return $this->repository->activePurchaseOptions(
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
    ): Tax {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $data = $this->validateTax(
            $companyId,
            $input
        );

        if (
            $data['is_default']
            && $data['status'] !== 'active'
        ) {
            throw new ValidationException(
                'The default tax must be active.'
            );
        }

        if (
            $data['is_default']
            && !$data['applies_to_sales']
        ) {
            throw new ValidationException(
                'The default tax must apply to sales.'
            );
        }

        $tax = $this->repository->transaction(
            function (
                TaxRepository $repository
            ) use (
                $companyId,
                $userId,
                $data
            ): Tax {
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
                    'tax_type' => $data['tax_type'],
                    'rate' => $data['rate'],
                    'price_includes_tax' =>
                        $data['price_includes_tax'],
                    'applies_to_sales' =>
                        $data['applies_to_sales'],
                    'applies_to_purchases' =>
                        $data['applies_to_purchases'],
                    'is_default' =>
                        $data['is_default'],
                    'description' =>
                        $data['description'],
                    'status' => $data['status'],
                    'created_by' => $userId,
                ]);
            }
        );

        $this->audit->record(
            'taxes.created',
            'tax',
            $tax->id(),
            [
                'tax' => $tax->toArray(),
            ]
        );

        return $tax;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $taxId,
        int $userId,
        array $input
    ): Tax {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $taxId,
            'Tax ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $existing = $this->find(
            $companyId,
            $taxId
        );

        $data = $this->validateTax(
            $companyId,
            $input,
            $taxId
        );

        if (
            $existing->isDefault()
            && !$data['is_default']
        ) {
            throw new ValidationException(
                'The default tax cannot be unset directly. '
                . 'Set another tax as default first.'
            );
        }

        if (
            $existing->isDefault()
            && $data['status'] !== 'active'
        ) {
            throw new ValidationException(
                'The default tax cannot be inactive.'
            );
        }

        if (
            $data['is_default']
            && $data['status'] !== 'active'
        ) {
            throw new ValidationException(
                'The default tax must be active.'
            );
        }

        if (
            $data['is_default']
            && !$data['applies_to_sales']
        ) {
            throw new ValidationException(
                'The default tax must apply to sales.'
            );
        }

        $updated = $this->repository->transaction(
            function (
                TaxRepository $repository
            ) use (
                $companyId,
                $taxId,
                $userId,
                $data
            ): Tax {
                if ($data['is_default']) {
                    $repository->clearDefault(
                        $companyId,
                        $userId,
                        $taxId
                    );
                }

                $tax = $repository->update(
                    $companyId,
                    $taxId,
                    [
                        'name' => $data['name'],
                        'code' => $data['code'],
                        'tax_type' =>
                            $data['tax_type'],
                        'rate' => $data['rate'],
                        'price_includes_tax' =>
                            $data['price_includes_tax'],
                        'applies_to_sales' =>
                            $data['applies_to_sales'],
                        'applies_to_purchases' =>
                            $data['applies_to_purchases'],
                        'is_default' =>
                            $data['is_default'],
                        'description' =>
                            $data['description'],
                        'status' => $data['status'],
                        'updated_by' => $userId,
                    ]
                );

                if (!$tax instanceof Tax) {
                    throw new ValidationException(
                        'Tax could not be updated.'
                    );
                }

                return $tax;
            }
        );

        $this->audit->record(
            'taxes.updated',
            'tax',
            $taxId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $taxId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $taxId,
            'Tax ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $tax = $this->find(
            $companyId,
            $taxId
        );

        if ($tax->isDefault()) {
            throw new ValidationException(
                'The default tax cannot be deleted. '
                . 'Set another tax as default first.'
            );
        }

        $deleted = $this->repository->softDelete(
            $companyId,
            $taxId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Tax could not be deleted.'
            );
        }

        $this->audit->record(
            'taxes.deleted',
            'tax',
            $taxId,
            [
                'tax' => $tax->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $taxId,
        int $userId
    ): Tax {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $taxId,
            'Tax ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $tax = $this->find(
            $companyId,
            $taxId,
            true
        );

        if (!$tax->isDeleted()) {
            throw new ValidationException(
                'Tax is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $tax->code(),
                $taxId
            )
        ) {
            throw new ValidationException(
                'Another tax is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $tax->name(),
                $taxId
            )
        ) {
            throw new ValidationException(
                'Another tax is already using this name.'
            );
        }

        $restored = $this->repository->transaction(
            function (
                TaxRepository $repository
            ) use (
                $companyId,
                $taxId,
                $userId
            ): Tax {
                $success = $repository->restore(
                    $companyId,
                    $taxId,
                    $userId
                );

                if (!$success) {
                    throw new ValidationException(
                        'Tax could not be restored.'
                    );
                }

                $restoredTax = $repository->find(
                    $companyId,
                    $taxId
                );

                if (!$restoredTax instanceof Tax) {
                    throw new ValidationException(
                        'Restored tax could not be reloaded.'
                    );
                }

                if (
                    $restoredTax->isDefault()
                    && $repository->hasOtherDefault(
                        $companyId,
                        $taxId
                    )
                ) {
                    $restoredTax = $repository->update(
                        $companyId,
                        $taxId,
                        [
                            'name' =>
                                $restoredTax->name(),
                            'code' =>
                                $restoredTax->code(),
                            'tax_type' =>
                                $restoredTax->taxType(),
                            'rate' =>
                                $restoredTax->rate(),
                            'price_includes_tax' =>
                                $restoredTax
                                    ->priceIncludesTax(),
                            'applies_to_sales' =>
                                $restoredTax
                                    ->appliesToSales(),
                            'applies_to_purchases' =>
                                $restoredTax
                                    ->appliesToPurchases(),
                            'is_default' => false,
                            'description' =>
                                $restoredTax->description(),
                            'status' =>
                                $restoredTax->status(),
                            'updated_by' => $userId,
                        ]
                    );

                    if (!$restoredTax instanceof Tax) {
                        throw new ValidationException(
                            'Restored tax default status '
                            . 'could not be updated.'
                        );
                    }
                }

                return $restoredTax;
            }
        );

        $this->audit->record(
            'taxes.restored',
            'tax',
            $taxId,
            [
                'tax' => $restored->toArray(),
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
     *     tax_type: string,
     *     rate: float,
     *     price_includes_tax: bool,
     *     applies_to_sales: bool,
     *     applies_to_purchases: bool,
     *     is_default: bool,
     *     description: string|null,
     *     status: string
     * }
     */
    private function validateTax(
        int $companyId,
        array $input,
        ?int $taxId = null
    ): array {
        $name = trim(
            (string) ($input['name'] ?? '')
        );

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
        );

        $taxType = strtolower(
            trim(
                (string) (
                    $input['tax_type']
                    ?? 'percentage'
                )
            )
        );

        $description = trim(
            (string) ($input['description'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        $priceIncludesTax = $this->booleanInput(
            $input['price_includes_tax'] ?? false
        );

        $appliesToSales = $this->booleanInput(
            $input['applies_to_sales'] ?? false
        );

        $appliesToPurchases = $this->booleanInput(
            $input['applies_to_purchases'] ?? false
        );

        $isDefault = $this->booleanInput(
            $input['is_default'] ?? false
        );

        if ($name === '') {
            throw new ValidationException(
                'Tax name is required.'
            );
        }

        if (mb_strlen($name) > 160) {
            throw new ValidationException(
                'Tax name must be 160 characters or fewer.'
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
                'Tax code must start with a letter or number '
                . 'and may contain lowercase letters, numbers, '
                . 'dashes and underscores.'
            );
        }

        if (
            !in_array(
                $taxType,
                ['percentage', 'fixed'],
                true
            )
        ) {
            throw new ValidationException(
                'Tax type must be percentage or fixed.'
            );
        }

        $rate = $this->validateRate(
            $input['rate'] ?? 0,
            $taxType
        );

        if (
            $description !== ''
            && mb_strlen($description) > 500
        ) {
            throw new ValidationException(
                'Description must be 500 characters or fewer.'
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
                'Tax status must be active or inactive.'
            );
        }

        if (
            !$appliesToSales
            && !$appliesToPurchases
        ) {
            throw new ValidationException(
                'Tax must apply to sales, purchases, or both.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $code,
                $taxId
            )
        ) {
            throw new ValidationException(
                'Tax code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $taxId
            )
        ) {
            throw new ValidationException(
                'Tax name is already in use.'
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'tax_type' => $taxType,
            'rate' => $rate,
            'price_includes_tax' =>
                $priceIncludesTax,
            'applies_to_sales' =>
                $appliesToSales,
            'applies_to_purchases' =>
                $appliesToPurchases,
            'is_default' => $isDefault,
            'description' => $description === ''
                ? null
                : $description,
            'status' => $status,
        ];
    }

    private function validateRate(
        mixed $value,
        string $taxType
    ): float {
        if (
            $value === ''
            || $value === null
        ) {
            $value = 0;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                'Tax rate must be a valid number.'
            );
        }

        $rate = round(
            (float) $value,
            4
        );

        if ($rate < 0) {
            throw new ValidationException(
                'Tax rate must not be negative.'
            );
        }

        if (
            $taxType === 'percentage'
            && $rate > 100
        ) {
            throw new ValidationException(
                'Percentage tax rate must not exceed 100.'
            );
        }

        if ($rate > 99999999999999.9999) {
            throw new ValidationException(
                'Tax rate is too large.'
            );
        }

        return $rate;
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