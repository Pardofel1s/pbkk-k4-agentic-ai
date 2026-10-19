<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['isi'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * Models that can be commented on.
     *
     * @var list<class-string<Model>>
     */
    public const COMMENTABLES = [Finding::class, Report::class];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Delete comments whose finding/report no longer exists.
     *
     * Database cascades (user -> target -> scan -> finding) skip model events,
     * so models up that chain call this after they are deleted.
     */
    public static function pruneOrphans(): void
    {
        foreach (self::COMMENTABLES as $class) {
            $model = new $class();

            self::query()
                ->where('commentable_type', $model->getMorphClass())
                ->whereNotIn('commentable_id', $class::query()->select($model->getKeyName()))
                ->delete();
        }
    }
}
