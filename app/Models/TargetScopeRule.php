<?php

namespace App\Models;

use Database\Factories\TargetScopeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pola_domain', 'tipe'])]
class TargetScopeRule extends Model
{
    /** @use HasFactory<TargetScopeRuleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Target, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }
}
