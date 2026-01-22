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
        Schema::create('message_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('opens_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->unique(['message_id', 'subscriber_id']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_sends');
    }
};
