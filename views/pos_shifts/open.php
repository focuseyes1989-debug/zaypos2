<?php

declare(strict_types=1);

use App\Security\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Open POS Shift — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(
            app_url('/assets/css/app.css')
        ) ?>"
    >

    <style>
        .shift-form-shell {
            max-width: 820px;
            margin: 0 auto;
        }

        .shift-form-card {
            background: var(--surface, #fff);
            border: 1px solid
                var(--border-color, #dfe3e8);
            border-radius: 14px;
            padding: 22px;
        }

        .shift-form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .shift-form-grid .full {
            grid-column: 1 / -1;
        }

        .shift-form-note {
            margin-top: 18px;
            padding: 14px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid
                var(--border-color, #dfe3e8);
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 640px) {
            .shift-form-grid {
                grid-template-columns: 1fr;
            }

            .shift-form-grid .full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body class="app-page">

<?php
require BASE_PATH
    . '/views/partials/admin_header.php';
?>

<main class="dashboard-shell">
    <section class="page-heading">
        <div>
            <p class="eyebrow">
                POS MANAGEMENT
            </p>

            <h1>
                Open POS Shift
            </h1>

            <p class="muted">
                Start a cashier shift with an opening cash balance.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos-shifts')
                ) ?>"
            >
                POS Shifts
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url('/pos')
                ) ?>"
            >
                POS Checkout
            </a>
        </div>
    </section>

    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="shift-form-shell">
        <section class="shift-form-card">
            <form
                method="post"
                action="<?= e(
                    app_url('/pos-shifts/open')
                ) ?>"
            >
                <?= Csrf::input() ?>

                <div class="shift-form-grid">
                    <div class="field full">
                        <label for="warehouse_id">
                            Warehouse
                        </label>

                        <select
                            id="warehouse_id"
                            name="warehouse_id"
                            required
                        >
                            <option value="">
                                Select warehouse
                            </option>

                            <?php foreach (
                                $warehouseOptions
                                ?? []
                                as $warehouse
                            ): ?>
                                <?php
                                $warehouseId =
                                    is_array($warehouse)
                                        ? (
                                            $warehouse['id']
                                            ?? null
                                        )
                                        : (
                                            method_exists(
                                                $warehouse,
                                                'id'
                                            )
                                                ? $warehouse->id()
                                                : null
                                        );

                                $warehouseName =
                                    is_array($warehouse)
                                        ? (
                                            $warehouse['name']
                                            ?? (
                                                '#' . $warehouseId
                                            )
                                        )
                                        : (
                                            method_exists(
                                                $warehouse,
                                                'name'
                                            )
                                                ? $warehouse->name()
                                                : (
                                                    '#'
                                                    . $warehouseId
                                                )
                                        );
                                ?>

                                <?php if (
                                    $warehouseId !== null
                                ): ?>
                                    <option
                                        value="<?= e(
                                            (string)
                                            $warehouseId
                                        ) ?>"
                                    >
                                        <?= e(
                                            (string)
                                            $warehouseName
                                        ) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field full">
                        <label for="opening_cash">
                            Opening Cash
                        </label>

                        <input
                            id="opening_cash"
                            name="opening_cash"
                            type="number"
                            min="0"
                            step="0.0001"
                            value="0"
                            required
                        >

                        <p class="muted">
                            Cash physically available in the drawer
                            when the shift begins.
                        </p>
                    </div>

                    <div class="field full">
                        <label for="opening_notes">
                            Opening Notes
                        </label>

                        <textarea
                            id="opening_notes"
                            name="opening_notes"
                            rows="4"
                            placeholder="Optional notes..."
                        ></textarea>
                    </div>
                </div>

                <div class="shift-form-note">
                    Only one open POS shift is allowed per cashier.
                    POS checkout will automatically link new sales
                    to this shift.
                </div>

                <div class="form-actions">
                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Open Shift
                    </button>

                    <a
                        class="secondary-link"
                        href="<?= e(
                            app_url('/pos-shifts')
                        ) ?>"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </section>
    </div>
</main>

</body>
</html>
