<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApplicationAssignment
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasActiveAssignment()) {
            return response(
                'Access has not been granted to this application. Contact an administrator.',
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
