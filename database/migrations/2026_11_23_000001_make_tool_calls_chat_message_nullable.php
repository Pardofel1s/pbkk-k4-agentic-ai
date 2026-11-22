<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Scans run tools without a chat, so a tool call belongs to a chat message, a scan, or both.
     */
    public function up(): void
    {
        Schema::table('tool_calls', function (Blueprint $table) {
            $table->foreignId('chat_message_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tool_calls', function (Blueprint $table) {
            $table->foreignId('chat_message_id')->nullable(false)->change();
        });
    }
};
