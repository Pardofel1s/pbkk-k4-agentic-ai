<?php

use App\Tools\Tool;
use App\Tools\ToolRegistry;

function fakeTool(): Tool
{
    return new class () implements Tool {
        public function name(): string
        {
            return 'echo';
        }

        public function description(): string
        {
            return 'Echo the given text.';
        }

        public function parameters(): array
        {
            return ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']];
        }

        public function handle(array $arguments): string
        {
            return $arguments['text'];
        }
    };
}

it('exposes tools in the OpenAI tools format', function () {
    $schemas = (new ToolRegistry([fakeTool()]))->schemas();

    expect($schemas)->toHaveCount(1)
        ->and($schemas[0]['type'])->toBe('function')
        ->and($schemas[0]['function']['name'])->toBe('echo')
        ->and($schemas[0]['function']['parameters']['required'])->toBe(['text']);
});

it('runs a registered tool by name', function () {
    expect((new ToolRegistry([fakeTool()]))->get('echo')->handle(['text' => 'hi']))->toBe('hi');
});

it('rejects tools that are not registered', function () {
    (new ToolRegistry([fakeTool()]))->get('rm_rf');
})->throws(InvalidArgumentException::class);
