<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Returns a responsive, table-based HTML email layout with placeholders (title, preview, body, cta_label, cta_url) and optional brand color — use as a starting “graphic” template for campaigns.')]
class GenerateEmailTemplateHtmlTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'max:7'],
            'theme' => ['nullable', 'in:light,dark,minimal'],
        ]);

        $title = $validated['title'] ?? 'Newsletter';
        $brand = $validated['brand_color'] ?? '#2563eb';
        $theme = $validated['theme'] ?? 'light';

        $bg = match ($theme) {
            'dark' => '#0f172a',
            'minimal' => '#fafafa',
            default => '#f8fafc',
        };

        $text = match ($theme) {
            'dark' => '#f1f5f9',
            'minimal' => '#111827',
            default => '#0f172a',
        };

        $muted = match ($theme) {
            'dark' => '#94a3b8',
            'minimal' => '#6b7280',
            default => '#64748b',
        };

        $safeBrand = Str::startsWith($brand, '#') ? $brand : '#'.$brand;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="und">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:{$bg};font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:{$text};">
  <span style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0">{{preview_text}}</span>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:{$bg};padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
          <tr>
            <td style="padding:28px 28px 8px 28px;font-size:22px;font-weight:700;line-height:1.3;color:{$text};">
              {{title}}
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 16px 28px;font-size:14px;line-height:1.6;color:{$muted};">
              {{intro}}
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 24px 28px;font-size:16px;line-height:1.7;color:{$text};">
              {{body}}
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 32px 28px;" align="left">
              <a href="{{cta_url}}" style="display:inline-block;background:{$safeBrand};color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:999px;">
                {{cta_label}}
              </a>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 28px 28px 28px;font-size:12px;line-height:1.5;color:{$muted};border-top:1px solid #e2e8f0;">
              {{footer}}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        return Response::json([
            'html' => $html,
            'placeholders' => [
                'preview_text',
                'title',
                'intro',
                'body',
                'cta_url',
                'cta_label',
                'footer',
            ],
            'notes' => 'Replace {{...}} placeholders before sending. Keep table layout for best client compatibility.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Optional document / template title.'),
            'brand_color' => $schema->string()->description('Primary accent as hex, e.g. #2563eb.'),
            'theme' => $schema->string()->enum(['light', 'dark', 'minimal'])->description('Visual preset for background and text.'),
        ];
    }
}
