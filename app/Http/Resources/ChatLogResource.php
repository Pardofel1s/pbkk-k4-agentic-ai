<?php

namespace App\Http\Resources;

use App\Models\ChatLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Standard success envelope of POST /api/chat-ai:
 * {"status": "success", "data": {...}}.
 *
 * @mixin ChatLog
 */
class ChatLogResource extends JsonResource
{
    /**
     * The envelope is built in toArray(), so Laravel must not wrap it again.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'success',
            'data' => [
                'session_id' => $this->id,
                'model_used' => $this->model_used,
                'prompt' => $this->prompt,
                'ai_response' => $this->ai_response,
                'timestamp' => $this->created_at->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
