<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

abstract class BaseModel
{
    protected ?int $id = null;

    protected ?DateTimeImmutable $createdAt = null;

    protected ?DateTimeImmutable $updatedAt = null;

    protected ?DateTimeImmutable $deletedAt = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function exists(): bool
    {
        return $this->id !== null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function fillBaseAttributes(array $data): void
    {
        $this->id = $this->nullablePositiveInteger($data['id'] ?? null);

        $this->createdAt = $this->nullableDateTime(
            $data['created_at'] ?? null
        );

        $this->updatedAt = $this->nullableDateTime(
            $data['updated_at'] ?? null
        );

        $this->deletedAt = $this->nullableDateTime(
            $data['deleted_at'] ?? null
        );
    }

    protected function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                'Expected a numeric identifier.'
            );
        }

        $integer = (int) $value;

        if ($integer < 1) {
            throw new InvalidArgumentException(
                'Identifier must be greater than zero.'
            );
        }

        return $integer;
    }

    protected function nullableDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Expected a valid date and time string.'
            );
        }

        return new DateTimeImmutable($value);
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}