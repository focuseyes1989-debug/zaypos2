<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\AdminRepository;
use App\Services\RoleManagementService;

final class RoleController extends BaseAdminController
{
    private AdminRepository $repository;
    private RoleManagementService $service;

    public function __construct()
    {
        $this->repository = new AdminRepository();
        $this->service = new RoleManagementService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('roles.manage');
        View::render('roles.index', [
            'currentUser' => $currentUser,
            'roles' => $this->repository->roles((int) $currentUser['company_id']),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission('roles.manage');
        View::render('roles.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [],
            'permissions' => $this->repository->permissions(),
            'error' => flash('error'),
        ]);
        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission('roles.manage');
        $this->verifyCsrf();
        try {
            $this->service->create((int) $currentUser['company_id'], $_POST);
            flash('success', 'Role created successfully.');
            redirect('/roles');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/roles/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission('roles.manage');
        $roleId = $this->positiveId($_GET['id'] ?? null);
        $role = $this->repository->findRole((int) $currentUser['company_id'], $roleId);
        if ($role === null) {
            http_response_code(404);
            View::render('errors.404');
            return;
        }
        if ((int) $role['is_system'] === 1) {
            flash('error', 'Built-in system roles cannot be modified.');
            redirect('/roles');
        }
        View::render('roles.form', [
            'currentUser' => $currentUser,
            'mode' => 'edit',
            'input' => array_merge($role, $_SESSION['_old'] ?? []),
            'permissions' => $this->repository->permissions(),
            'error' => flash('error'),
        ]);
        unset($_SESSION['_old']);
    }

    public function update(): void
    {
        $currentUser = $this->requirePermission('roles.manage');
        $this->verifyCsrf();
        $roleId = $this->positiveId($_POST['id'] ?? null);
        try {
            $this->service->update((int) $currentUser['company_id'], $roleId, $_POST);
            flash('success', 'Role updated successfully.');
            redirect('/roles');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/roles/edit?id=' . $roleId);
        }
    }
}
