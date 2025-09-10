<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

test('guest is redirected to Azure', function () {
    Socialite::shouldReceive('driver->redirect')
        ->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));

    $this->get('/protected')
        ->assertRedirectContains('login.microsoftonline.com');
});



test('callback logs in and sets session', function () {
    $fakeUser = new class {
        public string $token = 'abc123';
        public int $expiresIn = 3600;
        public array $user = ['userPrincipalName' => 'jdoe@contoso.com'];
        public function getId()
        {
            return 'user-1';
        }
        public function getName()
        {
            return 'John Doe';
        }
        public function getEmail()
        {
            return 'jdoe@contoso.com';
        }
    };
    Socialite::shouldReceive('driver->user')->once()->andReturn($fakeUser);

    $this->withSession(['sso_redirect_url' => '/protected'])
        ->get('/auth/callback')
        ->assertRedirect('/protected');

    $this->assertTrue(Auth::guard('oidc')->check());
    $this->assertSame('abc123', session('entra_user_token'));
    $this->assertSame('jdoe@contoso.com', Auth::guard('oidc')->user()->email);
    $this->assertSame('jdoe@contoso.com', Auth::guard('oidc')->user()->principal_name);
    $this->assertSame('jdoe', Auth::guard('oidc')->user()->username);
    $this->assertSame('John Doe', Auth::guard('oidc')->user()->name);
    $this->assertNotNull(session('entra_user_token_expires'));
});

test('group check calls Graph', function () {
    $this->withSession(['entra_user_token' => 'abc123', 'entra_user_token_expires' => now()->addHours(1)]);
    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principal_name' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    $groupId = 'group-123';
    Http::fake([
        'graph.microsoft.com/v1.0/me/checkMemberGroups' =>
        Http::response(['value' => [$groupId]], 200),
    ]);

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $this->assertTrue($user->inGroup($groupId));
});
