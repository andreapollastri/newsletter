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
        // Update messages with status 'sending' or 'sent' to 'ready'
        // Messages that were sent will keep their sent_at timestamp
        DB::table('messages')
            ->whereIn('status', ['sending', 'sent'])
            ->update(['status' => 'ready']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'sent' status for messages that have been sent
        DB::table('messages')
            ->where('status', 'ready')
            ->whereNotNull('sent_at')
            ->update(['status' => 'sent']);
    }
};
