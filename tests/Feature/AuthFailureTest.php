<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

test('callback throws exception and results in 500', function () {
    Socialite::shouldReceive('driver->user')->once()->andThrow(new Exception('callback failed'));

    $this->get('/auth/callback')
        ->assertStatus(500)
        ->assertSee('Authentication Failed')
        ->assertSee('An unexpected error occurred in auth callback. Please try again, or contact support if the problem persists.');
});



test('expired token triggers redirect to Azure', function () {
    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    $this->withSession(['entra_user_token_expires' => now()->subMinute()]);

    Socialite::shouldReceive('driver->redirect')
        ->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));

    $this->get('/protected')
        ->assertRedirectContains('login.microsoftonline.com');
});
