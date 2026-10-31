<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school of Mulungushi University. Each school has a dean, and a dean sees only their own school's
 * research, gaps and students.
 */
class School extends Model
{
    /**
     * The university's schools, keyed by slug. The schools migration seeds this list.
     */
    public const DIRECTORY = [
        'agriculture-natural-resources' => 'School of Agriculture and Natural Resources',
        'business-studies' => 'School of Business Studies',
        'engineering-technology' => 'School of Engineering and Technology',
        'social-sciences' => 'School of Social Sciences',
        'natural-applied-sciences' => 'School of Natural and Applied Sciences',
        'education' => 'School of Education',
        'nursing-midwifery' => 'School of Nursing and Midwifery',
        'medicine-health-sciences' => 'School of Medicine and Health Sciences',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'slug'];

    /**
     * People who belong to the school: its dean, lecturers and students.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Research projects registered under the school.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The school's name without the "School of" prefix, for tight spaces.
     */
    public function shortName(): string
    {
        return (string) str($this->name)->after('School of ');
    }

    /**
     * Looks a school up by its slug.
     */
    public static function bySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }

    /**
     * Id => name map for select inputs, in the university's order.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->orderBy('id')->pluck('name', 'id')->all();
    }
}
