<?php

namespace App\Sanctum;

/**
 * Personal access token abilities (Sanctum scopes).
 */
final class TokenAbility
{
    public const Api = 'api';

    public const Mcp = 'mcp';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::Api, self::Mcp];
    }
}
