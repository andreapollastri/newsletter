<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $bouncedSendIds = DB::table('bounces')
            ->whereNotNull('message_send_id')
            ->pluck('message_send_id');

        if ($bouncedSendIds->isEmpty()) {
            return;
        }

        DB::table('message_opens')->whereIn('message_send_id', $bouncedSendIds)->delete();
        DB::table('message_clicks')->whereIn('message_send_id', $bouncedSendIds)->delete();
        DB::table('message_sends')
            ->whereIn('id', $bouncedSendIds)
            ->update([
                'opens_count' => 0,
                'clicks_count' => 0,
            ]);
    }
};
