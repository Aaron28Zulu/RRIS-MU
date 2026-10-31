<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether any user (including unauthenticated guests) can view the project catalog.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether any user (including unauthenticated guests) can view project metadata.
     * Note: This only grants access to public metadata (title, abstract, category, gaps).
     */
    public function view(?User $user, Project $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can download the full research document or raw datasets.
     * Strict Rule:
     * - The administrator, the research director and repository librarians can download every document.
     * - A dean can download documents from their own school only.
     * - Assigned supervisor can download documents for their assigned projects.
     * - The student author can download their own documents.
     * - Ordinary students, peers, or unauthenticated guests are STRICTLY DENIED.
     */
    public function downloadFullContent(?User $user, Project $project): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasUniversityWideAccess() || $user->isLibrarian()) {
            return true;
        }

        if ($user->oversees($project->school_id)) {
            return true;
        }

        // Assigned academic supervisor
        if ($user->hasRole(UserRole::SUPERVISOR->value) && $project->supervisor_id === $user->id) {
            return true;
        }

        // Student owner of this project
        return $project->student_id === $user->id;
    }

    /**
     * Alias for downloadFullContent to support viewFullContent checks.
     */
    public function viewFullContent(?User $user, Project $project): bool
    {
        return $this->downloadFullContent($user, $project);
    }

    /**
     * Determine whether the user can create projects / submit proposals.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::STUDENT->value,
            UserRole::RESEARCHER->value,
        ]);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        if ($this->governs($user, $project)) {
            return true;
        }

        if ($user->hasRole(UserRole::SUPERVISOR->value) && $project->supervisor_id === $user->id) {
            return true;
        }

        // Student author can only edit while the study is still moving through supervision
        return $project->student_id === $user->id && $project->status->isActive();
    }

    /**
     * Determine whether the user can evaluate or grade the project.
     */
    public function evaluate(User $user, Project $project): bool
    {
        if ($this->governs($user, $project)) {
            return true;
        }

        return $user->hasRole(UserRole::SUPERVISOR->value) && $project->supervisor_id === $user->id;
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->governs($user, $project);
    }

    /**
     * Bulk removal from the register: the administrator, or a dean (whose register shows only their school).
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDean();
    }

    /**
     * Academic authority over a project: the administrator anywhere, a dean within their own school.
     * The research director oversees and reads but does not grade, edit or remove research.
     */
    protected function governs(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isDean() && $user->school_id !== null && $user->school_id === $project->school_id;
    }
}
