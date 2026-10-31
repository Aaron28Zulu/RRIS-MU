<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One review panel member's scores for a challenge entry (each criterion scored 1 to 5).
 */
class EntryReview extends Model
{
    /**
     * Rubric criteria shown to the review panel, keyed by column.
     *
     * @var array<string, string>
     */
    public const CRITERIA = [
        'relevance_score' => 'Relevance to national development',
        'innovation_score' => 'Originality and innovation',
        'feasibility_score' => 'Feasibility with available resources',
        'impact_score' => 'Expected impact',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'challenge_entry_id',
        'reviewer_id',
        'relevance_score',
        'innovation_score',
        'feasibility_score',
        'impact_score',
        'comments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relevance_score' => 'integer',
            'innovation_score' => 'integer',
            'feasibility_score' => 'integer',
            'impact_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ChallengeEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ChallengeEntry::class, 'challenge_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Combined score out of 20.
     */
    public function total(): int
    {
        return $this->relevance_score + $this->innovation_score + $this->feasibility_score + $this->impact_score;
    }
}
