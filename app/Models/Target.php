<?php

namespace App\Models;

use App\Models\Concerns\PrunesOrphanComments;
use Database\Factories\TargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// status_verifikasi is deliberately not fillable: only CreateTarget / VerifyTarget set it.
#[Fillable(['nama', 'base_url', 'lingkungan'])]
class Target extends Model
{
    /** @use HasFactory<TargetFactory> */
    use HasFactory;
    use PrunesOrphanComments;

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    public function isVerified(): bool
    {
        return $this->status_verifikasi === self::VERIFIED;
    }

    /**
     * Targets a user may list: staff see all, clients only their own (mirrors TargetPolicy::view).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->can('targets.view-any')) {
            $query->whereBelongsTo($user);
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TargetScopeRule, $this>
     */
    public function targetScopeRules(): HasMany
    {
        return $this->hasMany(TargetScopeRule::class);
    }

    /**
     * @return HasMany<TargetTestAccount, $this>
     */
    public function targetTestAccounts(): HasMany
    {
        return $this->hasMany(TargetTestAccount::class);
    }

    /**
     * @return HasMany<Scan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * @return MorphMany<ToolCall, $this>
     */
    public function toolCalls(): MorphMany
    {
        return $this->morphMany(ToolCall::class, 'loggable');
    }
}
