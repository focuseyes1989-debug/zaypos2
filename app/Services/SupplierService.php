<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Supplier;
use App\Repositories\SupplierRepository;

final class SupplierService
{
    public function __construct(
        private readonly SupplierRepository $repository = new SupplierRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Supplier>,
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
            && !in_array($status, ['active', 'inactive'], true)
        ) {
            throw new ValidationException(
                'Invalid supplier status filter.'
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
        int $supplierId,
        bool $includeDeleted = false
    ): Supplier {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $supplierId,
            'Supplier ID'
        );

        $supplier = $this->repository->find(
            $companyId,
            $supplierId,
            $includeDeleted
        );

        if (!$supplier instanceof Supplier) {
            throw new ValidationException(
                'Supplier not found.'
            );
        }

        return $supplier;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     contact_person: string|null,
     *     phone: string|null
     * }>
     */
    public function activeOptions(int $companyId): array
    {
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
    ): Supplier {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $data = $this->validateSupplier(
            $companyId,
            $input
        );

        $supplier = $this->repository->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => $data['code'],
            'contact_person' => $data['contact_person'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'tax_number' => $data['tax_number'],
            'payment_terms_days' => $data['payment_terms_days'],
            'credit_limit' => $data['credit_limit'],
            'opening_balance' => $data['opening_balance'],
            'notes' => $data['notes'],
            'status' => $data['status'],
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'suppliers.created',
            'supplier',
            $supplier->id(),
            [
                'supplier' => $supplier->toArray(),
            ]
        );

        return $supplier;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $supplierId,
        int $userId,
        array $input
    ): Supplier {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $supplierId,
            'Supplier ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $existing = $this->find(
            $companyId,
            $supplierId
        );

        $data = $this->validateSupplier(
            $companyId,
            $input,
            $supplierId
        );

        $updated = $this->repository->update(
            $companyId,
            $supplierId,
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'contact_person' => $data['contact_person'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'tax_number' => $data['tax_number'],
                'payment_terms_days' => $data['payment_terms_days'],
                'credit_limit' => $data['credit_limit'],
                'opening_balance' => $data['opening_balance'],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Supplier) {
            throw new ValidationException(
                'Supplier could not be updated.'
            );
        }

        $this->audit->record(
            'suppliers.updated',
            'supplier',
            $supplierId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $supplierId,
        int $userId
    ): void {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $supplierId,
            'Supplier ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $supplier = $this->find(
            $companyId,
            $supplierId
        );

        $deleted = $this->repository->softDelete(
            $companyId,
            $supplierId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Supplier could not be deleted.'
            );
        }

        $this->audit->record(
            'suppliers.deleted',
            'supplier',
            $supplierId,
            [
                'supplier' => $supplier->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $supplierId,
        int $userId
    ): Supplier {
        $this->validatePositiveId(
            $companyId,
            'Company ID'
        );

        $this->validatePositiveId(
            $supplierId,
            'Supplier ID'
        );

        $this->validatePositiveId(
            $userId,
            'User ID'
        );

        $supplier = $this->find(
            $companyId,
            $supplierId,
            true
        );

        if (!$supplier->isDeleted()) {
            throw new ValidationException(
                'Supplier is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $supplier->code(),
                $supplierId
            )
        ) {
            throw new ValidationException(
                'Another supplier is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $supplier->name(),
                $supplierId
            )
        ) {
            throw new ValidationException(
                'Another supplier is already using this name.'
            );
        }

        $restored = $this->repository->restore(
            $companyId,
            $supplierId,
            $userId
        );

        if (!$restored) {
            throw new ValidationException(
                'Supplier could not be restored.'
            );
        }

        $supplier = $this->find(
            $companyId,
            $supplierId
        );

        $this->audit->record(
            'suppliers.restored',
            'supplier',
            $supplierId,
            [
                'supplier' => $supplier->toArray(),
            ]
        );

        return $supplier;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     contact_person: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     tax_number: string|null,
     *     payment_terms_days: int,
     *     credit_limit: float,
     *     opening_balance: float,
     *     notes: string|null,
     *     status: string
     * }
     */
    private function validateSupplier(
        int $companyId,
        array $input,
        ?int $supplierId = null
    ): array {
        $name = trim(
            (string) ($input['name'] ?? '')
        );

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
        );

        $contactPerson = trim(
            (string) ($input['contact_person'] ?? '')
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

        $taxNumber = trim(
            (string) ($input['tax_number'] ?? '')
        );

        $notes = trim(
            (string) ($input['notes'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        if ($name === '') {
            throw new ValidationException(
                'Supplier name is required.'
            );
        }

        if (mb_strlen($name) > 160) {
            throw new ValidationException(
                'Supplier name must be 160 characters or fewer.'
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
                'Supplier code must start with a letter or number and may contain lowercase letters, numbers, dashes and underscores.'
            );
        }

        if (
            $contactPerson !== ''
            && mb_strlen($contactPerson) > 160
        ) {
            throw new ValidationException(
                'Contact person must be 160 characters or fewer.'
            );
        }

        if ($phone !== '' && mb_strlen($phone) > 50) {
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
                    'Supplier email address is invalid.'
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
            $taxNumber !== ''
            && mb_strlen($taxNumber) > 100
        ) {
            throw new ValidationException(
                'Tax number must be 100 characters or fewer.'
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
                'Supplier status must be active or inactive.'
            );
        }

        $paymentTermsDays = $this->validateInteger(
            $input['payment_terms_days'] ?? 0,
            'Payment terms',
            0,
            65535
        );

        $creditLimit = $this->validateMoney(
            $input['credit_limit'] ?? 0,
            'Credit limit',
            false
        );

        $openingBalance = $this->validateMoney(
            $input['opening_balance'] ?? 0,
            'Opening balance',
            true
        );

        if (
            $this->repository->codeExists(
                $companyId,
                $code,
                $supplierId
            )
        ) {
            throw new ValidationException(
                'Supplier code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $supplierId
            )
        ) {
            throw new ValidationException(
                'Supplier name is already in use.'
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'contact_person' => $contactPerson === ''
                ? null
                : $contactPerson,
            'phone' => $phone === ''
                ? null
                : $phone,
            'email' => $email === ''
                ? null
                : $email,
            'address' => $address === ''
                ? null
                : $address,
            'tax_number' => $taxNumber === ''
                ? null
                : $taxNumber,
            'payment_terms_days' => $paymentTermsDays,
            'credit_limit' => $creditLimit,
            'opening_balance' => $openingBalance,
            'notes' => $notes === ''
                ? null
                : $notes,
            'status' => $status,
        ];
    }

    private function validateInteger(
        mixed $value,
        string $field,
        int $minimum,
        int $maximum
    ): int {
        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new ValidationException(
                "{$field} must be a whole number."
            );
        }

        $integer = (int) $value;

        if (
            $integer < $minimum
            || $integer > $maximum
        ) {
            throw new ValidationException(
                "{$field} must be between {$minimum} and {$maximum}."
            );
        }

        return $integer;
    }

    private function validateMoney(
        mixed $value,
        string $field,
        bool $allowNegative
    ): float {
        if (
            $value === ''
            || $value === null
        ) {
            $value = 0;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $amount = round(
            (float) $value,
            2
        );

        if (!$allowNegative && $amount < 0) {
            throw new ValidationException(
                "{$field} must not be negative."
            );
        }

        /*
         * DECIMAL(18,2) supports at most
         * 16 digits before the decimal point.
         */
        if (abs($amount) > 9999999999999999.99) {
            throw new ValidationException(
                "{$field} is too large."
            );
        }

        return $amount;
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