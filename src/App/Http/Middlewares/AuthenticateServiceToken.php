<?php

namespace App\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('transcription.token');
        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Service token is not configured.'], 503);
        }
        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
