<?php

use App\Actions\IssueCertificate;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function issuingAdmin(): User
{
    return User::query()->firstWhere('email', config('auth.admin.email'));
}

function cleanRetest(): Scan
{
    $original = Scan::factory()->create();
    Finding::factory()->for($original)->create(['status' => Finding::OPEN]);

    $retest = Scan::factory()->for($original->target)
        ->create(['retest_of_id' => $original->id, 'status' => Scan::COMPLETED]);
    Finding::factory()->for($retest)->create(['status' => Finding::FIXED]);

    return $retest;
}

it('issues a certificate for a finished re-test without open findings', function () {
    $retest = cleanRetest();

    $certificate = app(IssueCertificate::class)->handle(issuingAdmin(), $retest);

    expect($certificate->scan_id)->toBe($retest->id)
        ->and($certificate->nomor)->toBe(sprintf('CERT-%d-%05d', now()->year, $retest->id))
        ->and($certificate->due_date->isSameDay(now()->addYear()))->toBeTrue();
});

it('lets the target owner see the issued certificate', function () {
    $retest = cleanRetest();
    $certificate = app(IssueCertificate::class)->handle(issuingAdmin(), $retest);

    $owner = $retest->target->user->assignRole('client');

    expect($owner->can('view', $certificate))->toBeTrue();
});

it('refuses', function (Closure $scan, string $reason) {
    expect(fn () => app(IssueCertificate::class)->handle(issuingAdmin(), $scan()))
        ->toThrow(ValidationException::class, $reason);
})->with([
    'a first scan (not a re-test)' => [fn () => Scan::factory()->create(), 're-test'],
    'an unfinished re-test' => [fn () => tap(cleanRetest())->update(['status' => 'running']), 'belum selesai'],
    'a re-test with an open finding' => [
        fn () => tap(cleanRetest(), fn (Scan $scan) => Finding::factory()->for($scan)->create(['status' => Finding::OPEN])),
        'open',
    ],
    'a second certificate for the same scan' => [
        fn () => tap(cleanRetest(), fn (Scan $scan) => app(IssueCertificate::class)->handle(issuingAdmin(), $scan)),
        'sudah punya',
    ],
]);

it('lets only admins issue certificates', function (string $role) {
    app(IssueCertificate::class)->handle(User::factory()->create()->assignRole($role), cleanRetest());
})->with(['analyst', 'client'])->throws(AuthorizationException::class);
