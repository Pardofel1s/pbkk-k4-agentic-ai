<?php

namespace App\Actions;

use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateTarget
{
    /**
     * Clients register targets as pending; admin targets (demo dummy / research) start verified.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $by, array $input): Target
    {
        Gate::forUser($by)->authorize('create', Target::class);

        $data = Validator::make($input, [
            'nama' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url:http,https', 'max:255'],
            // Staging/UAT only: testing must never disturb production users (docs/rancangan.md).
            'lingkungan' => ['required', Rule::in(['staging', 'uat'])],
        ])->validate();

        $target = new Target($data);
        $target->user()->associate($by);
        $target->status_verifikasi = $by->can('targets.verify') ? Target::VERIFIED : Target::PENDING;
        $target->save();

        return $target;
    }
}
