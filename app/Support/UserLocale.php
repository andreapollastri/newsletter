<?php

namespace App\Support;

use Illuminate\Http\Request;

final class UserLocale
{
    public const array ALLOWED = ['it', 'en', 'de', 'fr', 'es', 'pt'];

    public static function isAllowed(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::ALLOWED, true);
    }

    /**
     * Fallback when the browser does not prefer any supported locale.
     */
    public static function default(): string
    {
        return 'en';
    }

    /**
     * Resolve the best matching supported locale from the request Accept-Language header.
     *
     * Uses primary language subtags only (e.g. pt-BR → pt). Does not use Symfony's
     * Request::getPreferredLanguage() because it can incorrectly match the first
     * configured locale when no overlap exists (e.g. Chinese vs. Italian).
     */
    public static function negotiateFromRequest(?Request $request): string
    {
        if ($request === null) {
            return self::default();
        }

        foreach ($request->getLanguages() as $language) {
            $normalized = strtolower(str_replace('_', '-', $language));
            $primary = explode('-', $normalized)[0];

            if (self::isAllowed($primary)) {
                return $primary;
            }
        }

        return self::default();
    }
}
