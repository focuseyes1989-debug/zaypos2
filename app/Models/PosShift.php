<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final class PosShift extends BaseModel
{
    private ?int $companyId = null;

    private ?int $branchId = null;

    private ?int $warehouseId = null;

    private ?int $userId = null;

    private string $shiftNumber = '';

    private string $status = 'open';

    private float $openingCash = 0.0;

    private float $cashSales = 0.0;

    private float $cashIn = 0.0;

    private float $cashOut = 0.0;

    private float $expectedCash = 0.0;

    private ?float $closingCash = null;

    private ?float $cashVariance = null;

    private ?DateTimeImmutable $openedAt = null;

    private ?DateTimeImmutable $closedAt = null;

    private ?int $openedBy = null;

    private ?int $closedBy = null;

    private ?string $openingNotes = null;

    private ?string $closingNotes = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        if ($data !== []) {
            $this->fill($data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): self
    {
        $this->fillBaseAttributes($data);

        $this->companyId =
            $this->requiredPositiveInteger(
                $data['company_id'] ?? null,
                'Company ID'
            );

        $this->branchId =
            $this->optionalPositiveInteger(
                $data['branch_id'] ?? null,
                'Branch ID'
            );

        $this->warehouseId =
            $this->requiredPositiveInteger(
                $data['warehouse_id'] ?? null,
                'Warehouse ID'
            );

        $this->userId =
            $this->requiredPositiveInteger(
                $data['user_id'] ?? null,
                'User ID'
            );

        $this->shiftNumber =
            $this->requiredString(
                $data['shift_number'] ?? '',
                'Shift number',
                100
            );

        $this->status =
            $this->normalizeStatus(
                $data['status'] ?? 'open'
            );

        $this->openingCash =
            $this->nonNegativeAmount(
                $data['opening_cash'] ?? 0,
                'Opening cash'
            );

        $this->cashSales =
            $this->nonNegativeAmount(
                $data['cash_sales'] ?? 0,
                'Cash sales'
            );

        $this->cashIn =
            $this->nonNegativeAmount(
                $data['cash_in'] ?? 0,
                'Cash in'
            );

        $this->cashOut =
            $this->nonNegativeAmount(
                $data['cash_out'] ?? 0,
                'Cash out'
            );

        /*
         * Expected cash can technically become negative
         * if cash-out exceeds opening cash + receipts.
         */
        $this->expectedCash =
            $this->signedAmount(
                $data['expected_cash'] ?? 0,
                'Expected cash'
            );

        $this->closingCash =
            $this->nullableNonNegativeAmount(
                $data['closing_cash'] ?? null,
                'Closing cash'
            );

        /*
         * Variance may be positive or negative.
         */
        $this->cashVariance =
            $this->nullableSignedAmount(
                $data['cash_variance'] ?? null,
                'Cash variance'
            );

        $this->openedAt =
            $this->requiredDateTime(
                $data['opened_at'] ?? null,
                'Opened at'
            );

        $this->closedAt =
            $this->nullableDateTimeValue(
                $data['closed_at'] ?? null,
                'Closed at'
            );

        $this->openedBy =
            $this->requiredPositiveInteger(
                $data['opened_by'] ?? null,
                'Opened by'
            );

        $this->closedBy =
            $this->optionalPositiveInteger(
                $data['closed_by'] ?? null,
                'Closed by'
            );

        $this->openingNotes =
            $this->optionalString(
                $data['opening_notes'] ?? null
            );

        $this->closingNotes =
            $this->optionalString(
                $data['closing_notes'] ?? null
            );

        $this->validateState();

        return $this;
    }

    public function companyId(): int
    {
        return $this->requiredStoredId(
            $this->companyId,
            'Company ID'
        );
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function warehouseId(): int
    {
        return $this->requiredStoredId(
            $this->warehouseId,
            'Warehouse ID'
        );
    }

    public function userId(): int
    {
        return $this->requiredStoredId(
            $this->userId,
            'User ID'
        );
    }

    public function shiftNumber(): string
    {
        return $this->shiftNumber;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function openingCash(): float
    {
        return $this->openingCash;
    }

    public function cashSales(): float
    {
        return $this->cashSales;
    }

    public function cashIn(): float
    {
        return $this->cashIn;
    }

    public function cashOut(): float
    {
        return $this->cashOut;
    }

    public function expectedCash(): float
    {
        return $this->expectedCash;
    }

    public function closingCash(): ?float
    {
        return $this->closingCash;
    }

    public function cashVariance(): ?float
    {
        return $this->cashVariance;
    }

    public function openedAt(): DateTimeImmutable
    {
        if (!$this->openedAt instanceof DateTimeImmutable) {
            throw new InvalidArgumentException(
                'Opened at is not available.'
            );
        }

        return $this->openedAt;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function openedBy(): int
    {
        return $this->requiredStoredId(
            $this->openedBy,
            'Opened by'
        );
    }

    public function closedBy(): ?int
    {
        return $this->closedBy;
    }

    public function openingNotes(): ?string
    {
        return $this->openingNotes;
    }

    public function closingNotes(): ?string
    {
        return $this->closingNotes;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Expected cash calculated from shift activity.
     */
    public function calculatedExpectedCash(): float
    {
        return round(
            $this->openingCash
            + $this->cashSales
            + $this->cashIn
            - $this->cashOut,
            4
        );
    }

    /**
     * Calculated variance when closing cash exists.
     */
    public function calculatedVariance(): ?float
    {
        if ($this->closingCash === null) {
            return null;
        }

        return round(
            $this->closingCash
            - $this->expectedCash,
            4
        );
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            'open',
            'closed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'open' => 'Open',
            'closed' => 'Closed',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[
            $this->status
        ] ?? ucfirst($this->status);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' =>
                $this->id(),

            'company_id' =>
                $this->companyId,

            'branch_id' =>
                $this->branchId,

            'warehouse_id' =>
                $this->warehouseId,

            'user_id' =>
                $this->userId,

            'shift_number' =>
                $this->shiftNumber,

            'status' =>
                $this->status,

            'opening_cash' =>
                $this->openingCash,

            'cash_sales' =>
                $this->cashSales,

            'cash_in' =>
                $this->cashIn,

            'cash_out' =>
                $this->cashOut,

            'expected_cash' =>
                $this->expectedCash,

            'closing_cash' =>
                $this->closingCash,

            'cash_variance' =>
                $this->cashVariance,

            'opened_at' =>
                $this->openedAt?->format(
                    'Y-m-d H:i:s'
                ),

            'closed_at' =>
                $this->closedAt?->format(
                    'Y-m-d H:i:s'
                ),

            'opened_by' =>
                $this->openedBy,

            'closed_by' =>
                $this->closedBy,

            'opening_notes' =>
                $this->openingNotes,

            'closing_notes' =>
                $this->closingNotes,

            'created_at' =>
                $this->createdAt()?->format(
                    'Y-m-d H:i:s'
                ),

            'updated_at' =>
                $this->updatedAt()?->format(
                    'Y-m-d H:i:s'
                ),
        ];
    }

    private function validateState(): void
    {
        if (
            $this->status === 'open'
            && (
                $this->closedAt !== null
                || $this->closedBy !== null
                || $this->closingCash !== null
                || $this->cashVariance !== null
            )
        ) {
            throw new InvalidArgumentException(
                'Open shift must not contain closing information.'
            );
        }

        if ($this->status === 'closed') {
            if ($this->closedAt === null) {
                throw new InvalidArgumentException(
                    'Closed shift requires closed at.'
                );
            }

            if ($this->closedBy === null) {
                throw new InvalidArgumentException(
                    'Closed shift requires closed by.'
                );
            }

            if ($this->closingCash === null) {
                throw new InvalidArgumentException(
                    'Closed shift requires closing cash.'
                );
            }

            if ($this->cashVariance === null) {
                throw new InvalidArgumentException(
                    'Closed shift requires cash variance.'
                );
            }
        }

        if (
            $this->closedAt !== null
            && $this->openedAt !== null
            && $this->closedAt < $this->openedAt
        ) {
            throw new InvalidArgumentException(
                'Closed at must not be earlier than opened at.'
            );
        }
    }

    private function requiredStoredId(
        ?int $value,
        string $label
    ): int {
        if ($value === null) {
            throw new InvalidArgumentException(
                $label . ' is not available.'
            );
        }

        return $value;
    }

    private function requiredPositiveInteger(
        mixed $value,
        string $label
    ): int {
        $integer =
            $this->nullablePositiveInteger(
                $value
            );

        if ($integer === null) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        return $integer;
    }

    private function optionalPositiveInteger(
        mixed $value,
        string $label
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                $label
                . ' must be a valid identifier.'
            );
        }

        $integer =
            (int) $value;

        if ($integer < 1) {
            throw new InvalidArgumentException(
                $label
                . ' must be greater than zero.'
            );
        }

        return $integer;
    }

    private function requiredString(
        mixed $value,
        string $label,
        ?int $maximumLength = null
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        $value =
            trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        if (
            $maximumLength !== null
            && mb_strlen($value)
                > $maximumLength
        ) {
            throw new InvalidArgumentException(
                $label
                . ' must not exceed '
                . $maximumLength
                . ' characters.'
            );
        }

        return $value;
    }

    private function optionalString(
        mixed $value,
        ?int $maximumLength = null
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Expected a valid string.'
            );
        }

        $value =
            trim($value);

        if ($value === '') {
            return null;
        }

        if (
            $maximumLength !== null
            && mb_strlen($value)
                > $maximumLength
        ) {
            throw new InvalidArgumentException(
                'Value must not exceed '
                . $maximumLength
                . ' characters.'
            );
        }

        return $value;
    }

    private function normalizeStatus(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Shift status is required.'
            );
        }

        $value =
            strtolower(
                trim($value)
            );

        if ($value === '') {
            $value = 'open';
        }

        if (
            !in_array(
                $value,
                self::statuses(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid POS shift status.'
            );
        }

        return $value;
    }

    private function nonNegativeAmount(
        mixed $value,
        string $label
    ): float {
        $number =
            $this->signedAmount(
                $value,
                $label
            );

        if ($number < 0) {
            throw new InvalidArgumentException(
                $label
                . ' must be zero or greater.'
            );
        }

        return $number;
    }

    private function nullableNonNegativeAmount(
        mixed $value,
        string $label
    ): ?float {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->nonNegativeAmount(
            $value,
            $label
        );
    }

    private function signedAmount(
        mixed $value,
        string $label
    ): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                $label
                . ' must be a valid number.'
            );
        }

        $number =
            round(
                (float) $value,
                4
            );

        if (!is_finite($number)) {
            throw new InvalidArgumentException(
                $label
                . ' must be a finite number.'
            );
        }

        return $number;
    }

    private function nullableSignedAmount(
        mixed $value,
        string $label
    ): ?float {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->signedAmount(
            $value,
            $label
        );
    }

    private function requiredDateTime(
        mixed $value,
        string $label
    ): DateTimeImmutable {
        $date =
            $this->nullableDateTimeValue(
                $value,
                $label
            );

        if ($date === null) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        return $date;
    }

    private function nullableDateTimeValue(
        mixed $value,
        string $label
    ): ?DateTimeImmutable {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                $label
                . ' must be a valid date and time.'
            );
        }

        try {
            return new DateTimeImmutable(
                trim($value)
            );
        } catch (\Throwable) {
            throw new InvalidArgumentException(
                $label
                . ' must be a valid date and time.'
            );
        }
    }
}