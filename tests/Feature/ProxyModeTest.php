<?php

use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

test('proxy enabled logs in proxy user and marks proxy session', function () {
    // Simulate local env and localhost redirect for proxy feature
    config()->set('entra-oidc.azure.redirect', 'http://localhost/auth/callback');

    // Enable proxy and configure principal
    config()->set('entra-oidc.proxy.enabled', true);
    config()->set('entra-oidc.proxy.principal', 'devuser@contoso.com');
    config()->set('entra-oidc.proxy.name', 'Dev User');
    config()->set('entra-oidc.proxy.email', 'devuser@contoso.com');

    // Hit a protected route (middleware should short-circuit to proxy)
    $this->get('/protected')
        ->assertOk()
        ->assertSee('ok');

    $this->assertTrue(Auth::guard('oidc')->check());
    $user = Auth::guard('oidc')->user();
    $this->assertSame('devuser@contoso.com', $user->principal_name);
    $this->assertSame('devuser', $user->username);
    $this->assertTrue((bool) session('ms:is-proxy'));
});

test('proxy disabled restores original user when recorded', function () {
    // Arrange original user and mark proxy session
    $original = new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'Original User',
        'email' => 'orig@contoso.com',
        'principal_name' => 'orig@contoso.com',
        'username' => 'orig',
    ]);
    $original->save();
    Auth::guard('oidc')->login($original);
    session()->put('ms:real-user-id', 'user-1');
    session()->put('ms:is-proxy', true);

    // Simulate local env for middleware to consider proxy logic block
    putenv('APP_ENV=local');
    config()->set('app.env', 'local');
    config()->set('entra-oidc.azure.redirect', 'http://localhost/auth/callback');

    // Disable proxy flag
    config()->set('entra-oidc.proxy.enabled', false);

    $this->get('/protected')
        ->assertOk()
        ->assertSee('ok');

    $this->assertTrue(Auth::guard('oidc')->check());
    $this->assertSame('user-1', Auth::guard('oidc')->id());
    $this->assertFalse((bool) session('ms:is-proxy'));
});

test('proxy disabled without original user redirects to Azure SSO', function () {
    // Mark as proxy session but without original user id
    session()->put('ms:is-proxy', true);

    // Local env and localhost redirect
    putenv('APP_ENV=local');
    config()->set('app.env', 'local');
    config()->set('entra-oidc.azure.redirect', 'http://localhost/auth/callback');

    // Disable proxy
    config()->set('entra-oidc.proxy.enabled', false);

    // Fake Socialite redirect to avoid provider internals
    Socialite::shouldReceive('driver->redirect')
        ->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));

    $this->get('/protected')
        ->assertRedirectContains('login.microsoftonline.com');
});

test('proxy disabled in with non-local redirect', function () {
    config()->set('entra-oidc.proxy.enabled', true);
    config()->set('entra-oidc.proxy.principal', 'devuser@contoso.com');
    config()->set('entra-oidc.proxy.name', 'Dev User');
    config()->set('entra-oidc.proxy.email', 'devuser@contoso.com');

    Socialite::shouldReceive('driver->redirect')
        ->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));

    $this->get('/protected')
        ->assertRedirectContains('login.microsoftonline.com');
});
