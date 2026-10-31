<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\SimilarityCheckStatus;
use App\Enums\UserRole;
use App\Filament\Supervisor\Resources\SupervisorProjectResource\Pages\ListSupervisorProjects;
use App\Filament\Supervisor\Resources\SupervisorProjectResource\Pages\ViewSupervisorProject;
use App\Filament\Supervisor\Widgets\SupervisorStatsOverviewWidget;
use App\Jobs\RunSimilarityAnalysisJob;
use App\Models\Project;
use App\Models\SimilarityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }
    }

    public function test_guest_is_redirected_to_login_when_accessing_supervisor_portal(): void
    {
        $response = $this->get('/supervisor');

        $response->assertRedirect(route('login'));
    }

    public function test_student_without_supervisor_role_is_redirected_to_home_with_error(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get('/supervisor');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Access restricted to authorized academic staff.');
    }

    public function test_authorized_supervisor_can_access_supervisor_portal(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $response = $this->actingAs($supervisor)->get('/supervisor');

        $response->assertOk();
    }

    public function test_supervisor_eloquent_query_only_returns_assigned_projects(): void
    {
        $supervisorA = User::factory()->create(['name' => 'Dr. Supervisor A', 'role' => UserRole::SUPERVISOR]);
        $supervisorA->assignRole(UserRole::SUPERVISOR->value);

        $supervisorB = User::factory()->create(['name' => 'Dr. Supervisor B', 'role' => UserRole::SUPERVISOR]);
        $supervisorB->assignRole(UserRole::SUPERVISOR->value);

        $studentA = User::factory()->create(['name' => 'Student Author Alpha', 'role' => UserRole::STUDENT]);
        $studentA->assignRole(UserRole::STUDENT->value);

        $studentB = User::factory()->create(['name' => 'Student Author Beta', 'role' => UserRole::STUDENT]);
        $studentB->assignRole(UserRole::STUDENT->value);

        $assignedProject = Project::create([
            'title' => 'Assigned Project for Dr. A',
            'abstract' => 'Research scope under Dr. A supervision.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $studentA->id,
            'supervisor_id' => $supervisorA->id,
        ]);

        $unassignedProject = Project::create([
            'title' => 'Unassigned Project for Dr. B',
            'abstract' => 'Research scope under Dr. B supervision.',
            'category' => ResearchCategory::NATURAL_SCIENCES,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $studentB->id,
            'supervisor_id' => $supervisorB->id,
        ]);

        $this->actingAs($supervisorA);

        Livewire::test(ListSupervisorProjects::class)
            ->assertSee('Assigned Project for Dr. A')
            ->assertDontSee('Unassigned Project for Dr. B');
    }

    public function test_supervisor_stats_widget_computes_accurate_kpis(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        // 1. Proposal submitted
        $p1 = Project::create([
            'title' => 'Project 1 Proposal',
            'abstract' => 'Pending review.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::PROPOSAL_SUBMITTED,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        // 2. Under review with high similarity alert (28%)
        $p2 = Project::create([
            'title' => 'Project 2 Draft',
            'abstract' => 'Under review draft.',
            'category' => ResearchCategory::ECONOMICS,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        SimilarityReport::create([
            'project_id' => $p2->id,
            'similarity_score' => 28.5,
            'matched_sources_json' => [
                ['title' => 'Prior Thesis Reference', 'percentage' => 22.0],
            ],
        ]);

        // 3. Completed project
        Project::create([
            'title' => 'Project 3 Completed',
            'abstract' => 'Completed study.',
            'category' => ResearchCategory::ENVIRONMENTAL_SCIENCE,
            'status' => ProjectStatus::COMPLETED,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'completion_year' => 2025,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStatsOverviewWidget::class)
            ->assertSee('Advisees')
            ->assertSee('Awaiting review')
            ->assertSee('Similarity alerts')
            ->assertSee('Completed');
    }

    public function test_supervisor_review_submission_action_grades_and_transitions_milestone(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $project = Project::create([
            'title' => 'Soil Nitrogen Fixation Trials',
            'abstract' => 'Evaluating biofertilizer formulations.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(ListSupervisorProjects::class)
            ->callTableAction('review_submission', $project, [
                'rubric_score' => 86.5,
                'decision' => 'approve',
                'feedback' => 'Exceptional experimental design and data interpretation.',
            ])
            ->assertHasNoTableActionErrors();

        $project->refresh();

        $this->assertSame(ProjectStatus::COMPLETED, $project->status);
        $this->assertNotNull($project->completion_year);

        $this->assertDatabaseHas('supervisor_evaluations', [
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'rubric_score' => 86.5,
            'feedback' => 'Exceptional experimental design and data interpretation.',
        ]);
    }

    public function test_supervisor_can_view_project_page_and_header_action(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $project = Project::create([
            'title' => 'Soil Nitrogen Fixation Trials',
            'abstract' => 'Evaluating biofertilizer formulations.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(ViewSupervisorProject::class, ['record' => $project->getKey()])
            ->assertSuccessful()
            ->assertActionExists('review_submission');
    }

    public function test_supervisor_can_evaluate_via_view_page_header_action(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $project = Project::create([
            'title' => 'Maize Yield Analysis Under Drought Conditions',
            'abstract' => 'Evaluating drought resilience traits.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(ViewSupervisorProject::class, ['record' => $project->getKey()])
            ->callAction('review_submission', [
                'rubric_score' => 91.0,
                'decision' => 'approve',
                'feedback' => 'Thorough analysis and well-structured manuscript.',
            ])
            ->assertHasNoActionErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::COMPLETED, $project->status);
        $this->assertDatabaseHas('supervisor_evaluations', [
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'rubric_score' => 91.0,
            'feedback' => 'Thorough analysis and well-structured manuscript.',
        ]);
    }

    public function test_supervisor_view_project_infolist_renders_manuscript_preview_iframe(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        Storage::disk('secure_research')->put('manuscripts/advisee_draft.pdf', '%PDF-1.4 Mock PDF Content');

        $project = Project::create([
            'title' => 'Drought Tolerant Cassava Genotypes',
            'abstract' => 'Evaluating physiological markers for drought adaptation.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'full_document_path' => 'manuscripts/advisee_draft.pdf',
        ]);

        $this->actingAs($supervisor);

        $documentUrl = $project->getDocumentUrl();
        $this->assertNotNull($documentUrl);
        $this->assertStringContainsString('projects/'.$project->id.'/preview', $documentUrl);

        Livewire::test(ViewSupervisorProject::class, ['record' => $project->getKey()])
            ->assertSuccessful()
            ->assertSeeHtml('<iframe')
            ->assertSeeHtml('h-[600px] w-full rounded-lg border border-gray-700')
            ->assertSee('Manuscript Document');
    }

    public function test_manuscript_preview_endpoint_allows_signed_url_and_rejects_tampered_requests(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        Storage::disk('secure_research')->put('manuscripts/preview_test.pdf', '%PDF-1.4 Academic Manuscript');

        $project = Project::create([
            'title' => 'Soil Microbiome Dynamics Under Agroforestry',
            'abstract' => 'Longitudinal analysis of mycorrhizal colonization.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'full_document_path' => 'manuscripts/preview_test.pdf',
        ]);

        $validSignedUrl = $project->getDocumentUrl();

        // 1. Unauthenticated request is redirected to login by auth middleware
        $guestResponse = $this->get($validSignedUrl);
        $guestResponse->assertRedirect(route('login'));

        // 2. Authorized assigned supervisor with valid signed URL gets 200 OK
        $response = $this->actingAs($supervisor)->get($validSignedUrl);
        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline; filename="soil-microbiome-dynamics-under-agroforestry-manuscript.pdf"');

        // 3. Unassigned supervisor attempting to access another supervisor's advisee project gets 403
        $unassignedSupervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $unassignedSupervisor->assignRole(UserRole::SUPERVISOR->value);
        $unassignedResponse = $this->actingAs($unassignedSupervisor)->get($validSignedUrl);
        $unassignedResponse->assertForbidden();

        // 4. Tampered or unsigned URL gets 403 from signed middleware
        $tamperedUrl = route('projects.document.preview', ['project' => $project->id]);
        $tamperedResponse = $this->actingAs($supervisor)->get($tamperedUrl);
        $tamperedResponse->assertForbidden();
    }

    public function test_supervisor_review_submission_requires_feedback_when_revisions_requested(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $project = Project::create([
            'title' => 'Poultry Probiotic Feed Formulations',
            'abstract' => 'Formulating synbiotic additives for gut health.',
            'category' => ResearchCategory::NATURAL_SCIENCES,
            'status' => ProjectStatus::UNDER_REVIEW,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(ListSupervisorProjects::class)
            ->callTableAction('review_submission', $project, [
                'rubric_score' => 45.0,
                'decision' => 'revisions',
                'feedback' => '',
            ])
            ->assertHasTableActionErrors(['feedback']);
    }

    public function test_similarity_breakdown_renders_untested_processing_and_completed_states(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);
        $supervisor->assignRole(UserRole::SUPERVISOR->value);

        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        // 1. Untested project displays "Untested" and "Run AI Plagiarism Check"
        $project = Project::create([
            'title' => 'Aquaponics Tilapia Nutrition',
            'abstract' => 'Evaluating duckweed supplement.',
            'category' => ResearchCategory::NATURAL_SCIENCES,
            'status' => ProjectStatus::UNDER_REVIEW,
            'similarity_check_status' => SimilarityCheckStatus::UNTESTED,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(ViewSupervisorProject::class, ['record' => $project->getKey()])
            ->mountAction('review_submission')
            ->assertSee('Untested')
            ->assertSee('Run AI Plagiarism Check');

        // 2. Processing state displays analysis in progress notice
        $project->update(['similarity_check_status' => SimilarityCheckStatus::PROCESSING]);

        Livewire::test(ViewSupervisorProject::class, ['record' => $project->getKey()])
            ->mountAction('review_submission')
            ->assertSee('Analysis in progress')
            ->assertSee('Document text parsing and AI similarity analysis are currently in progress');

        // 3. Project with similarity report displays score badge and side-by-side comparison
        SimilarityReport::create([
            'project_id' => $project->id,
            'similarity_score' => 28.5,
            'matched_sources_json' => [
                [
                    'source_project_title' => 'Prior Thesis on Duckweed Feeding',
                    'student_text' => 'The inclusion of Lemna minor increased crude protein retention.',
                    'original_text' => 'Dietary supplementation of Lemna minor demonstrated enhanced crude protein.',
                    'percentage' => 28.5,
                ],
            ],
        ]);

        $project->update(['similarity_check_status' => SimilarityCheckStatus::COMPLETED]);

        Livewire::test(ViewSupervisorProject::class, ['record' => $project->getKey()])
            ->mountAction('review_submission')
            ->assertSee('28.5%')
            ->assertSee('Prior Thesis on Duckweed Feeding')
            ->assertSee('The inclusion of Lemna minor increased crude protein retention.')
            ->assertSee('Dietary supplementation of Lemna minor demonstrated enhanced crude protein.');
    }

    public function test_run_similarity_analysis_job_executes_and_updates_status(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $supervisor = User::factory()->create(['role' => UserRole::SUPERVISOR]);

        // Prior completed project with overlapping abstract
        Project::create([
            'title' => 'Prior Biochar Soil Study',
            'abstract' => 'Comprehensive soil microbial biomass and nutrient retention assessment in maize fields.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::COMPLETED,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'completion_year' => 2024,
        ]);

        $project = Project::create([
            'title' => 'Biochar Organic Amendments',
            'abstract' => 'Comprehensive soil microbial biomass and nutrient retention assessment under tropical irrigation.',
            'category' => ResearchCategory::AGRICULTURE,
            'status' => ProjectStatus::UNDER_REVIEW,
            'similarity_check_status' => SimilarityCheckStatus::UNTESTED,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $job = new RunSimilarityAnalysisJob($project);
        $job->handle();

        $project->refresh();

        $this->assertEquals(SimilarityCheckStatus::COMPLETED, $project->similarity_check_status);
        $this->assertNotNull($project->similarityReport);
        $this->assertGreaterThan(0.0, $project->similarityReport->similarity_score);
    }
}
