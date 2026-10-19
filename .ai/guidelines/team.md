# PBKK Kelompok 4 - Team Rules

- Before starting a task, read `README.md` (roles, area ownership, task checklist, ERD) and `docs/rancangan.md` (DAST case study). Ask the user which role they have if it is unclear.
- Security tools must only reach hosts on the allowlist (`config('llm.allowed_hosts')`) and the target's `target_scope_rules`. Never point tools at third-party systems; demos run against our own local dummy target.
- Stack is fixed by the course: Laravel 13, Breeze (Livewire/Volt class API), Livewire 3, SQLite + Eloquent, Spatie Permission, NativePHP Desktop, Pest. Do not swap or add frameworks.
- Code style is PSR-12 (`vendor/bin/pint`). Code and identifiers in English; README and PR text may be Indonesian.
- Never commit to `main`. Branch as `<type>/<area>-<desc>` (e.g. `feat/be1-chat-models`), Conventional Commits, PR with green CI and every "Cek sebelum merge" checklist item ticked (self-merge allowed).
- Stay inside your area (see `.github/CODEOWNERS`). Changes outside it should be reviewed by that owner.
- Never ask for, print, or commit `LLM_API_KEY`; teammates get it from Kamal (@Pardofel1s) privately.
- LLM calls go through the OpenAI-compatible endpoint in `config/llm.php`. Tests must use `Http::fake()`; never hit Senopati/Ollama from tests.
- Tools implement `App\Tools\Tool` and are registered in `config/llm.php` `tools`. Tool arguments come from the LLM: validate them, and run external commands only via `Symfony\Component\Process\Process` with an argument array (never `shell_exec`, never string commands).
- Long-running tool executions run in a queued job (`ShouldQueue`, `QUEUE_CONNECTION=database`).
- Avoid N+1: eager load nested relations with `with()`; prove it with Laravel Debugbar.
