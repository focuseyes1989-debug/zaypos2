<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final class PosCashMovement extends BaseModel
{
    private ?int $companyId = null;

    private ?int $shiftId = null;

    private string $movementType = 'cash_in';

    private float $amount = 0.0;

    private ?string $referenceNumber = null;

    private ?string $notes = null;

    private ?DateTimeImmutable $movementAt = null;

    private ?int $createdBy = null;

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

        $this->shiftId =
            $this->requiredPositiveInteger(
                $data['shift_id'] ?? null,
                'Shift ID'
            );

        $this->movementType =
            $this->normalizeMovementType(
                $data['movement_type']
                    ?? 'cash_in'
            );

        $this->amount =
            $this->positiveAmount(
                $data['amount'] ?? 0
            );

        $this->referenceNumber =
            $this->optionalString(
                $data['reference_number']
                    ?? null,
                190
            );

        $this->notes =
            $this->optionalString(
                $data['notes'] ?? null
            );

        $this->movementAt =
            $this->requiredDateTime(
                $data['movement_at']
                    ?? null,
                'Movement at'
            );

        $this->createdBy =
            $this->requiredPositiveInteger(
                $data['created_by']
                    ?? null,
                'Created by'
            );

        return $this;
    }

    public function companyId(): int
    {
        return $this->requiredStoredId(
            $this->companyId,
            'Company ID'
        );
    }

    public function shiftId(): int
    {
        return $this->requiredStoredId(
            $this->shiftId,
            'Shift ID'
        );
    }

    public function movementType(): string
    {
        return $this->movementType;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function referenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function movementAt(): DateTimeImmutable
    {
        if (
            !$this->movementAt
            instanceof DateTimeImmutable
        ) {
            throw new InvalidArgumentException(
                'Movement at is not available.'
            );
        }

        return $this->movementAt;
    }

    public function createdBy(): int
    {
        return $this->requiredStoredId(
            $this->createdBy,
            'Created by'
        );
    }

    public function isCashIn(): bool
    {
        return $this->movementType
            === 'cash_in';
    }

    public function isCashOut(): bool
    {
        return $this->movementType
            === 'cash_out';
    }

    /**
     * Signed amount for register calculations.
     */
    public function signedAmount(): float
    {
        return $this->isCashOut()
            ? -$this->amount
            : $this->amount;
    }

    /**
     * @return array<int, string>
     */
    public static function movementTypes(): array
    {
        return [
            'cash_in',
            'cash_out',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function movementTypeOptions(): array
    {
        return [
            'cash_in' => 'Cash In',
            'cash_out' => 'Cash Out',
        ];
    }

    public function movementTypeLabel(): string
    {
        return self::movementTypeOptions()[
            $this->movementType
        ] ?? ucfirst(
            str_replace(
                '_',
                ' ',
                $this->movementType
            )
        );
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

            'shift_id' =>
                $this->shiftId,

            'movement_type' =>
                $this->movementType,

            'amount' =>
                $this->amount,

            'reference_number' =>
                $this->referenceNumber,

            'notes' =>
                $this->notes,

            'movement_at' =>
                $this->movementAt?->format(
                    'Y-m-d H:i:s'
                ),

            'created_by' =>
                $this->createdBy,

            'created_at' =>
                $this->createdAt()?->format(
                    'Y-m-d H:i:s'
                ),
        ];
    }

    private function requiredStoredId(
        ?int $value,
        string $label
    ): int {
        if ($value === null) {
            throw new InvalidArgumentException(
                $label
                . ' is not available.'
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

    private function normalizeMovementType(
        mixed $value
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Movement type is required.'
            );
        }

        $value =
            strtolower(
                trim($value)
            );

        if ($value === '') {
            $value = 'cash_in';
        }

        if (
            !in_array(
                $value,
                self::movementTypes(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid POS cash movement type.'
            );
        }

        return $value;
    }

    private function positiveAmount(
        mixed $value
    ): float {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            throw new InvalidArgumentException(
                'Cash movement amount is required.'
            );
        }

        $amount =
            round(
                (float) $value,
                4
            );

        if (!is_finite($amount)) {
            throw new InvalidArgumentException(
                'Cash movement amount must be finite.'
            );
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Cash movement amount must be greater than zero.'
            );
        }

        return $amount;
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

    private function requiredDateTime(
        mixed $value,
        string $label
    ): DateTimeImmutable {
        if (
            $value instanceof DateTimeImmutable
        ) {
            return $value;
        }

        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            throw new InvalidArgumentException(
                $label . ' is required.'
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