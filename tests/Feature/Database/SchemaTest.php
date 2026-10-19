<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\Target;
use App\Models\TargetTestAccount;
use App\Models\ToolCall;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function chatWithToolCalls(User $user, int $messages): ChatSession
{
    $session = ChatSession::factory()->for($user)->create();

    ChatMessage::factory($messages)->for($session)->create()->each(
        fn (ChatMessage $message) => ToolCall::factory(2)->for($message)
            ->for(Finding::factory(), 'loggable')
            ->create()
    );

    return $session;
}

it('shows the chat history with a constant number of queries (no N+1)', function () {
    $user = User::factory()->create();

    $countQueries = function (ChatSession $session) use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('chat-sessions.show', $session))->assertOk();

        return count(DB::getQueryLog());
    };

    expect($countQueries(chatWithToolCalls($user, 2)))
        ->toBe($countQueries(chatWithToolCalls($user, 10)));
});

it('hides other users\' chat sessions', function () {
    $session = chatWithToolCalls(User::factory()->create(), 1);

    $this->actingAs(User::factory()->create())
        ->get(route('chat-sessions.show', $session))
        ->assertNotFound();
});

it('cascades deletes from a user down to messages and tool calls', function () {
    $user = User::factory()->create();
    chatWithToolCalls($user, 3);

    $user->delete();

    expect(ChatSession::count())->toBe(0)
        ->and(ChatMessage::count())->toBe(0)
        ->and(ToolCall::count())->toBe(0);
});

it('cascades deletes from a target down to scans and findings', function () {
    $target = Target::factory()->create();
    Finding::factory(2)->for(Scan::factory()->for($target))->create();

    $target->delete();

    expect(Scan::count())->toBe(0)
        ->and(Finding::count())->toBe(0);
});

it('links a retest to its original scan', function () {
    $original = Scan::factory()->create();
    $retest = Scan::factory()->for($original->target)->create(['retest_of_id' => $original->id]);

    expect($retest->retestOf->is($original))->toBeTrue()
        ->and($original->retests->pluck('id')->all())->toBe([$retest->id]);
});

it('stores test account passwords encrypted', function () {
    $account = TargetTestAccount::factory()->create(['encrypted_password' => 'rahasia123']);

    expect(DB::table('target_test_accounts')->value('encrypted_password'))->not->toBe('rahasia123')
        ->and($account->fresh()->encrypted_password)->toBe('rahasia123');
});
