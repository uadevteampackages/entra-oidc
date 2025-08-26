<?php

namespace UaDevTeamPackages\EntraOidc\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProxyAuthManager
{
    public static function isLocalHost(): bool
    {
        return app()->environment(['local', 'testing'])
            && Str::contains((string) config('ms-graph-api.azure.redirect'), 'localhost');
    }

    public static function isEnabled(): bool
    {
        return (bool) config('ms-graph-api.proxy.enabled');
    }

    public static function isProxySession(): bool
    {
        return (bool) session('ms:is-proxy', false);
    }

    public static function getProxyPrincipal(): string
    {
        return (string) config('ms-graph-api.proxy.principal', '');
    }

    public static function canUseAppCredentials(): bool
    {
        return self::isLocalHost()
            && self::isEnabled()
            && \UaDevTeamPackages\EntraOidc\MsGraphAppClient::isEnabled()
            && (bool) config('ms-graph-api.client_credentials.enabled')
            && self::getProxyPrincipal() !== '';
    }

    public static function loginProxyUser(): void
    {
        $principal = (string) config('ms-graph-api.proxy.principal', '');
        if ($principal === '') {
            return;
        }

        $modelClass = config('ms-graph-api.user_model', \UaDevTeamPackages\EntraOidc\Models\OidcUser::class);
        $email     = (string) (config('ms-graph-api.proxy.email') ?? $principal);
        $name      = (string) (config('ms-graph-api.proxy.name') ?? 'Proxy User');
        $username  = strtolower(explode('@', $principal)[0] ?? $principal);
        $id        = (string) (config('ms-graph-api.proxy.id') ?? 'proxy-' . md5($principal));

        if (Auth::guard('oidc')->check() && !session()->has('ms:real-user-id')) {
            session()->put('ms:real-user-id', Auth::guard('oidc')->id());
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = $modelClass::query()->updateOrCreate(
            ['id' => $id],
            [
                'name' => $name,
                'email' => $email,
                'principalName' => $principal,
                'username' => $username,
            ]
        );

        Auth::guard('oidc')->login($user);
        session()->put('ms:is-proxy', true);
    }

    /**
     * If proxy session exists but proxy is disabled, restore original user or redirect to SSO.
     * Returns a Response on redirect, or null to continue.
     */
    public static function restoreIfProxyDisabled(): mixed
    {
        if (!self::isProxySession() || self::isEnabled()) {
            return null;
        }

        $originalUserId = session()->pull('ms:real-user-id');
        session()->forget('ms:is-proxy');

        if (!empty($originalUserId)) {
            $modelClass = config('ms-graph-api.user_model', \UaDevTeamPackages\EntraOidc\Models\OidcUser::class);
            $originalUser = $modelClass::query()->find($originalUserId);
            if ($originalUser) {
                Auth::guard('oidc')->login($originalUser);
                return null;
            }
        }

        Auth::guard('oidc')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->put('sso_redirect_url', url()->current());
        return \Laravel\Socialite\Facades\Socialite::driver('azure')->redirect();
    }
}
