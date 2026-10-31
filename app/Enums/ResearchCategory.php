<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\School;

/**
 * Subject (discipline) of a catalogued study, grouped by the school that teaches it.
 */
enum ResearchCategory: string
{
    case AGRICULTURE = 'agriculture';
    case ENVIRONMENTAL_SCIENCE = 'environmental_science';
    case BUSINESS_MANAGEMENT = 'business_management';
    case ACCOUNTING_FINANCE = 'accounting_finance';
    case ECONOMICS = 'economics';
    case EDUCATION = 'education';
    case HEALTH_SCIENCES = 'health_sciences';
    case NURSING_MIDWIFERY = 'nursing_midwifery';
    case COMPUTING = 'computing';
    case ENGINEERING = 'engineering';
    case NATURAL_SCIENCES = 'natural_sciences';
    case SOCIAL_SCIENCES = 'social_sciences';
    case PUBLIC_ADMINISTRATION = 'public_administration';

    public function label(): string
    {
        return match ($this) {
            self::AGRICULTURE => 'Agriculture & Food Systems',
            self::ENVIRONMENTAL_SCIENCE => 'Environment & Natural Resources',
            self::BUSINESS_MANAGEMENT => 'Business & Management',
            self::ACCOUNTING_FINANCE => 'Accounting & Finance',
            self::ECONOMICS => 'Economics',
            self::EDUCATION => 'Education',
            self::HEALTH_SCIENCES => 'Health Sciences',
            self::NURSING_MIDWIFERY => 'Nursing & Midwifery',
            self::COMPUTING => 'Computing & Information Systems',
            self::ENGINEERING => 'Engineering',
            self::NATURAL_SCIENCES => 'Mathematics & Natural Sciences',
            self::SOCIAL_SCIENCES => 'Social Sciences',
            self::PUBLIC_ADMINISTRATION => 'Political Science & Public Administration',
        };
    }

    /**
     * One- or two-word name for dense tables; pair it with label() in a tooltip.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::AGRICULTURE => 'Agriculture',
            self::ENVIRONMENTAL_SCIENCE => 'Environment',
            self::BUSINESS_MANAGEMENT => 'Business',
            self::ACCOUNTING_FINANCE => 'Accounting',
            self::ECONOMICS => 'Economics',
            self::EDUCATION => 'Education',
            self::HEALTH_SCIENCES => 'Health',
            self::NURSING_MIDWIFERY => 'Nursing',
            self::COMPUTING => 'Computing',
            self::ENGINEERING => 'Engineering',
            self::NATURAL_SCIENCES => 'Sciences',
            self::SOCIAL_SCIENCES => 'Social Sciences',
            self::PUBLIC_ADMINISTRATION => 'Public Admin',
        };
    }

    /**
     * One-line scope note shown when browsing subjects.
     */
    public function description(): string
    {
        return match ($this) {
            self::AGRICULTURE => 'Crop and animal science, agribusiness and food security.',
            self::ENVIRONMENTAL_SCIENCE => 'Climate, water, forestry, land use and conservation.',
            self::BUSINESS_MANAGEMENT => 'Strategy, entrepreneurship, marketing and human resources.',
            self::ACCOUNTING_FINANCE => 'Financial reporting, auditing, banking and public finance.',
            self::ECONOMICS => 'Development, labour, trade and applied economic analysis.',
            self::EDUCATION => 'Teaching and learning, curriculum, inclusion and school leadership.',
            self::HEALTH_SCIENCES => 'Public health, clinical medicine, pharmacy and health systems.',
            self::NURSING_MIDWIFERY => 'Nursing practice, midwifery, maternal and child health.',
            self::COMPUTING => 'Software, data, networks and digital services.',
            self::ENGINEERING => 'Civil, electrical, mechanical and renewable energy systems.',
            self::NATURAL_SCIENCES => 'Mathematics, statistics, physics, chemistry and biology.',
            self::SOCIAL_SCIENCES => 'Sociology, psychology, media and development studies.',
            self::PUBLIC_ADMINISTRATION => 'Governance, public policy, local government and law.',
        };
    }

    /**
     * Slug of the school that teaches the subject (see School::DIRECTORY).
     */
    public function schoolSlug(): string
    {
        return match ($this) {
            self::AGRICULTURE, self::ENVIRONMENTAL_SCIENCE => 'agriculture-natural-resources',
            self::BUSINESS_MANAGEMENT, self::ACCOUNTING_FINANCE, self::ECONOMICS => 'business-studies',
            self::COMPUTING, self::ENGINEERING => 'engineering-technology',
            self::NATURAL_SCIENCES => 'natural-applied-sciences',
            self::SOCIAL_SCIENCES, self::PUBLIC_ADMINISTRATION => 'social-sciences',
            self::EDUCATION => 'education',
            self::NURSING_MIDWIFERY => 'nursing-midwifery',
            self::HEALTH_SCIENCES => 'medicine-health-sciences',
        };
    }

    /**
     * School of the university that the subject belongs to, without the "School of" prefix.
     */
    public function school(): string
    {
        return (string) str(School::DIRECTORY[$this->schoolSlug()])->after('School of ');
    }

    /**
     * Filament colour token used for subject badges, shared by subjects of the same school.
     */
    public function color(): string
    {
        return match ($this->schoolSlug()) {
            'agriculture-natural-resources' => 'success',
            'business-studies' => 'warning',
            'medicine-health-sciences', 'nursing-midwifery' => 'danger',
            'engineering-technology', 'natural-applied-sciences' => 'info',
            'education' => 'primary',
            default => 'gray',
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
     * Subjects grouped under their school, for grouped select inputs and facet lists.
     *
     * @return array<string, list<self>>
     */
    public static function bySchool(): array
    {
        return collect(self::cases())->groupBy(fn (self $case): string => $case->school())->map->values()->map->all()->all();
    }
}
