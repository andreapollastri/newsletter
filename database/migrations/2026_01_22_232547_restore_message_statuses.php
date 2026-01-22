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
        // Update messages with sent_at to 'sent' status
        DB::table('messages')
            ->where('status', 'ready')
            ->whereNotNull('sent_at')
            ->update(['status' => 'sent']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'ready' status
        DB::table('messages')
            ->where('status', 'sent')
            ->update(['status' => 'ready']);
    }
};
