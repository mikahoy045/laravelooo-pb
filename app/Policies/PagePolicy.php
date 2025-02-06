<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Page;
use Illuminate\Auth\Access\HandlesAuthorization;

class PagePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true; // Public access
    }

    public function view(User $user, Page $page)
    {
        return true; // Public access
    }

    public function create(User $user)
    {
        return $user->isAdmin();
    }

    public function update(User $user, Page $page)
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Page $page)
    {
        return $user->isAdmin();
    }
} 