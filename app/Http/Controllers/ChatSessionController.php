<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatSessionController extends Controller
{
    /**
     * Chat history: every message with its tool calls in a fixed number of queries.
     */
    public function show(Request $request, int $chatSession): View
    {
        // Scoped to the owner, so another user's session id is a 404 (no IDOR).
        $session = $request->user()->chatSessions()
            ->with(['chatMessages.toolCalls.tool', 'chatMessages.toolCalls.loggable'])
            ->findOrFail($chatSession);

        return view('chat-sessions.show', ['session' => $session]);
    }
}
