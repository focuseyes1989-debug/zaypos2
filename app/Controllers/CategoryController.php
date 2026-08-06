<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\CategoryService;

final class CategoryController extends BaseAdminController
{
    private CategoryService $service;

    public function __construct()
    {
        $this->service = new CategoryService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('categories.view');

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

            View::render('categories.index', [
                'currentUser' => $currentUser,
                'categories' => $result['items'],
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
            redirect('/categories');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'categories.create'
        );

        View::render('categories.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'status' => 'active',
                'sort_order' => 0,
            ],
            'parents' => $this->service->parentOptions(
                (int) $currentUser['company_id']
            ),
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'categories.create'
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
                'Category created successfully.'
            );

            redirect('/categories');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash('error', $exception->getMessage());

            redirect('/categories/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'categories.update'
        );

        $categoryId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $category = $this->service->find(
                (int) $currentUser['company_id'],
                $categoryId
            );

            $input = array_merge(
                $category->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('categories.form', [
                'currentUser' => $currentUser,
                'mode' => 'edit',
                'input' => $input,
                'parents' => $this->service->parentOptions(
                    (int) $currentUser['company_id'],
                    $categoryId
                ),
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
            'categories.update'
        );

        $this->verifyCsrf();

        $categoryId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $categoryId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Category updated successfully.'
            );

            redirect('/categories');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash('error', $exception->getMessage());

            redirect('/categories/edit?id=' . $categoryId);
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'categories.delete'
        );

        $this->verifyCsrf();

        $categoryId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $categoryId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Category deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/categories');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'categories.restore'
        );

        $this->verifyCsrf();

        $categoryId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $categoryId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Category restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/categories?deleted=1');
    }
}