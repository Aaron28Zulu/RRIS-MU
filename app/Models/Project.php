<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\SimilarityCheckStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'abstract',
        'category',
        'school_id',
        'status',
        'similarity_check_status',
        'completion_year',
        'supervisor_id',
        'student_id',
        'full_document_path',
        'raw_data_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'similarity_check_status' => SimilarityCheckStatus::class,
            'category' => ResearchCategory::class,
            'completion_year' => 'integer',
            'school_id' => 'integer',
        ];
    }

    /**
     * New projects are filed under the student's school, or their subject's school when the student
     * has none recorded.
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->school_id !== null) {
                return;
            }

            $project->school_id = User::query()->whereKey($project->student_id)->value('school_id')
                ?? ($project->category instanceof ResearchCategory ? School::bySlug($project->category->schoolSlug())?->id : null);
        });
    }

    /**
     * School the project is registered under.
     *
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Primary student author of the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Assigned academic supervisor.
     *
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Research gaps identified and linked to this project.
     *
     * @return HasMany<ResearchGap, $this>
     */
    public function researchGaps(): HasMany
    {
        return $this->hasMany(ResearchGap::class);
    }

    /**
     * Supervisor reviews and evaluations for this project.
     *
     * @return HasMany<SupervisorEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(SupervisorEvaluation::class);
    }

    /**
     * Versioned manuscripts submitted for this project.
     *
     * @return HasMany<ProjectManuscript, $this>
     */
    public function manuscripts(): HasMany
    {
        return $this->hasMany(ProjectManuscript::class)->latest();
    }

    /**
     * The most recently uploaded manuscript version.
     *
     * @return HasOne<ProjectManuscript, $this>
     */
    public function latestManuscript(): HasOne
    {
        return $this->hasOne(ProjectManuscript::class)->latestOfMany();
    }

    /**
     * The most recent supervisor evaluation.
     *
     * @return HasOne<SupervisorEvaluation, $this>
     */
    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(SupervisorEvaluation::class)->latestOfMany();
    }

    /**
     * Plagiarism & similarity reports generated for this project.
     *
     * @return HasMany<SimilarityReport, $this>
     */
    public function similarityReports(): HasMany
    {
        return $this->hasMany(SimilarityReport::class);
    }

    /**
     * The most recent similarity report.
     *
     * @return HasOne<SimilarityReport, $this>
     */
    public function latestSimilarityReport(): HasOne
    {
        return $this->hasOne(SimilarityReport::class)->latestOfMany();
    }

    /**
     * The similarity report linked to this project.
     *
     * @return HasOne<SimilarityReport, $this>
     */
    public function similarityReport(): HasOne
    {
        return $this->hasOne(SimilarityReport::class)->latestOfMany();
    }

    /**
     * Users who saved this record to their research folder.
     *
     * @return BelongsToMany<User, $this>
     */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_projects')->withTimestamps();
    }

    /**
     * Scope a query to only include completed projects visible to public discovery.
     *
     * @param  Builder<Project>  $query
     */
    public function scopePublicCatalog(Builder $query): void
    {
        $query->where('status', ProjectStatus::COMPLETED->value);
    }

    /**
     * Limit the register to what the user oversees: one school for a dean, everything for the research
     * director and administrator.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeWithinOversightOf(Builder $query, User $user): void
    {
        $schoolId = $user->oversightSchoolId();

        if ($schoolId !== null) {
            $query->where($query->qualifyColumn('school_id'), $schoolId);
        }
    }

    /**
     * Completed archive records whose title or abstract share key terms with a proposed title,
     * so students can spot duplicated topics before they submit.
     *
     * @return Collection<int, Project>
     */
    public static function catalogMatchesFor(string $title, int $limit = 5): Collection
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($title)) ?: [])
            ->filter(fn (string $word): bool => mb_strlen($word) >= 5)
            ->unique()
            ->take(6);

        if ($terms->isEmpty()) {
            return new Collection;
        }

        return static::query()
            ->publicCatalog()
            ->select(['id', 'title', 'completion_year', 'category', 'student_id'])
            ->with('student:id,name')
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'like', "%{$term}%")->orWhere('abstract', 'like', "%{$term}%");
                }
            })
            ->latest('completion_year')
            ->take($limit)
            ->get();
    }

    /**
     * Generate a secure, temporary signed URL pointing to the manuscript file for in-browser preview.
     */
    public function getDocumentUrl(): string
    {
        if (! $this->full_document_path) {
            return '';
        }

        return URL::temporarySignedRoute(
            'projects.document.preview',
            now()->addMinutes(30),
            ['project' => $this->id]
        );
    }

    /**
     * Plain-text abstract with any rich-text markup removed.
     */
    public function plainAbstract(): string
    {
        return trim(html_entity_decode(strip_tags((string) $this->abstract)));
    }

    /**
     * APA-style citation for the catalogue record.
     */
    public function citation(): string
    {
        $author = $this->student?->name ?? 'Unknown author';
        $year = $this->completion_year ?? 'n.d.';

        return sprintf(
            '%s (%s). %s [Research project]. %s, Mulungushi University. Research Repository Interface System.',
            $author,
            $year,
            $this->title,
            $this->school?->name ?? 'School of '.$this->category->school()
        );
    }
}
