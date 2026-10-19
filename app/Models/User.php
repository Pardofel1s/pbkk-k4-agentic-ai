<?php

namespace App\Models;

use App\Models\Concerns\PrunesOrphanComments;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use PrunesOrphanComments;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<ChatSession, $this>
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * @return HasMany<Target, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Ownership checks query by id instead of walking relations, so policies
     * stay safe under preventLazyLoading() when called inside loops.
     */
    public function ownsTarget(int $targetId): bool
    {
        return $this->targets()->whereKey($targetId)->exists();
    }

    public function ownsScan(int $scanId): bool
    {
        return Scan::query()
            ->whereKey($scanId)
            ->whereHas('target', fn (Builder $query) => $query->whereBelongsTo($this))
            ->exists();
    }
}
