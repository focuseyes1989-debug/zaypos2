<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\CustomerService;

final class CustomerController extends BaseAdminController
{
    private CustomerService $service;

    public function __construct()
    {
        $this->service = new CustomerService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('customers.view');

        try {
            $result = $this->service->paginate(
                (int) $currentUser['company_id'],
                [
                    'search' => $_GET['search'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'page' => $_GET['page'] ?? 1,
                    'per_page' => 20,
                    'deleted' => $_GET['deleted'] ?? false,
                ]
            );

            View::render('customers.index', [
                'currentUser' => $currentUser,
                'customers' => $result['items'],
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['per_page'],
                'lastPage' => $result['last_page'],
                'filters' => [
                    'search' => trim(
                        (string) ($_GET['search'] ?? '')
                    ),
                    'status' => trim(
                        (string) ($_GET['status'] ?? '')
                    ),
                    'deleted' => filter_var(
                        $_GET['deleted'] ?? false,
                        FILTER_VALIDATE_BOOL
                    ),
                ],
                'success' => flash('success'),
                'error' => flash('error'),
            ]);
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
            redirect('/customers');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'customers.create'
        );

        View::render('customers.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'credit_limit' => 0,
                'opening_balance' => 0,
                'status' => 'active',
            ],
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'customers.create'
        );

        $this->verifyCsrf();

        try {
            $this->service->create(
                (int) $currentUser['company_id'],
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Customer created successfully.'
            );

            redirect('/customers');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/customers/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'customers.update'
        );

        $customerId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $customer = $this->service->find(
                (int) $currentUser['company_id'],
                $customerId
            );

            $input = array_merge(
                $customer->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('customers.form', [
                'currentUser' => $currentUser,
                'mode' => 'edit',
                'input' => $input,
                'error' => flash('error'),
            ]);

            unset($_SESSION['_old']);
        } catch (ValidationException) {
            http_response_code(404);
            View::render('errors.404');
        }
    }

    public function update(): void
    {
        $currentUser = $this->requirePermission(
            'customers.update'
        );

        $this->verifyCsrf();

        $customerId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $customerId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Customer updated successfully.'
            );

            redirect('/customers');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());

            redirect(
                '/customers/edit?id=' . $customerId
            );
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'customers.delete'
        );

        $this->verifyCsrf();

        $customerId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $customerId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Customer deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/customers');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'customers.restore'
        );

        $this->verifyCsrf();

        $customerId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $customerId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Customer restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/customers?deleted=1');
    }
}