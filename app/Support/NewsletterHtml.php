<?php

namespace App\Support;

use App\Models\Message;

/**
 * Builds newsletter email HTML. Shared by real sends, test sends and in-panel previews so all three
 * render exactly the same markup.
 */
class NewsletterHtml
{
    /**
     * Sample message body used when previewing a template on its own.
     */
    public const SAMPLE_BODY = '<h2>Your headline goes here</h2>'
        .'<p>This is sample content used to preview the template. When you send a message, its content replaces the <code>{{body}}</code> placeholder.</p>'
        .'<p><a href="https://example.com">A sample link</a></p>';

    /**
     * Merge the message body into its template ({{body}} placeholder, or appended when missing).
     */
    public static function compose(Message $message): string
    {
        $message->loadMissing('template');

        return static::mergeIntoTemplate($message->template?->html_content, (string) $message->html_content);
    }

    public static function mergeIntoTemplate(?string $templateHtml, string $body): string
    {
        if ($templateHtml === null || $templateHtml === '') {
            return $body;
        }

        if (str_contains($templateHtml, '{{body}}')) {
            return str_replace('{{body}}', $body, $templateHtml);
        }

        return $templateHtml.$body;
    }

    /**
     * Make relative image sources absolute so they load in mail clients (uploads live on the public disk).
     */
    public static function absolutizeImageSources(string $html): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return preg_replace_callback(
            '/src=["\'](?!https?:\/\/|\/\/|data:|cid:)([^"\']+)["\']/i',
            function (array $matches) use ($baseUrl): string {
                $path = ltrim($matches[1], '/');

                if (! str_starts_with($path, 'storage/')) {
                    $path = 'storage/'.$path;
                }

                return 'src="'.$baseUrl.'/'.$path.'"';
            },
            $html,
        ) ?? $html;
    }

    public static function fillPlaceholders(string $content, string $name, string $email, string $unsubscribeUrl): string
    {
        return str_replace(
            ['{{name}}', '{{email}}', '{{unsubscribe_url}}'],
            [$name, $email, $unsubscribeUrl],
            $content,
        );
    }

    /**
     * Full email for an in-panel preview, personalised with the given recipient details.
     */
    public static function preview(Message $message, string $name, string $email): string
    {
        return static::fillPlaceholders(
            static::absolutizeImageSources(static::compose($message)),
            $name,
            $email,
            '#',
        );
    }
}
