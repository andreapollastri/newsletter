<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->deduplicateOpens();
        $this->deduplicateClicks();

        Schema::table('message_opens', function (Blueprint $table) {
            $table->unique('message_send_id');
        });

        Schema::table('message_clicks', function (Blueprint $table) {
            $table->unique(['message_send_id', 'url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_opens', function (Blueprint $table) {
            $table->dropUnique(['message_send_id']);
        });

        Schema::table('message_clicks', function (Blueprint $table) {
            $table->dropUnique(['message_send_id', 'url']);
        });
    }

    private function deduplicateOpens(): void
    {
        $duplicateSendIds = DB::table('message_opens')
            ->select('message_send_id')
            ->groupBy('message_send_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('message_send_id');

        foreach ($duplicateSendIds as $messageSendId) {
            $keepId = DB::table('message_opens')
                ->where('message_send_id', $messageSendId)
                ->orderBy('opened_at')
                ->orderBy('id')
                ->value('id');

            DB::table('message_opens')
                ->where('message_send_id', $messageSendId)
                ->where('id', '!=', $keepId)
                ->delete();

            DB::table('message_sends')
                ->where('id', $messageSendId)
                ->update(['opens_count' => 1]);
        }
    }

    private function deduplicateClicks(): void
    {
        $duplicates = DB::table('message_clicks')
            ->select('message_send_id', 'url')
            ->groupBy('message_send_id', 'url')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keepId = DB::table('message_clicks')
                ->where('message_send_id', $duplicate->message_send_id)
                ->where('url', $duplicate->url)
                ->orderBy('clicked_at')
                ->orderBy('id')
                ->value('id');

            DB::table('message_clicks')
                ->where('message_send_id', $duplicate->message_send_id)
                ->where('url', $duplicate->url)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        $sendIds = $duplicates->pluck('message_send_id')->unique();

        foreach ($sendIds as $messageSendId) {
            $uniqueClicks = DB::table('message_clicks')
                ->where('message_send_id', $messageSendId)
                ->count();

            DB::table('message_sends')
                ->where('id', $messageSendId)
                ->update(['clicks_count' => $uniqueClicks]);
        }
    }
};
