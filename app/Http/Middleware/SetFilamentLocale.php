<?php

namespace App\Http\Middleware;

use App\Support\UserLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetFilamentLocale
{
    /**
     * Authenticated users use their saved locale when valid; guests (e.g. login) use Accept-Language, falling back to English.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && UserLocale::isAllowed($user->locale)) {
            App::setLocale($user->locale);
        } else {
            App::setLocale(UserLocale::negotiateFromRequest($request));
        }

        return $next($request);
    }
}
