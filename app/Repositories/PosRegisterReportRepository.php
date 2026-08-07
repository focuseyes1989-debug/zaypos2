<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class PosRegisterReportRepository
{
    private PDO $database;

    public function __construct(
        ?PDO $database = null
    ) {
        $this->database =
            $database
            ?? Database::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shiftSummary(
        int $companyId,
        int $shiftId
    ): ?array {
        return $this->fetchOne(
            <<<'SQL'
SELECT
    ps.id AS shift_id,
    ps.shift_number,
    ps.status,
    ps.company_id,
    ps.branch_id,
    ps.warehouse_id,
    ps.user_id,

    ps.opening_cash,
    ps.cash_sales,
    ps.cash_in,
    ps.cash_out,
    ps.expected_cash,
    ps.closing_cash,
    ps.cash_variance,

    ps.opened_at,
    ps.closed_at,
    ps.opened_by,
    ps.closed_by,

    COUNT(DISTINCT s.id) AS sale_count,

    COALESCE(SUM(s.subtotal), 0) AS subtotal,
    COALESCE(SUM(s.discount_amount), 0) AS discount_amount,
    COALESCE(SUM(s.tax_amount), 0) AS tax_amount,
    COALESCE(SUM(s.shipping_amount), 0) AS shipping_amount,
    COALESCE(SUM(s.other_amount), 0) AS other_amount,
    COALESCE(SUM(s.grand_total), 0) AS grand_total,
    COALESCE(SUM(s.paid_amount), 0) AS paid_amount,
    COALESCE(SUM(s.balance_due), 0) AS balance_due

FROM pos_shifts ps

LEFT JOIN sales s
    ON s.company_id = ps.company_id
   AND s.pos_shift_id = ps.id
   AND s.status = 'completed'
   AND s.deleted_at IS NULL

WHERE ps.company_id = :company_id
  AND ps.id = :shift_id

GROUP BY
    ps.id,
    ps.shift_number,
    ps.status,
    ps.company_id,
    ps.branch_id,
    ps.warehouse_id,
    ps.user_id,
    ps.opening_cash,
    ps.cash_sales,
    ps.cash_in,
    ps.cash_out,
    ps.expected_cash,
    ps.closing_cash,
    ps.cash_variance,
    ps.opened_at,
    ps.closed_at,
    ps.opened_by,
    ps.closed_by

LIMIT 1
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentBreakdown(
        int $companyId,
        int $shiftId
    ): array {
        return $this->fetchAll(
            <<<'SQL'
SELECT
    sp.payment_method,
    COUNT(sp.id) AS payment_count,
    COALESCE(SUM(sp.amount), 0) AS amount

FROM sale_payments sp

INNER JOIN sales s
    ON s.id = sp.sale_id
   AND s.company_id = sp.company_id

WHERE sp.company_id = :company_id
  AND s.pos_shift_id = :shift_id
  AND s.status = 'completed'
  AND s.deleted_at IS NULL
  AND sp.deleted_at IS NULL

GROUP BY sp.payment_method

ORDER BY
    amount DESC,
    sp.payment_method ASC
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    /**
     * @return array{
     *     cash_in: float,
     *     cash_out: float,
     *     movement_count: int
     * }
     */
    public function cashMovementSummary(
        int $companyId,
        int $shiftId
    ): array {
        $row =
            $this->fetchOne(
                <<<'SQL'
SELECT
    COALESCE(
        SUM(
            CASE
                WHEN movement_type = 'cash_in'
                THEN amount
                ELSE 0
            END
        ),
        0
    ) AS cash_in,

    COALESCE(
        SUM(
            CASE
                WHEN movement_type = 'cash_out'
                THEN amount
                ELSE 0
            END
        ),
        0
    ) AS cash_out,

    COUNT(*) AS movement_count

FROM pos_cash_movements

WHERE company_id = :company_id
  AND shift_id = :shift_id
SQL,
                [
                    'company_id' => $companyId,
                    'shift_id' => $shiftId,
                ]
            );

        return [
            'cash_in' =>
                (float) ($row['cash_in'] ?? 0),

            'cash_out' =>
                (float) ($row['cash_out'] ?? 0),

            'movement_count' =>
                (int) ($row['movement_count'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salesByShift(
        int $companyId,
        int $shiftId
    ): array {
        return $this->fetchAll(
            <<<'SQL'
SELECT
    s.id,
    s.sale_number,
    s.sale_date,
    s.customer_id,
    s.payment_status,
    s.subtotal,
    s.discount_amount,
    s.tax_amount,
    s.shipping_amount,
    s.other_amount,
    s.grand_total,
    s.paid_amount,
    s.balance_due,
    s.completed_at,
    s.completed_by

FROM sales s

WHERE s.company_id = :company_id
  AND s.pos_shift_id = :shift_id
  AND s.status = 'completed'
  AND s.deleted_at IS NULL

ORDER BY
    s.completed_at ASC,
    s.id ASC
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cashMovements(
        int $companyId,
        int $shiftId
    ): array {
        return $this->fetchAll(
            <<<'SQL'
SELECT
    id,
    movement_type,
    amount,
    reference_number,
    notes,
    movement_at,
    created_by,
    created_at

FROM pos_cash_movements

WHERE company_id = :company_id
  AND shift_id = :shift_id

ORDER BY
    movement_at ASC,
    id ASC
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    public function saleCount(
        int $companyId,
        int $shiftId
    ): int {
        return (int) $this->fetchValue(
            <<<'SQL'
SELECT COUNT(*)

FROM sales

WHERE company_id = :company_id
  AND pos_shift_id = :shift_id
  AND status = 'completed'
  AND deleted_at IS NULL
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    public function salesTotal(
        int $companyId,
        int $shiftId
    ): float {
        return (float) $this->fetchValue(
            <<<'SQL'
SELECT COALESCE(
    SUM(grand_total),
    0
)

FROM sales

WHERE company_id = :company_id
  AND pos_shift_id = :shift_id
  AND status = 'completed'
  AND deleted_at IS NULL
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    public function cashSalesTotal(
        int $companyId,
        int $shiftId
    ): float {
        return (float) $this->fetchValue(
            <<<'SQL'
SELECT COALESCE(
    SUM(sp.amount),
    0
)

FROM sale_payments sp

INNER JOIN sales s
    ON s.id = sp.sale_id
   AND s.company_id = sp.company_id

WHERE sp.company_id = :company_id
  AND s.pos_shift_id = :shift_id
  AND s.status = 'completed'
  AND s.deleted_at IS NULL
  AND sp.payment_method = 'cash'
  AND sp.deleted_at IS NULL
SQL,
            [
                'company_id' => $companyId,
                'shift_id' => $shiftId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function dailySummary(
        int $companyId,
        string $date
    ): array {
        $row =
            $this->fetchOne(
                <<<'SQL'
SELECT
    COUNT(*) AS sale_count,
    COALESCE(SUM(subtotal), 0) AS subtotal,
    COALESCE(SUM(discount_amount), 0) AS discount_amount,
    COALESCE(SUM(tax_amount), 0) AS tax_amount,
    COALESCE(SUM(shipping_amount), 0) AS shipping_amount,
    COALESCE(SUM(other_amount), 0) AS other_amount,
    COALESCE(SUM(grand_total), 0) AS grand_total,
    COALESCE(SUM(paid_amount), 0) AS paid_amount,
    COALESCE(SUM(balance_due), 0) AS balance_due

FROM sales

WHERE company_id = :company_id
  AND sale_date = :sale_date
  AND status = 'completed'
  AND deleted_at IS NULL
SQL,
                [
                    'company_id' => $companyId,
                    'sale_date' => $date,
                ]
            );

        return $row ?? [
            'sale_count' => 0,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'other_amount' => 0,
            'grand_total' => 0,
            'paid_amount' => 0,
            'balance_due' => 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dailyPaymentBreakdown(
        int $companyId,
        string $date
    ): array {
        return $this->fetchAll(
            <<<'SQL'
SELECT
    sp.payment_method,
    COUNT(sp.id) AS payment_count,
    COALESCE(SUM(sp.amount), 0) AS amount

FROM sale_payments sp

INNER JOIN sales s
    ON s.id = sp.sale_id
   AND s.company_id = sp.company_id

WHERE sp.company_id = :company_id
  AND s.sale_date = :sale_date
  AND s.status = 'completed'
  AND s.deleted_at IS NULL
  AND sp.deleted_at IS NULL

GROUP BY sp.payment_method

ORDER BY
    amount DESC,
    sp.payment_method ASC
SQL,
            [
                'company_id' => $companyId,
                'sale_date' => $date,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function shiftsByDate(
        int $companyId,
        string $date
    ): array {
        return $this->fetchAll(
            <<<'SQL'
SELECT
    ps.id,
    ps.shift_number,
    ps.status,
    ps.branch_id,
    ps.warehouse_id,
    ps.user_id,
    ps.opening_cash,
    ps.cash_sales,
    ps.cash_in,
    ps.cash_out,
    ps.expected_cash,
    ps.closing_cash,
    ps.cash_variance,
    ps.opened_at,
    ps.closed_at

FROM pos_shifts ps

WHERE ps.company_id = :company_id
  AND DATE(ps.opened_at) = :report_date

ORDER BY
    ps.opened_at DESC,
    ps.id DESC
SQL,
            [
                'company_id' => $companyId,
                'report_date' => $date,
            ]
        );
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>|null
     */
    private function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        $statement =
            $this->database->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row === false
            ? null
            : $row;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        $statement =
            $this->database->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        /** @var list<array<string, mixed>> $rows */
        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        return $rows;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function fetchValue(
        string $sql,
        array $parameters = []
    ): mixed {
        $statement =
            $this->database->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        return $statement->fetchColumn();
    }
}