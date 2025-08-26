<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

test('group check failure returns true', function () {
    $this->withSession(['entra_user_token' => 'abc123']);

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
    $this->withSession(['entra_user_token' => 'abc123']);

    Auth::guard('oidc')->login(new \UaDevTeamPackages\EntraOidc\Models\OidcUser([
        'id' => 'user-1',
        'name' => 'John Doe',
        'email' => 'jdoe@contoso.com',
        'principalName' => 'jdoe@contoso.com',
        'username' => 'jdoe',
    ]));

    Http::fake([
        'graph.microsoft.com/v1.0/me/checkMemberGroups' => Http::response([], 401),
    ]);

    /** @var \UaDevTeamPackages\EntraOidc\Models\OidcUser $user */
    $user = Auth::guard('oidc')->user();
    $this->assertFalse($user->inGroup('group-123'));
});
