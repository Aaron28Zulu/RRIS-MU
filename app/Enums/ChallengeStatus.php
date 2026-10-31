<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a Research and Innovation Challenge call.
 */
enum ChallengeStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case REVIEWING = 'reviewing';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open for entries',
            self::REVIEWING => 'Panel review',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * Filament colour token used for status badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::OPEN => 'success',
            self::REVIEWING => 'warning',
            self::CLOSED => 'gray',
        };
    }

    /**
     * Value => label map for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }

    /**
     * Whether students may still submit entries to the call.
     */
    public function acceptsEntries(): bool
    {
        return $this === self::OPEN;
    }

    /**
     * Whether the call is listed on the public innovation pages.
     */
    public function isPublished(): bool
    {
        return $this !== self::DRAFT;
    }
}
