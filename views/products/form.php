<?php

declare(strict_types=1);

use App\Security\Csrf;

$isEdit = $mode === 'edit';

$selectedBaseUnitId = (int) ($input['base_unit_id'] ?? 0);
$selectedPurchaseUnitId = (int) ($input['purchase_unit_id'] ?? 0);
$selectedSaleUnitId = (int) ($input['sale_unit_id'] ?? 0);

$currentImagePath = trim((string) ($input['image_path'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $isEdit ? 'Edit product' : 'Add product' ?>
        — ZAY POS 2.0
    </title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >

    <style>
        .product-image-field {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .product-image-preview-box {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .product-image-preview {
            width: 150px;
            height: 150px;
            border: 1px solid #d9dee7;
            border-radius: 14px;
            background: #f8fafc;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image-preview img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image-placeholder {
            width: 150px;
            height: 150px;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            background: #f8fafc;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 16px;
            box-sizing: border-box;
            font-size: 13px;
        }

        .product-image-help {
            display: block;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .product-image-remove {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 400;
        }

        .product-image-remove input {
            width: auto;
            margin: 0;
        }

        .product-image-file {
            max-width: 520px;
        }

        @media (max-width: 640px) {
            .product-image-preview,
            .product-image-placeholder {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>

<body class="app-page">

<?php require BASE_PATH . '/views/partials/admin_header.php'; ?>

<main class="form-shell">
    <div class="breadcrumb">
        <a href="<?= e(app_url('/products')) ?>">
            Products
        </a>

        <span>/</span>

        <span>
            <?= $isEdit ? 'Edit' : 'Add product' ?>
        </span>
    </div>

    <section class="form-card">
        <div class="form-heading">
            <h1>
                <?= $isEdit
                    ? 'Edit product'
                    : 'Add product' ?>
            </h1>

            <p class="muted">
                Manage product identity, image, units, pricing and stock rules.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            enctype="multipart/form-data"
            action="<?= e(
                app_url(
                    $isEdit
                        ? '/products/update'
                        : '/products'
                )
            ) ?>"
        >
            <?= Csrf::input() ?>

            <?php if ($isEdit): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) ($input['id'] ?? 0) ?>"
                >
            <?php endif; ?>

            <div class="form-grid">

                <div class="field full">
                    <label for="name">
                        Product name
                    </label>

                    <input
                        id="name"
                        name="name"
                        value="<?= e($input['name'] ?? '') ?>"
                        maxlength="190"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="code">
                        Product code
                    </label>

                    <input
                        id="code"
                        name="code"
                        value="<?= e($input['code'] ?? '') ?>"
                        maxlength="80"
                        pattern="[a-z0-9][a-z0-9_-]*"
                        placeholder="product-code"
                        required
                    >
                </div>

                <div class="field">
                    <label for="sku">
                        SKU
                    </label>

                    <input
                        id="sku"
                        name="sku"
                        value="<?= e($input['sku'] ?? '') ?>"
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="barcode">
                        Barcode
                    </label>

                    <input
                        id="barcode"
                        name="barcode"
                        value="<?= e($input['barcode'] ?? '') ?>"
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="product_type">
                        Product type
                    </label>

                    <select
                        id="product_type"
                        name="product_type"
                    >
                        <option
                            value="stock"
                            <?= ($input['product_type'] ?? 'stock')
                                === 'stock'
                                ? 'selected'
                                : '' ?>
                        >
                            Stock
                        </option>

                        <option
                            value="service"
                            <?= ($input['product_type'] ?? '')
                                === 'service'
                                ? 'selected'
                                : '' ?>
                        >
                            Service
                        </option>
                    </select>
                </div>

                <div class="field full product-image-field">
                    <label for="image">
                        Product image
                    </label>

                    <div class="product-image-preview-box">
                        <?php if ($currentImagePath !== ''): ?>
                            <div
                                class="product-image-preview"
                                id="current-image-box"
                            >
                                <img
                                    src="<?= e(
                                        app_url(
                                            '/' . ltrim(
                                                $currentImagePath,
                                                '/'
                                            )
                                        )
                                    ) ?>"
                                    alt="<?= e(
                                        $input['name']
                                            ?? 'Product image'
                                    ) ?>"
                                >
                            </div>
                        <?php else: ?>
                            <div
                                class="product-image-placeholder"
                                id="current-image-box"
                            >
                                No product image
                            </div>
                        <?php endif; ?>

                        <div
                            class="product-image-preview"
                            id="new-image-preview-box"
                            style="display: none;"
                        >
                            <img
                                id="new-image-preview"
                                src=""
                                alt="New product image preview"
                            >
                        </div>
                    </div>

                    <input
                        class="product-image-file"
                        id="image"
                        name="image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <small class="product-image-help">
                        Upload a JPG, PNG or WebP product image.
                        A new image will replace the current image when editing.
                    </small>

                    <?php if (
                        $isEdit
                        && $currentImagePath !== ''
                    ): ?>
                        <label class="product-image-remove">
                            <input
                                id="remove_image"
                                type="checkbox"
                                name="remove_image"
                                value="1"
                            >

                            Remove current product image
                        </label>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="category_id">
                        Category
                    </label>

                    <select
                        id="category_id"
                        name="category_id"
                    >
                        <option value="">
                            No category
                        </option>

                        <?php foreach ($categoryOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) ($input['category_id'] ?? 0)
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="brand_id">
                        Brand
                    </label>

                    <select
                        id="brand_id"
                        name="brand_id"
                    >
                        <option value="">
                            No brand
                        </option>

                        <?php foreach ($brandOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) ($input['brand_id'] ?? 0)
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="base_unit_id">
                        Base unit
                    </label>

                    <select
                        id="base_unit_id"
                        name="base_unit_id"
                        required
                    >
                        <option value="">
                            Select base unit
                        </option>

                        <?php foreach ($unitOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= $selectedBaseUnitId
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>

                                <?php if ($option['symbol'] !== null): ?>
                                    (<?= e($option['symbol']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="purchase_unit_id">
                        Purchase unit
                    </label>

                    <select
                        id="purchase_unit_id"
                        name="purchase_unit_id"
                    >
                        <option value="">
                            Same as base unit
                        </option>

                        <?php foreach ($unitOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= $selectedPurchaseUnitId
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="sale_unit_id">
                        Sale unit
                    </label>

                    <select
                        id="sale_unit_id"
                        name="sale_unit_id"
                    >
                        <option value="">
                            Same as base unit
                        </option>

                        <?php foreach ($unitOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= $selectedSaleUnitId
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="tax_id">
                        Tax
                    </label>

                    <select
                        id="tax_id"
                        name="tax_id"
                    >
                        <option value="">
                            No tax
                        </option>

                        <?php foreach ($taxOptions as $option): ?>
                            <option
                                value="<?= (int) $option['id'] ?>"
                                <?= (int) ($input['tax_id'] ?? 0)
                                    === (int) $option['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option['name']) ?>

                                <?php if (
                                    $option['tax_type']
                                    === 'percentage'
                                ): ?>
                                    (<?= e(
                                        (string) $option['rate']
                                    ) ?>%)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="purchase_price">
                        Purchase price
                    </label>

                    <input
                        id="purchase_price"
                        name="purchase_price"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['purchase_price'] ?? 0
                        ) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="sale_price">
                        Sale price
                    </label>

                    <input
                        id="sale_price"
                        name="sale_price"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['sale_price'] ?? 0
                        ) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="wholesale_price">
                        Wholesale price
                    </label>

                    <input
                        id="wholesale_price"
                        name="wholesale_price"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['wholesale_price'] ?? 0
                        ) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="reorder_level">
                        Reorder level
                    </label>

                    <input
                        id="reorder_level"
                        name="reorder_level"
                        type="number"
                        min="0"
                        step="0.0001"
                        value="<?= e(
                            $input['reorder_level'] ?? 0
                        ) ?>"
                    >
                </div>

                <div class="field">
                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >
                        <option
                            value="active"
                            <?= ($input['status'] ?? 'active')
                                === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= ($input['status'] ?? '')
                                === 'inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="track_stock"
                            value="1"
                            <?= !empty($input['track_stock'])
                                ? 'checked'
                                : '' ?>
                        >
                        Track stock
                    </label>
                </div>

                <div class="field">
                    <label>
                        <input
                            type="checkbox"
                            name="allow_negative_stock"
                            value="1"
                            <?= !empty(
                                $input['allow_negative_stock']
                            )
                                ? 'checked'
                                : '' ?>
                        >
                        Allow negative stock
                    </label>
                </div>

                <div class="field full">
                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                    ><?= e(
                        $input['description'] ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(app_url('/products')) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button compact"
                    type="submit"
                >
                    <?= $isEdit
                        ? 'Save changes'
                        : 'Create product' ?>
                </button>
            </div>
        </form>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const imageInput = document.getElementById('image');
    const previewBox = document.getElementById(
        'new-image-preview-box'
    );
    const previewImage = document.getElementById(
        'new-image-preview'
    );
    const currentImageBox = document.getElementById(
        'current-image-box'
    );
    const removeImage = document.getElementById(
        'remove_image'
    );

    if (imageInput && previewBox && previewImage) {
        imageInput.addEventListener('change', function () {
            const file = this.files && this.files[0]
                ? this.files[0]
                : null;

            if (!file) {
                previewImage.removeAttribute('src');
                previewBox.style.display = 'none';

                if (
                    currentImageBox
                    && (!removeImage || !removeImage.checked)
                ) {
                    currentImageBox.style.display = '';
                }

                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {
                alert(
                    'Please select a JPG, PNG or WebP image.'
                );

                imageInput.value = '';
                previewImage.removeAttribute('src');
                previewBox.style.display = 'none';

                if (currentImageBox) {
                    currentImageBox.style.display = '';
                }

                return;
            }

            const reader = new FileReader();

            reader.addEventListener('load', function (event) {
                previewImage.src = event.target.result;
                previewBox.style.display = 'flex';

                if (currentImageBox) {
                    currentImageBox.style.display = 'none';
                }

                if (removeImage) {
                    removeImage.checked = false;
                }
            });

            reader.readAsDataURL(file);
        });
    }

    if (removeImage) {
        removeImage.addEventListener('change', function () {
            if (this.checked) {
                if (currentImageBox) {
                    currentImageBox.style.display = 'none';
                }

                if (imageInput) {
                    imageInput.value = '';
                }

                if (previewImage) {
                    previewImage.removeAttribute('src');
                }

                if (previewBox) {
                    previewBox.style.display = 'none';
                }
            } else if (currentImageBox) {
                currentImageBox.style.display = '';
            }
        });
    }
});
</script>

</body>
</html>