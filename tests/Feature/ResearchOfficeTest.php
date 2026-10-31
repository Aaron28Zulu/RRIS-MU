<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ChallengeStatus;
use App\Enums\EntryStatus;
use App\Enums\EntryType;
use App\Enums\EventType;
use App\Enums\ResearchCategory;
use App\Enums\SiteActivity;
use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AssociationEventResource\Pages\ManageAssociationEvents;
use App\Filament\Resources\ChallengeEntryResource\Pages\ManageChallengeEntries;
use App\Filament\Resources\InnovationChallengeResource\Pages\ManageInnovationChallenges;
use App\Filament\Resources\ResearchSiteResource\Pages\ManageResearchSites;
use App\Livewire\Innovation\ChallengePage;
use App\Livewire\Innovation\EventPage;
use App\Livewire\Innovation\ResearchSitePage;
use App\Models\AssociationEvent;
use App\Models\ChallengeEntry;
use App\Models\InnovationChallenge;
use App\Models\ResearchSite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResearchOfficeTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $officer;

    protected User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        $this->student = $this->makeUser('Kelvin Mwape', UserRole::STUDENT);
        $this->supervisor = $this->makeUser('Dr. C. Mulenga', UserRole::SUPERVISOR);

        // The research director leads the Research Office and runs its programmes.
        $this->officer = $this->makeUser('Prof. E. Banda', UserRole::RESEARCH_DIRECTOR);
    }

    protected function makeUser(string $name, UserRole $role): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function makeChallenge(ChallengeStatus $status = ChallengeStatus::OPEN): InnovationChallenge
    {
        return InnovationChallenge::create([
            'title' => 'Mulungushi University Vision 2030 Research and Innovation Challenge',
            'edition' => 'Semester 1, 2026/27',
            'description' => 'Call for research proposals and innovative project ideas.',
            'status' => $status,
            'opens_on' => now()->subWeek(),
            'closes_on' => now()->addWeek(),
        ]);
    }

    protected function makeEntry(InnovationChallenge $challenge, EntryStatus $status = EntryStatus::SUBMITTED, string $title = 'Solar drip kits for vegetable growers'): ChallengeEntry
    {
        return $challenge->entries()->create([
            'student_id' => $this->student->id,
            'title' => $title,
            'entry_type' => EntryType::INNOVATION,
            'category' => ResearchCategory::AGRICULTURE,
            'problem_statement' => str_repeat('Smallholders lack affordable irrigation. ', 3),
            'proposed_solution' => str_repeat('Build and test a low-cost solar drip kit. ', 3),
            'expected_impact' => 'Higher dry-season vegetable yields.',
            'status' => $status,
        ]);
    }

    public function test_innovation_hub_lists_the_offices_programmes_publicly(): void
    {
        $this->makeChallenge();
        $this->makeChallenge(ChallengeStatus::DRAFT)->update(['edition' => 'Unannounced draft call']);
        AssociationEvent::create([
            'title' => 'Inter-School Research Debate',
            'event_type' => EventType::DEBATE,
            'description' => 'Debate on research uptake.',
            'starts_at' => now()->addDays(5),
        ]);
        ResearchSite::create(['name' => 'Kabwe Borehole Monitoring Network', 'focus' => 'Borehole water quality', 'category' => ResearchCategory::NATURAL_SCIENCES, 'period' => 'Semester 1, 2026/27', 'status' => SiteStatus::ACTIVE]);

        $this->get(route('innovation'))
            ->assertOk()
            ->assertSee('Vision 2030 Research &amp; Innovation Challenge', false)
            ->assertSee('Semester 1, 2026/27')
            ->assertDontSee('Unannounced draft call')
            ->assertSee('Inter-School Research Debate')
            ->assertSee('Kabwe Borehole Monitoring Network');
    }

    public function test_draft_challenge_pages_are_not_reachable(): void
    {
        $challenge = $this->makeChallenge(ChallengeStatus::DRAFT);

        $this->get(route('innovation.challenge', $challenge))->assertNotFound();
    }

    public function test_student_can_enter_an_open_challenge_and_the_office_is_notified(): void
    {
        $challenge = $this->makeChallenge();

        Livewire::actingAs($this->student)
            ->test(ChallengePage::class, ['challenge' => $challenge])
            ->set('title', 'Solar drip kits for vegetable growers')
            ->set('entry_type', EntryType::INNOVATION->value)
            ->set('category', ResearchCategory::AGRICULTURE->value)
            ->set('problem_statement', str_repeat('Smallholders lack affordable irrigation. ', 3))
            ->set('proposed_solution', str_repeat('Build and test a low-cost solar drip kit. ', 3))
            ->set('expected_impact', 'Higher dry-season vegetable yields for smallholders.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('innovation.challenge', $challenge));

        $this->assertDatabaseHas('challenge_entries', [
            'innovation_challenge_id' => $challenge->id,
            'student_id' => $this->student->id,
            'status' => EntryStatus::SUBMITTED->value,
        ]);
        $this->assertSame(1, $this->officer->notifications()->count());
    }

    public function test_entries_are_refused_once_the_call_closes(): void
    {
        $challenge = $this->makeChallenge(ChallengeStatus::REVIEWING);

        Livewire::actingAs($this->student)
            ->test(ChallengePage::class, ['challenge' => $challenge])
            ->set('title', 'Solar drip kits for vegetable growers')
            ->call('submit')
            ->assertHasErrors('title');

        $this->assertDatabaseCount('challenge_entries', 0);
    }

    public function test_lecturers_cannot_submit_entries(): void
    {
        $challenge = $this->makeChallenge();

        Livewire::actingAs($this->supervisor)
            ->test(ChallengePage::class, ['challenge' => $challenge])
            ->call('submit')
            ->assertForbidden();
    }

    public function test_only_shortlisted_and_recognised_entries_are_shown_publicly(): void
    {
        $challenge = $this->makeChallenge();
        $this->makeEntry($challenge, EntryStatus::SUBMITTED, 'Still under review idea');
        $this->makeEntry($challenge, EntryStatus::NOT_SHORTLISTED, 'Not shortlisted idea');
        $this->makeEntry($challenge, EntryStatus::SHORTLISTED, 'Shortlisted cassava processing idea');

        $this->get(route('innovation.challenge', $challenge))
            ->assertOk()
            ->assertSee('Shortlisted cassava processing idea')
            ->assertDontSee('Still under review idea')
            ->assertDontSee('Not shortlisted idea');
    }

    public function test_panel_members_score_entries_and_the_mean_is_out_of_twenty(): void
    {
        $challenge = $this->makeChallenge(ChallengeStatus::REVIEWING);
        $entry = $this->makeEntry($challenge);

        $this->actingAs($this->supervisor);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageChallengeEntries::class)
            ->callTableAction('score', $entry, [
                'relevance_score' => 5,
                'innovation_score' => 4,
                'feasibility_score' => 3,
                'impact_score' => 4,
                'comments' => 'Promising, needs a costed budget.',
            ])
            ->assertHasNoTableActionErrors();

        $entry->reviews()->create([
            'reviewer_id' => $this->officer->id,
            'relevance_score' => 4,
            'innovation_score' => 4,
            'feasibility_score' => 4,
            'impact_score' => 2,
        ]);

        $this->assertSame(15.0, $entry->fresh()->panelScore());
    }

    public function test_students_cannot_score_their_own_entry_even_with_panel_duties(): void
    {
        // A student researcher who also tutors, so sits on the review panel.
        $member = $this->makeUser('Peter Mwaba', UserRole::RESEARCHER);
        $member->assignRole(UserRole::SUPERVISOR->value);

        $challenge = $this->makeChallenge(ChallengeStatus::REVIEWING);
        $entry = $challenge->entries()->create([
            'student_id' => $member->id,
            'title' => 'Office member entry',
            'entry_type' => EntryType::RESEARCH_PROPOSAL,
            'category' => ResearchCategory::ECONOMICS,
            'problem_statement' => 'Problem',
            'proposed_solution' => 'Solution',
            'expected_impact' => 'Impact',
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageChallengeEntries::class)
            ->assertTableActionHidden('score', $entry);
    }

    public function test_office_decision_notifies_the_student(): void
    {
        $challenge = $this->makeChallenge(ChallengeStatus::REVIEWING);
        $entry = $this->makeEntry($challenge);

        $this->actingAs($this->officer);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageChallengeEntries::class)
            ->callTableAction('decide', $entry, [
                'status' => EntryStatus::RECOGNISED->value,
                'award' => 'Winner',
                'mentor_id' => $this->supervisor->id,
                'office_feedback' => 'Excellent proposal.',
            ])
            ->assertHasNoTableActionErrors();

        $entry->refresh();
        $this->assertSame(EntryStatus::RECOGNISED, $entry->status);
        $this->assertSame('Winner', $entry->award);
        $this->assertSame($this->supervisor->id, $entry->mentor_id);
        $this->assertSame(1, $this->student->notifications()->count());
    }

    public function test_lecturers_review_but_cannot_record_office_decisions(): void
    {
        $entry = $this->makeEntry($this->makeChallenge(ChallengeStatus::REVIEWING));

        $this->actingAs($this->supervisor);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageChallengeEntries::class)
            ->assertCanSeeTableRecords([$entry])
            ->assertTableActionVisible('score', $entry)
            ->assertTableActionHidden('decide', $entry);

        $this->get('/supervisor/innovation-challenges')->assertForbidden();
    }

    public function test_research_director_runs_the_research_office(): void
    {
        $this->actingAs($this->officer);

        $this->get('/supervisor')->assertOk()->assertSee('Research Office');
        $this->get('/supervisor/innovation-challenges')->assertOk();
        $this->get('/supervisor/association-events')->assertOk();
        $this->get('/supervisor/research-sites')->assertOk();
        $this->get('/supervisor/research-domains')->assertOk();
    }

    public function test_deans_and_supervisors_do_not_run_the_research_office(): void
    {
        $dean = $this->makeUser('Dr. M. Phiri', UserRole::DEAN);

        foreach ([$dean, $this->supervisor] as $user) {
            $this->actingAs($user);
            $this->get('/supervisor/innovation-challenges')->assertForbidden();
            $this->get('/supervisor/research-sites')->assertForbidden();
        }
    }

    public function test_ordinary_students_cannot_open_the_office_workspace(): void
    {
        $this->actingAs($this->student)->get('/supervisor')->assertRedirect('/');
    }

    public function test_office_can_create_a_challenge_call_from_the_panel(): void
    {
        $this->actingAs($this->officer);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageInnovationChallenges::class)
            ->callAction('create', [
                'title' => 'Mulungushi University Vision 2030 Research and Innovation Challenge',
                'edition' => 'Semester 2, 2026/27',
                'description' => 'Second call of the year.',
                'status' => ChallengeStatus::OPEN->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('innovation_challenges', ['edition' => 'Semester 2, 2026/27', 'status' => 'open']);
    }

    public function test_debate_registration_records_side_and_respects_speaker_slots(): void
    {
        $debate = AssociationEvent::create([
            'title' => 'Inter-School Research Debate',
            'event_type' => EventType::DEBATE,
            'motion' => 'Is limited funding the main barrier to research uptake?',
            'description' => 'Debate.',
            'starts_at' => now()->addDays(3),
            'speaker_slots' => 1,
        ]);

        Livewire::actingAs($this->student)
            ->test(EventPage::class, ['event' => $debate])
            ->set('wants_to_speak', true)
            ->call('signUp')
            ->assertHasErrors('position')
            ->set('position', 'opposition')
            ->call('signUp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_registrations', [
            'association_event_id' => $debate->id,
            'user_id' => $this->student->id,
            'wants_to_speak' => true,
            'position' => 'opposition',
        ]);

        Livewire::actingAs($this->officer)
            ->test(EventPage::class, ['event' => $debate])
            ->set('wants_to_speak', true)
            ->set('position', 'proposition')
            ->call('signUp')
            ->assertHasErrors('registration');

        Livewire::actingAs($this->officer)
            ->test(EventPage::class, ['event' => $debate])
            ->call('signUp')
            ->assertHasNoErrors();

        $this->assertSame(2, $debate->attendees()->count());
    }

    public function test_full_events_refuse_new_registrations(): void
    {
        $workshop = AssociationEvent::create([
            'title' => 'Project ARIS Launch Workshop',
            'event_type' => EventType::WORKSHOP,
            'description' => 'Launch.',
            'starts_at' => now()->addDays(3),
            'capacity' => 1,
        ]);
        $workshop->attendees()->attach($this->officer->id);

        Livewire::actingAs($this->student)
            ->test(EventPage::class, ['event' => $workshop])
            ->call('signUp')
            ->assertHasErrors('registration');
    }

    public function test_office_records_event_outcomes(): void
    {
        $debate = AssociationEvent::create([
            'title' => 'Inter-School Research Debate',
            'event_type' => EventType::DEBATE,
            'description' => 'Debate.',
            'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($this->officer);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageAssociationEvents::class)
            ->callTableAction('outcomes', $debate, ['outcomes' => 'Extension services and market access matter as much as finance.'])
            ->assertHasNoTableActionErrors();

        $this->get(route('innovation.event', $debate))
            ->assertOk()
            ->assertSee('Extension services and market access matter as much as finance.');
    }

    public function test_students_log_site_records_and_export_them_as_csv(): void
    {
        $site = ResearchSite::create([
            'name' => 'Kabwe Borehole Monitoring Network',
            'focus' => 'Borehole water quality',
            'category' => ResearchCategory::NATURAL_SCIENCES,
            'period' => 'Semester 1, 2026/27',
            'status' => SiteStatus::ACTIVE,
            'started_on' => now()->subDays(20),
        ]);

        Livewire::actingAs($this->student)
            ->test(ResearchSitePage::class, ['site' => $site])
            ->set('activity', SiteActivity::MEASUREMENT->value)
            ->set('metric', 'Nitrate')
            ->set('value', '42.5')
            ->set('unit', 'mg/L')
            ->call('record')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_observations', [
            'research_site_id' => $site->id,
            'recorded_by' => $this->student->id,
            'metric' => 'Nitrate',
            'unit' => 'mg/L',
        ]);

        $csv = $this->actingAs($this->student)
            ->get(route('innovation.site.export', $site))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('study_day', $csv);
        $this->assertStringContainsString('"Mathematics & Natural Sciences"', $csv);
        $this->assertStringContainsString('Nitrate,42.50,mg/L', $csv);
        $this->assertStringContainsString('Kelvin Mwape', $csv);
    }

    public function test_site_records_need_a_note_or_measurement_and_closed_sites_reject_records(): void
    {
        $site = ResearchSite::create(['name' => 'Partner Schools Reading Study', 'focus' => 'Reading fluency', 'category' => ResearchCategory::EDUCATION, 'period' => 'Term 1, 2027', 'status' => SiteStatus::ACTIVE]);

        Livewire::actingAs($this->student)
            ->test(ResearchSitePage::class, ['site' => $site])
            ->set('activity', SiteActivity::SURVEY->value)
            ->call('record')
            ->assertHasErrors('notes');

        $site->update(['status' => SiteStatus::CLOSED]);

        Livewire::actingAs($this->student)
            ->test(ResearchSitePage::class, ['site' => $site->fresh()])
            ->set('activity', SiteActivity::ANALYSIS->value)
            ->set('notes', 'Late record')
            ->call('record')
            ->assertHasErrors('activity');

        $this->assertDatabaseCount('site_observations', 0);
    }

    public function test_guests_cannot_log_or_export_site_data(): void
    {
        $site = ResearchSite::create(['name' => 'Partner Schools Reading Study', 'focus' => 'Reading fluency', 'category' => ResearchCategory::EDUCATION, 'period' => 'Term 1, 2027', 'status' => SiteStatus::ACTIVE]);

        $this->get(route('innovation.site.export', $site))->assertRedirect(route('login'));

        Livewire::test(ResearchSitePage::class, ['site' => $site])
            ->set('activity', SiteActivity::SURVEY->value)
            ->set('notes', 'Interviewed head teacher')
            ->call('record')
            ->assertForbidden();
    }

    public function test_office_can_add_a_research_site_from_the_panel(): void
    {
        $this->actingAs($this->officer);
        Filament::setCurrentPanel(Filament::getPanel('supervisor'));

        Livewire::test(ManageResearchSites::class)
            ->callAction('create', [
                'name' => 'Rural Health Post Solar Monitoring',
                'category' => ResearchCategory::ENGINEERING->value,
                'focus' => 'Solar energy output',
                'period' => '2026/27',
                'status' => SiteStatus::SETUP->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('research_sites', ['name' => 'Rural Health Post Solar Monitoring', 'category' => 'engineering']);
    }
}
