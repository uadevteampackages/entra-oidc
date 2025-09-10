<?php

namespace UaDevTeamPackages\EntraOidc\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseController
{
    public function callback(): \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
    {
        try {
            Log::debug('auth callback called');

            /** @var \SocialiteProviders\Manager\OAuth2\User $oauth_user */
            $oauth_user = \Laravel\Socialite\Facades\Socialite::driver('azure')->user();

            $username = explode('@', $oauth_user->user['userPrincipalName'] ?? '')[0];
            if (!empty($username)) {
                $username = strtolower($username);
            } else {
                $username = null;
            }

            $modelClass = config('entra-oidc.user_model', \UaDevTeamPackages\EntraOidc\Models\OidcUser::class);

            $user = $modelClass::query()->updateOrCreate(
                ['id' => $oauth_user->getId()],
                [
                    'name' => $oauth_user->getName(),
                    'email' => $oauth_user->getEmail(),
                    'principal_name' => $oauth_user->user['userPrincipalName'] ?? null,
                    'username' => $username,
                ]
            );

            session()->put('entra_user_token', $oauth_user->token);
            session()->put('entra_user_token_expires', now()->addSeconds($oauth_user->expiresIn));

            Auth::guard('oidc')->login($user);
            Log::info('user logged in', ['userId' => $user->id, 'upn' => $user->principal_name]);

            $redirectTo = session()->pull('sso_redirect_url', '/');
            Log::debug('redirecting to', ['redirectTo' => $redirectTo]);
            return redirect($redirectTo);
        } catch (\Exception $e) {
            Log::error('auth callback failed', ['error' => $e->getMessage()]);
            return response()->view('entra-oidc::error', [
                'title' => 'Authentication Failed',
                'message' => 'An unexpected error occurred in auth callback. Please try again, or contact support if the problem persists.',
            ], 500);
        }
    }

    public function logout(): \Illuminate\Http\RedirectResponse
    {
        Auth::guard('oidc')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $tenant = config('entra-oidc.azure.tenant') ?: 'common';
        $postLogoutRedirect = route('entra-oidc.postLogout');
        $azureLogoutUrl = 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/logout?post_logout_redirect_uri=' . urlencode($postLogoutRedirect);
        return redirect($azureLogoutUrl);
    }

    public function postLogout(): \Illuminate\Contracts\View\View
    {
        return view('entra-oidc::logout');
    }
}
