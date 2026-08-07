<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\WarehouseService;

final class WarehouseController extends BaseAdminController
{
    private WarehouseService $service;

    public function __construct()
    {
        $this->service = new WarehouseService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'warehouses.view'
        );

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

            View::render('warehouses.index', [
                'currentUser' => $currentUser,
                'warehouses' => $result['items'],
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
            redirect('/warehouses');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'warehouses.create'
        );

        View::render('warehouses.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'is_default' => 0,
                'allow_negative_stock' => 0,
                'status' => 'active',
            ],
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'warehouses.create'
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
                'Warehouse created successfully.'
            );

            redirect('/warehouses');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/warehouses/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'warehouses.update'
        );

        $warehouseId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $warehouse = $this->service->find(
                (int) $currentUser['company_id'],
                $warehouseId
            );

            $input = array_merge(
                $warehouse->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('warehouses.form', [
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
            'warehouses.update'
        );

        $this->verifyCsrf();

        $warehouseId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $warehouseId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Warehouse updated successfully.'
            );

            redirect('/warehouses');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());

            redirect(
                '/warehouses/edit?id=' . $warehouseId
            );
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'warehouses.delete'
        );

        $this->verifyCsrf();

        $warehouseId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $warehouseId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Warehouse deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/warehouses');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'warehouses.restore'
        );

        $this->verifyCsrf();

        $warehouseId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $warehouseId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Warehouse restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/warehouses?deleted=1');
    }
}