<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Admins and planners have the same permissions here.
 * Installers work with their jobs only (see OrderJobPolicy) and have no access to orders.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Planner);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Planner);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Planner);
    }
}
