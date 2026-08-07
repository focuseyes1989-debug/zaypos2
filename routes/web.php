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
use App\Controllers\CustomerController;
use App\Controllers\WarehouseController;
use App\Controllers\TaxController;
use App\Controllers\ProductController;
use App\Controllers\InventoryController;
use App\Controllers\PurchaseController;
use App\Controllers\PurchasePaymentController;
use App\Controllers\SaleController;
use App\Controllers\SalePaymentController;
use App\Controllers\SaleReturnController;
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

$router->get('/customers', [
    CustomerController::class,
    'index',
]);

$router->get('/customers/create', [
    CustomerController::class,
    'create',
]);

$router->post('/customers', [
    CustomerController::class,
    'store',
]);

$router->get('/customers/edit', [
    CustomerController::class,
    'edit',
]);

$router->post('/customers/update', [
    CustomerController::class,
    'update',
]);

$router->post('/customers/delete', [
    CustomerController::class,
    'delete',
]);

$router->post('/customers/restore', [
    CustomerController::class,
    'restore',
]);

$router->get('/warehouses', [
    WarehouseController::class,
    'index',
]);

$router->get('/warehouses/create', [
    WarehouseController::class,
    'create',
]);

$router->post('/warehouses', [
    WarehouseController::class,
    'store',
]);

$router->get('/warehouses/edit', [
    WarehouseController::class,
    'edit',
]);

$router->post('/warehouses/update', [
    WarehouseController::class,
    'update',
]);

$router->post('/warehouses/delete', [
    WarehouseController::class,
    'delete',
]);

$router->post('/warehouses/restore', [
    WarehouseController::class,
    'restore',
]);

$router->get('/taxes', [
    TaxController::class,
    'index',
]);

$router->get('/taxes/create', [
    TaxController::class,
    'create',
]);

$router->post('/taxes', [
    TaxController::class,
    'store',
]);

$router->get('/taxes/edit', [
    TaxController::class,
    'edit',
]);

$router->post('/taxes/update', [
    TaxController::class,
    'update',
]);

$router->post('/taxes/delete', [
    TaxController::class,
    'delete',
]);

$router->post('/taxes/restore', [
    TaxController::class,
    'restore',
]);

$router->get('/products', [
    ProductController::class,
    'index',
]);

$router->get('/products/create', [
    ProductController::class,
    'create',
]);

$router->post('/products', [
    ProductController::class,
    'store',
]);

$router->get('/products/edit', [
    ProductController::class,
    'edit',
]);

$router->post('/products/update', [
    ProductController::class,
    'update',
]);

$router->post('/products/delete', [
    ProductController::class,
    'delete',
]);

$router->post('/products/restore', [
    ProductController::class,
    'restore',
]);

$router->get('/inventory', [
    InventoryController::class,
    'index',
]);

$router->get('/inventory/movements', [
    InventoryController::class,
    'movements',
]);

$router->get('/inventory/opening', [
    InventoryController::class,
    'opening',
]);

$router->post('/inventory/opening', [
    InventoryController::class,
    'storeOpening',
]);

$router->get('/inventory/adjustment', [
    InventoryController::class,
    'adjustment',
]);

$router->post('/inventory/adjustment', [
    InventoryController::class,
    'storeAdjustment',
]);

$router->get('/inventory/transfer', [
    InventoryController::class,
    'transfer',
]);

$router->post('/inventory/transfer', [
    InventoryController::class,
    'storeTransfer',
]);

$router->get('/purchases', [
    PurchaseController::class,
    'index',
]);

$router->get('/purchases/show', [
    PurchaseController::class,
    'show',
]);

$router->get('/purchases/create', [
    PurchaseController::class,
    'create',
]);

$router->post('/purchases', [
    PurchaseController::class,
    'store',
]);

$router->get('/purchases/edit', [
    PurchaseController::class,
    'edit',
]);

$router->post('/purchases/update', [
    PurchaseController::class,
    'update',
]);

$router->post('/purchases/items/add', [
    PurchaseController::class,
    'addItem',
]);

$router->post('/purchases/items/update', [
    PurchaseController::class,
    'updateItem',
]);

$router->post('/purchases/items/delete', [
    PurchaseController::class,
    'deleteItem',
]);

$router->post('/purchases/receive', [
    PurchaseController::class,
    'receive',
]);

$router->post('/purchases/cancel', [
    PurchaseController::class,
    'cancel',
]);

$router->post('/purchases/delete', [
    PurchaseController::class,
    'delete',
]);

$router->post('/purchases/restore', [
    PurchaseController::class,
    'restore',
]);

$router->get('/purchase-payments', [
    PurchasePaymentController::class,
    'index',
]);

$router->get('/purchase-payments/create', [
    PurchasePaymentController::class,
    'create',
]);

$router->post('/purchase-payments', [
    PurchasePaymentController::class,
    'store',
]);

$router->get('/purchase-payments/history', [
    PurchasePaymentController::class,
    'history',
]);

$router->post('/purchase-payments/delete', [
    PurchasePaymentController::class,
    'delete',
]);

$router->post('/purchase-payments/restore', [
    PurchasePaymentController::class,
    'restore',
]);

$router->get('/sales', [
    SaleController::class,
    'index',
]);

$router->get('/sales/show', [
    SaleController::class,
    'show',
]);

$router->get('/sales/create', [
    SaleController::class,
    'create',
]);

$router->post('/sales', [
    SaleController::class,
    'store',
]);

$router->get('/sales/edit', [
    SaleController::class,
    'edit',
]);

$router->post('/sales/update', [
    SaleController::class,
    'update',
]);

$router->post('/sales/items/add', [
    SaleController::class,
    'addItem',
]);

$router->post('/sales/items/update', [
    SaleController::class,
    'updateItem',
]);

$router->post('/sales/items/delete', [
    SaleController::class,
    'deleteItem',
]);

$router->post('/sales/complete', [
    SaleController::class,
    'complete',
]);

$router->post('/sales/cancel', [
    SaleController::class,
    'cancel',
]);

$router->post('/sales/delete', [
    SaleController::class,
    'delete',
]);

$router->post('/sales/restore', [
    SaleController::class,
    'restore',
]);

$router->get('/sale-payments', [
    SalePaymentController::class,
    'index',
]);

$router->get('/sale-payments/create', [
    SalePaymentController::class,
    'create',
]);

$router->post('/sale-payments', [
    SalePaymentController::class,
    'store',
]);

$router->get('/sale-payments/history', [
    SalePaymentController::class,
    'history',
]);

$router->post('/sale-payments/delete', [
    SalePaymentController::class,
    'delete',
]);

$router->post('/sale-payments/restore', [
    SalePaymentController::class,
    'restore',
]);

$router->get('/sale-returns', [
    SaleReturnController::class,
    'index',
]);

$router->get('/sale-returns/show', [
    SaleReturnController::class,
    'show',
]);

$router->get('/sale-returns/create', [
    SaleReturnController::class,
    'create',
]);

$router->post('/sale-returns', [
    SaleReturnController::class,
    'store',
]);

$router->get('/sale-returns/edit', [
    SaleReturnController::class,
    'edit',
]);

$router->post('/sale-returns/update', [
    SaleReturnController::class,
    'update',
]);

$router->post('/sale-returns/items/add', [
    SaleReturnController::class,
    'addItem',
]);

$router->post('/sale-returns/items/update', [
    SaleReturnController::class,
    'updateItem',
]);

$router->post('/sale-returns/items/delete', [
    SaleReturnController::class,
    'deleteItem',
]);

$router->post('/sale-returns/complete', [
    SaleReturnController::class,
    'complete',
]);

$router->post('/sale-returns/cancel', [
    SaleReturnController::class,
    'cancel',
]);

$router->post('/sale-returns/delete', [
    SaleReturnController::class,
    'delete',
]);

$router->post('/sale-returns/restore', [
    SaleReturnController::class,
    'restore',
]);

$router->get('/sale-returns/refund', [
    SaleReturnController::class,
    'refund',
]);

$router->post('/sale-returns/refund', [
    SaleReturnController::class,
    'storeRefund',
]);

$router->get('/sale-returns/refund-history', [
    SaleReturnController::class,
    'refundHistory',
]);

$router->post('/sale-returns/refunds/delete', [
    SaleReturnController::class,
    'deleteRefund',
]);

$router->post('/sale-returns/refunds/restore', [
    SaleReturnController::class,
    'restoreRefund',
]);