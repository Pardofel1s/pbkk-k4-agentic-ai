<?php

namespace App\Tools;

use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use App\Services\Agent\TargetScope;

/**
 * Who runs a tool and against which target. Every tool receives one, so it can
 * keep its requests inside the target's scope.
 */
readonly class ToolContext
{
    public function __construct(
        public User $user,
        public Target $target,
        public ?Scan $scan = null,
    ) {
    }

    /**
     * Whether the tool may send a request to this URL (see {@see TargetScope}).
     */
    public function allowsUrl(string $url): bool
    {
        return app(TargetScope::class)->allows($this->target, $url);
    }
}
