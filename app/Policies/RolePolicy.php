<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true; // Public access
    }

    public function view(User $user, Role $role)
    {
        return true; // Public access
    }

    public function create(User $user)
    {
        return $user->isAdmin();
    }

    public function update(User $user, Role $role)
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Role $role)
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Role $role): bool
    {
        return $user->is_admin;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $user->is_admin;
    }
} 