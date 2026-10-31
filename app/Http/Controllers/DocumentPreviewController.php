<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPreviewController extends Controller
{
    /**
     * Stream an advisee manuscript inline for in-browser iframe preview.
     * Enforces signed URL verification, authentication, and supervisor isolation.
     */
    public function show(Request $request, Project $project): StreamedResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // 1. Authorization Guard (ProjectPolicy@viewFullContent): supervisors see only their advisees,
        // students only their own work, deans only their school; the director, admin and librarians see all.
        if (Gate::forUser($user)->denies('viewFullContent', $project)) {
            abort(403, 'Unauthorized access to restricted research manuscript.');
        }

        // 2. File Verification:
        if (! $project->full_document_path) {
            abort(404, 'No manuscript document associated with this project.');
        }

        $disk = Storage::disk('secure_research');

        if (! $disk->exists($project->full_document_path)) {
            abort(404, 'The requested manuscript could not be found on secure storage.');
        }

        // 3. MIME Type & Inline Disposition Resolution:
        $extension = strtolower(pathinfo($project->full_document_path, PATHINFO_EXTENSION));

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            default => $disk->mimeType($project->full_document_path) ?: 'application/octet-stream',
        };

        $filename = str($project->title)->slug()."-manuscript.{$extension}";

        return $disk->response(
            $project->full_document_path,
            $filename,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }
}
