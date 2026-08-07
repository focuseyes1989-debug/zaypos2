<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Customer;
use App\Repositories\CustomerRepository;

final class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $repository = new CustomerRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: list<Customer>,
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
                'Invalid customer status filter.'
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
        int $customerId,
        bool $includeDeleted = false
    ): Customer {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($customerId, 'Customer ID');

        $customer = $this->repository->find(
            $companyId,
            $customerId,
            $includeDeleted
        );

        if (!$customer instanceof Customer) {
            throw new ValidationException(
                'Customer not found.'
            );
        }

        return $customer;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     customer_group: string|null
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
    ): Customer {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($userId, 'User ID');

        $data = $this->validateCustomer(
            $companyId,
            $input
        );

        $customer = $this->repository->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => $data['code'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'tax_number' => $data['tax_number'],
            'customer_group' => $data['customer_group'],
            'credit_limit' => $data['credit_limit'],
            'opening_balance' => $data['opening_balance'],
            'notes' => $data['notes'],
            'status' => $data['status'],
            'created_by' => $userId,
        ]);

        $this->audit->record(
            'customers.created',
            'customer',
            $customer->id(),
            [
                'customer' => $customer->toArray(),
            ]
        );

        return $customer;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(
        int $companyId,
        int $customerId,
        int $userId,
        array $input
    ): Customer {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($customerId, 'Customer ID');
        $this->validatePositiveId($userId, 'User ID');

        $existing = $this->find(
            $companyId,
            $customerId
        );

        $data = $this->validateCustomer(
            $companyId,
            $input,
            $customerId
        );

        $updated = $this->repository->update(
            $companyId,
            $customerId,
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'tax_number' => $data['tax_number'],
                'customer_group' => $data['customer_group'],
                'credit_limit' => $data['credit_limit'],
                'opening_balance' => $data['opening_balance'],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]
        );

        if (!$updated instanceof Customer) {
            throw new ValidationException(
                'Customer could not be updated.'
            );
        }

        $this->audit->record(
            'customers.updated',
            'customer',
            $customerId,
            [
                'before' => $existing->toArray(),
                'after' => $updated->toArray(),
            ]
        );

        return $updated;
    }

    public function delete(
        int $companyId,
        int $customerId,
        int $userId
    ): void {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($customerId, 'Customer ID');
        $this->validatePositiveId($userId, 'User ID');

        $customer = $this->find(
            $companyId,
            $customerId
        );

        $deleted = $this->repository->softDelete(
            $companyId,
            $customerId,
            $userId
        );

        if (!$deleted) {
            throw new ValidationException(
                'Customer could not be deleted.'
            );
        }

        $this->audit->record(
            'customers.deleted',
            'customer',
            $customerId,
            [
                'customer' => $customer->toArray(),
            ]
        );
    }

    public function restore(
        int $companyId,
        int $customerId,
        int $userId
    ): Customer {
        $this->validatePositiveId($companyId, 'Company ID');
        $this->validatePositiveId($customerId, 'Customer ID');
        $this->validatePositiveId($userId, 'User ID');

        $customer = $this->find(
            $companyId,
            $customerId,
            true
        );

        if (!$customer->isDeleted()) {
            throw new ValidationException(
                'Customer is not deleted.'
            );
        }

        if (
            $this->repository->codeExists(
                $companyId,
                $customer->code(),
                $customerId
            )
        ) {
            throw new ValidationException(
                'Another customer is already using this code.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $customer->name(),
                $customerId
            )
        ) {
            throw new ValidationException(
                'Another customer is already using this name.'
            );
        }

        $restored = $this->repository->restore(
            $companyId,
            $customerId,
            $userId
        );

        if (!$restored) {
            throw new ValidationException(
                'Customer could not be restored.'
            );
        }

        $customer = $this->find(
            $companyId,
            $customerId
        );

        $this->audit->record(
            'customers.restored',
            'customer',
            $customerId,
            [
                'customer' => $customer->toArray(),
            ]
        );

        return $customer;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null,
     *     tax_number: string|null,
     *     customer_group: string|null,
     *     credit_limit: float,
     *     opening_balance: float,
     *     notes: string|null,
     *     status: string
     * }
     */
    private function validateCustomer(
        int $companyId,
        array $input,
        ?int $customerId = null
    ): array {
        $name = trim((string) ($input['name'] ?? ''));

        $code = mb_strtolower(
            trim((string) ($input['code'] ?? ''))
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

        $customerGroup = trim(
            (string) ($input['customer_group'] ?? '')
        );

        $notes = trim(
            (string) ($input['notes'] ?? '')
        );

        $status = strtolower(
            trim((string) ($input['status'] ?? 'active'))
        );

        if ($name === '') {
            throw new ValidationException(
                'Customer name is required.'
            );
        }

        if (mb_strlen($name) > 160) {
            throw new ValidationException(
                'Customer name must be 160 characters or fewer.'
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
                'Customer code must start with a letter or number and may contain lowercase letters, numbers, dashes and underscores.'
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
                    'Customer email address is invalid.'
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
            $customerGroup !== ''
            && mb_strlen($customerGroup) > 100
        ) {
            throw new ValidationException(
                'Customer group must be 100 characters or fewer.'
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
                'Customer status must be active or inactive.'
            );
        }

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
                $customerId
            )
        ) {
            throw new ValidationException(
                'Customer code is already in use.'
            );
        }

        if (
            $this->repository->nameExists(
                $companyId,
                $name,
                $customerId
            )
        ) {
            throw new ValidationException(
                'Customer name is already in use.'
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'phone' => $phone === '' ? null : $phone,
            'email' => $email === '' ? null : $email,
            'address' => $address === '' ? null : $address,
            'tax_number' => $taxNumber === '' ? null : $taxNumber,
            'customer_group' => $customerGroup === ''
                ? null
                : $customerGroup,
            'credit_limit' => $creditLimit,
            'opening_balance' => $openingBalance,
            'notes' => $notes === '' ? null : $notes,
            'status' => $status,
        ];
    }

    private function validateMoney(
        mixed $value,
        string $field,
        bool $allowNegative
    ): float {
        if ($value === '' || $value === null) {
            $value = 0;
        }

        if (!is_numeric($value)) {
            throw new ValidationException(
                "{$field} must be a valid number."
            );
        }

        $amount = round((float) $value, 2);

        if (!$allowNegative && $amount < 0) {
            throw new ValidationException(
                "{$field} must not be negative."
            );
        }

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