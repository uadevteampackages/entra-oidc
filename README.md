## Entra OIDC for Laravel

Lightweight Laravel package that adds Microsoft Entra ID (OIDC) authentication via Socialite, a cacheable controller-based callback/logout flow, an `oidc-auth` middleware, a simple `OidcUser` model and optional helpers for Microsoft Graph (delegated and application permissions).

### Requirements
- **PHP**: 8.1+
- **Laravel**: 10 or 11

### Installation
```bash
composer require ua-dev-team-packages/entra-oidc
```

### What you get
- **Middleware**: `oidc-auth` to protect routes and trigger the OIDC sign-in
- **Routes** (registered in the `web` group):
  - `GET /auth/callback` – OIDC callback (controller-based; route cache friendly)
  - `GET /logout` – logs out locally and from Microsoft, then redirects to post-logout
  - `GET /postLogout` – simple view confirming logout
- **Views**: loaded under the `entra-oidc` namespace
- **Migration**: `oidc_users` table (string PK `id`)
- **Model**: `UaDevTeamPackages\EntraOidc\Models\OidcUser` (customizable)
- **Trait**: `ChecksEntraGroup` to check Entra group membership
- **Graph App Client**: `MsGraphAppClient` for application-permissions flows (optional)

## Configure Azure app
Create an app registration in Entra and configure Redirect URI (web) to your callback.

Add to `.env`:
```env
AUTH_GUARD='oidc'
ENTRA_OIDC_CLIENT_ID=... 
ENTRA_OIDC_CLIENT_SECRET=...
ENTRA_OIDC_TENANT_ID=...
ENTRA_OIDC_REDIRECT_URI=${APP_URL}/auth/callback
```

## Publish assets
```bash
php artisan vendor:publish --tag=entra-oidc-all
```

Run migrations 
```bash
php artisan migrate
```

## Protecting routes with oidc-auth
Apply the middleware to any routes that require sign-in.
```php
use Illuminate\Support\Facades\Route;

Route::middleware('oidc-auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show']);
});
```
Do not apply `oidc-auth` globally to the `web` group; the callback must remain publicly accessible.

## Accessing the signed-in user
```php
use Illuminate\Support\Facades\Auth;

$user = Auth::user();
// \Models\OidcUser

$id = $user->id;
$email = $user->email;
$username = $user->username;
$upn = $user->principalName;
```

## Checking group membership
`OidcUser` includes the `ChecksEntraGroup` trait. It calls Graph using the delegated token from session (or application permissions in local proxy mode; see below).
```php
$isMember = Auth::user()->inGroup('00000000-0000-0000-0000-000000000000');
```

## Local development proxy (impersonation)
Enable a local-only proxy user to bypass the Entra login while you develop.

Add to `.env` (local only):
```env
ENTRA_OIDC_PROXY_ENABLED=true
ENTRA_OIDC_PROXY_PRINCIPAL=jane.doe@contoso.com
ENTRA_OIDC_PROXY_NAME="Jane Doe (Dev)"
# Optional overrides
# ENTRA_OIDC_PROXY_EMAIL=...
# ENTRA_OIDC_PROXY_ID=...

# Optional: Application-permissions Graph access in proxy mode
ENTRA_OIDC_APP_ENABLED=true
ENTRA_OIDC_APP_TENANT=${AZURE_TENANT_ID}
ENTRA_OIDC_APP_CLIENT_ID=...
ENTRA_OIDC_APP_CLIENT_SECRET=...
```

Behavior:
- Active only when the app is running in a local/testing environment
- Logs in a proxy user via the `oidc` guard
- If app credentials are configured, group checks can run against Graph without a delegated token

## Microsoft Graph: application permissions (optional)
Use `MsGraphAppClient` if you need app-only tokens (e.g., group checks for proxy users):
```php
use UaDevTeamPackages\EntraOidc\MsGraphAppClient;

$token = MsGraphAppClient::getAccessToken();
$userId = MsGraphAppClient::getUserIdByPrincipal('jane.doe@contoso.com');
$inGroup = MsGraphAppClient::isUserInGroup($userId, '00000000-0000-0000-0000-000000000000');
```

## Customizing the user model
Publish the stub and point the package config to your own model if needed:
```bash
php artisan vendor:publish --tag=entra-oidc-model
php artisan vendor:publish --tag=entra-oidc-config
```
```php
// config/entra-oidc.php
'user_model' => App\Models\OidcUser::class,
```

## Inertia.js compatibility
This package is fully compatible with Inertia.js applications. When a session expires or authentication is required, the middleware automatically detects Inertia requests (via the `X-Inertia` header) and returns a proper response that triggers a full-page redirect instead of attempting an XHR redirect. This prevents CORS errors that would otherwise occur when trying to redirect to Microsoft's OAuth endpoints.

No additional configuration is required - the middleware handles this automatically.

## Route caching
Routes are controller-based and cache-friendly. After configuring your app, you can safely run:
```bash
php artisan route:cache
```

## Testing
This repository includes tests using Pest and Orchestra Testbench.
```bash
composer install
vendor/bin/pest
# or
vendor/bin/phpunit
```

### Changelog
See `CHANGELOG.md` for recent changes.

### Credits
- **Joey Stowe** – `https://github.com/joeystowe`

### License
MIT. See `LICENSE.md`.
