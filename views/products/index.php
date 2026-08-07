<?php

declare(strict_types=1);

use App\Models\Product;
use App\Security\Csrf;

$queryForPage = static function (int $targetPage) use ($filters): string {
    return http_build_query([
        'search' => $filters['search'],
        'status' => $filters['status'],
        'product_type' => $filters['product_type'],
        'category_id' => $filters['category_id'] ?? '',
        'brand_id' => $filters['brand_id'] ?? '',
        'deleted' => $filters['deleted'] ? '1' : '',
        'page' => $targetPage,
    ]);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Products — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >

    <style>
        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 280px;
        }

        .product-thumb {
            width: 56px;
            height: 56px;
            flex: 0 0 56px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #d9dee7;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-thumb img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .product-thumb-placeholder {
            font-size: 10px;
            line-height: 1.2;
            color: #64748b;
            text-align: center;
            padding: 5px;
        }

        .product-details {
            min-width: 0;
        }

        .product-details strong {
            display: block;
            margin-bottom: 5px;
        }

        .product-details small {
            display: block;
            line-height: 1.5;
        }
    </style>
</head>

<body class="app-page">

<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="dashboard-shell">
    <section class="page-heading">
        <div>
            <p class="eyebrow">MASTER DATA</p>

            <h1>Products</h1>

            <p class="muted">
                Manage stock items, services, pricing and product settings.
            </p>
        </div>

        <?php if (
            !$filters['deleted']
            && in_array(
                'products.create',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a
                class="primary-link"
                href="<?= e(app_url('/products/create')) ?>"
            >
                Add product
            </a>
        <?php endif; ?>
    </section>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form
        class="filter-bar"
        method="get"
        action="<?= e(app_url('/products')) ?>"
    >
        <input
            name="search"
            value="<?= e($filters['search']) ?>"
            maxlength="190"
            placeholder="Search name, code, SKU or barcode"
        >

        <select name="product_type">
            <option value="">All types</option>

            <option
                value="stock"
                <?= $filters['product_type'] === 'stock'
                    ? 'selected'
                    : '' ?>
            >
                Stock
            </option>

            <option
                value="service"
                <?= $filters['product_type'] === 'service'
                    ? 'selected'
                    : '' ?>
            >
                Service
            </option>
        </select>

        <select name="category_id">
            <option value="">All categories</option>

            <?php foreach ($categoryOptions as $option): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) ($filters['category_id'] ?? 0)
                        === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="brand_id">
            <option value="">All brands</option>

            <?php foreach ($brandOptions as $option): ?>
                <option
                    value="<?= (int) $option['id'] ?>"
                    <?= (int) ($filters['brand_id'] ?? 0)
                        === (int) $option['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">All statuses</option>

            <option
                value="active"
                <?= $filters['status'] === 'active'
                    ? 'selected'
                    : '' ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= $filters['status'] === 'inactive'
                    ? 'selected'
                    : '' ?>
            >
                Inactive
            </option>
        </select>

        <select name="deleted">
            <option
                value=""
                <?= !$filters['deleted']
                    ? 'selected'
                    : '' ?>
            >
                Current products
            </option>

            <option
                value="1"
                <?= $filters['deleted']
                    ? 'selected'
                    : '' ?>
            >
                Deleted products
            </option>
        </select>

        <button
            class="secondary-button"
            type="submit"
        >
            Filter
        </button>

        <a
            class="text-link"
            href="<?= e(app_url('/products')) ?>"
        >
            Clear
        </a>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Purchase</th>
                    <th>Sale</th>
                    <th>Wholesale</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th class="actions-column">Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($products === []): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">
                            No products found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($products as $product): ?>
                    <?php
                    if (!$product instanceof Product) {
                        continue;
                    }

                    $imagePath = $product->imagePath();
                    ?>

                    <tr>
                        <td>
                            <div class="product-cell">
                                <?php if (
                                    $imagePath !== null
                                    && $imagePath !== ''
                                ): ?>
                                    <div class="product-thumb">
                                        <img
                                            src="<?= e(
                                                app_url(
                                                    '/' . ltrim(
                                                        $imagePath,
                                                        '/'
                                                    )
                                                )
                                            ) ?>"
                                            alt="<?= e($product->name()) ?>"
                                            loading="lazy"
                                        >
                                    </div>
                                <?php else: ?>
                                    <div class="product-thumb">
                                        <div class="product-thumb-placeholder">
                                            No image
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="product-details">
                                    <strong>
                                        <?= e($product->name()) ?>
                                    </strong>

                                    <small>
                                        Code:
                                        <code>
                                            <?= e($product->code()) ?>
                                        </code>

                                        <?php if ($product->sku() !== null): ?>
                                            &nbsp; SKU:
                                            <code>
                                                <?= e($product->sku()) ?>
                                            </code>
                                        <?php endif; ?>

                                        <?php if ($product->barcode() !== null): ?>
                                            &nbsp; Barcode:
                                            <code>
                                                <?= e($product->barcode()) ?>
                                            </code>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </td>

                        <td>
                            <?= e(
                                ucfirst(
                                    $product->productType()
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $product->purchasePrice(),
                                    2
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $product->salePrice(),
                                    2
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                number_format(
                                    $product->wholesalePrice(),
                                    2
                                )
                            ) ?>
                        </td>

                        <td>
                            <?php if ($product->isServiceProduct()): ?>
                                Service
                            <?php elseif ($product->tracksStock()): ?>
                                Tracked

                                <?php if ($product->allowsNegativeStock()): ?>
                                    <small>
                                        Negative allowed
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                Not tracked
                            <?php endif; ?>
                        </td>

                        <td>
                            <span
                                class="status-badge <?= $product->isActive()
                                    ? 'status-active'
                                    : 'status-inactive' ?>"
                            >
                                <?= e(
                                    ucfirst(
                                        $product->status()
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td class="actions-column">
                            <?php if ($filters['deleted']): ?>

                                <?php if (
                                    in_array(
                                        'products.restore',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/products/restore')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Restore this product?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $product->id() ?>"
                                        >

                                        <button
                                            class="table-button"
                                            type="submit"
                                        >
                                            Restore
                                        </button>
                                    </form>
                                <?php endif; ?>

                            <?php else: ?>

                                <?php if (
                                    in_array(
                                        'products.update',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <a
                                        class="table-link"
                                        href="<?= e(
                                            app_url(
                                                '/products/edit?id='
                                                . (int) $product->id()
                                            )
                                        ) ?>"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        'products.delete',
                                        $currentUser['permissions'] ?? [],
                                        true
                                    )
                                ): ?>
                                    <form
                                        method="post"
                                        action="<?= e(
                                            app_url('/products/delete')
                                        ) ?>"
                                        onsubmit="return confirm(
                                            'Delete this product?'
                                        );"
                                    >
                                        <?= Csrf::input() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $product->id() ?>"
                                        >

                                        <button
                                            class="table-button"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>

                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($lastPage > 1): ?>
        <nav
            class="pagination"
            aria-label="Product pages"
        >
            <?php for (
                $number = 1;
                $number <= $lastPage;
                $number++
            ): ?>
                <a
                    class="<?= $number === $page
                        ? 'current'
                        : '' ?>"
                    href="<?= e(
                        app_url(
                            '/products?'
                            . $queryForPage($number)
                        )
                    ) ?>"
                >
                    <?= $number ?>
                </a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</main>

</body>
</html>