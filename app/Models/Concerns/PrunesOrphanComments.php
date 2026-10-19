<?php

namespace App\Models\Concerns;

use App\Models\Comment;

/**
 * For every model whose deletion can (directly or via DB cascade) remove a
 * commentable finding/report. Mass deletes (Model::query()->delete()) skip
 * model events and therefore skip this cleanup.
 */
trait PrunesOrphanComments
{
    public static function bootPrunesOrphanComments(): void
    {
        static::deleted(fn () => Comment::pruneOrphans());
    }
}
