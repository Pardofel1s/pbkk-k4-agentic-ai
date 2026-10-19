<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;

class ChatSessionPolicy
{
    /**
     * Admins may audit any session (read-only); otherwise owner only.
     */
    public function view(User $user, ChatSession $chatSession): bool
    {
        return $chatSession->user_id === $user->id || $user->can('chat.view-any');
    }

    public function create(User $user): bool
    {
        return $user->can('chat.use');
    }

    /**
     * Sending messages to the agent.
     */
    public function update(User $user, ChatSession $chatSession): bool
    {
        return $chatSession->user_id === $user->id && $user->can('chat.use');
    }
}
