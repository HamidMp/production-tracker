<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Listing jobs needs no policy check: the query is scoped with OrderJob::visibleTo()
 */
class OrderJobPolicy
{
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
}
