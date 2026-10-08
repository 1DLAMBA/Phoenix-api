<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() instanceof Admin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
