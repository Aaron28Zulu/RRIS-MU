<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case STUDENT = 'student';
    case RESEARCHER = 'researcher';
    case SUPERVISOR = 'supervisor';
    case ADMIN = 'admin';
    case DEAN = 'dean';
    case RESEARCH_DIRECTOR = 'research_director';
    case LIBRARIAN = 'librarian';

    public function label(): string
    {
        return match ($this) {
            self::STUDENT => 'Student',
            self::RESEARCHER => 'Student Researcher',
            self::SUPERVISOR => 'Academic Supervisor',
            self::ADMIN => 'System Administrator',
            self::DEAN => 'Dean of School',
            self::RESEARCH_DIRECTOR => 'Director of Research',
            self::LIBRARIAN => 'Repository Librarian',
        };
    }

    /**
     * What the role may do, shown to the administrator when granting it.
     */
    public function description(): string
    {
        return match ($this) {
            self::STUDENT => 'Student portal: submits a proposal and their own manuscripts.',
            self::RESEARCHER => 'Student portal: runs a supervised study and submits milestones.',
            self::SUPERVISOR => 'Lecturer portal: reviews and grades only the students assigned to them.',
            self::ADMIN => 'Manages user accounts, roles, schools and thematic areas, and can sign in as another user.',
            self::DEAN => 'Oversees one school: sees, grades and removes only that school\'s projects, gaps and students.',
            self::RESEARCH_DIRECTOR => 'University-wide: compares research across all schools, reads every manuscript, and runs the Research Office (challenge, events, sites, thematic areas). Does not grade.',
            self::LIBRARIAN => 'Repository workspace: views every uploaded manuscript with its summary and downloads files for publishing. Cannot grade or edit research.',
        };
    }

    /**
     * Whether the role belongs to the student-facing portal.
     */
    public function isStudentRole(): bool
    {
        return in_array($this, [self::STUDENT, self::RESEARCHER], true);
    }

    /**
     * Whether the account must be linked to a school when it is created.
     */
    public function requiresSchool(): bool
    {
        return $this === self::DEAN;
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
}
