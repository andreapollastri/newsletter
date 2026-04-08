<?php

namespace App\Http\Controllers;

use App\Models\MessageClick;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    /**
     * Track email open via pixel.
     *
     * When the send row no longer exists (e.g. testing-audience purge after completion), the pixel still loads so images do not break.
     */
    public function open(string $messageSend, Request $request): Response
    {
        if (! Str::isUuid($messageSend)) {
            abort(404);
        }

        $record = MessageSend::find($messageSend);

        if ($record) {
            MessageOpen::create([
                'message_send_id' => $record->id,
                'opened_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $record->increment('opens_count');
        }

        // Return 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track email click and redirect.
     *
     * When the send row no longer exists (e.g. testing-audience purge after completion), the destination URL is still applied so links in archived mail remain usable.
     */
    public function click(string $messageSend, Request $request): RedirectResponse
    {
        if (! Str::isUuid($messageSend)) {
            abort(404);
        }

        $url = $request->query('url');

        if (! $url) {
            abort(400, 'Missing URL parameter');
        }

        // Decode the URL
        $decodedUrl = base64_decode($url);

        if (! $decodedUrl || ! filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        $record = MessageSend::find($messageSend);

        if ($record) {
            MessageClick::create([
                'message_send_id' => $record->id,
                'url' => $decodedUrl,
                'clicked_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $record->increment('clicks_count');
        }

        return redirect()->away($decodedUrl);
    }
}
