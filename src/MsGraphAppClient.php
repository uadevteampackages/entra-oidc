<?php

namespace UaDevTeamPackages\EntraOidc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MsGraphAppClient
{
    public static function isEnabled(): bool
    {
        return (bool) config('entra-oidc.client_credentials.enabled')
            && (string) config('entra-oidc.client_credentials.client_id') !== ''
            && (string) config('entra-oidc.client_credentials.client_secret') !== ''
            && (string) config('entra-oidc.client_credentials.tenant') !== '';
    }

    public static function getAccessToken(): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $cacheKey = 'ms_graph_app_token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tenant = (string) config('entra-oidc.client_credentials.tenant');
        $clientId = (string) config('entra-oidc.client_credentials.client_id');
        $clientSecret = (string) config('entra-oidc.client_credentials.client_secret');

        $response = Http::asForm()
            ->timeout(5)
            ->retry(3, 100)
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ]);



        $accessToken = (string) ($response->json('access_token') ?? '');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        if ($accessToken === '') {
            return null;
        }

        // Cache slightly less than full expiry to be safe
        $ttlSeconds = max(300, $expiresIn - 60);
        Cache::put($cacheKey, $accessToken, $ttlSeconds);
        return $accessToken;
    }

    public static function getUserIdByPrincipal(string $principalName): ?string
    {
        $token = self::getAccessToken();
        if ($token === null) {
            return null;
        }

        $principalPath = rawurlencode($principalName);
        $response = Http::withToken($token)
            ->timeout(5)
            ->retry(3, 100)
            ->get("https://graph.microsoft.com/v1.0/users/{$principalPath}", [
                '$select' => 'id',
            ]);


        if ($response->failed()) {
            return null;
        }

        return (string) ($response->json('id') ?? '');
    }

    public static function isUserInGroup(string $userId, string $groupId): bool
    {
        $token = self::getAccessToken();
        if ($token === null) {
            return false;
        }

        $response = Http::withToken($token)
            ->timeout(5)
            ->retry(3, 100, throw: false)
            ->post("https://graph.microsoft.com/v1.0/users/{$userId}/checkMemberGroups", [
                'groupIds' => [$groupId],
            ]);

        if ($response->status() === 400) {
            return false;
        }

        if ($response->failed()) {
            logger()->warning('Graph API checkMemberGroups failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \Exception('Failed to check if user is in group');
        }

        $ids = (array) ($response->json('value') ?? []);
        return in_array($groupId, $ids, true);
    }
}
