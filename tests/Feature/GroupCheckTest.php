<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

test('group check failure returns true', function () {
    $this->withSession(['entra_user_token' => 'abc123', 'entra_user_token_expires' => now()->addHours(1)]);

    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    Http::fake([
        'graph.microsoft.com/v1.0/me/checkMemberGroups' => Http::response([
            'value' => ['group-123'],
        ], 200),
    ]);

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $this->assertTrue($user->inGroup('group-123'));
});

test('group check failure returns false', function () {
    $this->withSession(['entra_user_token' => 'abc123', 'entra_user_token_expires' => now()->addHours(1)]);

    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    Http::fake([
        'graph.microsoft.com/v1.0/me/checkMemberGroups' => Http::response([
            'value' => [],
        ], 200),
    ]);

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $this->assertFalse($user->inGroup('group-123'));
});

test('group check failure throws exception', function () {
    $this->withSession(['entra_user_token' => 'abc123', 'entra_user_token_expires' => now()->addHours(1)]);

    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    Http::fake([
        'graph.microsoft.com/v1.0/me/checkMemberGroups' => Http::response([], 500),
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Graph API checkMemberGroups failed. Please try again.');

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $user->inGroup('group-123');
});

test('group check failure throws exception when token is expired', function () {
    $this->withSession(['entra_user_token' => 'abc123', 'entra_user_token_expires' => now()->subHours(1)]);

    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The user token has expired. Please login again.');

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $user->inGroup('group-123');
});

test('group check failure throws exception when token is not set', function () {
    $this->withSession(['entra_user_token_expires' => now()->addHours(1)]);

    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The user token is not set. Please login again.');

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $user->inGroup('group-123');
});
