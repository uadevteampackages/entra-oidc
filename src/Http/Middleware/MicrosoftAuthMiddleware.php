<?php

namespace UaDevTeamPackages\EntraOidc\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use UaDevTeamPackages\EntraOidc\Support\ProxyAuthManager as Proxy;

class MicrosoftAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        // Local-only proxy handling and restore
        if (Proxy::isLocalHost()) {
            Log::debug('EntraOidc: Local host detected');
            if (Proxy::isEnabled()) {
                Log::debug('EntraOidc: Proxy enabled');
                Proxy::loginProxyUser();
                return $next($request);
            }

            if ($response = Proxy::restoreIfProxyDisabled()) {
                return $response;
            }
        }


        // If user is already authenticated, skip middleware
        if (Auth::guard('oidc')->check()) {
            //check if the token is expired
            if (session()->get('entra_user_token_expires') && session()->get('entra_user_token_expires') < now()) {
                // Persist the intended URL so we can redirect after SSO completes
                session()->put('sso_redirect_url', url()->current());
                return \Laravel\Socialite\Facades\Socialite::driver('azure')->redirect();
            }
            return $next($request);
        }

        // Persist the intended URL so we can redirect after SSO completes
        session()->put('sso_redirect_url', url()->current());
        return \Laravel\Socialite\Facades\Socialite::driver('azure')->redirect();
    }
}
