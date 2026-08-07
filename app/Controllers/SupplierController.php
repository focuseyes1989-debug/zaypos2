<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\SupplierService;

final class SupplierController extends BaseAdminController
{
    private SupplierService $service;

    public function __construct()
    {
        $this->service = new SupplierService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('suppliers.view');

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

            View::render('suppliers.index', [
                'currentUser' => $currentUser,
                'suppliers' => $result['items'],
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
            redirect('/suppliers');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'suppliers.create'
        );

        View::render('suppliers.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'payment_terms_days' => 0,
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
            'suppliers.create'
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
                'Supplier created successfully.'
            );

            redirect('/suppliers');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash('error', $exception->getMessage());

            redirect('/suppliers/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'suppliers.update'
        );

        $supplierId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $supplier = $this->service->find(
                (int) $currentUser['company_id'],
                $supplierId
            );

            $input = array_merge(
                $supplier->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('suppliers.form', [
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
            'suppliers.update'
        );

        $this->verifyCsrf();

        $supplierId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $supplierId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Supplier updated successfully.'
            );

            redirect('/suppliers');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash('error', $exception->getMessage());

            redirect(
                '/suppliers/edit?id=' . $supplierId
            );
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'suppliers.delete'
        );

        $this->verifyCsrf();

        $supplierId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $supplierId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Supplier deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/suppliers');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'suppliers.restore'
        );

        $this->verifyCsrf();

        $supplierId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $supplierId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Supplier restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/suppliers?deleted=1');
    }
}