<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return null; // This will trigger our JSON response instead of redirect
        }

        return route('login');
    }

    protected function unauthenticated($request, array $guards)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401));
        }

        parent::unauthenticated($request, $guards);
    }
}
