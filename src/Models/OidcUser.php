<?php

namespace UaDevTeamPackages\EntraOidc\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use UaDevTeamPackages\EntraOidc\Traits\ChecksEntraGroup;

/**
 * OIDC-authenticated user representation.
 *
 * This model is designed to be filled from the OIDC session payload and used
 * as a convenient typed container for user attributes.
 */
class OidcUser extends Authenticatable
{
    use ChecksEntraGroup;
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
        'principal_name',
        'username',
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
        'principal_name' => 'string',
        'username' => 'string',
    ];

    // /**
    //  * Build an instance from a plain array of attributes.
    //  */
    // public static function fromArray(array $attributes): self
    // {
    //     $instance = static::query()->find($attributes['id'] ?? null) ?? new self();
    //     $instance->unguard();
    //     $instance->fill($attributes);
    //     $instance->save();
    //     $instance->reguard();
    //     return $instance;
    // }

    // isInEntraGroup now provided by ChecksEntraGroup trait
}
