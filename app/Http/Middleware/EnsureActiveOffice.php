<?php

namespace App\Http\Middleware;

use App\Services\ActiveOfficeContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOffice
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! app(ActiveOfficeContext::class)->current()) {
            abort(403, 'Your account has no office assignment.');
        }

        return $next($request);
    }
}
