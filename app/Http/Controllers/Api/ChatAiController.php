<?php

namespace App\Http\Controllers\Api;

use App\Actions\AskChatAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatAiRequest;
use App\Http\Resources\ChatLogResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatAiController extends Controller
{
    /**
     * Send a prompt to the LLM and return the logged exchange.
     */
    public function store(ChatAiRequest $request, AskChatAi $askChatAi): JsonResponse
    {
        try {
            $log = $askChatAi->handle($request->user(), $request->validated('prompt'));
        } catch (ConnectionException | RequestException $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Layanan LLM tidak dapat dihubungi. Coba lagi nanti.',
            ], 502);
        }

        // A JsonResource answers 201 for a freshly created model; this is a chat reply, so 200.
        return (new ChatLogResource($log))->response()->setStatusCode(200);
    }

    /**
     * Emit text fragments and a final API resource after the log is saved.
     */
    public function stream(ChatAiRequest $request, AskChatAi $askChatAi): StreamedResponse
    {
        $user = $request->user();
        $prompt = $request->validated('prompt');

        return response()->stream(function () use ($user, $prompt, $request, $askChatAi): void {
            try {
                $log = $askChatAi->handle($user, $prompt, function (string $text): void {
                    $this->sendEvent('chunk', ['text' => $text]);
                });

                $this->sendEvent('done', (new ChatLogResource($log))->resolve($request));
            } catch (Throwable $exception) {
                report($exception);

                $this->sendEvent('error', [
                    'status' => 'error',
                    'message' => 'Percakapan gagal diselesaikan. Silakan coba lagi.',
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )."\n\n";

        if (ob_get_level() > 0 && (ob_get_status()['flags'] & PHP_OUTPUT_HANDLER_FLUSHABLE)) {
            ob_flush();
        }

        flush();
    }
}
