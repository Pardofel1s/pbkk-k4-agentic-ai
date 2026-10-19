<?php

namespace App\Services\Agent;

use App\Models\ChatMessage;
use App\Models\Tool as ToolRecord;
use App\Models\ToolCall as ToolCallRecord;
use App\Services\Llm\ToolCall as LlmToolCall;
use App\Tools\ToolContext;
use App\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Runs one tool call the LLM asked for and logs it in `tool_calls`.
 *
 * It never throws: whatever happens, the LLM gets a text answer back, so the
 * agent loop can carry on (or explain the failure to the user).
 */
class ToolExecutor
{
    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    /**
     * Longest a tool may run (see Tool::handle()); the agent loop only starts
     * a tool when this much of the turn's time budget is left.
     */
    public const MAX_SECONDS = 120;

    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    /**
     * An unknown tool name is only written to the Laravel log, because
     * `tool_calls.tool_id` must point at a real tool.
     */
    public function execute(LlmToolCall $call, ChatMessage $parent, ToolContext $context): string
    {
        try {
            $tool = $this->tools->get($call->name);
        } catch (InvalidArgumentException) {
            Log::warning('LLM requested an unknown tool.', ['tool' => $call->name, 'chat_message_id' => $parent->id]);

            return "Error: unknown tool [{$call->name}].";
        }

        $record = ToolRecord::query()->firstOrCreate(
            ['nama' => $tool->name()],
            ['deskripsi' => $tool->description(), 'status' => true],
        );

        $log = $this->startLog($call, $parent, $context, $record);

        if (! $record->status) {
            return $this->fail($log, 'Tool is disabled.', "Error: tool [{$call->name}] is disabled.");
        }

        try {
            $output = $tool->handle($call->arguments, $context);
        } catch (ValidationException $e) {
            $reason = implode(' ', $e->validator->errors()->all());

            return $this->fail($log, $reason, "Error: invalid arguments. {$reason}");
        } catch (Throwable $e) {
            report($e);

            return $this->fail($log, $e->getMessage(), 'Error: the tool failed unexpectedly.');
        }

        $log->update(['status' => self::SUCCESS, 'hasil' => ['output' => $output]]);

        return $output;
    }

    private function startLog(LlmToolCall $call, ChatMessage $parent, ToolContext $context, ToolRecord $record): ToolCallRecord
    {
        $log = $parent->toolCalls()->make([
            'tool_id' => $record->id,
            'scan_id' => $context->scan?->id,
            'parameter' => $call->arguments,
            'status' => self::RUNNING,
        ]);
        $log->loggable()->associate($context->target);
        $log->save();

        return $log;
    }

    private function fail(ToolCallRecord $log, string $reason, string $answer): string
    {
        $log->update(['status' => self::FAILED, 'error_msg' => $reason]);

        return $answer;
    }
}
