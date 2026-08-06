<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\AdminRepository;
use App\Services\UserManagementService;

final class UserController extends BaseAdminController
{
    private AdminRepository $repository;
    private UserManagementService $service;

    public function __construct()
    {
        $this->repository = new AdminRepository();
        $this->service = new UserManagementService();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission('users.view');
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = (string) ($_GET['status'] ?? '');
        $roleId = max(0, (int) ($_GET['role_id'] ?? 0));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->repository->paginateUsers(
            (int) $currentUser['company_id'], $search, $status, $roleId, $page
        );

        View::render('users.index', [
            'currentUser' => $currentUser,
            'users' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'roles' => $this->repository->roles((int) $currentUser['company_id']),
            'filters' => compact('search', 'status', 'roleId'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission('users.create');
        View::render('users.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',
            'input' => $_SESSION['_old'] ?? [],
            'branches' => $this->repository->branches((int) $currentUser['company_id']),
            'roles' => $this->repository->assignableRoles(
                (int) $currentUser['company_id'], $this->isSuperAdmin($currentUser)
            ),
            'error' => flash('error'),
        ]);
        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission('users.create');
        $this->verifyCsrf();
        try {
            $this->service->create(
                (int) $currentUser['company_id'], $this->isSuperAdmin($currentUser), $_POST
            );
            flash('success', 'User account created successfully.');
            redirect('/users');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/users/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission('users.update');
        $userId = $this->positiveId($_GET['id'] ?? null);
        $user = $this->repository->findUser((int) $currentUser['company_id'], $userId);
        if ($user === null) {
            http_response_code(404);
            View::render('errors.404');
            return;
        }
        if (!$this->isSuperAdmin($currentUser)
            && $this->repository->userHasRoleCode($userId, 'super_admin')) {
            http_response_code(403);
            View::render('errors.403');
            return;
        }
        $input = array_merge($user, $_SESSION['_old'] ?? []);
        View::render('users.form', [
            'currentUser' => $currentUser,
            'mode' => 'edit',
            'input' => $input,
            'branches' => $this->repository->branches((int) $currentUser['company_id']),
            'roles' => $this->repository->assignableRoles(
                (int) $currentUser['company_id'], $this->isSuperAdmin($currentUser)
            ),
            'error' => flash('error'),
        ]);
        unset($_SESSION['_old']);
    }

    public function update(): void
    {
        $currentUser = $this->requirePermission('users.update');
        $this->verifyCsrf();
        $userId = $this->positiveId($_POST['id'] ?? null);
        try {
            $this->service->update(
                (int) $currentUser['company_id'], $userId, (int) $currentUser['id'],
                $this->isSuperAdmin($currentUser), $_POST
            );
            flash('success', 'User account updated successfully.');
            redirect('/users');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);
            flash('error', $exception->getMessage());
            redirect('/users/edit?id=' . $userId);
        }
    }

    public function toggleStatus(): void
    {
        $currentUser = $this->requirePermission('users.disable');
        $this->verifyCsrf();
        $userId = $this->positiveId($_POST['id'] ?? null);
        try {
            $status = $this->service->toggleStatus(
                (int) $currentUser['company_id'], $userId, (int) $currentUser['id'],
                $this->isSuperAdmin($currentUser)
            );
            flash('success', 'User is now ' . $status . '.');
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/users');
    }

    public function resetPassword(): void
    {
        $currentUser = $this->requirePermission('users.password_reset');
        $this->verifyCsrf();
        $userId = $this->positiveId($_POST['id'] ?? null);
        try {
            $this->service->resetPassword(
                (int) $currentUser['company_id'], $userId, $this->isSuperAdmin($currentUser),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirmation'] ?? '')
            );
            flash('success', 'Password reset successfully. Existing sessions were signed out.');
        } catch (ValidationException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/users/edit?id=' . $userId);
    }

    /** @param array<string, mixed> $currentUser */
    private function isSuperAdmin(array $currentUser): bool
    {
        return in_array('super_admin', $currentUser['roles'] ?? [], true);
    }
}
