<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI'),
        'tenant' => env('AZURE_TENANT_ID'),
    ],

    // The Eloquent model class used for OIDC-authenticated users. You can publish
    // a stub model and customize it in your application, then point this config
    // value to your own class, e.g. App\Models\OidcUser::class
    'user_model' => env('MS_GRAPH_USER_MODEL', UaDevTeamPackages\EntraOidc\Models\OidcUser::class),

    // Local development proxy user support
    'proxy' => [
        'enabled'   => env('MS_GRAPH_PROXY_ENABLED', false),
        'principal' => env('MS_GRAPH_PROXY_PRINCIPAL'),
        'email'     => env('MS_GRAPH_PROXY_EMAIL'),
        'name'      => env('MS_GRAPH_PROXY_NAME', 'Dev User'),
        'id'        => env('MS_GRAPH_PROXY_ID'),
        'token'     => env('MS_GRAPH_PROXY_TOKEN'),
    ],

    // Optional application-permissions client credentials for Graph access in dev/proxy mode
    'client_credentials' => [
        'enabled'       => env('MS_GRAPH_APP_ENABLED', false),
        'tenant'        => env('MS_GRAPH_APP_TENANT', env('AZURE_TENANT_ID')),
        'client_id'     => env('MS_GRAPH_APP_CLIENT_ID'),
        'client_secret' => env('MS_GRAPH_APP_CLIENT_SECRET'),
    ],
];
