<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolStudentResource\Pages;

use App\Filament\Resources\SchoolStudentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListSchoolStudents extends ListRecords
{
    protected static string $resource = SchoolStudentResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $user = auth()->user();

        if ($user?->oversightSchoolId() === null) {
            return 'Student researchers across every school.';
        }

        return $user->school !== null
            ? "Student researchers registered in the {$user->school->name}."
            : 'No school is linked to your account yet. Ask the system administrator to assign one.';
    }
}
