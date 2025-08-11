<?php

namespace Joeystowe\MsGraphApi\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Joeystowe\MsGraphApi\Models\OidcUser;

class MicrosoftAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        // If user is already authenticated, skip middleware
        if (Auth::guard('oidc')->check()) {
            return $next($request);
        }

        // Persist the intended URL so we can redirect after SSO completes
        session()->put('sso_redirect_url', url()->current());
        return \Laravel\Socialite\Facades\Socialite::driver('azure')->redirect();
    }
}
