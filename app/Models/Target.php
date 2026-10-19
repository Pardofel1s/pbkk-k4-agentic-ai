<?php

namespace App\Models;

use Database\Factories\TargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['nama', 'base_url', 'lingkungan', 'status_verifikasi'])]
class Target extends Model
{
    /** @use HasFactory<TargetFactory> */
    use HasFactory;

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
