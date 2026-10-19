<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Comment;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanProfile;
use App\Models\Target;
use App\Models\TargetScopeRule;
use App\Models\TargetTestAccount;
use App\Models\Tool;
use App\Models\ToolCall;
use App\Models\User;
use App\Models\VulnerabilityType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndAdminSeeder::class);

        // Dev logins (password: "password"); the admin comes from ADMIN_* in .env.
        $analyst = User::factory()->create(['name' => 'Analyst', 'email' => 'analyst@example.com'])
            ->assignRole('analyst');
        $client = User::factory()->create(['name' => 'Client PT XXX', 'email' => 'client@example.com'])
            ->assignRole('client');

        $types = VulnerabilityType::factory(5)->create();
        $profiles = ScanProfile::factory(3)->create();
        $tools = Tool::factory(3)->create();

        $targets = Target::factory(2)
            ->for($client)
            ->state(['status_verifikasi' => Target::VERIFIED])
            ->has(TargetScopeRule::factory(2))
            ->has(TargetTestAccount::factory(2))
            ->create();

        $findings = collect();

        foreach ($targets as $target) {
            $scan = Scan::factory()->for($target)->for($profiles->random())->create();
            $findings->push(...Finding::factory(3)->for($scan)
                ->state(fn () => ['vulnerability_type_id' => $types->random()->id])
                ->create());
            Report::factory()->for($scan)->create();

            $retest = Scan::factory()->for($target)->for($profiles->random())
                ->create(['retest_of_id' => $scan->id]);
            Certificate::factory()->for($retest)->create();
        }

        $discussed = $findings->first();
        Comment::factory()->for($client)->for($discussed, 'commentable')
            ->create(['isi' => 'Sudah kami patch di staging, mohon re-test.']);
        Comment::factory()->for($analyst)->for($discussed, 'commentable')
            ->create(['isi' => 'Siap, re-test dijadwalkan.']);

        ChatSession::factory(3)
            ->for($analyst)
            ->has(ChatMessage::factory(4))
            ->create();

        foreach (ChatMessage::all() as $message) {
            $finding = $findings->random();

            ToolCall::factory()
                ->for($message)
                ->for($tools->random())
                ->create([
                    'scan_id' => $finding->scan_id,
                    'loggable_type' => $finding->getMorphClass(),
                    'loggable_id' => $finding->id,
                ]);
        }
    }
}
