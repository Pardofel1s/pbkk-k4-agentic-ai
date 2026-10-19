<?php

use App\Actions\CreateTarget;
use App\Actions\RegisterAnalyst;
use App\Actions\VerifyTarget;
use App\Models\Certificate;
use App\Models\ChatSession;
use App\Models\Comment;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userWithRole(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

function seededAdmin(): User
{
    return User::query()->firstWhere('email', config('auth.admin.email'));
}

function targetOf(User $owner, string $status = Target::VERIFIED): Target
{
    return Target::factory()->for($owner)->create(['status_verifikasi' => $status]);
}

function targetInput(array $overrides = []): array
{
    return [...['nama' => 'Portal 2.0', 'base_url' => 'https://staging.example.test', 'lingkungan' => 'staging'], ...$overrides];
}

describe('accounts', function () {
    it('seeds exactly one admin, idempotently', function () {
        $this->seed(RolesAndAdminSeeder::class);

        expect(User::role('admin')->count())->toBe(1)
            ->and(seededAdmin()->hasRole('admin'))->toBeTrue();
    });

    it('runs the full development seeder (WithoutModelEvents) on an empty database', function () {
        // Same state as `migrate:fresh --seed`: no roles or permissions yet.
        Role::query()->delete();
        Permission::query()->delete();

        $this->seed(DatabaseSeeder::class);

        expect(User::query()->firstWhere('email', 'analyst@example.com')->can('scans.run'))->toBeTrue()
            ->and(User::query()->firstWhere('email', 'client@example.com')->can('scans.run'))->toBeFalse();
    });

    it('refuses to create the admin without ADMIN_PASSWORD', function () {
        seededAdmin()->delete();
        config(['auth.admin.password' => null]);

        $this->seed(RolesAndAdminSeeder::class);
    })->throws(RuntimeException::class, 'ADMIN_PASSWORD');

    it('registers public sign-ups as clients only', function () {
        Volt::test('pages.auth.register')
            ->set('name', 'Client')
            ->set('email', 'client@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        expect(User::query()->firstWhere('email', 'client@example.com')->getRoleNames()->all())->toBe(['client']);
    });

    it('lets only admins register analysts', function () {
        $input = ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'password', 'password_confirmation' => 'password'];

        $analyst = app(RegisterAnalyst::class)->handle(seededAdmin(), $input);

        expect($analyst->getRoleNames()->all())->toBe(['analyst'])
            ->and(fn () => app(RegisterAnalyst::class)->handle($analyst, [...$input, 'email' => 'eve@example.com']))
            ->toThrow(AuthorizationException::class);
    });

    it('never lets the admin delete their own account', function () {
        expect(seededAdmin()->can('delete', seededAdmin()))->toBeFalse()
            ->and(seededAdmin()->can('delete', userWithRole('client')))->toBeTrue()
            ->and(userWithRole('analyst')->can('delete', userWithRole('client')))->toBeFalse();
    });
});

describe('targets', function () {
    it('creates client targets as pending and admin targets as verified', function () {
        expect(app(CreateTarget::class)->handle(userWithRole('client'), targetInput())->status_verifikasi)->toBe(Target::PENDING)
            ->and(app(CreateTarget::class)->handle(seededAdmin(), targetInput())->status_verifikasi)->toBe(Target::VERIFIED);
    });

    it('does not let analysts create targets', function () {
        app(CreateTarget::class)->handle(userWithRole('analyst'), targetInput());
    })->throws(AuthorizationException::class);

    it('rejects production targets', function () {
        app(CreateTarget::class)->handle(userWithRole('client'), targetInput(['lingkungan' => 'production']));
    })->throws(ValidationException::class);

    it('cannot be self-verified through mass assignment', function () {
        $target = targetOf(userWithRole('client'), Target::PENDING);

        $target->update(['status_verifikasi' => Target::VERIFIED]);

        expect($target->fresh()->status_verifikasi)->toBe(Target::PENDING);
    });

    it('lets only admins verify targets', function () {
        $target = targetOf(userWithRole('client'), Target::PENDING);

        app(VerifyTarget::class)->handle(seededAdmin(), $target, Target::VERIFIED);

        expect($target->fresh()->isVerified())->toBeTrue()
            ->and(fn () => app(VerifyTarget::class)->handle(userWithRole('analyst'), $target, Target::REJECTED))
            ->toThrow(AuthorizationException::class);
    });

    it('locks a target for its owner once verified', function () {
        $client = userWithRole('client');

        expect($client->can('update', targetOf($client, Target::PENDING)))->toBeTrue()
            ->and($client->can('delete', targetOf($client, Target::REJECTED)))->toBeTrue()
            ->and($client->can('update', targetOf($client)))->toBeFalse()
            ->and($client->can('delete', targetOf($client)))->toBeFalse()
            ->and($client->can('update', targetOf(userWithRole('client'), Target::PENDING)))->toBeFalse()
            ->and(seededAdmin()->can('update', targetOf($client)))->toBeTrue();
    });

    it('lists only their own targets for clients', function () {
        $client = userWithRole('client');
        targetOf($client);
        targetOf(userWithRole('client'));

        expect(Target::visibleTo($client)->count())->toBe(1)
            ->and(Target::visibleTo(userWithRole('analyst'))->count())->toBe(2);
    });
});

describe('testing results', function () {
    it('runs scans only by analysts and only on verified targets', function () {
        $analyst = userWithRole('analyst');
        $client = userWithRole('client');

        expect($analyst->can('create', [Scan::class, targetOf($client)]))->toBeTrue()
            ->and($analyst->can('create', [Scan::class, targetOf($client, Target::PENDING)]))->toBeFalse()
            ->and($client->can('create', [Scan::class, targetOf($client)]))->toBeFalse()
            ->and(seededAdmin()->can('create', [Scan::class, targetOf($client)]))->toBeFalse();
    });

    it('shows clients only their own results, read-only', function () {
        $client = userWithRole('client');
        $own = Scan::factory()->for(targetOf($client))->create();
        $other = Scan::factory()->create();

        expect($client->can('view', $own))->toBeTrue()
            ->and($client->can('view', $other))->toBeFalse()
            ->and($client->can('view', Finding::factory()->for($own)->create()))->toBeTrue()
            ->and($client->can('view', Finding::factory()->for($other)->create()))->toBeFalse()
            ->and($client->can('update', Finding::factory()->for($own)->create()))->toBeFalse()
            ->and($client->can('view', Report::factory()->for($own)->create()))->toBeTrue()
            ->and($client->can('view', Report::factory()->for($other)->create()))->toBeFalse()
            ->and($client->can('view', Certificate::factory()->for($own)->create()))->toBeTrue()
            ->and($client->can('view', Certificate::factory()->for($other)->create()))->toBeFalse();
    });

    it('keeps chat sessions from clients and lets admins audit them read-only', function () {
        $session = ChatSession::factory()->for(userWithRole('analyst'))->create();

        expect(userWithRole('client')->can('view', $session))->toBeFalse()
            ->and(seededAdmin()->can('view', $session))->toBeTrue()
            ->and(seededAdmin()->can('update', $session))->toBeFalse()
            ->and(userWithRole('analyst')->can('view', $session))->toBeFalse();
    });
});

describe('comments', function () {
    it('lets clients comment only on their own findings and reports', function () {
        $client = userWithRole('client');
        $scan = Scan::factory()->for(targetOf($client))->create();
        $finding = Finding::factory()->for($scan)->create();

        expect($client->can('create', [Comment::class, $finding]))->toBeTrue()
            ->and($client->can('create', [Comment::class, Report::factory()->for($scan)->create()]))->toBeTrue()
            ->and($client->can('create', [Comment::class, Finding::factory()->create()]))->toBeFalse()
            ->and($client->can('create', [Comment::class, $scan]))->toBeFalse()
            ->and(userWithRole('analyst')->can('create', [Comment::class, $finding]))->toBeTrue()
            ->and(seededAdmin()->can('create', [Comment::class, $finding]))->toBeFalse();
    });

    it('lets only the author delete a comment', function () {
        $comment = Comment::factory()->for(userWithRole('client'))->create();

        expect($comment->user->can('delete', $comment))->toBeTrue()
            ->and(userWithRole('analyst')->can('delete', $comment))->toBeFalse();
    });

    it('removes comments of findings and reports deleted through a cascade', function () {
        $target = targetOf(userWithRole('client'));
        $scan = Scan::factory()->for($target)->create();
        Comment::factory()->for(Finding::factory()->for($scan)->create(), 'commentable')->create();
        Comment::factory()->for(Report::factory()->for($scan)->create(), 'commentable')->create();
        $unrelated = Comment::factory()->create();

        $target->delete();

        expect(Comment::query()->pluck('id')->all())->toBe([$unrelated->id]);
    });
});
