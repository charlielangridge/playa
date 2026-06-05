# Playa

Playa provides temporary cookie-backed player identities for Laravel applications. Use it when an app needs to remember a visitor or device for QR-code journeys, event games, voting screens, kiosk flows, demos, or similar unauthenticated flows without requiring a user account.

Playa stores player records in the database and stores only the player's UUID in an HttpOnly cookie.

## Installation

Install the package and publish the migration:

@verbatim
<code-snippet name="Install Playa and publish its migration" lang="shell">
composer require charlielangridge/playa
php artisan vendor:publish --tag="playa-migrations"
php artisan migrate
</code-snippet>
@endverbatim

Publish the config only when the application needs to change the cookie name, lifetime, table name, player model, or user-linking behavior:

@verbatim
<code-snippet name="Publish Playa config" lang="shell">
php artisan vendor:publish --tag="playa-config"
</code-snippet>
@endverbatim

## Resolving Players

Add the `playa` middleware to routes that should have a temporary player. Use it on web routes so Laravel can decrypt incoming cookies and encrypt outgoing cookies.

@verbatim
<code-snippet name="Add Playa middleware to a route" lang="php">
use Illuminate\Support\Facades\Route;

Route::get('/join/{game}', JoinGameController::class)
    ->middleware(['web', 'playa'])
    ->name('games.join');
</code-snippet>
@endverbatim

When the route is visited, Playa creates a player if the request has no valid player cookie, stores the player's UUID in a cookie, refreshes `last_seen_at`, refreshes `expires_at` when renewal is enabled, and makes the player available from the request and facade.

## Accessing Players

Prefer `$request->player()` in controllers, route closures, and other request-aware code.

@verbatim
<code-snippet name="Access the current Playa player from the request" lang="php">
use Illuminate\Http\Request;

public function __invoke(Request $request)
{
    $player = $request->player();
}
</code-snippet>
@endverbatim

Use the facade when the current request is not directly available:

@verbatim
<code-snippet name="Access the current Playa player from the facade" lang="php">
use CharlieLangridge\Playa\Facades\Playa;

$player = Playa::player();
</code-snippet>
@endverbatim

You may also resolve or create players directly:

@verbatim
<code-snippet name="Resolve or create Playa players directly" lang="php">
use CharlieLangridge\Playa\Facades\Playa;

$player = Playa::findByUuid($uuid);

$player = Playa::create([
    'name' => 'Sam',
    'username' => 'sam-27',
]);
</code-snippet>
@endverbatim

Call `Playa::forget()` when the application needs to clear the current device identity. During a request using the `playa` middleware, the response will clear the player cookie.

## Player Data

Use the built-in `name`, `username`, and `data` attributes for simple profile details. Store application-specific structured values in `data`.

@verbatim
<code-snippet name="Store Playa player details" lang="php">
$player = request()->player();

$player->update([
    'name' => 'Sam',
    'username' => 'sam-27',
    'data' => [
        'team' => 'blue',
        'accepted_rules_at' => now()->toIso8601String(),
    ],
]);
</code-snippet>
@endverbatim

If the application needs relationships, casts, scopes, helper methods, or extra columns, create an application model that extends `CharlieLangridge\Playa\Models\Player` and configure it with the `player_model` config key. Add extra columns in the application's own migrations.

@verbatim
<code-snippet name="Create a custom Playa player model" lang="php">
namespace App\Models;

use CharlieLangridge\Playa\Models\Player as PlayaPlayer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends PlayaPlayer
{
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Configure a custom Playa player model" lang="php">
'player_model' => App\Models\Player::class,
</code-snippet>
@endverbatim

## User Linking

Players can be linked to authenticated users without becoming authenticated users themselves.

@verbatim
<code-snippet name="Link and unlink a Playa player to a user" lang="php">
$player = request()->player();

$player->linkUser(auth()->user());
$player->unlinkUser();
</code-snippet>
@endverbatim

Manual linking is the default. Enable `auto_link_authenticated_user` only when every authenticated request using the `playa` middleware should claim the current player.

## Expiry, Renewal, and Pruning

By default, players last for 30 days and renew on each visit through the `playa` middleware. Configure `lifetime_minutes`, `renew_on_visit`, and `cookie.lifetime_minutes` in `config/playa.php` when the application needs different behavior.

Expired, missing, invalid, or deleted player cookies cause Playa to create a fresh player and send a replacement cookie.

Expired rows are not deleted automatically. Schedule `playa:prune` when the application should clean them up.

@verbatim
<code-snippet name="Schedule Playa player pruning" lang="php">
use Illuminate\Support\Facades\Schedule;

Schedule::command('playa:prune')->daily();
</code-snippet>
@endverbatim

Use a grace period when recently expired rows should be retained:

@verbatim
<code-snippet name="Prune Playa players with a grace period" lang="shell">
php artisan playa:prune --hours=24
</code-snippet>
@endverbatim

## Cookie Troubleshooting

- If `$request->player()` is `null`, confirm the route uses the `playa` middleware.
- If a new player is created on every visit, check the configured cookie domain, path, `secure` option, and whether the browser is returning the cookie.
- Keep `playa.cookie.http_only` enabled unless the application specifically needs JavaScript to read the cookie UUID.
- Set `playa.cookie.secure` to `true` in production when the application always runs over HTTPS.
