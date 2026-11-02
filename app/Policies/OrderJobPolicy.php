<?php

namespace App\Policies;

use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Listing jobs needs no policy check: the query is scoped with OrderJob::visibleTo()
 */
class OrderJobPolicy
{
    /**
     * statuses an installer may set on their own jobs
     * cutting, edge banding and assembly happen in the workshop and are recorded by planners
     */
    private const INSTALLER_STATUSES = [JobStatus::Installing, JobStatus::Installed];

    public function view(User $user, OrderJob $job): Response
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Planner) || $job->isAssignedTo($user)) {
            return Response::allow();
        }

        // 404 instead of 403, so installers can't tell which job ids exist
        return Response::denyAsNotFound('Not Found.');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Planner);
    }

    /**
     * whether the transition itself is allowed is checked by ChangeJobStatus
     */
    public function changeStatus(User $user, OrderJob $job, JobStatus $status): Response
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Planner)) {
            return Response::allow();
        }

        if (! $job->isAssignedTo($user)) {
            return Response::denyAsNotFound('Not Found.');
        }

        return in_array($status, self::INSTALLER_STATUSES, true)
            ? Response::allow()
            : Response::deny('Installers can only update the installation stages.');
    }
}
