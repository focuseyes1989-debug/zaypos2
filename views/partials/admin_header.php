<?php

declare(strict_types=1);

use App\Security\Csrf;
?>
<header class="topbar">
    <a
        class="topbar-brand"
        href="<?= e(app_url('/dashboard')) ?>"
    >
        <span class="mini-brand">ZAY</span>
        <span>POS System 2.0</span>
    </a>

    <nav
        class="topnav"
        aria-label="Main navigation"
    >
        <a href="<?= e(app_url('/dashboard')) ?>">
            Dashboard
        </a>

        <?php if (
            in_array(
                'users.view',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a href="<?= e(app_url('/users')) ?>">
                Users
            </a>
        <?php endif; ?>

        <?php if (
            in_array(
                'roles.manage',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a href="<?= e(app_url('/roles')) ?>">
                Roles
            </a>
        <?php endif; ?>

        <?php if (
            in_array(
                'categories.view',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a href="<?= e(app_url('/categories')) ?>">
                Categories
            </a>
        <?php endif; ?>

        <?php if (
            in_array(
                'units.view',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a href="<?= e(app_url('/units')) ?>">
                Units
            </a>
        <?php endif; ?>

        <?php if (
            in_array(
                'brands.view',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a href="<?= e(app_url('/brands')) ?>">
                Brands
            </a>
        <?php endif; ?>

        <?php if (
            in_array(
                'suppliers.view',
                $currentUser['permissions'] ?? [],
                true
            )
        ): ?>
            <a href="<?= e(app_url('/suppliers')) ?>">
                Suppliers
            </a>
        <?php endif; ?>
    
    <?php if (
        in_array(
            'customers.view',
            $currentUser['permissions'] ?? [],
            true
        )
    ): ?>
        <a href="<?= e(app_url('/customers')) ?>">
            Customers
        </a>
    <?php endif; ?>    

    <?php if (
        in_array(
            'warehouses.view',
            $currentUser['permissions'] ?? [],
            true
        )
    ): ?>
        <a href="<?= e(app_url('/warehouses')) ?>">
            Warehouses
        </a>
    <?php endif; ?>

    <?php if (
        in_array(
            'taxes.view',
            $currentUser['permissions'] ?? [],
            true
        )
    ): ?>
        <a href="<?= e(app_url('/taxes')) ?>">
            Taxes
        </a>
    <?php endif; ?>

</nav>

    <div class="topbar-user">
        <div>
            <strong>
                <?= e($currentUser['full_name'] ?? '') ?>
            </strong>

            <small>
                <?= e(
                    implode(
                        ', ',
                        $currentUser['roles'] ?? []
                    )
                ) ?>
            </small>
        </div>

        <a
            class="secondary-button"
            href="<?= e(app_url('/account/password')) ?>"
        >
            Password
        </a>

        <form
            method="post"
            action="<?= e(app_url('/logout')) ?>"
        >
            <?= Csrf::input() ?>

            <button
                class="secondary-button"
                type="submit"
            >
                Sign out
            </button>
        </form>
    </div>
</header>