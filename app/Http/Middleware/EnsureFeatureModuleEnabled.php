<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FeatureModuleService;
use Closure;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureModuleEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $controller = $request->route()?->getControllerClass();

        if ($user instanceof User && is_string($controller) && is_a($controller, ResourcePage::class, true)) {
            app(FeatureModuleService::class)->assertResource($controller::getResource(), $user);
        }

        return $next($request);
    }
}
