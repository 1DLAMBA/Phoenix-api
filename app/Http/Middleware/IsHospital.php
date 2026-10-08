<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use Closure;
use Illuminate\Http\Request;

class IsHospital
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() instanceof Hospital) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
