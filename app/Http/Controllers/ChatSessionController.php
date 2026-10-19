<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use Illuminate\View\View;

class ChatSessionController extends Controller
{
    /**
     * Chat history: every message with its tool calls in a fixed number of queries.
     * Access is checked by the route's can:view middleware (ChatSessionPolicy).
     */
    public function show(ChatSession $chatSession): View
    {
        $chatSession->load(['chatMessages.toolCalls.tool', 'chatMessages.toolCalls.loggable']);

        return view('chat-sessions.show', ['session' => $chatSession]);
    }
}
