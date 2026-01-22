<?php

namespace App\Http\Controllers;

use App\Models\MessageClick;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /**
     * Track email open via pixel.
     */
    public function open(MessageSend $messageSend, Request $request): Response
    {
        // Create open record
        MessageOpen::create([
            'message_send_id' => $messageSend->id,
            'opened_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Increment counter
        $messageSend->increment('opens_count');

        // Return 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track email click and redirect.
     */
    public function click(MessageSend $messageSend, Request $request): \Illuminate\Http\RedirectResponse
    {
        $url = $request->query('url');

        if (! $url) {
            abort(400, 'Missing URL parameter');
        }

        // Decode the URL
        $decodedUrl = base64_decode($url);

        if (! $decodedUrl || ! filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        // Create click record
        MessageClick::create([
            'message_send_id' => $messageSend->id,
            'url' => $decodedUrl,
            'clicked_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Increment counter
        $messageSend->increment('clicks_count');

        // Redirect to original URL
        return redirect()->away($decodedUrl);
    }
}
