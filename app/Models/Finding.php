<?php

namespace App\Models;

use App\Models\Concerns\PrunesOrphanComments;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['vulnerability_type_id', 'severity', 'url_terdampak', 'parameter', 'proof', 'rekomendasi', 'status'])]
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;
    use PrunesOrphanComments;

    public const OPEN = 'open';

    public const FIXED = 'fixed';

    /**
     * @return BelongsTo<Scan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * @return BelongsTo<VulnerabilityType, $this>
     */
    public function vulnerabilityType(): BelongsTo
    {
        return $this->belongsTo(VulnerabilityType::class);
    }

    /**
     * @return MorphMany<ToolCall, $this>
     */
    public function toolCalls(): MorphMany
    {
        return $this->morphMany(ToolCall::class, 'loggable');
    }
    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
