<?php

use App\Livewire\DemoWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('landing is public and private workspace routes require authentication', function () {
    $this->get('/')->assertOk()->assertSee('Setiap celah,')->assertSee('data simulasi');
    foreach (['dashboard','targets','scans/create','findings','chat','reports','certificates','settings','tools','users'] as $path) {
        $this->get('/'.$path)->assertRedirect('/login');
    }
});

test('all workspace pages and detail views render for authenticated users', function () {
    $this->actingAs(User::factory()->create());
    foreach (['dashboard','targets','targets/create','targets/t1','scans','scans/create','scans/s1','findings','findings/f1','chat','chat/c1','reports','reports/r1','certificates','scan-profiles','tools','vulnerability-types','users','settings','executions/s1','profile'] as $path) {
        $this->get('/'.$path)->assertOk()->assertSee('Mode demo');
    }
    $this->get('/targets/unknown')->assertNotFound();
});

test('target creation validates input and persists escaped data only in the current users demo session', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->actingAs($first);
    Http::preventStrayRequests();
    Bus::fake();
    Volt::test('pages.workspace', ['area' => 'target-create'])
        ->set('targetForm.url', 'javascript:alert(1)')->call('saveTarget')->assertHasErrors(['targetForm.name','targetForm.url']);
    Volt::test('pages.workspace', ['area' => 'target-create'])
        ->set('targetForm.name', '<script>alert(1)</script>')
        ->set('targetForm.url', 'https://private.example.test')
        ->set('targetForm.authorized', true)->call('saveTarget')->assertHasNoErrors();
    expect(DemoWorkspace::read()['targets'])->toHaveCount(4);
    $this->get('/targets')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->actingAs($second);
    expect(DemoWorkspace::read()['targets'])->toHaveCount(3);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('scan wizard requires valid target permission and acknowledgement and never executes external work', function () {
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    Bus::fake();
    $component = Volt::test('pages.workspace', ['area' => 'scan-create']);
    $component->call('startScan')->assertHasErrors(['acknowledged']);
    $component->set('acknowledged', true)->set('targetId', 't3')->call('startScan')->assertHasErrors(['targetId']);
    $component->set('targetId', 'missing')->call('startScan')->assertHasErrors(['targetId']);
    $component->set('targetId', 't1')->set('account', 'unassigned-user')->call('startScan')->assertHasErrors(['account']);
    $component->set('account', '')->call('startScan')->assertHasNoErrors();
    $component->call('startScan')->assertHasNoErrors();
    $scans = DemoWorkspace::read()['scans'];
    expect($scans)->toHaveCount(4);
    $new = end($scans);
    expect($new['status'])->toBe('queued')->and($new['scope'])->toBe('/');
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('cancellation remains requested until the demo confirmation and retries create a separate scan', function () {
    $this->actingAs(User::factory()->create());
    $component = Volt::test('pages.workspace', ['area' => 'scans','record' => 's2']);
    $component->call('cancelScan')->assertHasNoErrors();
    expect(DemoWorkspace::find('scans', 's2')['status'])->toBe('cancel_requested');
    $component->call('advanceScan');
    expect(DemoWorkspace::find('scans', 's2')['status'])->toBe('cancelled');
    $component->call('retryScan');
    expect(DemoWorkspace::read()['scans'])->toHaveCount(4);
    expect(DemoWorkspace::find('scans', 's2')['status'])->toBe('cancelled');
});

test('failed simulations can be retried without overwriting the original attempt', function () {
    $this->actingAs(User::factory()->create());
    $component = Volt::test('pages.workspace', ['area' => 'scans', 'record' => 's2']);
    $component->call('failScan');
    expect(DemoWorkspace::find('scans', 's2')['status'])->toBe('failed');
    $component->call('retryScan');
    expect(DemoWorkspace::read()['scans'])->toHaveCount(4);
    expect(DemoWorkspace::find('scans', 's2')['status'])->toBe('failed');
});

test('review requires reasons and cannot claim resolution without verified retest', function () {
    $this->actingAs(User::factory()->create());
    $component = Volt::test('pages.workspace', ['area' => 'findings','record' => 'f1']);
    $component->set('reviewStatus', 'false_positive')->call('saveReview')->assertHasErrors(['reviewNote']);
    $component->set('reviewNote', 'Pemeriksaan bukti contoh telah ditinjau.')->call('saveReview')->assertHasNoErrors();
    expect(DemoWorkspace::find('findings', 'f1')['status'])->toBe('false_positive');
    $component->set('reviewStatus', 'resolved')->call('saveReview')->assertHasErrors(['reviewStatus']);
    expect(DemoWorkspace::find('findings', 'f1')['status'])->toBe('false_positive');
    $component->set('reviewStatus', 'fixed_pending_retest')->call('saveReview')->call('retestFinding');
    $scans = DemoWorkspace::read()['scans'];
    expect(end($scans))->toMatchArray(['retest_of' => 's1', 'finding_source' => 'f1', 'status' => 'queued']);
    expect(DemoWorkspace::find('findings', 'f1')['status'])->toBe('fixed_pending_retest');
});

test('report versions preserve snapshots and export clearly marked escaped demo HTML', function () {
    $this->actingAs(User::factory()->create());
    Volt::test('pages.workspace', ['area' => 'scans','record' => 's2'])->call('generateReport', 's2')->assertHasErrors(['report']);
    $original = DemoWorkspace::find('reports', 'r1')['findings_snapshot'];
    Volt::test('pages.workspace', ['area' => 'findings','record' => 'f1'])
        ->set('reviewStatus', 'confirmed')->set('reviewNote', 'Contoh alasan review yang valid.')->call('saveReview');
    expect(DemoWorkspace::find('reports', 'r1')['findings_snapshot'])->toBe($original);
    Volt::test('pages.workspace', ['area' => 'scans','record' => 's1'])->call('generateReport', 's1')->assertHasNoErrors();
    $reports = DemoWorkspace::read()['reports'];
    expect($reports)->toHaveCount(2)->and(end($reports)['version'])->toBe(2);
    Volt::test('pages.workspace', ['area' => 'reports','record' => 'r1'])->call('downloadReport')->assertFileDownloaded('jejaksa-demo-r1-v1.html');
    $this->get('/reports/r1')->assertSee('CONTOH')->assertSee('DATA SIMULASI');
});

test('chat creates only an explicitly approved demo plan and escapes message HTML', function () {
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    $component = Volt::test('pages.workspace', ['area' => 'chat','record' => 'c1']);
    $component->set('message', '<img src=x onerror=alert(1)>')->call('sendMessage')->assertHasNoErrors()->assertSee('&lt;img', false);
    expect(DemoWorkspace::read()['scans'])->toHaveCount(3);
    $component->call('confirmPlan');
    expect(DemoWorkspace::read()['scans'])->toHaveCount(4);
    expect(DemoWorkspace::find('chats', 'c1')['plan'])->toBeFalse();
    Http::assertNothingSent();
});

test('tool activation never changes readiness and certificate issuance stays unavailable', function () {
    $this->actingAs(User::factory()->create());
    Volt::test('pages.workspace', ['area' => 'tools'])->call('toggleTool', 'discovery');
    expect(DemoWorkspace::find('tools', 'discovery'))->toMatchArray(['active' => true,'ready' => false]);
    $this->get('/certificates')->assertSee('Penerbitan belum tersedia')->assertSee('CONTOH');
});
