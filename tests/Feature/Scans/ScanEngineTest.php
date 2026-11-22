<?php

use App\Actions\AnalyzeReport;
use App\Actions\GenerateReport;
use App\Actions\StartScan;
use App\Jobs\RunScan;
use App\Models\Comment;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\ScanProfile;
use App\Models\Target;
use App\Models\User;
use App\Services\Scan\FindingRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function scanAnalyst(): User
{
    return User::factory()->create()->assignRole('analyst');
}

function scanTarget(string $status = Target::VERIFIED): Target
{
    return Target::factory()->create(['base_url' => 'http://localhost:8000', 'status_verifikasi' => $status]);
}

function webProfile(array $tools = ['check_security_headers', 'check_cookie_flags', 'check_csrf_token']): ScanProfile
{
    return ScanProfile::factory()->create(['pengaturan' => ['tools' => $tools]]);
}

/**
 * A target page with no security headers, a cookie missing Secure/SameSite and a POST form without a token.
 */
function fakeWeakTarget(): void
{
    Http::fake(['localhost:8000*' => Http::response(
        '<form method="post" action="/login"><input name="email"></form>',
        200,
        ['Set-Cookie' => 'session=abc; HttpOnly'],
    )]);
}

it('queues a scan only for an analyst on a verified target', function () {
    Bus::fake();
    $profile = webProfile();

    $scan = app(StartScan::class)->handle(scanAnalyst(), scanTarget(), $profile->id);

    expect($scan->status)->toBe(Scan::QUEUED);
    Bus::assertDispatched(RunScan::class, fn (RunScan $job) => $job->scanId === $scan->id);

    expect(fn () => app(StartScan::class)->handle(scanAnalyst(), scanTarget(Target::PENDING), $profile->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(StartScan::class)->handle(User::factory()->create()->assignRole('client'), scanTarget(), $profile->id))
        ->toThrow(AuthorizationException::class);
});

it('rejects a test account of another target', function () {
    Bus::fake();
    $other = scanTarget();
    $account = $other->targetTestAccounts()->create(['label_role' => 'nasabah', 'username' => 'u', 'encrypted_password' => 'p']);

    expect(fn () => app(StartScan::class)->handle(scanAnalyst(), scanTarget(), webProfile()->id, $account->id))
        ->toThrow(ValidationException::class);
});

it('runs the profile tools, logs each call and turns the output into findings', function () {
    fakeWeakTarget();
    $analyst = scanAnalyst();
    $scan = Scan::factory()->for(scanTarget())->for(webProfile())->create(['status' => Scan::QUEUED, 'progress' => 0]);

    (new RunScan($scan->id, $analyst->id))->handle(app(App\Services\Agent\ToolExecutor::class), app(App\Tools\ToolRegistry::class), app(FindingRules::class));

    $scan->refresh();
    $types = $scan->findings()->with('vulnerabilityType')->get()->map(fn (Finding $f) => $f->vulnerabilityType->kode_cwe.' '.$f->parameter);

    expect($scan->status)->toBe(Scan::COMPLETED)
        ->and($scan->progress)->toBe(100)
        ->and($scan->toolCalls()->count())->toBe(3)
        ->and($scan->toolCalls()->whereNull('chat_message_id')->count())->toBe(3)
        ->and($types)->toContain('CWE-693 Content-Security-Policy', 'CWE-1021 X-Frame-Options', 'CWE-614 session', 'CWE-1275 session', 'CWE-352 form #1 action="/login"')
        // HSTS is only raised for HTTPS targets, and the cookie already has HttpOnly.
        ->and($types->filter(fn ($t) => str_starts_with($t, 'CWE-319') || str_starts_with($t, 'CWE-1004')))->toBeEmpty();
});

it('stops a cancelled scan before running the next tool', function () {
    fakeWeakTarget();
    $scan = Scan::factory()->for(scanTarget())->for(webProfile())->create(['status' => Scan::QUEUED, 'progress' => 0]);
    $scan->update(['status' => Scan::CANCELLED]);

    (new RunScan($scan->id, scanAnalyst()->id))->handle(app(App\Services\Agent\ToolExecutor::class), app(App\Tools\ToolRegistry::class), app(FindingRules::class));

    expect($scan->fresh()->status)->toBe(Scan::CANCELLED)->and($scan->toolCalls()->count())->toBe(0);
});

it('fails the scan when the target is no longer verified', function () {
    $scan = Scan::factory()->for(scanTarget(Target::REJECTED))->for(webProfile())->create(['status' => Scan::QUEUED]);

    (new RunScan($scan->id, scanAnalyst()->id))->handle(app(App\Services\Agent\ToolExecutor::class), app(App\Tools\ToolRegistry::class), app(FindingRules::class));

    expect($scan->fresh()->status)->toBe(Scan::FAILED);
});

it('closes findings of the original scan that a re-test no longer finds', function () {
    Http::fake(['localhost:8000*' => Http::response('ok', 200, [
        'Content-Security-Policy' => "default-src 'self'", 'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer', 'Permissions-Policy' => 'camera=()',
    ])]);
    $rules = app(FindingRules::class);
    $profile = webProfile(['check_security_headers']);
    $original = Scan::factory()->for(scanTarget())->for($profile)->create();
    $csp = $original->findings()->create(['vulnerability_type_id' => $rules->type('missing-csp')->id, 'severity' => 'medium',
        'url_terdampak' => 'http://localhost:8000', 'parameter' => 'Content-Security-Policy', 'status' => Finding::CONFIRMED]);
    $frames = $original->findings()->create(['vulnerability_type_id' => $rules->type('missing-frame-options')->id, 'severity' => 'medium',
        'url_terdampak' => 'http://localhost:8000', 'parameter' => 'X-Frame-Options', 'status' => Finding::OPEN]);
    $retest = Scan::factory()->for($original->target)->for($profile)->create(['status' => Scan::QUEUED, 'retest_of_id' => $original->id]);

    (new RunScan($retest->id, scanAnalyst()->id))->handle(app(App\Services\Agent\ToolExecutor::class), app(App\Tools\ToolRegistry::class), $rules);

    expect($csp->fresh()->status)->toBe(Finding::FIXED)
        ->and($frames->fresh()->status)->toBe(Finding::OPEN);
});

it('saves each report version as an html snapshot of the findings', function () {
    Storage::fake('local');
    $scan = Scan::factory()->for(scanTarget())->create(['status' => Scan::COMPLETED]);
    Finding::factory()->for($scan)->create(['url_terdampak' => 'http://localhost:8000/<script>']);

    $first = app(GenerateReport::class)->handle(scanAnalyst(), $scan);
    $second = app(GenerateReport::class)->handle(scanAnalyst(), $scan);

    expect([$first->versi, $second->versi])->toBe([1, 2]);
    Storage::disk('local')->assertExists($second->file_path);
    expect(Storage::disk('local')->get($second->file_path))
        ->toContain($scan->target->nama)
        ->toContain('&lt;script&gt;')
        ->not->toContain('http://localhost:8000/<script>');

    $running = Scan::factory()->create(['status' => Scan::RUNNING]);
    expect(fn () => app(GenerateReport::class)->handle(scanAnalyst(), $running))->toThrow(ValidationException::class);
});

it('adds the AI analysis of a report as a comment', function () {
    Storage::fake('local');
    config()->set('llm.base_url', 'https://llm.test/v1');
    Http::fake(['llm.test/*' => Http::response(['model' => 'qwen3:14b', 'choices' => [['message' => ['role' => 'assistant', 'content' => 'Perbaiki CSRF dulu.']]]])]);
    $analyst = scanAnalyst();
    $scan = Scan::factory()->for(scanTarget())->create(['status' => Scan::COMPLETED]);
    $report = app(GenerateReport::class)->handle($analyst, $scan);

    $comment = app(AnalyzeReport::class)->handle($analyst, $report);

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->user_id)->toBe($analyst->id)
        ->and($comment->isi)->toStartWith('Analisis AI (qwen3:14b)')->toContain('Perbaiki CSRF dulu.');
    Http::assertSent(fn ($request) => ! isset($request->data()['tools']));
});

it('reports a clear error when the AI is down', function () {
    Storage::fake('local');
    config()->set('llm.base_url', 'https://llm.test/v1');
    Http::fake(['llm.test/*' => Http::response('down', 504)]);
    $analyst = scanAnalyst();
    $report = app(GenerateReport::class)->handle($analyst, Scan::factory()->for(scanTarget())->create(['status' => Scan::COMPLETED]));

    expect(fn () => app(AnalyzeReport::class)->handle($analyst, $report))->toThrow(ValidationException::class);
});
