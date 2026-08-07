<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BrandController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\RoleController;
use App\Controllers\UserController;
use App\Controllers\UnitController;
use App\Controllers\SupplierController;
use App\Core\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);
$router->get('/system-check', [HomeController::class, 'systemCheck']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/account/password', [
    AuthController::class,
    'showPassword',
]);
$router->post('/account/password', [
    AuthController::class,
    'changePassword',
]);

$router->get('/dashboard', [
    DashboardController::class,
    'index',
]);

$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/edit', [UserController::class, 'edit']);
$router->post('/users/update', [UserController::class, 'update']);
$router->post('/users/toggle-status', [
    UserController::class,
    'toggleStatus',
]);
$router->post('/users/reset-password', [
    UserController::class,
    'resetPassword',
]);

$router->get('/roles', [RoleController::class, 'index']);
$router->get('/roles/create', [RoleController::class, 'create']);
$router->post('/roles', [RoleController::class, 'store']);
$router->get('/roles/edit', [RoleController::class, 'edit']);
$router->post('/roles/update', [RoleController::class, 'update']);

$router->get('/categories', [
    CategoryController::class,
    'index',
]);
$router->get('/categories/create', [
    CategoryController::class,
    'create',
]);
$router->post('/categories', [
    CategoryController::class,
    'store',
]);
$router->get('/categories/edit', [
    CategoryController::class,
    'edit',
]);
$router->post('/categories/update', [
    CategoryController::class,
    'update',
]);
$router->post('/categories/delete', [
    CategoryController::class,
    'delete',
]);
$router->post('/categories/restore', [
    CategoryController::class,
    'restore',
]);
$router->get('/brands', [
    BrandController::class,
    'index',
]);

$router->get('/brands/create', [
    BrandController::class,
    'create',
]);

$router->post('/brands', [
    BrandController::class,
    'store',
]);

$router->get('/brands/edit', [
    BrandController::class,
    'edit',
]);

$router->post('/brands/update', [
    BrandController::class,
    'update',
]);

$router->post('/brands/delete', [
    BrandController::class,
    'delete',
]);

$router->post('/brands/restore', [
    BrandController::class,
    'restore',
]);

$router->get('/units', [
    UnitController::class,
    'index',
]);

$router->get('/units/create', [
    UnitController::class,
    'create',
]);

$router->post('/units', [
    UnitController::class,
    'store',
]);

$router->get('/units/edit', [
    UnitController::class,
    'edit',
]);

$router->post('/units/update', [
    UnitController::class,
    'update',
]);

$router->post('/units/delete', [
    UnitController::class,
    'delete',
]);

$router->post('/units/restore', [
    UnitController::class,
    'restore',
]);

$router->get('/suppliers', [
    SupplierController::class,
    'index',
]);

$router->get('/suppliers/create', [
    SupplierController::class,
    'create',
]);

$router->post('/suppliers', [
    SupplierController::class,
    'store',
]);

$router->get('/suppliers/edit', [
    SupplierController::class,
    'edit',
]);

$router->post('/suppliers/update', [
    SupplierController::class,
    'update',
]);

$router->post('/suppliers/delete', [
    SupplierController::class,
    'delete',
]);

$router->post('/suppliers/restore', [
    SupplierController::class,
    'restore',
]);