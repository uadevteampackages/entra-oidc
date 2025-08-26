<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web']], function () {

    Route::get('/auth/callback', function () {
        try {
            Log::debug('auth callback called');

            $oauth_user = \Laravel\Socialite\Facades\Socialite::driver('azure')->user();

            // Extract username from UPN
            $username = explode('@', $oauth_user->user['userPrincipalName'] ?? '')[0];
            if (!empty($username)) {
                $username = strtolower($username);
            } else {
                $username = null;
            }
            // Resolve the user model class from config so apps can customize it
            $modelClass = config('ms-graph-api.user_model', \UaDevTeamPackages\EntraOidc\Models\OidcUser::class);

            $user = $modelClass::query()->updateOrCreate(
                ['id' => $oauth_user->getId()],
                [
                    'name' => $oauth_user->getName(),
                    'email' => $oauth_user->getEmail(),
                    'principalName' => $oauth_user->user['userPrincipalName'] ?? null,
                    'username' => $username,
                ]
            );

            // Store delegated access token and expiry in server-side session
            session()->put('entra_user_token', $oauth_user->token);
            session()->put('entra_user_token_expires', now()->addSeconds($oauth_user->expiresIn));

            // Log in using the OIDC guard so the provider matches the model
            Auth::guard('oidc')->login($user);
            Log::info('user logged in', ['userId' => $user->id, 'upn' => $user->principalName]);

            $redirectTo = session()->pull('sso_redirect_url', '/');
            Log::debug('redirecting to', ['redirectTo' => $redirectTo]);
            return redirect($redirectTo);
        } catch (\Exception $e) {
            Log::error('auth callback failed', ['error' => $e->getMessage()]);
            return response()->view('ms-graph-api::error', [
                'title' => 'Authentication Failed',
                'message' => 'An unexpected error occurred in auth callback. Please try again, or contact support if the problem persists.',
            ], 500);
        }
    });


    Route::get('logout', function () {
        Auth::guard('oidc')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $tenant = config('ms-graph-api.azure.tenant') ?: 'common';
        $postLogoutRedirect = route('postLogout');
        $azureLogoutUrl = 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/logout?post_logout_redirect_uri=' . urlencode($postLogoutRedirect);
        return redirect($azureLogoutUrl);
    });

    Route::get('postLogout', function () {
        // Ideally you would build a styled logout page
        return view('ms-graph-api::logout');
    })->name('postLogout');
});
