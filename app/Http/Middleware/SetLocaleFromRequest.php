<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the response locale for the public API.
 *
 * Precedence: an explicit `?locale=` wins (the SPA controls its own language
 * switch), then `Accept-Language`, then the configured fallback. Anything
 * unsupported is ignored rather than erroring — a bad header should not fail
 * an otherwise valid request.
 */
class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('localization.supported'));

        $locale = $request->query('locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported);
        }

        app()->setLocale($locale ?: config('localization.fallback'));

        return $next($request);
    }
}
