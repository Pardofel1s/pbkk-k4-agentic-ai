<?php

use App\Jobs\RunScan;
use App\Models\Comment;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanProfile;
use App\Models\Target;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

function workspaceUser(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

/**
 * A client's verified target with one completed scan, one finding and one report.
 *
 * @return array{client: User, target: Target, scan: Scan, finding: Finding, report: Report}
 */
function workspaceFixture(): array
{
    Storage::fake('local');
    $client = workspaceUser('client');
    $target = Target::factory()->for($client)->create(['status_verifikasi' => Target::VERIFIED, 'base_url' => 'http://localhost:8000']);
    $scan = Scan::factory()->for($target)->for(ScanProfile::factory()->state(['pengaturan' => ['tools' => ['check_security_headers']]]))->create(['status' => Scan::COMPLETED]);
    $finding = Finding::factory()->for($scan)->create(['status' => Finding::OPEN, 'url_terdampak' => 'http://localhost:8000/']);
    $report = app(App\Actions\GenerateReport::class)->handle(workspaceUser('analyst'), $scan);

    return compact('client', 'target', 'scan', 'finding', 'report');
}

test('landing is public and private workspace routes require authentication', function () {
    $this->get('/')->assertOk()->assertSee('Setiap celah,')->assertDontSee('data simulasi');
    foreach (['dashboard', 'targets', 'scans/create', 'findings', 'reports', 'certificates', 'settings', 'tools', 'users', 'agent'] as $path) {
        $this->get('/'.$path)->assertRedirect('/login');
    }
});

test('every workspace page renders real data for each role without demo placeholders', function () {
    ['client' => $client, 'target' => $target, 'scan' => $scan, 'finding' => $finding, 'report' => $report] = workspaceFixture();
    $admin = User::query()->firstWhere('email', config('auth.admin.email'));

    $pages = ['dashboard', 'targets', "targets/{$target->id}", 'scans', "scans/{$scan->id}", 'findings', "findings/{$finding->id}",
        'reports', "reports/{$report->id}", 'certificates', 'vulnerability-types', 'users', 'settings', 'profile'];

    foreach ([[workspaceUser('analyst'), [...$pages, 'scans/create', 'scan-profiles', 'tools', "executions/{$scan->id}", 'agent']],
        [$admin, [...$pages, 'targets/create', 'scan-profiles', 'tools', "executions/{$scan->id}"]],
        [$client, [...$pages, 'targets/create']]] as [$user, $paths]) {
        $this->actingAs($user);
        foreach ($paths as $path) {
            $this->get('/'.$path)->assertOk()->assertDontSee('Mode demo')->assertDontSee('simulasi')->assertDontSee('CONTOH');
        }
    }

    $this->actingAs(workspaceUser('analyst'))->get('/targets/'.$target->id)->assertSee($target->base_url);
    $this->get('/targets/999999')->assertNotFound();
    $this->get('/targets/abc')->assertNotFound();
});

test('clients only reach their own records and cannot run scans or see tool output', function () {
    ['scan' => $scan, 'finding' => $finding, 'report' => $report, 'target' => $target] = workspaceFixture();
    $this->actingAs(workspaceUser('client'));

    foreach (["targets/{$target->id}", "scans/{$scan->id}", "findings/{$finding->id}", "reports/{$report->id}"] as $path) {
        $this->get('/'.$path)->assertForbidden();
    }
    $this->get('/scans/create')->assertForbidden();
    $this->get("/executions/{$scan->id}")->assertForbidden();
    $this->get('/targets')->assertOk()->assertDontSee($target->nama);
});

test('/chat now points to the live agent', function () {
    $this->actingAs(workspaceUser('analyst'));
    $this->get('/chat')->assertRedirect('/agent');
    $this->get('/chat/1')->assertRedirect('/agent');
});

test('clients register pending targets with scope rules; html is escaped and bad patterns are refused', function () {
    $client = workspaceUser('client');
    $this->actingAs($client);

    Volt::test('pages.workspace', ['area' => 'target-create'])
        ->set('targetForm.base_url', 'javascript:alert(1)')->call('saveTarget')->assertHasErrors(['targetForm.nama', 'targetForm.base_url']);
    Volt::test('pages.workspace', ['area' => 'target-create'])
        ->set('targetForm', ['nama' => 'X', 'base_url' => 'https://app.test', 'lingkungan' => 'staging', 'include' => 'evil host!', 'exclude' => ''])
        ->call('saveTarget')->assertHasErrors('targetForm.include');
    Volt::test('pages.workspace', ['area' => 'target-create'])
        ->set('targetForm', ['nama' => '<script>alert(1)</script>', 'base_url' => 'https://app.test/', 'lingkungan' => 'uat', 'include' => "api.app.test\n*.cdn.app.test", 'exclude' => 'pay.app.test'])
        ->call('saveTarget')->assertHasNoErrors()->assertRedirect();

    $target = $client->targets()->with('targetScopeRules')->sole();
    expect($target->status_verifikasi)->toBe(Target::PENDING)
        ->and($target->base_url)->toBe('https://app.test')
        ->and($target->targetScopeRules->pluck('tipe', 'pola_domain')->all())->toBe(['api.app.test' => 'include', '*.cdn.app.test' => 'include', 'pay.app.test' => 'exclude']);
    $this->get('/targets')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

    $this->actingAs(workspaceUser('analyst'));
    Volt::test('pages.workspace', ['area' => 'target-create'])->assertForbidden();
});

test('admin verifies a target, which then locks it for the client', function () {
    $client = workspaceUser('client');
    $target = Target::factory()->for($client)->create(['status_verifikasi' => Target::PENDING]);

    $this->actingAs(User::query()->firstWhere('email', config('auth.admin.email')));
    Volt::test('pages.workspace', ['area' => 'targets', 'record' => (string) $target->id])->call('verifyTarget', Target::VERIFIED);
    expect($target->fresh()->status_verifikasi)->toBe(Target::VERIFIED);

    $this->actingAs($client);
    Volt::test('pages.workspace', ['area' => 'targets', 'record' => (string) $target->id])
        ->set('targetForm.nama', 'Diubah')->call('saveTarget')->assertForbidden();
    Volt::test('pages.workspace', ['area' => 'targets', 'record' => (string) $target->id])->call('verifyTarget', Target::PENDING)->assertForbidden();
});

test('test account passwords are stored encrypted and never rendered', function () {
    $client = workspaceUser('client');
    $target = Target::factory()->for($client)->create(['status_verifikasi' => Target::PENDING]);
    $this->actingAs($client);

    Volt::test('pages.workspace', ['area' => 'targets', 'record' => (string) $target->id])
        ->set('accountForm', ['label_role' => 'nasabah', 'username' => 'budi', 'password' => 'Rahasia#123'])
        ->call('addAccount')->assertHasNoErrors();

    expect(Illuminate\Support\Facades\DB::table('target_test_accounts')->value('encrypted_password'))->not->toContain('Rahasia#123');
    $this->get("/targets/{$target->id}?tab=accounts")->assertSee('budi')->assertDontSee('Rahasia#123');
});

test('the scan wizard queues a real scan only on verified targets after acknowledgement', function () {
    Bus::fake();
    $analyst = workspaceUser('analyst');
    $pending = Target::factory()->create(['status_verifikasi' => Target::PENDING]);
    $target = Target::factory()->create(['status_verifikasi' => Target::VERIFIED]);
    $profile = ScanProfile::factory()->create(['pengaturan' => ['tools' => ['check_security_headers']]]);
    $this->actingAs($analyst);

    $wizard = Volt::test('pages.workspace', ['area' => 'scan-create']);
    $wizard->set('targetId', $pending->id)->call('nextStep')->assertHasErrors('targetId');
    $wizard->set('targetId', $target->id)->set('profileId', $profile->id)->call('startScan')->assertHasErrors('acknowledged');
    $wizard->set('acknowledged', true)->call('startScan')->assertHasNoErrors()->assertRedirect();

    $scan = Scan::query()->sole();
    expect($scan->target_id)->toBe($target->id)->and($scan->status)->toBe(Scan::QUEUED);
    Bus::assertDispatched(RunScan::class);
});

test('analysts cancel running scans and re-test completed ones', function () {
    Bus::fake();
    ['scan' => $scan] = workspaceFixture();
    $running = Scan::factory()->for($scan->target)->for($scan->scanProfile)->create(['status' => Scan::RUNNING]);
    $this->actingAs(workspaceUser('analyst'));

    Volt::test('pages.workspace', ['area' => 'scans', 'record' => (string) $running->id])->call('cancelScan');
    expect($running->fresh()->status)->toBe(Scan::CANCELLED);

    Volt::test('pages.workspace', ['area' => 'scans', 'record' => (string) $scan->id])->call('retryScan')->assertRedirect();
    expect(Scan::query()->latest('id')->first()->retest_of_id)->toBe($scan->id);
});

test('analysts review findings, clients comment, and fixed is left to re-tests', function () {
    ['client' => $client, 'finding' => $finding] = workspaceFixture();
    $analyst = workspaceUser('analyst');

    $this->actingAs($analyst);
    Volt::test('pages.workspace', ['area' => 'findings', 'record' => (string) $finding->id])
        ->set('reviewStatus', Finding::FIXED)->call('saveReview')->assertHasErrors('reviewStatus')
        ->set('reviewStatus', Finding::CONFIRMED)->call('saveReview')->assertHasNoErrors();
    expect($finding->fresh()->status)->toBe(Finding::CONFIRMED);

    $this->actingAs($client);
    Volt::test('pages.workspace', ['area' => 'findings', 'record' => (string) $finding->id])
        ->set('reviewStatus', Finding::FALSE_POSITIVE)->call('saveReview')->assertForbidden();
    Volt::test('pages.workspace', ['area' => 'findings', 'record' => (string) $finding->id])
        ->set('comment', 'Sudah kami **patch** <script>x</script>')->call('addComment')->assertHasNoErrors();

    $comment = Comment::query()->sole();
    expect($comment->user_id)->toBe($client->id);
    $this->get("/findings/{$finding->id}")->assertSee('<strong>patch</strong>', false)->assertDontSee('<script>x</script>', false);
});

test('reports get new versions and download as the stored html snapshot', function () {
    ['scan' => $scan, 'report' => $report, 'client' => $client] = workspaceFixture();
    $this->actingAs(workspaceUser('analyst'));

    Volt::test('pages.workspace', ['area' => 'reports', 'record' => (string) $report->id])->call('generateReport')->assertRedirect();
    expect($scan->reports()->max('versi'))->toBe(2);

    $this->actingAs($client);
    Volt::test('pages.workspace', ['area' => 'reports', 'record' => (string) $report->id])
        ->call('downloadReport')->assertFileDownloaded("jejaksa-scan-{$scan->id}-v1.html");
    Volt::test('pages.workspace', ['area' => 'reports', 'record' => (string) $report->id])->call('generateReport')->assertForbidden();
});

test('admins issue certificates only for clean re-tests', function () {
    ['scan' => $scan] = workspaceFixture();
    $retest = Scan::factory()->for($scan->target)->for($scan->scanProfile)->create(['status' => Scan::COMPLETED, 'retest_of_id' => $scan->id]);
    $dirty = Scan::factory()->for($scan->target)->for($scan->scanProfile)->create(['status' => Scan::COMPLETED, 'retest_of_id' => $scan->id]);
    Finding::factory()->for($dirty)->create(['status' => Finding::OPEN]);

    $this->actingAs(User::query()->firstWhere('email', config('auth.admin.email')));
    Volt::test('pages.workspace', ['area' => 'certificates'])->call('issueCertificate', $dirty->id)->assertHasErrors('scan');
    Volt::test('pages.workspace', ['area' => 'certificates'])->call('issueCertificate', $retest->id)->assertHasNoErrors();
    expect($retest->certificate()->exists())->toBeTrue();
    $this->get('/certificates')->assertSee($retest->certificate->nomor);

    $this->actingAs(workspaceUser('analyst'));
    Volt::test('pages.workspace', ['area' => 'certificates'])->call('issueCertificate', $dirty->id)->assertForbidden();
});

test('only admins toggle tools and register analysts; analysts create scan profiles', function () {
    $admin = User::query()->firstWhere('email', config('auth.admin.email'));
    $this->actingAs($admin);
    Volt::test('pages.workspace', ['area' => 'tools'])->assertSee('check_security_headers');
    $tool = Tool::query()->firstWhere('nama', 'check_security_headers');
    Volt::test('pages.workspace', ['area' => 'tools'])->call('toggleTool', $tool->id);
    expect($tool->fresh()->status)->toBeFalse();
    Volt::test('pages.workspace', ['area' => 'users'])
        ->set('userForm', ['name' => 'Baru', 'email' => 'baru@example.com', 'password' => 'password-123', 'password_confirmation' => 'password-123'])
        ->call('registerAnalyst')->assertHasNoErrors();
    expect(User::query()->firstWhere('email', 'baru@example.com')->hasRole('analyst'))->toBeTrue();

    $this->actingAs(workspaceUser('analyst'));
    Volt::test('pages.workspace', ['area' => 'tools'])->call('toggleTool', $tool->id)->assertForbidden();
    Volt::test('pages.workspace', ['area' => 'scan-profiles'])
        ->set('profileForm', ['nama' => 'Cepat', 'tipe' => 'quick', 'deskripsi' => '', 'tools' => ['not_a_tool']])->call('saveProfile')->assertHasErrors('profileForm.tools.0')
        ->set('profileForm.tools', ['check_cookie_flags'])->call('saveProfile')->assertHasNoErrors();
    expect(ScanProfile::query()->firstWhere('nama', 'Cepat')->pengaturan)->toBe(['tools' => ['check_cookie_flags']]);
});
