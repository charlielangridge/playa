# Playa

Playa gives a Laravel app a lightweight `Player` model for visitors who are not necessarily logged in.

It is useful for QR-code journeys, event games, voting screens, kiosk flows, demos, and other places where you want to remember "this device came back" without making somebody create an account first.

The package stores the player in your database and keeps only the player's UUID in an HttpOnly cookie.

## Installation

Install the package with Composer:

```bash
composer require charlielangridge/playa
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="playa-migrations"
php artisan migrate
```

Publish the config if you want to change the cookie name, lifetime, table name, or user-linking behaviour:

```bash
php artisan vendor:publish --tag="playa-config"
```

## Basic Usage

Add the `playa` middleware to any route that should have a temporary player.

```php
use Illuminate\Support\Facades\Route;

Route::get('/join/{game}', JoinGameController::class)
    ->middleware(['web', 'playa'])
    ->name('games.join');
```

When a visitor opens that URL, Playa will:

- create a `playa_players` row if the device has no valid player cookie
- store the player's UUID in a cookie
- resolve the same player on later visits
- refresh `last_seen_at`
- refresh `expires_at` when renewal is enabled
- make the player available from the request and the facade

This works well for direct links and QR codes. The QR code can point at a normal application route such as:

```text
https://example.com/join/summer-party
```

The route handles your application flow; Playa handles the device identity.

## Accessing The Current Player

From a controller or route closure:

```php
use Illuminate\Http\Request;

public function __invoke(Request $request)
{
    $player = $request->player();

    // $player is an instance of CharlieLangridge\Playa\Models\Player
}
```

From somewhere that does not receive the request:

```php
use CharlieLangridge\Playa\Facades\Playa;

$player = Playa::player();
```

You can also resolve or create players directly:

```php
use CharlieLangridge\Playa\Facades\Playa;

$player = Playa::findByUuid($uuid);

$player = Playa::create([
    'name' => 'Sam',
    'username' => 'sam-27',
]);
```

## Storing Player Details

The `Player` model has first-class `name` and `username` columns, plus a `data` JSON column for application-specific details.

```php
$player = request()->player();

$player->update([
    'name' => 'Sam',
    'username' => 'sam-27',
    'data' => [
        'team' => 'blue',
        'accepted_rules_at' => now()->toIso8601String(),
    ],
]);
```

Playa does not ship profile routes or forms. Build those in your application so validation, copy, and consent match the flow you are building.

## Linking A Player To A User

Players can be linked to your app's user model without becoming authenticated users themselves.

```php
$player = request()->player();

$player->linkUser(auth()->user());
```

To remove the link:

```php
$player->unlinkUser();
```

Automatic linking is disabled by default. If you want any authenticated request with the `playa` middleware to claim the current player, enable it in `config/playa.php`:

```php
'auto_link_authenticated_user' => true,
```

## Expiry And Renewal

By default, a player lasts for 30 days:

```php
'lifetime_minutes' => 60 * 24 * 30,
```

Renewal is enabled by default:

```php
'renew_on_visit' => true,
```

With renewal enabled, each visit through the `playa` middleware refreshes the database `expires_at` value and the cookie lifetime.

With renewal disabled, the player expires at the original `expires_at` time even if the device keeps visiting.

When a cookie points to an expired, missing, or invalid player, Playa creates a fresh player and sends a replacement cookie.

## Pruning Expired Players

Expired rows are not deleted automatically. Schedule the prune command in your application if you want to clean them up:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('playa:prune')->daily();
```

You can keep recently expired rows around for a grace period:

```bash
php artisan playa:prune --hours=24
```

## Configuration

The published config looks like this:

```php
return [
    'table_name' => 'playa_players',

    'lifetime_minutes' => 60 * 24 * 30,

    'renew_on_visit' => true,

    'cookie' => [
        'name' => 'playa_player',
        'path' => '/',
        'domain' => null,
        'secure' => null,
        'http_only' => true,
        'same_site' => 'lax',
        'lifetime_minutes' => null,
    ],

    'user_model' => null,
    'user_id_type' => 'bigint',

    'auto_link_authenticated_user' => false,
];
```

### Table Name

The default table is `playa_players` to avoid colliding with application tables that already use `players`.

Change this before publishing/running the migration:

```php
'table_name' => 'players',
```

### Cookie Options

The cookie stores the player UUID only. By default it is HttpOnly and SameSite Lax.

Set `secure` to `true` in production if your app always runs over HTTPS:

```php
'cookie' => [
    'secure' => true,
],
```

If `cookie.lifetime_minutes` is `null`, Playa uses `lifetime_minutes` for the cookie as well.

### User Id Type

The migration supports the common Laravel user key types:

```php
'user_id_type' => 'bigint', // bigint, uuid, ulid, or string
```

Set this before publishing/running the migration.

If `user_model` is `null`, Playa falls back to `config('auth.providers.users.model')`.

## Events

Playa dispatches events for the main lifecycle points:

- `CharlieLangridge\Playa\Events\PlayerCreated`
- `CharlieLangridge\Playa\Events\PlayerResolved`
- `CharlieLangridge\Playa\Events\PlayerRenewed`
- `CharlieLangridge\Playa\Events\PlayerExpired`
- `CharlieLangridge\Playa\Events\PlayerLinkedToUser`

For example:

```php
use CharlieLangridge\Playa\Events\PlayerCreated;
use Illuminate\Support\Facades\Event;

Event::listen(PlayerCreated::class, function (PlayerCreated $event) {
    // $event->player
});
```

## Forgetting The Current Player

If your application needs to clear the device identity, call:

```php
use CharlieLangridge\Playa\Facades\Playa;

Playa::forget();
```

When called during a request using the `playa` middleware, the response will clear the player cookie.

## Troubleshooting

### `$request->player()` is null

Make sure the route uses the `playa` middleware.

```php
Route::get('/join/{game}', JoinGameController::class)
    ->middleware(['web', 'playa']);
```

### A new player is created on every visit

Check that the browser is receiving and returning the configured cookie. Common causes are a mismatched cookie domain, a path that does not include the route being visited, or `secure` being enabled on a non-HTTPS local site.

### The cookie is visible but Laravel cannot resolve it

Use the middleware on web routes so Laravel's cookie middleware can decrypt incoming cookies and encrypt outgoing ones.

### User linking is not happening

Manual linking is the default:

```php
request()->player()->linkUser(auth()->user());
```

If you want automatic linking, enable `auto_link_authenticated_user` and make sure the request is already authenticated before the `playa` middleware runs.

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format the code:

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Charlie Langridge](https://github.com/charlielangridge)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
