<?php

namespace CharlieLangridge\Playa\Http\Middleware;

use CharlieLangridge\Playa\Playa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayer
{
    public function __construct(protected Playa $playa) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->playa->resolve($request);

        $response = $next($request);

        if ($this->playa->shouldForgetCurrentPlayer()) {
            $response->headers->setCookie($this->playa->forgetCookie());

            return $response;
        }

        $player = $this->playa->player();

        if ($player) {
            $response->headers->setCookie($this->playa->cookieFor($player));
        }

        return $response;
    }
}
