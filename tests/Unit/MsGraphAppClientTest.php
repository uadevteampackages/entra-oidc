<?php

use Illuminate\Support\Facades\Http;

test('getAccessToken returns null when client credentials disabled', function () {
    config()->set('ms-graph-api.client_credentials.enabled', false);

    $token = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::getAccessToken();
    $this->assertNull($token);
});

test('getAccessToken returns null when token endpoint fails', function () {
    config()->set('ms-graph-api.client_credentials.enabled', true);
    config()->set('ms-graph-api.client_credentials.tenant', 'common');
    config()->set('ms-graph-api.client_credentials.client_id', 'id');
    config()->set('ms-graph-api.client_credentials.client_secret', 'secret');

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([], 400),
    ]);

    $token = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::getAccessToken();
    $this->assertNull($token);
});

test('getUserIdByPrincipal returns null when no app token available', function () {
    config()->set('ms-graph-api.client_credentials.enabled', false);
    $id = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::getUserIdByPrincipal('jdoe@contoso.com');
    $this->assertNull($id);
});

test('isUserInGroup returns false when no app token available', function () {
    config()->set('ms-graph-api.client_credentials.enabled', false);
    $inGroup = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::isUserInGroup('uid', 'gid');
    $this->assertFalse($inGroup);
});

test('getUserIdByPrincipal returns id on success', function () {
    config()->set('entra-oidc.client_credentials.enabled', true);
    config()->set('entra-oidc.client_credentials.tenant', 'common');
    config()->set('entra-oidc.client_credentials.client_id', 'id');
    config()->set('entra-oidc.client_credentials.client_secret', 'secret');

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*' => Http::response(['id' => 'user-1'], 200),
    ]);

    $id = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::getUserIdByPrincipal('jdoe@contoso.com');
    $this->assertSame('user-1', $id);
});

test('isUserInGroup returns true when Graph includes the group', function () {
    config()->set('entra-oidc.client_credentials.enabled', true);
    config()->set('entra-oidc.client_credentials.tenant', 'common');
    config()->set('entra-oidc.client_credentials.client_id', 'id');
    config()->set('entra-oidc.client_credentials.client_secret', 'secret');

    $groupId = 'group-123';

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/checkMemberGroups' => Http::response(['value' => [$groupId]], 200),
    ]);

    $inGroup = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::isUserInGroup('user-1', $groupId);
    $this->assertTrue($inGroup);
});
