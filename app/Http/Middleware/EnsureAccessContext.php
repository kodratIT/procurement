<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AccessContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccessContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && app(AccessContextService::class)->assignment() === null) {
            abort(Response::HTTP_FORBIDDEN, 'Your account has no valid active access context.');
        }

        return $next($request);
    }
}
