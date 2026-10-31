<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\UserRole;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\RepositoryResource\Pages\ListRepositoryFiles;
use App\Filament\Resources\SchoolStudentResource\Pages\ListSchoolStudents;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Filament\Widgets\EmergingGapsTable;
use App\Filament\Widgets\SchoolsOverviewTable;
use App\Models\Project;
use App\Models\ResearchDomain;
use App\Models\ResearchGap;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deans are limited to their own school; the research director sees every school but does not grade;
 * repository librarians read and download every uploaded file.
 */
class SchoolAccessTest extends TestCase
{
    use RefreshDatabase;

    protected School $agriculture;

    protected School $business;

    protected User $dean;

    protected Project $agricultureStudy;

    protected Project $businessStudy;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('secure_research');

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        $this->agriculture = School::where('slug', 'agriculture-natural-resources')->firstOrFail();
        $this->business = School::where('slug', 'business-studies')->firstOrFail();

        $this->dean = $this->makeUser('Prof. K. Lungu', UserRole::DEAN, $this->agriculture);

        $farmer = $this->makeUser('Lukundo Bwalya', UserRole::RESEARCHER, $this->agriculture);
        $accountant = $this->makeUser('Joseph Banda', UserRole::RESEARCHER, $this->business);

        $this->agricultureStudy = $this->makeProject('Biochar on sandy soils', $farmer, ResearchCategory::AGRICULTURE);
        $this->businessStudy = $this->makeProject('Mobile money and SME bookkeeping', $accountant, ResearchCategory::ACCOUNTING_FINANCE);
    }

    protected function makeUser(string $name, UserRole $role, ?School $school = null): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role, 'school_id' => $school?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function makeProject(string $title, User $student, ResearchCategory $category): Project
    {
        Storage::disk('secure_research')->put("manuscripts/{$student->id}.pdf", 'Manuscript');

        return Project::create([
            'title' => $title,
            'abstract' => 'A study.',
            'category' => $category,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'full_document_path' => "manuscripts/{$student->id}.pdf",
        ]);
    }

    protected function inPanel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));
    }

    public function test_the_eight_schools_are_seeded(): void
    {
        $this->assertSame(array_values(School::DIRECTORY), School::orderBy('id')->pluck('name')->all());
    }

    public function test_projects_are_filed_under_the_students_school_or_their_subjects_school(): void
    {
        $this->assertSame($this->agriculture->id, $this->agricultureStudy->school_id);

        $unplaced = $this->makeUser('New Student', UserRole::STUDENT);
        $nursing = $this->makeProject('Kangaroo mother care uptake', $unplaced, ResearchCategory::NURSING_MIDWIFERY);

        $this->assertSame('School of Nursing and Midwifery', $nursing->school->name);
    }

    public function test_administrator_must_choose_a_school_for_a_dean(): void
    {
        $this->inPanel($this->makeUser('Admin User', UserRole::ADMIN));

        Livewire::test(ManageUsers::class)
            ->callAction('create', [
                'name' => 'Dr. B. Mumba',
                'email' => 'bmumba@rris.mu.ac.zm',
                'password' => 'a-strong-password',
                'role' => UserRole::DEAN->value,
                'additional_roles' => [],
            ])
            ->assertHasActionErrors(['school_id' => 'required']);

        Livewire::test(ManageUsers::class)
            ->callAction('create', [
                'name' => 'Dr. B. Mumba',
                'email' => 'bmumba@rris.mu.ac.zm',
                'password' => 'a-strong-password',
                'role' => UserRole::DEAN->value,
                'school_id' => $this->business->id,
                'additional_roles' => [],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($this->business->id, User::where('email', 'bmumba@rris.mu.ac.zm')->value('school_id'));
    }

    public function test_dean_sees_and_governs_only_their_own_school(): void
    {
        $this->inPanel($this->dean);

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords([$this->agricultureStudy])
            ->assertCanNotSeeTableRecords([$this->businessStudy]);

        $this->get(ProjectResource::getUrl('edit', ['record' => $this->businessStudy]))->assertNotFound();

        $this->assertTrue($this->dean->can('evaluate', $this->agricultureStudy));
        $this->assertTrue($this->dean->can('delete', $this->agricultureStudy));
        $this->assertTrue($this->dean->can('downloadFullContent', $this->agricultureStudy));

        $this->assertFalse($this->dean->can('evaluate', $this->businessStudy));
        $this->assertFalse($this->dean->can('update', $this->businessStudy));
        $this->assertFalse($this->dean->can('delete', $this->businessStudy));
        $this->assertFalse($this->dean->can('downloadFullContent', $this->businessStudy));

        $this->get(route('projects.download', $this->businessStudy))->assertForbidden();
        $this->get(route('projects.download', $this->agricultureStudy))->assertOk();
    }

    public function test_dean_sees_only_their_schools_students_and_gaps(): void
    {
        $domain = ResearchDomain::create(['name' => 'Soil', 'slug' => 'soil']);
        $ownGap = ResearchGap::create(['project_id' => $this->agricultureStudy->id, 'domain_id' => $domain->id, 'extracted_gap_text' => 'Biochar ageing', 'is_emerging' => true]);
        $otherGap = ResearchGap::create(['project_id' => $this->businessStudy->id, 'domain_id' => $domain->id, 'extracted_gap_text' => 'SME credit', 'is_emerging' => true]);

        $this->inPanel($this->dean);

        Livewire::test(ListSchoolStudents::class)
            ->assertCanSeeTableRecords([$this->agricultureStudy->student])
            ->assertCanNotSeeTableRecords([$this->businessStudy->student]);

        Livewire::test(EmergingGapsTable::class)
            ->assertCanSeeTableRecords([$ownGap])
            ->assertCanNotSeeTableRecords([$otherGap]);

        $this->assertFalse(SchoolsOverviewTable::canView());
    }

    public function test_dean_without_a_school_sees_nothing(): void
    {
        $this->inPanel($this->makeUser('Dr. Unassigned', UserRole::DEAN));

        Livewire::test(ListProjects::class)->assertCanNotSeeTableRecords([$this->agricultureStudy, $this->businessStudy]);
    }

    public function test_research_director_oversees_every_school_but_does_not_grade(): void
    {
        $director = $this->makeUser('Prof. E. Banda', UserRole::RESEARCH_DIRECTOR);
        $this->inPanel($director);

        Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$this->agricultureStudy, $this->businessStudy]);
        Livewire::test(SchoolsOverviewTable::class)->assertCanSeeTableRecords([$this->agriculture, $this->business]);

        $this->assertTrue($director->can('downloadFullContent', $this->businessStudy));
        $this->assertFalse($director->can('evaluate', $this->businessStudy));
        $this->assertFalse($director->can('update', $this->businessStudy));
        $this->assertFalse($director->can('delete', $this->businessStudy));
    }

    public function test_librarian_browses_and_downloads_every_uploaded_file(): void
    {
        $librarian = $this->makeUser('Mrs. G. Chileshe', UserRole::LIBRARIAN);
        $this->inPanel($librarian);

        $this->get('/supervisor')->assertOk();
        Livewire::test(ListRepositoryFiles::class)->assertCanSeeTableRecords([$this->agricultureStudy, $this->businessStudy]);

        $this->get(route('projects.download', $this->businessStudy))->assertOk();

        // Librarians do not grade, edit or see the supervision register.
        $this->assertFalse($librarian->can('evaluate', $this->businessStudy));
        $this->assertFalse($librarian->can('update', $this->businessStudy));
        $this->get(ProjectResource::getUrl('index'))->assertForbidden();
        $this->get('/supervisor/users')->assertForbidden();
    }

    public function test_dean_repository_view_is_limited_to_their_school(): void
    {
        $this->inPanel($this->dean);

        Livewire::test(ListRepositoryFiles::class)
            ->assertCanSeeTableRecords([$this->agricultureStudy])
            ->assertCanNotSeeTableRecords([$this->businessStudy]);
    }

    public function test_public_and_other_students_still_cannot_download(): void
    {
        $this->businessStudy->update(['status' => ProjectStatus::COMPLETED]);
        $peer = $this->makeUser('Kelvin Mwape', UserRole::STUDENT, $this->business);

        $this->get(route('projects.download', $this->businessStudy))->assertRedirect(route('login'));
        $this->actingAs($peer)->get(route('projects.download', $this->businessStudy))->assertForbidden();
        $this->actingAs($peer)->get('/supervisor/repository-files')->assertRedirect('/');
    }
}
