<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Log::info('RoleMiddleware executed with roles: ' . implode(',', $roles));

        $user = auth()->user();

        if (!$user || !$user->member || !in_array($user->member->access_level, $roles)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}

