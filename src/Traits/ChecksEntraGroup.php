<?php

namespace UaDevTeamPackages\EntraOidc\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UaDevTeamPackages\EntraOidc\Support\ProxyAuthManager as Proxy;

trait ChecksEntraGroup
{
    /**
     * Check if the current authenticated user (delegated) is in the given Entra group.
     * When running locally with proxy enabled and app credentials configured,
     * this will use application permissions to perform the group check.
     */
    public function inGroup(string $groupId): bool
    {
        // Local proxy override using helper to gate behavior
        if (Proxy::isLocalHost() && Proxy::isEnabled()) {
            $configuredPrincipal = Proxy::getProxyPrincipal();

            if (Proxy::canUseAppCredentials()) {
                $userId = \UaDevTeamPackages\EntraOidc\MsGraphAppClient::getUserIdByPrincipal($configuredPrincipal);
                if (is_string($userId) && $userId !== '') {
                    return \UaDevTeamPackages\EntraOidc\MsGraphAppClient::isUserInGroup($userId, $groupId);
                }
            } else {
                throw new \Exception('In order to get a proxy user to be in a group, you must have the MS_GRAPH_APP_ENABLED environment variable set to true and the MS_GRAPH_APP_CLIENT_ID, and MS_GRAPH_APP_CLIENT_SECRET environment variables set.');
            }
        }

        // No proxy override, read the token from session
        $rawToken = (string)(session()->get('entra_user_token') ?? '');
        if ($rawToken === '') {
            return false;
        }

        $authHeader = str_starts_with(strtolower($rawToken), 'bearer ')
            ? $rawToken
            : 'Bearer ' . $rawToken;

        $response = Http::withHeaders([
            'Authorization' => $authHeader,
        ])->post('https://graph.microsoft.com/v1.0/me/checkMemberGroups', [
            'groupIds' => [$groupId],
        ]);

        if ($response->failed()) {
            Log::warning('Graph API checkMemberGroups failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return false;
        }

        $memberGroupIds = (array)($response->json('value') ?? []);
        return in_array($groupId, $memberGroupIds, true);
    }
}
