# PBKK Kelompok 4 - Team Rules

- Stack is fixed by the course: Laravel 13, Breeze (Livewire/Volt class API), Livewire 3, SQLite + Eloquent, Spatie Permission, NativePHP Desktop, Pest. Do not swap or add frameworks.
- Code style is PSR-12 (`vendor/bin/pint`). Code and identifiers in English; README and PR text may be Indonesian.
- Never commit to `main`. Branch as `<type>/<area>-<desc>` (e.g. `feat/be1-chat-models`), Conventional Commits, PR + 1 approval + green CI.
- Stay inside your area (see `.github/CODEOWNERS`). Changes outside it need that owner's review.
- LLM calls go through the OpenAI-compatible endpoint in `config/llm.php`. Tests must use `Http::fake()`; never hit Senopati/Ollama from tests.
- Tools implement `App\Tools\Tool` and are registered in `config/llm.php` `tools`. Tool arguments come from the LLM: validate them, and run external commands only via `Symfony\Component\Process\Process` with an argument array (never `shell_exec`, never string commands).
- Long-running tool executions run in a queued job (`ShouldQueue`, `QUEUE_CONNECTION=database`).
- Avoid N+1: eager load nested relations with `with()`; prove it with Laravel Debugbar.
