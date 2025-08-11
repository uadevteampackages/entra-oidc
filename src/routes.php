<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web']], function () {

    Route::get('/auth/callback', function () {
        $oauth_user = \Laravel\Socialite\Facades\Socialite::driver('azure')->user();

        // Extract username from UPN
        $username = explode('@', $oauth_user->user['userPrincipalName'] ?? '')[0];
        if (!empty($username)) {
            $username = strtolower($username);
        } else {
            $username = null;
        }
        // Resolve the user model class from config so apps can customize it
        $modelClass = config('ms-graph-api.user_model', \Joeystowe\MsGraphApi\Models\OidcUser::class);

        $user = $modelClass::query()->updateOrCreate(
            ['id' => $oauth_user->getId()],
            [
                'name' => $oauth_user->getName(),
                'email' => $oauth_user->getEmail(),
                'principalName' => $oauth_user->user['userPrincipalName'] ?? null,
                'username' => $username,
                'token' => $oauth_user->token,
            ]
        );

        // Log in using the OIDC guard so the provider matches the model
        Auth::guard('oidc')->login($user);


        $redirectTo = session()->pull('sso_redirect_url', '/');
        return redirect($redirectTo);
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
