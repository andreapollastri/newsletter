<?php

namespace App\Http\Controllers;

use App\Models\MessageClick;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    /**
     * Track email open via pixel.
     *
     * When the send row no longer exists (e.g. testing-audience purge after completion), the pixel still loads so images do not break.
     * Duplicate opens for the same send are ignored (unique open per message send).
     * Opens for bounced sends are ignored: NDRs include the original HTML and would otherwise count as opens.
     */
    public function open(string $messageSend, Request $request): Response
    {
        if (! Str::isUuid($messageSend)) {
            abort(404);
        }

        $record = MessageSend::find($messageSend);

        if ($record) {
            $this->recordIfNotBounced($record, function (MessageSend $record) use ($request): void {
                $created = MessageOpen::query()->firstOrCreate(
                    ['message_send_id' => $record->id],
                    [
                        'opened_at' => now(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $record->increment('opens_count');
                }
            });
        }

        $pixel = hex2bin('47494638396101000100800000000000ffffff21f90401000000002c000000000100010000020144003b');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track email click and redirect.
     *
     * When the send row no longer exists (e.g. testing-audience purge after completion), the destination URL is still applied so links in archived mail remain usable.
     * Duplicate clicks for the same send + URL are ignored (unique click per destination).
     * Clicks for bounced sends are ignored: NDRs include the original HTML and would otherwise count as clicks.
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
            $this->recordIfNotBounced($record, function (MessageSend $record) use ($decodedUrl, $request): void {
                $created = MessageClick::query()->firstOrCreate(
                    [
                        'message_send_id' => $record->id,
                        'url' => $decodedUrl,
                    ],
                    [
                        'clicked_at' => now(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $record->increment('clicks_count');
                }
            });
        }

        return redirect()->away($decodedUrl);
    }

    /**
     * @param  callable(MessageSend): void  $callback
     */
    private function recordIfNotBounced(MessageSend $record, callable $callback): void
    {
        try {
            DB::transaction(function () use ($record, $callback): void {
                $locked = MessageSend::query()->lockForUpdate()->find($record->id);

                if ($locked === null || $locked->bounce()->exists()) {
                    return;
                }

                $callback($locked);
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent tracking for the same send — already recorded.
        }
    }
}
