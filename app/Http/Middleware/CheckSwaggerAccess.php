<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSwaggerAccess
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('Swagger middleware called', ['env' => app()->environment()]);
        
        // Only allow in staging environment
        if (app()->environment() !== 'staging') {
            Log::info('Blocking Swagger access - not in staging');
            abort(404);
        }

        return $next($request);
    }
} 