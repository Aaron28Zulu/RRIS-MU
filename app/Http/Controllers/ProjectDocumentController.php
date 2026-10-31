<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectManuscript;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    /**
     * Download the project's primary manuscript or full report.
     * Enforces strict ProjectPolicy@downloadFullContent authorization:
     * Only the student author, assigned supervisor, or academic administrators can access.
     * Ordinary students and peers are strictly denied (HTTP 403).
     *
     * @throws AuthorizationException
     */
    public function downloadManuscript(Request $request, Project $project): StreamedResponse
    {
        if (Gate::denies('downloadFullContent', $project)) {
            abort(403, 'Unauthorized access to restricted research manuscript.');
        }

        if (! $project->full_document_path) {
            abort(404, 'No document file recorded for this project.');
        }

        // Check private secure storage first, then fallback to local
        $disk = Storage::disk('secure_research')->exists($project->full_document_path)
            ? 'secure_research'
            : 'local';

        if (! Storage::disk($disk)->exists($project->full_document_path)) {
            abort(404, 'The requested document file could not be located on secure storage.');
        }

        $extension = pathinfo($project->full_document_path, PATHINFO_EXTENSION);
        $filename = str($project->title)->slug()."-manuscript.{$extension}";

        return Storage::disk($disk)->download($project->full_document_path, $filename);
    }

    /**
     * Download the supervisor's annotated markup draft.
     * Strictly verifies the authenticated user is the student owner or assigned supervisor.
     *
     * @throws AuthorizationException
     */
    public function downloadMarkup(Request $request, Project $project): StreamedResponse
    {
        $user = $request->user();

        // Strict ownership & supervisor guard
        $isStudentOwner = $user && $project->student_id === $user->id;
        $isAssignedSupervisor = $user && $project->supervisor_id === $user->id;
        // Overseers follow the manuscript rules: deans only for their own school.
        $isOverseer = $user && $user->hasOversight() && Gate::forUser($user)->allows('downloadFullContent', $project);

        if (! ($isStudentOwner || $isAssignedSupervisor || $isOverseer)) {
            abort(403, 'Unauthorized to view supervisor evaluation markup.');
        }

        $evaluation = $project->evaluations()
            ->whereNotNull('document_markup_path')
            ->latest()
            ->first();

        if (! $evaluation || ! $evaluation->document_markup_path) {
            abort(404, 'No supervisor markup document available for this project.');
        }

        $disk = Storage::disk('secure_research')->exists($evaluation->document_markup_path)
            ? 'secure_research'
            : 'local';

        if (! Storage::disk($disk)->exists($evaluation->document_markup_path)) {
            abort(404, 'The marked-up document was not found on secure storage.');
        }

        $extension = pathinfo($evaluation->document_markup_path, PATHINFO_EXTENSION);
        $filename = str($project->title)->slug()."-supervisor-markup.{$extension}";

        return Storage::disk($disk)->download($evaluation->document_markup_path, $filename);
    }

    /**
     * Download a specific version of a project manuscript.
     * Enforces strict ProjectPolicy@downloadFullContent authorization.
     *
     * @throws AuthorizationException
     */
    public function downloadManuscriptVersion(Request $request, Project $project, ProjectManuscript $manuscript): StreamedResponse
    {
        if ($manuscript->project_id !== $project->id) {
            abort(404, 'Manuscript version does not belong to the specified project.');
        }

        if (Gate::denies('downloadFullContent', $project)) {
            abort(403, 'Unauthorized access to restricted research manuscript version.');
        }

        if (! $manuscript->file_path) {
            abort(404, 'No document file recorded for this manuscript version.');
        }

        $disk = Storage::disk('secure_research')->exists($manuscript->file_path)
            ? 'secure_research'
            : 'local';

        if (! Storage::disk($disk)->exists($manuscript->file_path)) {
            abort(404, 'The requested document file could not be located on secure storage.');
        }

        $extension = pathinfo($manuscript->file_path, PATHINFO_EXTENSION);
        $filename = str($project->title)->slug()."-{$manuscript->version}.{$extension}";

        return Storage::disk($disk)->download($manuscript->file_path, $filename);
    }

    /**
     * Stream the project manuscript inline for in-browser iframe preview.
     * Enforces valid temporary signed URL or ProjectPolicy@viewFullContent authorization.
     *
     * @throws AuthorizationException
     */
    public function previewManuscript(Request $request, Project $project): StreamedResponse
    {
        $hasValidSignature = $request->hasValidSignature();
        $isAuthorizedUser = $request->user() && Gate::allows('viewFullContent', $project);

        if (! $hasValidSignature && ! $isAuthorizedUser) {
            abort(403, 'Unauthorized access to restricted research manuscript preview.');
        }

        if (! $project->full_document_path) {
            abort(404, 'No document file recorded for this project.');
        }

        $disk = Storage::disk('secure_research')->exists($project->full_document_path)
            ? 'secure_research'
            : 'local';

        if (! Storage::disk($disk)->exists($project->full_document_path)) {
            abort(404, 'The requested document file could not be located on secure storage.');
        }

        return Storage::disk($disk)->response($project->full_document_path);
    }
}
