<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\UserRole;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Supervisor\Resources\SupervisorProjectResource\Pages\ListSupervisorProjects;
use App\Filament\Supervisor\Widgets\ReviewQueueWidget;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LecturerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected User $otherSupervisor;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        $this->supervisor = $this->makeUser(UserRole::SUPERVISOR, 'Dr. C. Mulenga');
        $this->otherSupervisor = $this->makeUser(UserRole::SUPERVISOR, 'Dr. J. Tembo');
        $this->student = $this->makeUser(UserRole::STUDENT, 'Kelvin Mwape');
    }

    protected function makeUser(UserRole $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function makeProject(string $title, ProjectStatus $status, User $supervisor): Project
    {
        return Project::create([
            'title' => $title,
            'abstract' => 'Abstract text for the lecturer portal tests.',
            'category' => ResearchCategory::NATURAL_SCIENCES,
            'status' => $status,
            'student_id' => $this->student->id,
            'supervisor_id' => $supervisor->id,
        ]);
    }

    public function test_dashboard_greets_the_lecturer_and_lists_only_their_pending_reviews(): void
    {
        $this->makeProject('Pending Proposal From My Advisee', ProjectStatus::PROPOSAL_SUBMITTED, $this->supervisor);
        $this->makeProject('Pending Draft From Another Advisee', ProjectStatus::UNDER_REVIEW, $this->otherSupervisor);
        $this->makeProject('Fieldwork Still Running', ProjectStatus::UNDER_SUPERVISION, $this->supervisor);

        $this->actingAs($this->supervisor)
            ->get('/supervisor')
            ->assertOk()
            ->assertSee('Dr. Mulenga');

        Livewire::test(ReviewQueueWidget::class)
            ->assertSee('Pending Proposal From My Advisee')
            ->assertDontSee('Pending Draft From Another Advisee')
            ->assertDontSee('Fieldwork Still Running');
    }

    public function test_advisee_list_tabs_filter_by_stage(): void
    {
        $this->makeProject('Awaiting Decision Study', ProjectStatus::UNDER_REVIEW, $this->supervisor);
        $this->makeProject('Completed Study', ProjectStatus::COMPLETED, $this->supervisor);

        $this->actingAs($this->supervisor);

        Livewire::test(ListSupervisorProjects::class)
            ->set('activeTab', 'awaiting')
            ->assertSee('Awaiting Decision Study')
            ->assertDontSee('Completed Study');
    }

    public function test_school_wide_register_is_limited_to_oversight_roles(): void
    {
        $study = $this->makeProject('Any Departmental Study', ProjectStatus::UNDER_SUPERVISION, $this->otherSupervisor);

        $this->actingAs($this->supervisor)
            ->get(ProjectResource::getUrl('index'))
            ->assertForbidden();

        // Deans see their own school's register.
        $dean = $this->makeUser(UserRole::DEAN, 'Dr. M. Phiri');
        $dean->update(['school_id' => $study->school_id]);
        $this->actingAs($dean);

        $this->get(ProjectResource::getUrl('index'))->assertOk();

        Livewire::test(ListProjects::class)->assertSee('Any Departmental Study');
    }
}
