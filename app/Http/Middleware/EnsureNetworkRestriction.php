<?php

namespace App\Http\Middleware;

use App\Services\NetworkRestrictionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNetworkRestriction
{
    public function __construct(
        protected NetworkRestrictionService $networkService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Admin accounts on admin routes or web endpoints are not locked out of management
        $user = $request->user('api') ?: $request->user();

        if ($user && $user->role === 'admin' && ($request->is('admin/*') || $request->is('api/admin/*'))) {
            return $next($request);
        }

        // Exempt public, logout, or bootstrap endpoints
        if ($request->is('api/login') || $request->is('api/logout') || $request->is('api/network*')) {
            return $next($request);
        }

        // Validate network access - throws 403 HttpResponseException on denial
        $this->networkService->validateOrThrow($request);

        return $next($request);
    }
}
