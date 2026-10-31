<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'school_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'deactivated_at' => 'datetime',
            'school_id' => 'integer',
        ];
    }

    /**
     * School the person belongs to. A dean's school limits what they can see.
     *
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Projects where the user is the primary student author.
     *
     * @return HasMany<Project, $this>
     */
    public function projectsAsStudent(): HasMany
    {
        return $this->hasMany(Project::class, 'student_id');
    }

    /**
     * Projects where the user is the assigned supervisor.
     *
     * @return HasMany<Project, $this>
     */
    public function projectsAsSupervisor(): HasMany
    {
        return $this->hasMany(Project::class, 'supervisor_id');
    }

    /**
     * Evaluations submitted by this supervisor.
     *
     * @return HasMany<SupervisorEvaluation, $this>
     */
    public function supervisorEvaluations(): HasMany
    {
        return $this->hasMany(SupervisorEvaluation::class, 'supervisor_id');
    }

    /**
     * Catalogue records the user saved to their research folder.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function savedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'saved_projects')->withTimestamps();
    }

    /**
     * Two-letter monogram for avatars: first and last name, honorifics dropped ("Dr. L. Sakala" is "LS").
     */
    public function initials(): string
    {
        $parts = str($this->name)
            ->replace(['Dr. ', 'Prof. ', 'Mr. ', 'Mrs. ', 'Ms. '], '')
            ->squish()
            ->explode(' ')
            ->filter();

        return collect([$parts->first(), $parts->count() > 1 ? $parts->last() : null])
            ->filter()
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Whether the user belongs to the student-facing portal.
     */
    public function isStudent(): bool
    {
        return $this->hasAnyRole([UserRole::STUDENT->value, UserRole::RESEARCHER->value]);
    }

    /**
     * Whether the user works in the lecturer portal (supervisors, deans, the research director and admins).
     */
    public function isFaculty(): bool
    {
        return $this->hasAnyRole([
            UserRole::SUPERVISOR->value,
            UserRole::ADMIN->value,
            UserRole::DEAN->value,
            UserRole::RESEARCH_DIRECTOR->value,
        ]);
    }

    /**
     * Whether the user holds oversight of a project register: university-wide for the director and admin,
     * or one school for a dean.
     */
    public function hasOversight(): bool
    {
        return $this->hasAnyRole([
            UserRole::ADMIN->value,
            UserRole::DEAN->value,
            UserRole::RESEARCH_DIRECTOR->value,
        ]);
    }

    /**
     * Whether the user sees research from every school: the administrator and the research director.
     */
    public function hasUniversityWideAccess(): bool
    {
        return $this->hasAnyRole([UserRole::ADMIN->value, UserRole::RESEARCH_DIRECTOR->value]);
    }

    /**
     * Whether the user is a dean of school.
     */
    public function isDean(): bool
    {
        return $this->hasRole(UserRole::DEAN->value);
    }

    /**
     * The school a dean is limited to, or null when the user sees every school.
     * A dean with no school assigned yet is limited to nothing (school id 0), never to everything.
     */
    public function oversightSchoolId(): ?int
    {
        if (! $this->isDean() || $this->hasUniversityWideAccess()) {
            return null;
        }

        return $this->school_id ?? 0;
    }

    /**
     * Whether a dean (or wider overseer) may act on research from the given school.
     */
    public function oversees(?int $schoolId): bool
    {
        if ($this->hasUniversityWideAccess()) {
            return true;
        }

        return $this->isDean() && $this->school_id !== null && $this->school_id === $schoolId;
    }

    /**
     * Whether the user holds the repository librarian role.
     */
    public function isLibrarian(): bool
    {
        return $this->hasRole(UserRole::LIBRARIAN->value);
    }

    /**
     * Whether the user may open the repository of uploaded files with their summaries: librarians and
     * senior staff, the research director and admin, and deans for their own school.
     */
    public function canBrowseRepository(): bool
    {
        return $this->isLibrarian() || $this->hasOversight();
    }

    /**
     * Whether the user runs the Research Office's programmes (challenge, events, sites, thematic areas):
     * the research director who leads the office, and the administrator.
     */
    public function managesResearchOffice(): bool
    {
        return $this->hasAnyRole([
            UserRole::RESEARCH_DIRECTOR->value,
            UserRole::ADMIN->value,
        ]);
    }

    /**
     * Whether the user may sign in to the staff workspace (lecturer portal or repository workspace).
     */
    public function usesStaffWorkspace(): bool
    {
        return $this->isFaculty() || $this->isLibrarian();
    }

    /**
     * Name of the staff workspace shown in its header.
     */
    public function workspaceLabel(): string
    {
        return $this->isFaculty() ? 'Lecturer portal' : 'Repository workspace';
    }

    /**
     * Whether the user is the system administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN->value);
    }

    /**
     * Whether the user may manage accounts and roles and sign in as other users.
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Administrators may sign in as anyone except themselves and other administrators.
     */
    public function canImpersonate(self $target): bool
    {
        return $this->canManageUsers() && ! $this->is($target) && ! $target->isAdmin() && ! $target->isDeactivated();
    }

    /**
     * Whether the administrator switched the account off.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Whether removing the account would also delete research it authored, graded or recorded.
     * Such accounts are deactivated instead, so the archive stays intact.
     */
    public function hasResearchRecord(): bool
    {
        return $this->projectsAsStudent()->exists()
            || $this->supervisorEvaluations()->exists()
            || $this->challengeEntries()->exists()
            || EntryReview::where('reviewer_id', $this->id)->exists()
            || SiteObservation::where('recorded_by', $this->id)->exists();
    }

    /**
     * Sets the primary role shown on the account and grants it with any additional duties.
     *
     * @param  list<string>  $additionalRoles
     */
    public function assignAccountRoles(UserRole $primary, array $additionalRoles = []): void
    {
        $this->forceFill(['role' => $primary])->save();

        $this->syncRoles(collect([$primary->value, ...$additionalRoles])->unique()->values()->all());
    }

    /**
     * Challenge entries the user submitted.
     *
     * @return HasMany<ChallengeEntry, $this>
     */
    public function challengeEntries(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class, 'student_id');
    }

    /**
     * Office events the user registered for.
     *
     * @return BelongsToMany<AssociationEvent, $this>
     */
    public function registeredEvents(): BelongsToMany
    {
        return $this->belongsToMany(AssociationEvent::class, 'event_registrations')
            ->withPivot(['wants_to_speak', 'position'])
            ->withTimestamps();
    }

    /**
     * Determine whether the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'supervisor') {
            return ! $this->isDeactivated() && $this->usesStaffWorkspace();
        }

        return false;
    }
}
