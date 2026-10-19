<?php

namespace App\Models;

use App\Models\Concerns\PrunesOrphanComments;
use Database\Factories\ScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['target_test_account_id', 'scan_profile_id', 'retest_of_id', 'status', 'progress', 'started_at', 'finished_at'])]
class Scan extends Model
{
    /** @use HasFactory<ScanFactory> */
    use HasFactory;
    use PrunesOrphanComments;

    public const COMPLETED = 'completed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Target, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    /**
     * @return BelongsTo<TargetTestAccount, $this>
     */
    public function targetTestAccount(): BelongsTo
    {
        return $this->belongsTo(TargetTestAccount::class);
    }

    /**
     * @return BelongsTo<ScanProfile, $this>
     */
    public function scanProfile(): BelongsTo
    {
        return $this->belongsTo(ScanProfile::class);
    }

    /**
     * @return BelongsTo<Scan, $this>
     */
    public function retestOf(): BelongsTo
    {
        return $this->belongsTo(Scan::class, 'retest_of_id');
    }

    /**
     * @return HasMany<Scan, $this>
     */
    public function retests(): HasMany
    {
        return $this->hasMany(Scan::class, 'retest_of_id');
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * @return HasMany<ToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class);
    }
}
