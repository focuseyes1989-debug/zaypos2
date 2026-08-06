<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\UnitService;

final class UnitController extends BaseAdminController
{
    private UnitService $service;

    public function __construct()
    {
        $this->service = new UnitService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('units.view');

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

            View::render('units.index', [
                'currentUser' => $currentUser,
                'units' => $result['items'],
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
            redirect('/units');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission('units.create');

        View::render('units.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [
                'status' => 'active',
                'sort_order' => 0,
                'decimal_places' => 0,
            ],
            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission('units.create');

        $this->verifyCsrf();

        try {
            $this->service->create(
                (int) $currentUser['company_id'],
                (int) $currentUser['id'],
                $_POST
            );

            flash('success', 'Unit created successfully.');
            redirect('/units');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/units/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission('units.update');

        $unitId = $this->positiveId($_GET['id'] ?? null);

        try {
            $unit = $this->service->find(
                (int) $currentUser['company_id'],
                $unitId
            );

            $input = array_merge(
                $unit->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('units.form', [
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
        $currentUser = $this->requirePermission('units.update');

        $this->verifyCsrf();

        $unitId = $this->positiveId($_POST['id'] ?? null);

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $unitId,
                (int) $currentUser['id'],
                $_POST
            );

            flash('success', 'Unit updated successfully.');
            redirect('/units');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/units/edit?id=' . $unitId);
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission('units.delete');

        $this->verifyCsrf();

        $unitId = $this->positiveId($_POST['id'] ?? null);

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $unitId,
                (int) $currentUser['id']
            );

            flash('success', 'Unit deleted successfully.');
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/units');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission('units.restore');

        $this->verifyCsrf();

        $unitId = $this->positiveId($_POST['id'] ?? null);

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $unitId,
                (int) $currentUser['id']
            );

            flash('success', 'Unit restored successfully.');
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/units?deleted=1');
    }
}