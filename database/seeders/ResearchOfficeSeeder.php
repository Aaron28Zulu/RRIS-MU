<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChallengeStatus;
use App\Enums\EntryStatus;
use App\Enums\EntryType;
use App\Enums\EventType;
use App\Enums\ResearchCategory;
use App\Enums\SiteActivity;
use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Models\AssociationEvent;
use App\Models\InnovationChallenge;
use App\Models\Project;
use App\Models\ResearchSite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds the Office of Research and Innovation's semester work plan: the RRIS launch workshop,
 * the inter-school debate, the Research and Innovation Challenge and research sites run by several schools.
 */
class ResearchOfficeSeeder extends Seeder
{
    public function run(): void
    {
        // Safe to run on its own, before or after RoleAndUserSeeder.
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        // The research director leads the Research Office and coordinates its programmes.
        $director = User::where('email', 'director@rris.mu.ac.zm')->first();

        if ($director === null) {
            $director = User::create([
                'name' => 'Prof. E. Banda',
                'email' => 'director@rris.mu.ac.zm',
                'password' => Hash::make('password'),
                'department' => 'Directorate of Research and Graduate Studies',
                'email_verified_at' => now(),
            ]);
            $director->assignAccountRoles(UserRole::RESEARCH_DIRECTOR);
        }

        $peter = User::where('email', 'peter@rris.mu.ac.zm')->first();

        $lukundo = User::where('email', 'lukundo@rris.mu.ac.zm')->first();
        $kelvin = User::where('email', 'kelvin@rris.mu.ac.zm')->first();
        $supervisor = User::where('email', 'cmulenga@rris.mu.ac.zm')->first();

        AssociationEvent::updateOrCreate(['title' => 'RRIS Launch Workshop'], [
            'event_type' => EventType::WORKSHOP,
            'motion' => 'Preserving and sharing student research',
            'description' => "Official launch of the Research Repository Interface System (RRIS), the university's platform for uploading, storing and finding student research and innovations from every school.\n\nThe workshop demonstrates how the platform works, explains how it helps identify research gaps and avoid duplicated topics, and opens a discussion with students and lecturers on preserving the university's research.",
            'venue' => 'Main Campus Lecture Theatre 1',
            'starts_at' => now()->addDays(10)->setTime(14, 0),
            'capacity' => 150,
            'speaker_slots' => 4,
            'coordinator_id' => $director->id,
        ]);

        $debate = AssociationEvent::updateOrCreate(['title' => 'Inter-School Research Debate'], [
            'event_type' => EventType::DEBATE,
            'motion' => 'Is limited funding the main barrier to research uptake in Zambia?',
            'description' => "An evidence-based debate between teams from the university's schools. Students examine why research findings in agriculture, health, education and business often fail to change practice, from funding and policy to communication and data access, and propose practical, research-based solutions.",
            'venue' => 'Main Hall',
            'starts_at' => now()->addDays(24)->setTime(15, 30),
            'capacity' => 200,
            'speaker_slots' => 8,
            'coordinator_id' => $director->id,
        ]);

        if ($lukundo && $kelvin) {
            $debate->attendees()->syncWithoutDetaching([
                $lukundo->id => ['wants_to_speak' => true, 'position' => 'proposition'],
                $kelvin->id => ['wants_to_speak' => false, 'position' => null],
            ]);
        }

        $challenge = InnovationChallenge::updateOrCreate(['edition' => 'Semester 1, 2026/27'], [
            'title' => 'Mulungushi University Research and Innovation Challenge',
            'theme' => 'Research, Innovation and Knowledge Sharing for National Development',
            'description' => "The Office of Research and Innovation invites research proposals and innovative project ideas from every school that contribute to national development.\n\nA review panel evaluates every submission. Shortlisted projects present to the panel, outstanding proposals are recognised, and selected projects receive support and guidance from the office and are published in collaboration with the university.",
            'criteria' => 'Open to all registered students of Mulungushi University, individually or in teams of up to four.',
            'status' => ChallengeStatus::OPEN,
            'opens_on' => now()->subWeek()->toDateString(),
            'closes_on' => now()->addWeeks(3)->toDateString(),
            'presentation_on' => now()->addWeeks(5)->toDateString(),
            'coordinator_id' => $director->id,
        ]);

        $mutinta = User::where('email', 'mutinta@rris.mu.ac.zm')->first();

        if ($lukundo && $kelvin) {
            $challenge->entries()->updateOrCreate(['student_id' => $lukundo->id, 'title' => 'Low-cost soil moisture sensors for smallholder irrigation scheduling'], [
                'entry_type' => EntryType::INNOVATION,
                'category' => ResearchCategory::AGRICULTURE,
                'problem_statement' => 'Smallholder vegetable growers irrigate by guesswork, wasting scarce water in the dry season and losing yield when crops are stressed between irrigations.',
                'proposed_solution' => 'Build and field-test an Arduino-based soil moisture probe costing under K300 that signals when to irrigate, trialled at the university\'s research sites.',
                'expected_impact' => 'Reduced water use and more stable vegetable yields for smallholders, with a design students can reproduce for local farmers.',
                'team_members' => 'Peter Mwaba',
                'status' => EntryStatus::SHORTLISTED,
                'mentor_id' => $supervisor?->id,
            ]);

            $challenge->entries()->updateOrCreate(['student_id' => $kelvin->id, 'title' => 'Offline lecture notes on campus Wi-Fi hotspots'], [
                'entry_type' => EntryType::INNOVATION,
                'category' => ResearchCategory::COMPUTING,
                'problem_statement' => 'Many students cannot afford mobile data to download lecture notes and recorded lectures, and fall behind when materials are shared only online.',
                'proposed_solution' => 'A low-cost local server on campus Wi-Fi hotspots that mirrors course materials so students can download them without using mobile data, piloted in two schools.',
                'expected_impact' => 'Fairer access to learning materials and lower data costs for students, using equipment the university already owns.',
                'status' => EntryStatus::SUBMITTED,
            ]);
        }

        if ($mutinta) {
            $challenge->entries()->updateOrCreate(['student_id' => $mutinta->id, 'title' => 'Community health worker reminders for early antenatal booking'], [
                'entry_type' => EntryType::RESEARCH_PROPOSAL,
                'category' => ResearchCategory::HEALTH_SCIENCES,
                'problem_statement' => 'Most pregnant women in Kabwe book their first antenatal visit after 16 weeks, missing early screening and supplements.',
                'proposed_solution' => 'A cluster trial in four clinic catchment areas in which community health workers visit and remind newly pregnant women to book early, compared with routine care.',
                'expected_impact' => 'Evidence on a low-cost way to raise early antenatal booking that the district health office could scale.',
                'status' => EntryStatus::SUBMITTED,
            ]);
        }

        $nkabwe = User::where('email', 'nkabwe@rris.mu.ac.zm')->first();
        $rmusonda = User::where('email', 'rmusonda@rris.mu.ac.zm')->first();
        $chipo = User::where('email', 'chipo@rris.mu.ac.zm')->first();
        $mwila = User::where('email', 'mwila@rris.mu.ac.zm')->first();

        $fieldTrial = ResearchSite::updateOrCreate(['name' => 'University Farm · Block A'], [
            'focus' => 'Conservation agriculture maize trial',
            'category' => ResearchCategory::AGRICULTURE,
            'method' => 'Minimum tillage vs. ridging, 3 replicates',
            'location' => 'University farm, Block A',
            'period' => '2026/27 rainy season',
            'status' => SiteStatus::ACTIVE,
            'started_on' => now()->subDays(35)->toDateString(),
            'ends_on' => now()->addDays(85)->toDateString(),
            'objectives' => "Practise conservation agriculture (minimum tillage, mulching) and record growth and pest pressure through the season.\nData supports comparisons with the archive's conservation agriculture studies.",
            'coordinator_id' => $supervisor?->id ?? $director->id,
            'project_id' => Project::where('title', 'Soil Carbon Stocks Under Agroforestry Versus Continuous Maize Cultivation')->value('id'),
        ]);

        $water = ResearchSite::updateOrCreate(['name' => 'Kabwe Borehole Monitoring Network'], [
            'focus' => 'Borehole water quality in peri-urban Kabwe',
            'category' => ResearchCategory::NATURAL_SCIENCES,
            'method' => 'Monthly sampling at 12 boreholes',
            'location' => 'Makululu, Katondo and Bwacha',
            'period' => 'Semester 1, 2026/27',
            'status' => SiteStatus::ACTIVE,
            'started_on' => now()->subDays(60)->toDateString(),
            'ends_on' => now()->addDays(120)->toDateString(),
            'objectives' => 'Extend the archived borehole study with a full year of monthly data on faecal coliforms, nitrate and pH, so seasonal patterns can be compared.',
            'coordinator_id' => $rmusonda?->id ?? $director->id,
        ]);

        $classrooms = ResearchSite::updateOrCreate(['name' => 'Partner Schools Reading Study'], [
            'focus' => 'Early grade reading fluency',
            'category' => ResearchCategory::EDUCATION,
            'method' => 'Termly oral reading fluency tests, 4 schools',
            'location' => 'Four primary schools in Kabwe',
            'period' => 'Term 1, 2027',
            'status' => SiteStatus::SETUP,
            'started_on' => now()->addDays(14)->toDateString(),
            'objectives' => 'Collect comparable reading scores from Grade 2 learners for education students\' research on language of instruction.',
            'coordinator_id' => $nkabwe?->id ?? $director->id,
            'project_id' => Project::where('title', 'Mother Tongue Instruction and Early Grade Reading Outcomes')->value('id'),
        ]);

        ResearchSite::updateOrCreate(['name' => 'Rural Health Post Solar Monitoring'], [
            'focus' => 'Solar energy output and battery health',
            'category' => ResearchCategory::ENGINEERING,
            'method' => 'Data loggers at 6 health posts',
            'location' => 'Kapiri Mposhi and Chibombo districts',
            'period' => '2025/26',
            'status' => SiteStatus::CLOSED,
            'started_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subDays(20)->toDateString(),
            'objectives' => 'Year-long monitoring of off-grid solar systems to support the engineering department\'s energy access research.',
            'coordinator_id' => $rmusonda?->id ?? $director->id,
        ]);

        $recorders = array_values(array_filter([$lukundo, $kelvin, $peter, $director]));
        $sciencesRecorders = array_values(array_filter([$mwila, $peter, $director]));

        if ($fieldTrial->observations()->doesntExist()) {
            $fieldLog = [
                [35, SiteActivity::SETUP, null, null, null, 'Planted at 75 cm x 25 cm, one seed per station, with basal D compound.'],
                [28, SiteActivity::MEASUREMENT, 'Emergence', 92, '%', 'Good emergence across the plot.'],
                [21, SiteActivity::MAINTENANCE, null, null, null, 'First hand weeding; mulch topped up.'],
                [14, SiteActivity::MEASUREMENT, 'Plant height', 38, 'cm', null],
                [10, SiteActivity::SAMPLING, 'Fall armyworm incidence', 8, '%', 'Window-pane damage on outer rows; below action threshold.'],
                [7, SiteActivity::INTERVENTION, 'Urea top dressing', 100, 'kg/ha', 'Applied after 22 mm of rain.'],
                [2, SiteActivity::MEASUREMENT, 'Plant height', 71, 'cm', null],
            ];

            foreach ($fieldLog as $i => [$daysAgo, $activity, $metric, $value, $unit, $notes]) {
                $fieldTrial->observations()->create([
                    'recorded_by' => $recorders[$i % count($recorders)]->id,
                    'observed_on' => now()->subDays($daysAgo)->toDateString(),
                    'activity' => $activity,
                    'metric' => $metric,
                    'value' => $value,
                    'unit' => $unit,
                    'notes' => $notes,
                ]);
            }
        }

        if ($water->observations()->doesntExist() && $sciencesRecorders !== []) {
            $waterLog = [
                [58, SiteActivity::SETUP, null, null, null, 'Twelve boreholes mapped with GPS; owners briefed and consent recorded.'],
                [45, SiteActivity::SAMPLING, 'Faecal coliforms (Makululu mean)', 6, 'CFU/100 mL', 'Dry season round; two boreholes above the limit.'],
                [45, SiteActivity::MEASUREMENT, 'Nitrate (network mean)', 18.4, 'mg/L', null],
                [16, SiteActivity::SAMPLING, 'Faecal coliforms (Makululu mean)', 22, 'CFU/100 mL', 'First rains; latrines within 30 m of three boreholes.'],
                [15, SiteActivity::ANALYSIS, null, null, null, 'Results shared with the municipal water office.'],
            ];

            foreach ($waterLog as $i => [$daysAgo, $activity, $metric, $value, $unit, $notes]) {
                $water->observations()->create([
                    'recorded_by' => $sciencesRecorders[$i % count($sciencesRecorders)]->id,
                    'observed_on' => now()->subDays($daysAgo)->toDateString(),
                    'activity' => $activity,
                    'metric' => $metric,
                    'value' => $value,
                    'unit' => $unit,
                    'notes' => $notes,
                ]);
            }
        }

        if ($classrooms->observations()->doesntExist() && $chipo) {
            $classrooms->observations()->create([
                'recorded_by' => $chipo->id,
                'observed_on' => now()->subDays(3)->toDateString(),
                'activity' => SiteActivity::SETUP,
                'notes' => 'Head teachers at all four schools agreed to take part; parental consent forms sent home.',
            ]);
        }
    }
}
