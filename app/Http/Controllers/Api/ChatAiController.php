<?php

namespace App\Http\Controllers\Api;

use App\Actions\AskChatAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatAiRequest;
use App\Http\Resources\ChatLogResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class ChatAiController extends Controller
{
    /**
     * Send a prompt to the LLM and return the logged exchange.
     */
    public function store(ChatAiRequest $request, AskChatAi $askChatAi): JsonResponse
    {
        try {
            $log = $askChatAi->handle($request->user(), $request->validated('prompt'));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Layanan LLM tidak dapat dihubungi. Coba lagi nanti.',
            ], 502);
        }

        // A JsonResource answers 201 for a freshly created model; this is a chat reply, so 200.
        return (new ChatLogResource($log))->response()->setStatusCode(200);
    }
}
