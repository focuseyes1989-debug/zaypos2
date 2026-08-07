<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Repositories\SaleItemRepository;
use App\Repositories\SaleRepository;
use App\Services\SaleReturnService;
use Throwable;

final class SaleReturnController extends BaseAdminController
{
    private SaleReturnService $service;

    private SaleRepository $saleRepository;

    private SaleItemRepository $saleItemRepository;

    public function __construct()
    {
        $this->service =
            new SaleReturnService();

        $this->saleRepository =
            new SaleRepository();

        $this->saleItemRepository =
            new SaleItemRepository();
    }

    /**
     * Sale return list.
     */
    public function index(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.view'
            );

        $companyId =
            (int) $currentUser['company_id'];

        try {
            $result =
                $this->service->paginate(
                    $companyId,
                    [
                        'search' =>
                            $_GET['search'] ?? '',

                        'status' =>
                            $_GET['status'] ?? '',

                        'refund_status' =>
                            $_GET[
                                'refund_status'
                            ] ?? '',

                        'sale_id' =>
                            $_GET[
                                'sale_id'
                            ] ?? null,

                        'customer_id' =>
                            $_GET[
                                'customer_id'
                            ] ?? null,

                        'warehouse_id' =>
                            $_GET[
                                'warehouse_id'
                            ] ?? null,

                        'deleted' =>
                            $_GET[
                                'deleted'
                            ] ?? false,

                        'page' =>
                            $_GET['page'] ?? 1,

                        'per_page' =>
                            20,
                    ]
                );

            View::render(
                'sale_returns.index',
                [
                    'currentUser' =>
                        $currentUser,

                    'returns' =>
                        $result['items'],

                    'total' =>
                        $result['total'],

                    'page' =>
                        $result['page'],

                    'perPage' =>
                        $result['per_page'],

                    'lastPage' =>
                        $result['last_page'],

                    'filters' => [
                        'search' =>
                            trim(
                                (string) (
                                    $_GET[
                                        'search'
                                    ]
                                    ?? ''
                                )
                            ),

                        'status' =>
                            trim(
                                (string) (
                                    $_GET[
                                        'status'
                                    ]
                                    ?? ''
                                )
                            ),

                        'refund_status' =>
                            trim(
                                (string) (
                                    $_GET[
                                        'refund_status'
                                    ]
                                    ?? ''
                                )
                            ),

                        'sale_id' =>
                            $this
                                ->nullableInteger(
                                    $_GET[
                                        'sale_id'
                                    ] ?? null
                                ),

                        'customer_id' =>
                            $this
                                ->nullableInteger(
                                    $_GET[
                                        'customer_id'
                                    ] ?? null
                                ),

                        'warehouse_id' =>
                            $this
                                ->nullableInteger(
                                    $_GET[
                                        'warehouse_id'
                                    ] ?? null
                                ),

                        'deleted' =>
                            isset(
                                $_GET[
                                    'deleted'
                                ]
                            )
                            && $_GET[
                                'deleted'
                            ] === '1',
                    ],

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns'
            );
        }
    }

    /**
     * Sale return detail.
     */
    public function show(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.view'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleReturnId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'Sale return'
            );

        try {
            $detail =
                $this->service->detail(
                    $companyId,
                    $saleReturnId,
                    true
                );

            $summary =
                $this->service
                    ->refundSummary(
                        $companyId,
                        $saleReturnId
                    );

            View::render(
                'sale_returns.show',
                [
                    'currentUser' =>
                        $currentUser,

                    'return' =>
                        $detail['return'],

                    'sale' =>
                        $detail['sale'],

                    'items' =>
                        $detail['items'],

                    'refunds' =>
                        $detail['refunds'],

                    'refundSummary' =>
                        $summary,

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns'
            );
        }
    }

    /**
     * Create return form from an original sale.
     */
    public function create(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleId =
            $this->requiredInteger(
                $_GET['sale_id'] ?? null,
                'Sale'
            );

        try {
            $sale =
                $this->saleRepository->find(
                    $companyId,
                    $saleId
                );

            if (!$sale instanceof Sale) {
                throw new ValidationException(
                    'Sale not found.'
                );
            }

            if (
                $sale->status()
                !== 'completed'
            ) {
                throw new ValidationException(
                    'Only completed sales can be returned.'
                );
            }

            if ($sale->isDeleted()) {
                throw new ValidationException(
                    'Deleted sales cannot be returned.'
                );
            }

            View::render(
                'sale_returns.form',
                [
                    'currentUser' =>
                        $currentUser,

                    'sale' =>
                        $sale,

                    'return' =>
                        null,

                    'input' =>
                        $_SESSION['_old']
                        ?? [
                            'return_number' =>
                                '',

                            'return_date' =>
                                date(
                                    'Y-m-d'
                                ),

                            'reason' =>
                                '',

                            'notes' =>
                                '',
                        ],

                    'error' =>
                        flash('error'),
                ]
            );

            unset(
                $_SESSION['_old']
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sales/show?id='
                . $saleId
            );
        }
    }

    /**
     * Create draft sale return.
     */
    public function store(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $this->verifyCsrf();

        $saleId =
            $this->requiredInteger(
                $_POST['sale_id']
                    ?? null,
                'Sale'
            );

        try {
            $return =
                $this->service->create(
                    (int) $currentUser[
                        'company_id'
                    ],
                    (int) $currentUser[
                        'id'
                    ],
                    $_POST
                );

            flash(
                'success',
                'Sale return created successfully.'
            );

            redirect(
                '/sale-returns/edit?id='
                . $return->id()
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns/create?sale_id='
                . $saleId
            );
        }
    }

    /**
     * Edit draft return header and items.
     */
    public function edit(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleReturnId =
            $this->requiredInteger(
                $_GET['id'] ?? null,
                'Sale return'
            );

        try {
            $detail =
                $this->service->detail(
                    $companyId,
                    $saleReturnId
                );

            $return =
                $detail['return'];

            if (!$return->isDraft()) {
                throw new ValidationException(
                    'Only draft sale returns can be edited.'
                );
            }

            $saleItems =
                $this->saleItemRepository
                    ->bySale(
                        $return->saleId()
                    );

            View::render(
                'sale_returns.edit',
                [
                    'currentUser' =>
                        $currentUser,

                    'return' =>
                        $return,

                    'sale' =>
                        $detail['sale'],

                    'items' =>
                        $detail['items'],

                    'saleItems' =>
                        $saleItems,

                    'input' =>
                        $_SESSION['_old']
                        ?? [],

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );

            unset(
                $_SESSION['_old']
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns'
            );
        }
    }

    /**
     * Update draft return header.
     */
    public function update(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->update(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                (int) $currentUser[
                    'id'
                ],
                $_POST
            );

            flash(
                'success',
                'Sale return updated successfully.'
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/edit?id='
            . $saleReturnId
        );
    }

    /**
     * Add return item.
     */
    public function addItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->addItem(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                (int) $currentUser[
                    'id'
                ],
                $_POST
            );

            flash(
                'success',
                'Return item added successfully.'
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/edit?id='
            . $saleReturnId
        );
    }

    /**
     * Update return item.
     */
    public function updateItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        $itemId =
            $this->requiredInteger(
                $_POST['item_id']
                    ?? null,
                'Return item'
            );

        try {
            $this->service->updateItem(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                $itemId,
                (int) $currentUser[
                    'id'
                ],
                $_POST
            );

            flash(
                'success',
                'Return item updated successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/edit?id='
            . $saleReturnId
        );
    }

    /**
     * Delete return item.
     */
    public function deleteItem(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.create'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        $itemId =
            $this->requiredInteger(
                $_POST['item_id']
                    ?? null,
                'Return item'
            );

        try {
            $this->service->deleteItem(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                $itemId,
                (int) $currentUser[
                    'id'
                ]
            );

            flash(
                'success',
                'Return item deleted successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/edit?id='
            . $saleReturnId
        );
    }

    /**
     * Complete return and put stock back.
     */
    public function complete(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.complete'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->complete(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                (int) $currentUser[
                    'id'
                ]
            );

            flash(
                'success',
                'Sale return completed and inventory updated successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/show?id='
            . $saleReturnId
        );
    }

    /**
     * Cancel draft sale return.
     */
    public function cancel(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.cancel'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->cancel(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                (int) $currentUser[
                    'id'
                ],
                isset(
                    $_POST['reason']
                )
                    ? (string) $_POST[
                        'reason'
                    ]
                    : null
            );

            flash(
                'success',
                'Sale return cancelled successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/show?id='
            . $saleReturnId
        );
    }

    /**
     * Soft-delete draft sale return.
     */
    public function delete(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.delete'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->delete(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                (int) $currentUser[
                    'id'
                ]
            );

            flash(
                'success',
                'Sale return deleted successfully.'
            );

            redirect(
                '/sale-returns'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns/show?id='
                . $saleReturnId
            );
        }
    }

    /**
     * Restore deleted draft return.
     */
    public function restore(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.restore'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $return =
                $this->service->restore(
                    (int) $currentUser[
                        'company_id'
                    ],
                    $saleReturnId,
                    (int) $currentUser[
                        'id'
                    ]
                );

            flash(
                'success',
                'Sale return restored successfully.'
            );

            redirect(
                '/sale-returns/show?id='
                . $return->id()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns?deleted=1'
            );
        }
    }

    /**
     * Refund form.
     */
    public function refund(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.refunds_create'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleReturnId =
            $this->requiredInteger(
                $_GET[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $detail =
                $this->service->detail(
                    $companyId,
                    $saleReturnId
                );

            /** @var SaleReturn $return */
            $return =
                $detail['return'];

            if (!$return->isCompleted()) {
                throw new ValidationException(
                    'Refunds can only be recorded against completed sale returns.'
                );
            }

            if ($return->isDeleted()) {
                throw new ValidationException(
                    'Deleted sale returns cannot be refunded.'
                );
            }

            $summary =
                $this->service
                    ->refundSummary(
                        $companyId,
                        $saleReturnId
                    );

            if (
                (float) $summary[
                    'refund_balance'
                ] <= 0.00005
            ) {
                throw new ValidationException(
                    'Sale return is already fully refunded.'
                );
            }

            View::render(
                'sale_returns.refund',
                [
                    'currentUser' =>
                        $currentUser,

                    'return' =>
                        $return,

                    'sale' =>
                        $detail['sale'],

                    'summary' =>
                        $summary,

                    'refundMethodOptions' =>
                        $this->service
                            ->refundMethodOptions(),

                    'input' =>
                        $_SESSION['_old']
                        ?? [
                            'refund_number' =>
                                '',

                            'refund_date' =>
                                date(
                                    'Y-m-d'
                                ),

                            'amount' =>
                                $summary[
                                    'refund_balance'
                                ],

                            'refund_method' =>
                                'cash',

                            'reference_number' =>
                                '',

                            'notes' =>
                                '',
                        ],

                    'error' =>
                        flash('error'),
                ]
            );

            unset(
                $_SESSION['_old']
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns/show?id='
                . $saleReturnId
            );
        }
    }

    /**
     * Record refund.
     */
    public function storeRefund(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.refunds_create'
            );

        $this->verifyCsrf();

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->createRefund(
                (int) $currentUser[
                    'company_id'
                ],
                $saleReturnId,
                (int) $currentUser[
                    'id'
                ],
                $_POST
            );

            flash(
                'success',
                'Sale return refund recorded successfully.'
            );

            redirect(
                '/sale-returns/show?id='
                . $saleReturnId
            );
        } catch (Throwable $exception) {
            $this->rememberOld(
                $_POST
            );

            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns/refund?sale_return_id='
                . $saleReturnId
            );
        }
    }

    /**
     * Soft-delete refund.
     */
    public function deleteRefund(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.refunds_delete'
            );

        $this->verifyCsrf();

        $refundId =
            $this->requiredInteger(
                $_POST['refund_id']
                    ?? null,
                'Refund'
            );

        $saleReturnId =
            $this->requiredInteger(
                $_POST[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $this->service->deleteRefund(
                (int) $currentUser[
                    'company_id'
                ],
                $refundId,
                (int) $currentUser[
                    'id'
                ]
            );

            flash(
                'success',
                'Sale return refund deleted successfully.'
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );
        }

        redirect(
            '/sale-returns/refund-history?sale_return_id='
            . $saleReturnId
        );
    }

    /**
     * Restore deleted refund.
     */
    public function restoreRefund(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.refunds_restore'
            );

        $this->verifyCsrf();

        $refundId =
            $this->requiredInteger(
                $_POST['refund_id']
                    ?? null,
                'Refund'
            );

        try {
            $refund =
                $this->service
                    ->restoreRefund(
                        (int) $currentUser[
                            'company_id'
                        ],
                        $refundId,
                        (int) $currentUser[
                            'id'
                        ]
                    );

            flash(
                'success',
                'Sale return refund restored successfully.'
            );

            redirect(
                '/sale-returns/refund-history?sale_return_id='
                . $refund->saleReturnId()
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns'
            );
        }
    }

    /**
     * Return-specific refund history.
     */
    public function refundHistory(): void
    {
        $currentUser =
            $this->requirePermission(
                'sale_returns.refunds_view'
            );

        $companyId =
            (int) $currentUser[
                'company_id'
            ];

        $saleReturnId =
            $this->requiredInteger(
                $_GET[
                    'sale_return_id'
                ] ?? null,
                'Sale return'
            );

        try {
            $detail =
                $this->service->detail(
                    $companyId,
                    $saleReturnId,
                    true
                );

            $refunds =
                $this->service->refunds(
                    $companyId,
                    $saleReturnId,
                    true
                );

            $summary =
                $this->service
                    ->refundSummary(
                        $companyId,
                        $saleReturnId
                    );

            View::render(
                'sale_returns.refund_history',
                [
                    'currentUser' =>
                        $currentUser,

                    'return' =>
                        $detail['return'],

                    'sale' =>
                        $detail['sale'],

                    'refunds' =>
                        $refunds,

                    'summary' =>
                        $summary,

                    'success' =>
                        flash('success'),

                    'error' =>
                        flash('error'),
                ]
            );
        } catch (Throwable $exception) {
            flash(
                'error',
                $exception->getMessage()
            );

            redirect(
                '/sale-returns'
            );
        }
    }

    private function nullableInteger(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
            || $value === 0
            || $value === '0'
        ) {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            return null;
        }

        $integer =
            (int) $value;

        return $integer > 0
            ? $integer
            : null;
    }

    private function requiredInteger(
        mixed $value,
        string $field
    ): int {
        $integer =
            $this->nullableInteger(
                $value
            );

        if ($integer === null) {
            throw new ValidationException(
                "{$field} is required."
            );
        }

        return $integer;
    }
}