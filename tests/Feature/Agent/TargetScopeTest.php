<?php

use App\Models\Target;
use App\Services\Agent\TargetScope;

beforeEach(function () {
    config()->set('llm.allowed_hosts', ['app.test', 'api.app.test', 'admin.app.test', 'other.test']);

    $this->target = Target::factory()->create(['base_url' => 'https://app.test']);
    $this->scope = new TargetScope();
});

it('allows the target base host, case-insensitively', function () {
    expect($this->scope->allows($this->target, 'https://app.test/login'))->toBeTrue()
        ->and($this->scope->allows($this->target, 'http://APP.test'))->toBeTrue();
});

it('refuses non-http schemes', function () {
    expect($this->scope->allows($this->target, 'ftp://app.test'))->toBeFalse()
        ->and($this->scope->allows($this->target, 'not a url'))->toBeFalse();
});

it('refuses an allowlisted host that is not part of the target', function () {
    expect($this->scope->allows($this->target, 'https://other.test'))->toBeFalse();
});

it('allows hosts matched by an include rule', function () {
    $this->target->targetScopeRules()->create(['pola_domain' => '*.app.test', 'tipe' => 'include']);

    expect($this->scope->allows($this->target, 'https://api.app.test/v1'))->toBeTrue();
});

it('still requires the config allowlist for included hosts', function () {
    $this->target->targetScopeRules()->create(['pola_domain' => '*.app.test', 'tipe' => 'include']);

    expect($this->scope->allows($this->target, 'https://cdn.app.test'))->toBeFalse();
});

it('lets exclude rules win over include rules and the base host', function () {
    $this->target->targetScopeRules()->createMany([
        ['pola_domain' => '*.app.test', 'tipe' => 'include'],
        ['pola_domain' => 'admin.app.test', 'tipe' => 'exclude'],
        ['pola_domain' => 'app.test', 'tipe' => 'exclude'],
    ]);

    expect($this->scope->allows($this->target, 'https://api.app.test'))->toBeTrue()
        ->and($this->scope->allows($this->target, 'https://admin.app.test'))->toBeFalse()
        ->and($this->scope->allows($this->target, 'https://app.test'))->toBeFalse();
});
