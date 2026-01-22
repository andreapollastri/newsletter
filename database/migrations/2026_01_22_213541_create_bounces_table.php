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
        Schema::create('bounces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_send_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('type')->nullable();
            $table->text('raw_message')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index('email');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bounces');
    }
};
