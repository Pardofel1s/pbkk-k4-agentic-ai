<?php

namespace App\Models;

use Database\Factories\ToolCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['scan_id', 'tool_id', 'loggable_id', 'loggable_type', 'parameter', 'hasil', 'status', 'error_msg'])]
class ToolCall extends Model
{
    /** @use HasFactory<ToolCallFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameter' => 'array',
            'hasil' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /**
     * @return BelongsTo<Scan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
