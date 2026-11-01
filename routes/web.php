<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ImpersonationController;
use App\Http\Controllers\DocumentPreviewController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\SiteObservationExportController;
use App\Http\Middleware\EnsureUserIsStudent;
use App\Livewire\Innovation\ChallengePage;
use App\Livewire\Innovation\EventPage;
use App\Livewire\Innovation\InnovationHub;
use App\Livewire\Innovation\ResearchSitePage;
use App\Livewire\PublicRepository;
use App\Livewire\ResearchFolder;
use App\Livewire\ResearchGapExplorer;
use App\Livewire\ResearchRecord;
use App\Livewire\Student\StudentDashboard;
use App\Livewire\Student\SubmissionHistory;
use App\Livewire\StudentSubmissionWizard;
use Illuminate\Support\Facades\Route;

// Public Landing Page & Research Repository
Route::get('/', HomeController::class)->name('home');
Route::get('/catalog', PublicRepository::class)->name('catalog');
Route::get('/repository', PublicRepository::class)->name('repository');
Route::get('/repository/{project}', ResearchRecord::class)->name('repository.show');
Route::get('/research-gaps', ResearchGapExplorer::class)->name('gaps');

// Office of Research and Innovation: research and innovation challenge, debates and workshops, research sites
Route::get('/innovation', InnovationHub::class)->name('innovation');
Route::get('/innovation/challenges/{challenge}', ChallengePage::class)->name('innovation.challenge');
Route::get('/innovation/events/{event}', EventPage::class)->name('innovation.event');
Route::get('/innovation/sites/{site}', ResearchSitePage::class)->name('innovation.site');
Route::get('/innovation/sites/{site}/export', SiteObservationExportController::class)
    ->name('innovation.site.export')
    ->middleware('auth');

// Saved records folder for any signed-in user
Route::get('/folder', ResearchFolder::class)->name('folder')->middleware('auth');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/login/quick/{email}', [AuthController::class, 'quickLogin'])->name('login.quick');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Return from signing in as another user (the administrator starts it from the Users screen)
Route::post('/impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave')->middleware('auth');

// Authenticated Student Researcher Portal
Route::middleware(['auth', EnsureUserIsStudent::class])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', StudentDashboard::class)->name('dashboard');
        Route::get('/proposals/create', StudentSubmissionWizard::class)->name('proposal.create');
        Route::get('/proposals/new', fn () => redirect()->route('student.proposal.create'));
        Route::get('/manuscripts', SubmissionHistory::class)->name('manuscripts');
    });

// Authenticated & Policy-Guarded Document Downloads
Route::middleware(['auth'])->group(function () {
    // Manuscript download (Enforces ProjectPolicy@downloadFullContent)
    Route::get('/projects/{project}/download', [ProjectDocumentController::class, 'downloadManuscript'])
        ->name('projects.download');

    // Specific manuscript version download
    Route::get('/projects/{project}/manuscripts/{manuscript}/download', [ProjectDocumentController::class, 'downloadManuscriptVersion'])
        ->name('projects.manuscripts.download');

    // Supervisor annotated markup download (Enforces student-owner & supervisor isolation)
    Route::get('/projects/{project}/download-markup', [ProjectDocumentController::class, 'downloadMarkup'])
        ->name('projects.download.marked-up');
});

// Authenticated & Signed In-Browser Manuscript Preview
Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/projects/{project}/preview', [DocumentPreviewController::class, 'show'])
        ->name('projects.document.preview');
});
