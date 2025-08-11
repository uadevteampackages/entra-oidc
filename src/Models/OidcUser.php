<?php

namespace Joeystowe\MsGraphApi\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * OIDC-authenticated user representation.
 *
 * This model is designed to be filled from the OIDC session payload and used
 * as a convenient typed container for user attributes. It does not assume a
 * backing database table and disables timestamps.
 */
class OidcUser extends Authenticatable
{
    /**
     * The database table used by the model.
     */
    protected $table = 'oidc_users';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The primary key associated with the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'principalName',
        'username',
        'token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'name' => 'string',
        'email' => 'string',
        'principalName' => 'string',
        'bannerUsername' => 'string',
        'token' => 'string',
    ];

    /**
     * Build an instance from a plain array of attributes.
     */
    public static function fromArray(array $attributes): self
    {
        $instance = static::query()->find($attributes['id'] ?? null) ?? new self();
        $instance->unguard();
        $instance->fill($attributes);
        $instance->save();
        $instance->reguard();
        return $instance;
    }

    /**
     * Check if the current user is a member (transitively) of the given Entra group.
     */
    public function isInEntraGroup(string $groupId): bool
    {
        $rawToken = (string)($this->token ?? '');
        if ($rawToken === '') {
            return false;
        }

        $authHeader = str_starts_with(strtolower($rawToken), 'bearer ')
            ? $rawToken
            : 'Bearer ' . $rawToken;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => $authHeader,
        ])->post('https://graph.microsoft.com/v1.0/me/checkMemberGroups', [
            'groupIds' => [$groupId],
        ]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::warning('Graph API checkMemberGroups failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return false;
        }

        $memberGroupIds = (array)($response->json('value') ?? []);
        return in_array($groupId, $memberGroupIds, true);
    }
}
