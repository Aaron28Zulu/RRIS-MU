<?php

declare(strict_types=1);

use App\Enums\ResearchCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The university's eight schools. Users (deans, lecturers, students) and projects belong to a school,
 * so a dean's access can be limited to their own school.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const SCHOOLS = [
        'agriculture-natural-resources' => 'School of Agriculture and Natural Resources',
        'business-studies' => 'School of Business Studies',
        'engineering-technology' => 'School of Engineering and Technology',
        'social-sciences' => 'School of Social Sciences',
        'natural-applied-sciences' => 'School of Natural and Applied Sciences',
        'education' => 'School of Education',
        'nursing-midwifery' => 'School of Nursing and Midwifery',
        'medicine-health-sciences' => 'School of Medicine and Health Sciences',
    ];

    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        foreach (self::SCHOOLS as $slug => $name) {
            DB::table('schools')->insert(['name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('department')->constrained('schools')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('category')->constrained('schools')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Places existing accounts and projects in a school where it can be worked out safely. The
     * administrator assigns the rest from the Users screen.
     */
    private function backfill(): void
    {
        $schoolIds = DB::table('schools')->pluck('id', 'slug');
        $idsByName = DB::table('schools')->pluck('id', 'name');

        // People whose department is already a school name, for example the seeded dean.
        foreach ($idsByName as $name => $id) {
            DB::table('users')->whereNull('school_id')
                ->whereIn('department', [$name, str($name)->after('School of ')->toString()])
                ->update(['school_id' => $id]);
        }

        // Projects follow their subject's school.
        foreach (ResearchCategory::cases() as $category) {
            DB::table('projects')->whereNull('school_id')
                ->where('category', $category->value)
                ->update(['school_id' => $schoolIds[$category->schoolSlug()] ?? null]);
        }

        // Students belong to the school of their earliest project.
        DB::table('projects')->whereNotNull('school_id')->orderBy('id')->get(['student_id', 'school_id'])
            ->unique('student_id')
            ->each(fn (object $project) => DB::table('users')
                ->where('id', $project->student_id)
                ->whereNull('school_id')
                ->update(['school_id' => $project->school_id]));
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });

        Schema::dropIfExists('schools');
    }
};
