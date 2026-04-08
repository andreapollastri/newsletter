<?php

namespace App\Support;

class NewsletterUrlUtm
{
    /**
     * Append newsletter UTM query parameters to an absolute http(s) URL.
     * Other schemes (mailto, tel, etc.) are returned unchanged.
     */
    public static function append(string $url, string $campaignSlug, string $messageId): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return $url;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }

        parse_str($parsed['query'] ?? '', $query);

        $utm = [
            'utm_source' => 'nl',
            'utm_medium' => 'newsletter',
            'utm_campaign' => $campaignSlug,
            'utm_content' => $messageId,
        ];

        /** @var array<string, string> $merged */
        $merged = array_merge($query, $utm);

        return self::buildUrl($parsed, $merged);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, string>  $query
     */
    private static function buildUrl(array $parsed, array $query): string
    {
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'].'://' : '';
        $user = $parsed['user'] ?? '';
        $pass = isset($parsed['pass']) ? ':'.$parsed['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user.$pass.'@' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $queryString = http_build_query($query);
        $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

        return $scheme.$auth.$host.$port.$path.($queryString !== '' ? '?'.$queryString : '').$fragment;
    }
}
