<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Services\ProductService;

final class ProductController extends BaseAdminController
{
    private ProductService $service;
    private CategoryRepository $categoryRepository;
    private BrandRepository $brandRepository;
    private UnitRepository $unitRepository;
    private TaxRepository $taxRepository;

    public function __construct()
    {
        $this->service = new ProductService();
        $this->categoryRepository = new CategoryRepository();
        $this->brandRepository = new BrandRepository();
        $this->unitRepository = new UnitRepository();
        $this->taxRepository = new TaxRepository();
    }

    public function index(): void
    {
        $currentUser = $this->requirePermission(
            'products.view'
        );

        $companyId = (int) $currentUser['company_id'];

        try {
            $result = $this->service->paginate(
                $companyId,
                [
                    'search' => $_GET['search'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'product_type' => $_GET['product_type'] ?? '',
                    'category_id' => $_GET['category_id'] ?? null,
                    'brand_id' => $_GET['brand_id'] ?? null,
                    'page' => $_GET['page'] ?? 1,
                    'per_page' => 20,
                    'deleted' => $_GET['deleted'] ?? false,
                ]
            );

            View::render('products.index', [
                'currentUser' => $currentUser,
                'products' => $result['items'],
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['per_page'],
                'lastPage' => $result['last_page'],

                'categoryOptions' =>
                    $this->categoryRepository->activeOptions(
                        $companyId
                    ),

                'brandOptions' =>
                    $this->brandRepository->activeOptions(
                        $companyId
                    ),

                'filters' => [
                    'search' => trim(
                        (string) ($_GET['search'] ?? '')
                    ),
                    'status' => trim(
                        (string) ($_GET['status'] ?? '')
                    ),
                    'product_type' => trim(
                        (string) ($_GET['product_type'] ?? '')
                    ),
                    'category_id' => (
                        isset($_GET['category_id'])
                        && $_GET['category_id'] !== ''
                    )
                        ? (int) $_GET['category_id']
                        : null,
                    'brand_id' => (
                        isset($_GET['brand_id'])
                        && $_GET['brand_id'] !== ''
                    )
                        ? (int) $_GET['brand_id']
                        : null,
                    'deleted' => filter_var(
                        $_GET['deleted'] ?? false,
                        FILTER_VALIDATE_BOOL
                    ),
                ],

                'success' => flash('success'),
                'error' => flash('error'),
            ]);
        } catch (ValidationException $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/products');
        }
    }

    public function create(): void
    {
        $currentUser = $this->requirePermission(
            'products.create'
        );

        $companyId = (int) $currentUser['company_id'];

        View::render('products.form', [
            'currentUser' => $currentUser,
            'mode' => 'create',

            'input' => $_SESSION['_old'] ?? [
                'product_type' => 'stock',
                'purchase_price' => 0,
                'sale_price' => 0,
                'wholesale_price' => 0,
                'track_stock' => 1,
                'allow_negative_stock' => 0,
                'reorder_level' => 0,
                'status' => 'active',
            ],

            'categoryOptions' =>
                $this->categoryRepository->activeOptions(
                    $companyId
                ),

            'brandOptions' =>
                $this->brandRepository->activeOptions(
                    $companyId
                ),

            'unitOptions' =>
                $this->unitRepository->activeOptions(
                    $companyId
                ),

            'taxOptions' =>
                $this->taxRepository->activeOptions(
                    $companyId
                ),

            'error' => flash('error'),
        ]);

        unset($_SESSION['_old']);
    }

    public function store(): void
    {
        $currentUser = $this->requirePermission(
            'products.create'
        );

        $this->verifyCsrf();

        try {
            $this->service->create(
                (int) $currentUser['company_id'],
                (int) $currentUser['id'],
                $_POST,
                $_FILES['image'] ?? null
            );

            flash(
                'success',
                'Product created successfully.'
            );

            redirect('/products');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash(
                'error',
                $exception->getMessage()
            );

            redirect('/products/create');
        }
    }

    public function edit(): void
    {
        $currentUser = $this->requirePermission(
            'products.update'
        );

        $companyId = (int) $currentUser['company_id'];

        $productId = $this->positiveId(
            $_GET['id'] ?? null
        );

        try {
            $product = $this->service->find(
                $companyId,
                $productId
            );

            $input = array_merge(
                $product->toArray(),
                $_SESSION['_old'] ?? []
            );

            View::render('products.form', [
                'currentUser' => $currentUser,
                'mode' => 'edit',
                'input' => $input,

                'categoryOptions' =>
                    $this->categoryRepository->activeOptions(
                        $companyId
                    ),

                'brandOptions' =>
                    $this->brandRepository->activeOptions(
                        $companyId
                    ),

                'unitOptions' =>
                    $this->unitRepository->activeOptions(
                        $companyId
                    ),

                'taxOptions' =>
                    $this->taxRepository->activeOptions(
                        $companyId
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
            'products.update'
        );

        $this->verifyCsrf();

        $productId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->update(
                (int) $currentUser['company_id'],
                $productId,
                (int) $currentUser['id'],
                $_POST,
                $_FILES['image'] ?? null
            );

            flash(
                'success',
                'Product updated successfully.'
            );

            redirect('/products');
        } catch (ValidationException $exception) {
            $this->rememberOld($_POST);

            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/products/edit?id=' . $productId
            );
        }
    }

    public function delete(): void
    {
        $currentUser = $this->requirePermission(
            'products.delete'
        );

        $this->verifyCsrf();

        $productId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->delete(
                (int) $currentUser['company_id'],
                $productId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Product deleted successfully.'
            );
        } catch (ValidationException $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect('/products');
    }

    public function restore(): void
    {
        $currentUser = $this->requirePermission(
            'products.restore'
        );

        $this->verifyCsrf();

        $productId = $this->positiveId(
            $_POST['id'] ?? null
        );

        try {
            $this->service->restore(
                (int) $currentUser['company_id'],
                $productId,
                (int) $currentUser['id']
            );

            flash(
                'success',
                'Product restored successfully.'
            );
        } catch (ValidationException $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect('/products?deleted=1');
    }
}