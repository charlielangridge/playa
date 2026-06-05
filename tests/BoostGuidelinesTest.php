<?php

it('ships Laravel Boost AI guidelines', function () {
    $path = __DIR__.'/../resources/boost/guidelines/core.blade.php';

    expect($path)->toBeFile();

    $guidelines = file_get_contents($path);

    expect($guidelines)->toBeString()
        ->and($guidelines)->toContain('# Playa')
        ->and($guidelines)->toContain('temporary cookie-backed player identities')
        ->and($guidelines)->toContain('composer require charlielangridge/playa')
        ->and($guidelines)->toContain("->middleware(['web', 'playa'])")
        ->and($guidelines)->toContain('$request->player()')
        ->and($guidelines)->toContain('Playa::forget()')
        ->and($guidelines)->toContain('player_model')
        ->and($guidelines)->toContain('CharlieLangridge\\Playa\\Models\\Player')
        ->and($guidelines)->toContain('@verbatim')
        ->and($guidelines)->toContain('<code-snippet');
});
