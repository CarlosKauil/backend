<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlan
{
    public function handle(Request $request, Closure $next, string ...$plans): mixed
    {
        $user = $request->user();

        if (!$user || !in_array($user->plan, $plans)) {
            return response()->json([
                'error'   => 'Tu plan no permite esta acción.',
                'upgrade' => true,
            ], 403);
        }

        return $next($request);
    }
}