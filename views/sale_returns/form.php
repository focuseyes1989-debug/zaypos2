<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Security\Csrf;

$permissions =
    $currentUser['permissions'] ?? [];

if (!$sale instanceof Sale) {
    throw new RuntimeException(
        'Sale is required.'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Create Sale Return — ZAY POS 2.0</title>

    <link
        rel="stylesheet"
        href="<?= e(app_url('/assets/css/app.css')) ?>"
    >
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
                SALES RETURN
            </p>

            <h1>
                Create Sale Return
            </h1>

            <p class="muted">
                Create a draft return against
                <?= e($sale->saleNumber()) ?>.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sales/show?id='
                        . $sale->id()
                    )
                ) ?>"
            >
                Back to Sale
            </a>

            <a
                class="secondary-link"
                href="<?= e(
                    app_url(
                        '/sale-returns'
                    )
                ) ?>"
            >
                Sale Returns
            </a>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="error-box">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="form-card">
        <div class="table-card-heading">
            <div>
                <h2>
                    Original Sale
                </h2>

                <p class="muted">
                    The return will use the original
                    sale warehouse and customer.
                </p>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <small>
                    Sale number
                </small>

                <strong>
                    <?= e(
                        $sale->saleNumber()
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Sale date
                </small>

                <strong>
                    <?= e(
                        $sale
                            ->saleDate()
                            ->format(
                                'Y-m-d'
                            )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Warehouse ID
                </small>

                <strong>
                    <?= (int)
                        $sale->warehouseId()
                    ?>
                </strong>
            </div>

            <div>
                <small>
                    Customer ID
                </small>

                <strong>
                    <?= $sale->customerId()
                        !== null
                        ? (int)
                            $sale
                                ->customerId()
                        : 'Walk-in / None'
                    ?>
                </strong>
            </div>

            <div>
                <small>
                    Sale total
                </small>

                <strong>
                    <?= e(
                        number_format(
                            $sale
                                ->grandTotal(),
                            2
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <small>
                    Sale status
                </small>

                <strong>
                    <?= e(
                        ucfirst(
                            $sale->status()
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="form-card">
        <form
            method="post"
            action="<?= e(
                app_url(
                    '/sale-returns'
                )
            ) ?>"
        >
            <?= Csrf::input() ?>

            <input
                type="hidden"
                name="sale_id"
                value="<?= (int)
                    $sale->id()
                ?>"
            >

            <div class="form-grid">
                <div class="field">
                    <label for="return_number">
                        Return number
                    </label>

                    <input
                        id="return_number"
                        name="return_number"
                        type="text"
                        maxlength="100"
                        value="<?= e(
                            $input[
                                'return_number'
                            ] ?? ''
                        ) ?>"
                        placeholder="Leave blank to auto-generate"
                    >

                    <small>
                        Leave blank to generate automatically.
                    </small>
                </div>

                <div class="field">
                    <label for="return_date">
                        Return date
                    </label>

                    <input
                        id="return_date"
                        name="return_date"
                        type="date"
                        value="<?= e(
                            $input[
                                'return_date'
                            ]
                            ?? date(
                                'Y-m-d'
                            )
                        ) ?>"
                        required
                    >
                </div>

                <div class="field full">
                    <label for="reason">
                        Return reason
                    </label>

                    <input
                        id="reason"
                        name="reason"
                        type="text"
                        maxlength="500"
                        value="<?= e(
                            $input['reason']
                            ?? ''
                        ) ?>"
                        placeholder="Why is the customer returning the goods?"
                    >
                </div>

                <div class="field full">
                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="5"
                        maxlength="5000"
                        placeholder="Optional internal notes"
                    ><?= e(
                        $input['notes']
                        ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="secondary-link"
                    href="<?= e(
                        app_url(
                            '/sales/show?id='
                            . $sale->id()
                        )
                    ) ?>"
                >
                    Cancel
                </a>

                <button
                    class="primary-button"
                    type="submit"
                >
                    Create Draft Return
                </button>
            </div>
        </form>
    </section>
</main>

</body>
</html>