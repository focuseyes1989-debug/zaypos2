<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\BrandService;

final class BrandController extends BaseAdminController
{
    private BrandService $service;

    public function __construct()
    {
        $this->service = new BrandService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('brands.view');

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

            View::render('brands.index', [
                'currentUser' => $currentUser,
                'brands' => $result['items'],
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
            redirect('/brands');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'brands.create'
        );

        View::render('brands.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'status' => 'active',
                'sort_order' => 0,
            ],
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'brands.create'
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
                'Brand created successfully.'
            );

            redirect('/brands');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash('error', $exception->getMessage());

            redirect('/brands/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'brands.update'
        );

        $brandId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $brand = $this->service->find(
                (int) $currentUser['company_id'],
                $brandId
            );

            $input = array_merge(
                $brand->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('brands.form', [
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
            'brands.update'
        );

        $this->verifyCsrf();

        $brandId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $brandId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Brand updated successfully.'
            );

            redirect('/brands');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash('error', $exception->getMessage());

            redirect('/brands/edit?id=' . $brandId);
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'brands.delete'
        );

        $this->verifyCsrf();

        $brandId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $brandId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Brand deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/brands');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'brands.restore'
        );

        $this->verifyCsrf();

        $brandId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $brandId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Brand restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/brands?deleted=1');
    }
}