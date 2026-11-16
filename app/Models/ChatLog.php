<?php

namespace App\Models;

use Database\Factories\ChatLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One prompt/answer exchange of POST /api/chat-ai. The id doubles as the
 * `session_id` returned to API clients.
 */
#[Fillable(['prompt', 'ai_response', 'model_used', 'prompt_tokens', 'completion_tokens', 'total_tokens'])]
class ChatLog extends Model
{
    /** @use HasFactory<ChatLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
