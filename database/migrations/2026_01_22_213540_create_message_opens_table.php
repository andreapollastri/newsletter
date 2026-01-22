<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_opens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_send_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->index('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_opens');
    }
};
