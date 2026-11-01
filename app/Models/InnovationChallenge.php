<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A semester call of the university-wide Research and Innovation Challenge.
 */
class InnovationChallenge extends Model
{
    /**
     * Mirrors the column defaults so freshly created models behave like stored ones.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'edition',
        'theme',
        'description',
        'criteria',
        'status',
        'opens_on',
        'closes_on',
        'presentation_on',
        'coordinator_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChallengeStatus::class,
            'opens_on' => 'date',
            'closes_on' => 'date',
            'presentation_on' => 'date',
        ];
    }

    /**
     * Compact edition for tables: "Semester 1, 2026/27" becomes "Sem 1 · 2026/27".
     */
    public function shortEdition(): string
    {
        return (string) preg_replace('/^Semester\s+(\d+),?\s*/i', 'Sem $1 · ', $this->edition);
    }

    /**
     * Office member coordinating the call.
     *
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    /**
     * @return HasMany<ChallengeEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class);
    }

    /**
     * Calls visible on the public innovation pages.
     *
     * @param  Builder<InnovationChallenge>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', '!=', ChallengeStatus::DRAFT->value);
    }

    /**
     * Whether a student can submit an entry right now: the call is open and the deadline has not passed.
     */
    public function isAcceptingEntries(): bool
    {
        return $this->status->acceptsEntries()
            && ($this->opens_on === null || $this->opens_on->startOfDay()->isPast())
            && ($this->closes_on === null || $this->closes_on->endOfDay()->isFuture());
    }
}
