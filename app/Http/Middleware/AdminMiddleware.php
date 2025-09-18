<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::user()->member()->access_level !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
        }
        return $next($request);
    }
}
