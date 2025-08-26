# MS GRAPH API PLUGIN

This adds helper methods to call the MS Graph API and installs a middleware/routes to implement MS Authentication

## Installation

You can install the package via composer:

```bash
composer require joeystowe/ms-graph-api:^1.0
```

## Usage
### SSO Authentication
---
The plugin installs a middleware ('ms-auth') and 2 routes (/auth/callback and /logout). To protect a route with authentication you must apply the middleware to the desired routes and set your env variables

#### Apply middleware example
```php
Route::get('/', function () {
    return view('welcome');
})->middleware('ms-auth');
```
Or use middleware groups
```php
Route::middleware('ms-auth')->group(function () {
    Route::get('/admin/dashboard', 'AdminController@dashboard');
});
```

> [!CAUTION]
> You can not add the middleware globally or in the web group because the auth callback method needs to be publicy accessible

#### Set you .env variables
```php
// services.php
...
'azure' => [
	'client_id' => env('AZURE_CLIENT_ID'),
	'client_secret' => env('AZURE_CLIENT_SECRET'),
	'tenant' => env('AZURE_TENANT_ID'),
	'redirect' => env('AZURE_REDIRECT_URI'),
],
...
```
```env
// .env
...
AZURE_CLIENT_ID=<YOUR CLIENT ID>
AZURE_CLIENT_SECRET=<YOUR CLIENT SECRET>
AZURE_REDIRECT_URI=http://localhost:8080/auth/callback
AZURE_TENANT_ID=<YOUR TENANT ID>
...
```

#### Accessing the user
The ms-auth middleware sets the following __scoped__ session values

```php
session()->put('ms:user', (object)$user);
session()->put('ms:username', $user['bannerUsername']);
session()->put('ms:email', $user['email']);
session()->put('ms:principalName', $user['principalName']);
session()->put('ms:id', $user['id']);
session()->put('ms:session-token', $user['token']);
```

You can reference these directly or you can use the __LoggedInUser__ helper class:

```php
// Returns an object with the following properties set
Joeystowe\MsGraphApi\LoggedInUser::user();
{
  "id" => "1111-2222-33333-44444" //ms user id
  "name" => "John Doe" //Full Name
  "email" => "john.doe@eng.ua.edu"
  "principalName" => "jdoe@ua.edu"
  "bannerUsername" => "jdoe"
  "token" => "1111-2222-3333-4444" //ms session token
}

//Fetch users properties as an array
Joeystowe\MsGraphApi\LoggedInUser::userArray();

//Fetch users properties as a pre-filled User model
Joeystowe\MsGraphApi\LoggedInUser::userModel();

//Fetch a single user attribute (throws exception is property is not found)
Joeystowe\MsGraphApi\LoggedInUser::userAttribute('principalName')
//returns "jdoe@ua.edu"
```



#### Customizing the user model used by the OIDC guard

By default, this package uses `Joeystowe\MsGraphApi\Models\OidcUser` and also registers an `oidc` guard and `oidc_users` provider for you. If you would like to customize the model in your application (e.g., add fields or behaviors), you can publish a stub model and point the package config to your class:

```bash
php artisan vendor:publish --tag=ms-graph-api-model
php artisan vendor:publish --tag=ms-graph-api-config
php artisan vendor:publish --tag=ms-graph-api-migrations
```

Or publish all assets

```bash
php artisan vendor:publish --provider="Joeystowe\MsGraphApi\MsGraphApiServiceProvider"
//or
php artisan vendor:publish --tag=ms-graph-api-config --tag=assets --tag=ms-graph-api-migrations --tag=ms-graph-api-model
```


This will create `app/Models/OidcUser.php` and `config/ms-graph-api.php`. Update `config/ms-graph-api.php` to reference your application model:

```php
// config/ms-graph-api.php
'user_model' => App\Models\OidcUser::class,
```

Update .env

```php
AUTH_GUARD="oidc"
AZURE_TENANT_ID=""
AZURE_CLIENT_ID=""
AZURE_CLIENT_SECRET=""
AZURE_REDIRECT_URI="${APP_URL}/auth/callback"
```


The authentication callback respects this configuration and uses standard Eloquent `updateOrCreate(['id' => ...], [...])` to persist the user, so no special method is required on your model. By default the package auto-loads its migration; if you need to customize it, publish with `--tag=ms-graph-api-migrations` and edit in your app.

#### Logging Out
Simply hit the '/logout' route to log the user out. After logging out from MS the user will be redirected to a '/postLogout' page. Be sure to set your APP_URL correctly so the "log back in" url will work correctly.

You will also need to publish the assets for the postLogout page to be fully functional:
```bash
php artisan vendor:publish --tag=assets --ansi --force
```

#### Local development proxy user (impersonation)
For local development you can have the middleware short-circuit the Azure login and authenticate as a proxy user defined in your `.env`.

- Only active when `app()->environment('local')` is true and `MS_GRAPH_PROXY_ENABLED=true`.
- The proxy user is persisted (via your OIDC user model) and logged in with the `oidc` guard.
- If you need Microsoft Graph calls to work locally, provide a valid delegated token in `MS_GRAPH_PROXY_TOKEN`. Otherwise, Graph calls may 401.
 - Alternatively, you can configure application permissions using client credentials to query Graph for any user's groups in local/proxy mode.

Add to your `.env` (local only):

```env
MS_GRAPH_PROXY_ENABLED=true
MS_GRAPH_PROXY_PRINCIPAL=jdoe@contoso.com
MS_GRAPH_PROXY_NAME="John Doe (Dev)"
# Optional overrides
# MS_GRAPH_PROXY_EMAIL=jdoe@contoso.com
# MS_GRAPH_PROXY_ID=proxy-jdoe
# MS_GRAPH_PROXY_TOKEN=
# Optional: Application permissions (client credentials) for Graph in local/proxy mode
# Requires Graph app with appropriate app roles (e.g., User.Read.All, Group.Read.All)
MS_GRAPH_APP_ENABLED=true
MS_GRAPH_APP_TENANT=<tenant_id>
MS_GRAPH_APP_CLIENT_ID=<app_client_id>
MS_GRAPH_APP_CLIENT_SECRET=<app_client_secret>
```

Configuration is defined under `config/ms-graph-api.php`:

```php
'proxy' => [
    'enabled'   => env('MS_GRAPH_PROXY_ENABLED', false),
    'principal' => env('MS_GRAPH_PROXY_PRINCIPAL'),
    'email'     => env('MS_GRAPH_PROXY_EMAIL'),
    'name'      => env('MS_GRAPH_PROXY_NAME', 'Dev User'),
    'id'        => env('MS_GRAPH_PROXY_ID'),
    'token'     => env('MS_GRAPH_PROXY_TOKEN'),
],

'client_credentials' => [
    'enabled'       => env('MS_GRAPH_APP_ENABLED', false),
    'tenant'        => env('MS_GRAPH_APP_TENANT', env('AZURE_TENANT_ID')),
    'client_id'     => env('MS_GRAPH_APP_CLIENT_ID'),
    'client_secret' => env('MS_GRAPH_APP_CLIENT_SECRET'),
],
```

### Calling Graph API
---
The plugin also gives you helper methods to call the MS graph API
#### Logged In User Methods
##### Groups
```php
$user = Joeystowe\MsGraphApi\LoggedInUser::user();
//resolve instance of current user API
$graphApi = app(Joeystowe\MsGraphApi\MsGraphCurrentUserApi::class, ['token' => $user->token]);

//Get all user's groups, returns array of groups
$graphApi->groups()

//Check if a user is in a specific group, returns boolean
$graphApi->inGroup(groupId: $groupIdToCheck)
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Credits

-   [Joey Stowe](https://github.com/joeystowe)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


