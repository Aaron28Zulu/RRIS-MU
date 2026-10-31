<?php

declare(strict_types=1);

namespace App\Livewire\Innovation;

use App\Enums\EntryStatus;
use App\Enums\EntryType;
use App\Enums\ResearchCategory;
use App\Enums\UserRole;
use App\Models\ChallengeEntry;
use App\Models\InnovationChallenge;
use App\Models\Project;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A Vision 2030 challenge call: the brief, the shortlist, and the entry form for students.
 */
#[Layout('layouts.app')]
class ChallengePage extends Component
{
    use WithFileUploads;

    public InnovationChallenge $challenge;

    public string $title = '';

    public string $entry_type = '';

    public string $category = '';

    public string $problem_statement = '';

    public string $proposed_solution = '';

    public string $expected_impact = '';

    public string $team_members = '';

    /** @var mixed */
    public $attachment = null;

    public function mount(InnovationChallenge $challenge): void
    {
        abort_unless($challenge->status->isPublished(), 404);

        $this->challenge = $challenge;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => [
                'required', 'string', 'min:10', 'max:255',
                Rule::unique('challenge_entries')->where(fn ($query) => $query
                    ->where('innovation_challenge_id', $this->challenge->id)
                    ->where('student_id', Auth::id())),
            ],
            'entry_type' => ['required', Rule::enum(EntryType::class)],
            'category' => ['required', Rule::enum(ResearchCategory::class)],
            'problem_statement' => ['required', 'string', 'min:50', 'max:3000'],
            'proposed_solution' => ['required', 'string', 'min:50', 'max:3000'],
            'expected_impact' => ['required', 'string', 'min:20', 'max:2000'],
            'team_members' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.unique' => 'You have already entered a project with this title.',
        ];
    }

    public function submit(): void
    {
        /** @var User|null $student */
        $student = Auth::user();

        abort_unless($student?->isStudent(), 403);

        if (! $this->challenge->isAcceptingEntries()) {
            $this->addError('title', 'This call is no longer accepting entries.');

            return;
        }

        $validated = $this->validate();

        $entry = ChallengeEntry::create([
            ...collect($validated)->except('attachment')->all(),
            'innovation_challenge_id' => $this->challenge->id,
            'student_id' => $student->id,
            'team_members' => $validated['team_members'] ?: null,
            'status' => EntryStatus::SUBMITTED,
            'attachment_path' => $this->attachment?->store('challenge_entries/'.$this->challenge->id, 'secure_research'),
        ]);

        $directors = User::role(UserRole::RESEARCH_DIRECTOR->value)->whereNull('deactivated_at')->get();

        if ($directors->isNotEmpty()) {
            Notification::make()
                ->title('New challenge entry')
                ->body("{$student->name} entered '{$entry->title}' into {$this->challenge->edition}.")
                ->icon('heroicon-o-trophy')
                ->sendToDatabase($directors);
        }

        $this->reset(['title', 'entry_type', 'category', 'problem_statement', 'proposed_solution', 'expected_impact', 'team_members', 'attachment']);

        session()->flash('success', 'Your entry was submitted. The review panel will be in touch after entries close.');
        $this->redirectRoute('innovation.challenge', $this->challenge);
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        return view('livewire.innovation.challenge-page', [
            'shortlist' => $this->challenge->entries()
                ->publiclyListed()
                ->with('student:id,name')
                ->orderByRaw('case when status = ? then 0 else 1 end', [EntryStatus::RECOGNISED->value])
                ->get(),
            'myEntries' => $user
                ? $this->challenge->entries()->where('student_id', $user->id)->with('mentor:id,name')->latest()->get()
                : collect(),
            'canEnter' => $user?->isStudent() && $this->challenge->isAcceptingEntries(),
            'entryTypes' => EntryType::cases(),
            'schools' => ResearchCategory::bySchool(),
            'similarRecords' => Project::catalogMatchesFor($this->title, 4),
        ])->title($this->challenge->title);
    }
}
