<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Previously moved Office of Research and Innovation staff off the student role and onto the office role.
 * The office role has since been retired into the Research Director (see
 * 2026_10_31_000002_retire_research_office_role_and_add_librarian), which converts every office holder,
 * so this step is kept only so migration history stays intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
