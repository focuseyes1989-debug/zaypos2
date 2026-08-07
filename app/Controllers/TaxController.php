<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\TaxService;

final class TaxController extends BaseAdminController
{
    private TaxService $service;

    public function __construct()
    {
        $this->service = new TaxService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'taxes.view'
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

            View::render('taxes.index', [
                'currentUser' => $currentUser,
                'taxes' => $result['items'],
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
            redirect('/taxes');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'taxes.create'
        );

        View::render('taxes.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'tax_type' => 'percentage',
                'rate' => 0,
                'price_includes_tax' => 0,
                'applies_to_sales' => 1,
                'applies_to_purchases' => 1,
                'is_default' => 0,
                'status' => 'active',
            ],
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'taxes.create'
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
                'Tax created successfully.'
            );

            redirect('/taxes');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/taxes/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'taxes.update'
        );

        $taxId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $tax = $this->service->find(
                (int) $currentUser['company_id'],
                $taxId
            );

            $input = array_merge(
                $tax->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('taxes.form', [
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
            'taxes.update'
        );

        $this->verifyCsrf();

        $taxId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $taxId,
                (int) $currentUser['id'],
                $_POST
            );

            flash(
                'success',
                'Tax updated successfully.'
            );

            redirect('/taxes');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());

            redirect(
                '/taxes/edit?id=' . $taxId
            );
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'taxes.delete'
        );

        $this->verifyCsrf();

        $taxId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $taxId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Tax deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/taxes');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'taxes.restore'
        );

        $this->verifyCsrf();

        $taxId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $taxId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Tax restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/taxes?deleted=1');
    }
}