<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAnalyst
{
    /**
     * @param  array<string, mixed>  $input  name, email, password, password_confirmation
     */
    public function handle(User $admin, array $input): User
    {
        Gate::forUser($admin)->authorize('create', User::class);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ])->validate();

        return DB::transaction(fn () => User::query()
            ->create($data)
            ->assignRole('analyst'));
    }
}
