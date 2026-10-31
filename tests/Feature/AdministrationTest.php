<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\UserRole;
use App\Filament\Resources\ResearchDomainResource\Pages\ManageResearchDomains;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Http\Controllers\Auth\ImpersonationController;
use App\Models\Project;
use App\Models\ResearchDomain;
use App\Models\ResearchGap;
use App\Models\School;
use App\Models\User;
use Database\Seeders\ResearchOfficeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        $this->admin = $this->makeUser('Admin User', UserRole::ADMIN);
    }

    protected function makeUser(string $name, UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role, ...$attributes]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function makeProject(User $student): Project
    {
        return Project::create([
            'title' => 'Soil fertility in Kabwe smallholdings',
            'abstract' => 'A study of soil fertility.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::COMPLETED,
            'student_id' => $student->id,
        ]);
    }

    protected function inPanel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));
    }

    public function test_seeded_accounts_have_no_research_office_role_and_deans_have_schools(): void
    {
        $this->seed([RoleAndUserSeeder::class, ResearchOfficeSeeder::class]);

        $this->assertDatabaseMissing('roles', ['name' => 'research_officer']);
        $this->assertSame(8, School::count());

        foreach (User::role(UserRole::DEAN->value)->get() as $dean) {
            $this->assertNotNull($dean->school_id, "{$dean->name} has no school");
        }

        $librarian = User::where('email', 'librarian@rris.mu.ac.zm')->firstOrFail();
        $this->actingAs($librarian)->get('/supervisor')->assertOk()->assertSee('Repository workspace');
    }

    public function test_staff_sign_in_lands_in_the_staff_workspace(): void
    {
        $librarian = $this->makeUser('Mrs. G. Chileshe', UserRole::LIBRARIAN, ['password' => 'secret-pass']);

        $this->post(route('login.attempt'), ['email' => $librarian->email, 'password' => 'secret-pass'])
            ->assertRedirect('/supervisor');
    }

    public function test_retirement_migration_turns_office_staff_into_research_directors(): void
    {
        $officerRole = Role::firstOrCreate(['name' => 'research_officer', 'guard_name' => 'web']);

        $ethel = $this->makeUser('Ethel Kunda', UserRole::RESEARCHER, ['department' => 'Office of Research and Innovation']);
        $ethel->syncRoles([$officerRole]);
        DB::table('users')->where('id', $ethel->id)->update(['role' => 'research_officer']);

        $peter = $this->makeUser('Peter Mwaba', UserRole::RESEARCHER);
        $peter->assignRole($officerRole);

        $migration = require database_path('migrations/2026_10_31_000002_retire_research_office_role_and_add_librarian.php');
        $migration->up();

        $ethel = User::findOrFail($ethel->id);
        $this->assertSame(UserRole::RESEARCH_DIRECTOR, $ethel->role);
        $this->assertSame([UserRole::RESEARCH_DIRECTOR->value], $ethel->getRoleNames()->all());

        // A student who held it as an extra duty keeps only their student role.
        $this->assertSame([UserRole::RESEARCHER->value], User::findOrFail($peter->id)->getRoleNames()->all());
        $this->assertDatabaseMissing('roles', ['name' => 'research_officer']);
    }

    public function test_only_the_administrator_manages_users(): void
    {
        $this->actingAs($this->admin)->get('/supervisor/users')->assertOk();

        foreach ([UserRole::DEAN, UserRole::RESEARCH_DIRECTOR, UserRole::SUPERVISOR, UserRole::LIBRARIAN] as $role) {
            $this->actingAs($this->makeUser($role->label(), $role))->get('/supervisor/users')->assertForbidden();
        }
    }

    public function test_administrator_adds_a_user_with_a_primary_role_and_extra_duty(): void
    {
        $this->inPanel($this->admin);

        Livewire::test(ManageUsers::class)
            ->callAction('create', [
                'name' => 'Grace Phiri',
                'email' => 'grace@rris.mu.ac.zm',
                'department' => 'Economics',
                'password' => 'a-strong-password',
                'role' => UserRole::SUPERVISOR->value,
                'additional_roles' => [UserRole::LIBRARIAN->value],
            ])
            ->assertHasNoActionErrors();

        $grace = User::where('email', 'grace@rris.mu.ac.zm')->firstOrFail();
        $this->assertSame(UserRole::SUPERVISOR, $grace->role);
        $this->assertTrue($grace->hasAllRoles([UserRole::SUPERVISOR->value, UserRole::LIBRARIAN->value]));

        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['email' => 'grace@rris.mu.ac.zm', 'password' => 'a-strong-password'])
            ->assertRedirect('/supervisor');
    }

    public function test_administrator_changes_roles_and_extra_duties_are_replaced(): void
    {
        $user = $this->makeUser('Dr. A. Mwale', UserRole::SUPERVISOR);
        $user->assignRole(UserRole::LIBRARIAN->value);
        $school = School::where('slug', 'education')->firstOrFail();
        $this->inPanel($this->admin);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $user, [
                'name' => 'Dr. A. Mwale',
                'email' => $user->email,
                'role' => UserRole::DEAN->value,
                'school_id' => $school->id,
                'additional_roles' => [],
            ])
            ->assertHasNoTableActionErrors();

        $user->refresh();
        $this->assertSame(UserRole::DEAN, $user->role);
        $this->assertSame($school->id, $user->school_id);
        $this->assertSame([UserRole::DEAN->value], $user->getRoleNames()->all());
    }

    public function test_administrator_cannot_change_their_own_roles(): void
    {
        $this->inPanel($this->admin);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $this->admin, [
                'name' => 'Admin User',
                'email' => $this->admin->email,
                'role' => UserRole::STUDENT->value,
            ]);

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_accounts_with_research_are_deactivated_not_deleted(): void
    {
        $student = $this->makeUser('Lukundo Bwalya', UserRole::RESEARCHER, ['password' => 'secret-pass']);
        $this->makeProject($student);
        $newcomer = $this->makeUser('New Student', UserRole::STUDENT);
        $this->inPanel($this->admin);

        Livewire::test(ManageUsers::class)
            ->assertTableActionHidden('delete', $student)
            ->assertTableActionHidden('delete', $this->admin)
            ->callTableAction('delete', $newcomer)
            ->callTableAction('deactivate', $student);

        $this->assertModelMissing($newcomer);
        $this->assertTrue($student->fresh()->isDeactivated());
        $this->assertDatabaseCount('projects', 1);

        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['email' => $student->email, 'password' => 'secret-pass'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_deactivated_user_is_signed_out_of_an_open_session(): void
    {
        $student = $this->makeUser('Kelvin Mwape', UserRole::STUDENT, ['deactivated_at' => now()]);

        $this->actingAs($student)->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_administrator_signs_in_as_a_user_and_returns(): void
    {
        $student = $this->makeUser('Kelvin Mwape', UserRole::STUDENT);
        $this->inPanel($this->admin);

        Livewire::test(ManageUsers::class)
            ->callTableAction('impersonate', $student)
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
        $this->assertSame($this->admin->id, session(ImpersonationController::SESSION_KEY));

        $this->get(route('student.dashboard'))->assertOk()->assertSee('Return to my account');

        $this->post(route('impersonate.leave'))->assertRedirect('/supervisor/users');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }

    public function test_administrators_cannot_be_impersonated_and_others_cannot_impersonate(): void
    {
        $otherAdmin = $this->makeUser('Second Admin', UserRole::ADMIN);
        $student = $this->makeUser('Kelvin Mwape', UserRole::STUDENT);
        $this->inPanel($this->admin);

        Livewire::test(ManageUsers::class)
            ->assertTableActionHidden('impersonate', $otherAdmin)
            ->assertTableActionHidden('impersonate', $this->admin)
            ->assertTableActionVisible('impersonate', $student);

        $this->assertFalse($this->makeUser('Dean', UserRole::DEAN)->canImpersonate($student));

        // Without an impersonation in progress, the way back does nothing.
        $this->actingAs($student)->post(route('impersonate.leave'))->assertForbidden();
    }

    public function test_administrators_use_the_student_portal_only_by_impersonation(): void
    {
        $this->actingAs($this->admin)->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_deans_no_longer_run_the_office_programmes(): void
    {
        $dean = $this->makeUser('Dr. M. Phiri', UserRole::DEAN);
        $this->actingAs($dean);

        $this->get('/supervisor/innovation-challenges')->assertForbidden();
        $this->get('/supervisor/research-domains')->assertForbidden();

        $director = $this->makeUser('Prof. E. Banda', UserRole::RESEARCH_DIRECTOR);
        $this->actingAs($director)->get('/supervisor/innovation-challenges')->assertOk();
    }

    public function test_students_cannot_edit_a_completed_study(): void
    {
        $student = $this->makeUser('Lukundo Bwalya', UserRole::RESEARCHER);
        $project = $this->makeProject($student);

        $this->assertFalse($student->can('update', $project));

        $project->update(['status' => ProjectStatus::UNDER_SUPERVISION]);
        $this->assertTrue($student->can('update', $project->fresh()));
    }

    public function test_research_director_manages_thematic_areas_and_merges_duplicates(): void
    {
        $officer = $this->makeUser('Prof. E. Banda', UserRole::RESEARCH_DIRECTOR);
        $student = $this->makeUser('Lukundo Bwalya', UserRole::RESEARCHER);
        $project = $this->makeProject($student);

        $keep = ResearchDomain::create(['name' => 'Food Security', 'slug' => 'food-security']);
        $duplicate = ResearchDomain::create(['name' => 'Food security in Zambia', 'slug' => 'food-security-in-zambia']);
        $gap = ResearchGap::create(['project_id' => $project->id, 'domain_id' => $duplicate->id, 'extracted_gap_text' => 'Post-harvest storage losses.']);

        $this->inPanel($officer);

        Livewire::test(ManageResearchDomains::class)
            ->callAction('create', ['name' => 'Climate-smart agriculture', 'slug' => 'climate-smart-agriculture'])
            ->assertHasNoActionErrors()
            ->assertTableActionHidden('delete', $duplicate)
            ->callTableAction('merge', $duplicate, ['target_id' => $keep->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('research_domains', ['slug' => 'climate-smart-agriculture']);
        $this->assertModelMissing($duplicate);
        $this->assertSame($keep->id, $gap->fresh()->domain_id);

        $this->actingAs($this->makeUser('Dr. C. Mulenga', UserRole::SUPERVISOR))
            ->get('/supervisor/research-domains')
            ->assertForbidden();
    }
}
