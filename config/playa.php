<?php

use CharlieLangridge\Playa\Models\Player;

return [
    /*
     * The table used by the package migration and Player model.
     */
    'table_name' => 'playa_players',

    /*
     * The Eloquent model class used for player records. Configure this with
     * an application model that extends CharlieLangridge\Playa\Models\Player.
     */
    'player_model' => Player::class,

    /*
     * The maximum lifetime of a player identity in minutes.
     *
     * The default is 30 days. Set this to null if your application wants
     * player rows to stay valid until they are explicitly removed.
     */
    'lifetime_minutes' => 60 * 24 * 30,

    /*
     * When enabled, each resolved player gets a fresh expires_at timestamp
     * and the browser cookie is refreshed with the same lifetime.
     */
    'renew_on_visit' => true,

    /*
     * Request-scoped identity policies. Rolling preserves Playa's historical
     * lifetime and renewal defaults, while Session is fixed and browser-only.
     */
    'default_policy' => 'rolling',

    'policies' => [
        'session' => [
            'lifetime_minutes' => 60 * 24,
            'renew_on_visit' => false,
            'cookie_lifetime_minutes' => 0,
        ],
        // Rolling inherits the package's historical top-level settings.
        'rolling' => [],
    ],

    /*
     * Playa stores only the player's UUID on the device. Keep http_only
     * enabled unless your application has a specific reason to read this
     * value from JavaScript.
     */
    'cookie' => [
        'name' => 'playa_player',
        'path' => '/',
        'domain' => null,
        'secure' => null,
        'http_only' => true,
        'same_site' => 'lax',
        'lifetime_minutes' => null,
    ],

    /*
     * The model and id column type used when linking a temporary player
     * to an authenticated user. Supported id types: bigint, uuid, ulid,
     * and string.
     */
    'user_model' => null,
    'user_id_type' => 'bigint',

    /*
     * Keep this disabled by default so applications decide when a device
     * identity should become associated with a real user account.
     */
    'auto_link_authenticated_user' => false,
];
