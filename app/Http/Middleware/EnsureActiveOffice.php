<?php

namespace App\Http\Middleware;

use App\Services\ActiveOfficeContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOffice
{
    /**
     * Routes that must stay reachable without an active office. The switcher
     * page is the landing page where a user picks their office, so it can
     * never be blocked by this middleware.
     *
     * @var array<string>
     */
    public const EXEMPT_ROUTES = [
        'filament.admin.pages.office-switcher',
    ];

    public function handle(Request $request, Closure $next, string $redirectTo = ''): Response
    {
        if ($request->user() && ! app(ActiveOfficeContext::class)->current()) {
            // The switcher page itself must never redirect to itself.
            if (in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)) {
                return $next($request);
            }

            if ($redirectTo !== '') {
                return redirect()->route($redirectTo);
            }

            abort(403, 'Your account has no office assignment.');
        }

        return $next($request);
    }
}
