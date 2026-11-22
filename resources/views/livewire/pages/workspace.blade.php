<?php

use App\Actions\AnalyzeReport;
use App\Actions\CreateTarget;
use App\Actions\GenerateReport;
use App\Actions\IssueCertificate;
use App\Actions\RegisterAnalyst;
use App\Actions\StartScan;
use App\Actions\VerifyTarget;
use App\Models\Certificate;
use App\Models\Comment;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanProfile;
use App\Models\Target;
use App\Models\TargetScopeRule;
use App\Models\Tool;
use App\Models\User;
use App\Models\VulnerabilityType;
use App\Tools\ToolRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component {
    /** Host pattern for scope rules: app.test or *.app.test. */
    private const HOST_PATTERN = '/^(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)*$/i';

    #[Locked] public string $area = 'dashboard';
    #[Locked] public ?string $record = null;
    #[Url(except: 'overview')] public string $tab = 'overview';
    #[Url(as: 'q', except: '')] public string $search = '';
    #[Url(except: '')] public string $filter = '';
    public string $notice = '';
    public array $targetForm = ['nama' => '', 'base_url' => '', 'lingkungan' => 'staging', 'include' => '', 'exclude' => ''];
    public array $accountForm = ['label_role' => 'nasabah', 'username' => '', 'password' => ''];
    public array $profileForm = ['nama' => '', 'tipe' => 'quick', 'deskripsi' => '', 'tools' => []];
    public array $userForm = ['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''];
    public bool $showProfileForm = false;
    #[Locked] public int $step = 1;
    public ?int $targetId = null;
    public ?int $profileId = null;
    public ?int $accountId = null;
    public bool $acknowledged = false;
    public string $reviewStatus = Finding::OPEN;
    public string $comment = '';

    public function mount(string $area = 'dashboard', ?string $record = null): void
    {
        $this->area = $area;
        $this->record = $record;

        match ($area) {
            'targets' => Gate::authorize('viewAny', Target::class),
            'target-create' => Gate::authorize('create', Target::class),
            'scan-create' => abort_unless(auth()->user()->can('scans.run'), 403),
            'executions' => abort_unless(auth()->user()->can('targets.view-any'), 403),
            default => null,
        };

        if ($area === 'scan-create') {
            $this->targetId = $this->scannableTargets()->whereKey(request()->integer('target'))->value('id')
                ?? $this->scannableTargets()->value('id');
            $this->profileId = ScanProfile::query()->whereKey(request()->integer('profile'))->value('id')
                ?? ScanProfile::query()->value('id');
        }

        $item = $this->item();

        if ($item instanceof Target) {
            $this->targetForm = [
                'nama' => $item->nama,
                'base_url' => $item->base_url,
                'lingkungan' => $item->lingkungan,
                'include' => $item->targetScopeRules->where('tipe', 'include')->pluck('pola_domain')->implode("\n"),
                'exclude' => $item->targetScopeRules->where('tipe', 'exclude')->pluck('pola_domain')->implode("\n"),
            ];
        }

        if ($item instanceof Finding) {
            $this->reviewStatus = $item->status;
        }
    }

    /**
     * The record of a detail page, authorised for viewing. Missing ids are 404, foreign ones 403.
     */
    private function item(): ?Model
    {
        if ($this->record === null) {
            return null;
        }

        abort_unless(ctype_digit($this->record), 404);

        $item = match ($this->area) {
            'targets' => Target::query()->with(['targetScopeRules', 'targetTestAccounts', 'user'])->find($this->record),
            'scans', 'executions' => Scan::query()->with(['target', 'scanProfile', 'targetTestAccount', 'retestOf', 'certificate'])->find($this->record),
            'findings' => Finding::query()->with(['scan.target', 'vulnerabilityType'])->find($this->record),
            'reports' => Report::query()->with(['scan.target', 'scan.scanProfile'])->find($this->record),
            default => abort(404),
        };

        abort_if($item === null, 404);
        Gate::authorize('view', $item);

        return $item;
    }

    public function with(): array
    {
        $item = $this->item();

        return ['item' => $item, ...match ($this->area) {
            'dashboard' => $this->dashboardData(),
            'targets' => $item ? $this->targetData($item) : ['targets' => $this->visibleTargets()
                ->withCount('targetTestAccounts')
                ->where(fn (Builder $q) => $q->where('nama', 'like', "%{$this->search}%")->orWhere('base_url', 'like', "%{$this->search}%"))
                ->latest('id')->get()],
            'scans' => $item ? $this->scanData($item) : ['scans' => $this->visibleScans()
                ->when($this->filter !== '', fn (Builder $q) => $q->where('status', $this->filter))
                ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    ->whereKey((int) ltrim($this->search, '#'))
                    ->orWhereHas('target', fn (Builder $t) => $t->where('nama', 'like', "%{$this->search}%"))))
                ->latest('id')->get()],
            'scan-create' => $this->wizardData(),
            'findings' => $item ? ['comments' => $item->comments()->with('user')->oldest('id')->get()] : ['findings' => $this->visibleFindings()
                ->when($this->filter !== '', fn (Builder $q) => $q->where('severity', $this->filter))
                ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    ->where('url_terdampak', 'like', "%{$this->search}%")
                    ->orWhereHas('vulnerabilityType', fn (Builder $t) => $t->where('nama', 'like', "%{$this->search}%")->orWhere('kode_cwe', 'like', "%{$this->search}%"))))
                ->latest('id')->get()],
            'reports' => $item ? $this->reportData($item) : ['reports' => Report::query()
                ->with('scan.target')
                ->whereHas('scan', fn (Builder $q) => $q->whereIn('target_id', $this->visibleTargets()->select('id')))
                ->when($this->search !== '', fn (Builder $q) => $q->whereHas('scan.target', fn (Builder $t) => $t->where('nama', 'like', "%{$this->search}%")))
                ->latest('id')->get()],
            'executions' => ['toolCalls' => $item->toolCalls()->with('tool')->oldest('id')->get()],
            'certificates' => $this->certificateData(),
            'scan-profiles' => ['profiles' => ScanProfile::query()->withCount('scans')->orderBy('nama')->get(), 'toolNames' => array_keys(app(ToolRegistry::class)->all())],
            'tools' => ['tools' => $this->toolRows()],
            'vulnerability-types' => ['types' => VulnerabilityType::query()
                ->withCount(['findings' => fn (Builder $q) => $q->whereIn('scan_id', $this->visibleScans()->select('scans.id'))])
                ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode_cwe', 'like', "%{$this->search}%")->orWhere('kategori_owasp', 'like', "%{$this->search}%")))
                ->orderBy('kategori_owasp')->get()],
            'users' => ['users' => auth()->user()->can('users.manage') ? User::query()->with('roles')->orderBy('name')->get() : collect()],
            default => [],
        }];
    }

    /** @return Builder<Target> */
    private function visibleTargets(): Builder
    {
        return Target::query()->visibleTo(auth()->user());
    }

    /** @return Builder<Target> */
    private function scannableTargets(): Builder
    {
        return $this->visibleTargets()->where('status_verifikasi', Target::VERIFIED)->orderBy('nama');
    }

    /** @return Builder<Scan> */
    private function visibleScans(): Builder
    {
        return Scan::query()->with(['target', 'scanProfile'])->whereIn('target_id', $this->visibleTargets()->select('id'));
    }

    /** @return Builder<Finding> */
    private function visibleFindings(): Builder
    {
        return Finding::query()->with(['scan.target', 'vulnerabilityType'])->whereIn('scan_id', $this->visibleScans()->select('scans.id'));
    }

    private function dashboardData(): array
    {
        $open = $this->visibleFindings()->whereIn('status', [Finding::OPEN, Finding::CONFIRMED])->get();

        return [
            'targetCount' => $this->visibleTargets()->count(),
            'verifiedCount' => $this->visibleTargets()->where('status_verifikasi', Target::VERIFIED)->count(),
            'activeScans' => $this->visibleScans()->whereIn('status', [Scan::QUEUED, Scan::RUNNING])->count(),
            'openFindings' => $open,
            'reportCount' => Report::query()->whereHas('scan', fn (Builder $q) => $q->whereIn('target_id', $this->visibleTargets()->select('id')))->count(),
            'recentScans' => $this->visibleScans()->latest('id')->take(5)->get(),
            'pendingTargets' => auth()->user()->can('targets.verify') ? Target::query()->where('status_verifikasi', Target::PENDING)->latest('id')->get() : collect(),
        ];
    }

    private function targetData(Target $target): array
    {
        return [
            'scans' => $this->visibleScans()->where('target_id', $target->id)->latest('id')->get(),
            'reports' => Report::query()->with('scan')->whereHas('scan', fn (Builder $q) => $q->where('target_id', $target->id))->latest('id')->get(),
        ];
    }

    private function scanData(Scan $scan): array
    {
        return [
            'findings' => $scan->findings()->with(['scan.target', 'vulnerabilityType'])->get(),
            'toolCalls' => auth()->user()->can('targets.view-any') ? $scan->toolCalls()->with('tool')->oldest('id')->get() : collect(),
            'reports' => $scan->reports()->latest('versi')->get(),
            'retests' => $scan->retests()->latest('id')->get(),
        ];
    }

    private function reportData(Report $report): array
    {
        return [
            'findings' => $report->scan->findings()->with('vulnerabilityType')->get(),
            'comments' => $report->comments()->with('user')->oldest('id')->get(),
        ];
    }

    private function wizardData(): array
    {
        $target = $this->targetId ? $this->scannableTargets()->with(['targetScopeRules', 'targetTestAccounts'])->find($this->targetId) : null;

        return [
            'targets' => $this->scannableTargets()->get(),
            'profiles' => ScanProfile::query()->orderBy('nama')->get(),
            'selectedTarget' => $target,
            'selectedProfile' => $this->profileId ? ScanProfile::query()->find($this->profileId) : null,
        ];
    }

    private function certificateData(): array
    {
        $scans = $this->visibleScans()->whereNotNull('retest_of_id')->where('status', Scan::COMPLETED)
            ->withCount(['findings as unresolved_count' => fn (Builder $q) => $q->whereIn('status', [Finding::OPEN, Finding::CONFIRMED])])
            ->with('certificate')->latest('id')->get();

        return [
            'certificates' => Certificate::query()->with('scan.target')->whereIn('scan_id', $this->visibleScans()->select('scans.id'))->latest('issued_at')->get(),
            'candidates' => $scans->whereNull('certificate'),
        ];
    }

    /**
     * Catalogue rows for every registered tool, created on first view so the page always matches the code.
     */
    private function toolRows()
    {
        foreach (app(ToolRegistry::class)->all() as $tool) {
            Tool::query()->firstOrCreate(['nama' => $tool->name()], ['deskripsi' => $tool->description(), 'status' => true]);
        }

        return Tool::query()->whereIn('nama', array_keys(app(ToolRegistry::class)->all()))->withCount('toolCalls')->orderBy('nama')->get();
    }

    public function saveTarget(CreateTarget $create): void
    {
        $data = $this->validate([
            'targetForm.nama' => ['required', 'string', 'max:100'],
            'targetForm.base_url' => ['required', 'url:http,https', 'max:255', 'regex:/^https?:\/\/[^@\s]+$/i'],
            'targetForm.lingkungan' => ['required', Rule::in(['staging', 'uat'])],
            'targetForm.include' => ['nullable', 'string', 'max:2000'],
            'targetForm.exclude' => ['nullable', 'string', 'max:2000'],
        ])['targetForm'];

        $rules = [];
        foreach (['include', 'exclude'] as $tipe) {
            foreach (array_filter(array_map('trim', preg_split('/\R/', (string) $data[$tipe]))) as $pattern) {
                if (! preg_match(self::HOST_PATTERN, $pattern)) {
                    $this->addError("targetForm.{$tipe}", "Pola domain tidak valid: {$pattern}");

                    return;
                }
                $rules[] = ['pola_domain' => strtolower($pattern), 'tipe' => $tipe];
            }
        }

        $fields = ['nama' => $data['nama'], 'base_url' => rtrim($data['base_url'], '/'), 'lingkungan' => $data['lingkungan']];

        if ($this->record) {
            $target = $this->item();
            Gate::authorize('update', $target);
            $target->update($fields);
        } else {
            $target = $create->handle(auth()->user(), $fields);
        }

        $target->targetScopeRules()->delete();
        $target->targetScopeRules()->createMany($rules);

        $this->redirectRoute('targets.show', ['record' => $target->id], navigate: true);
    }

    public function deleteTarget(): void
    {
        $target = $this->item();
        Gate::authorize('delete', $target);
        $target->delete();
        $this->redirectRoute('targets.index', navigate: true);
    }

    public function verifyTarget(string $status, VerifyTarget $verify): void
    {
        $target = $verify->handle(auth()->user(), $this->item(), $status);
        $this->notice = "Status verifikasi target sekarang: {$target->status_verifikasi}.";
    }

    public function addAccount(): void
    {
        $target = $this->item();
        Gate::authorize('update', $target);
        $data = $this->validate([
            'accountForm.label_role' => ['required', Rule::in(['nasabah', 'mitra', 'admin'])],
            'accountForm.username' => ['required', 'string', 'max:100'],
            'accountForm.password' => ['required', 'string', 'max:255'],
        ])['accountForm'];

        $target->targetTestAccounts()->create([
            'label_role' => $data['label_role'],
            'username' => $data['username'],
            'encrypted_password' => $data['password'],
        ]);

        $this->accountForm = ['label_role' => 'nasabah', 'username' => '', 'password' => ''];
        $this->notice = 'Akun uji disimpan. Password disimpan terenkripsi dan tidak ditampilkan lagi.';
    }

    public function deleteAccount(int $id): void
    {
        $target = $this->item();
        Gate::authorize('update', $target);
        $target->targetTestAccounts()->whereKey($id)->delete();
    }

    public function saveProfile(): void
    {
        abort_unless(auth()->user()->can('scans.run'), 403);
        $data = $this->validate([
            'profileForm.nama' => ['required', 'string', 'max:100'],
            'profileForm.tipe' => ['required', Rule::in(['quick', 'full', 'auth'])],
            'profileForm.deskripsi' => ['nullable', 'string', 'max:300'],
            'profileForm.tools' => ['required', 'array', 'min:1'],
            'profileForm.tools.*' => [Rule::in(array_keys(app(ToolRegistry::class)->all()))],
        ])['profileForm'];

        ScanProfile::query()->create([
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'deskripsi' => $data['deskripsi'],
            'pengaturan' => ['tools' => array_values($data['tools'])],
        ]);

        $this->profileForm = ['nama' => '', 'tipe' => 'quick', 'deskripsi' => '', 'tools' => []];
        $this->showProfileForm = false;
        $this->notice = 'Profil scan dibuat.';
    }

    public function nextStep(): void
    {
        $this->validateWizard();
        $this->step = min(4, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    private function validateWizard(): void
    {
        $this->validate([
            'targetId' => ['required', Rule::in($this->scannableTargets()->pluck('id')->all())],
            'profileId' => ['required', Rule::exists(ScanProfile::class, 'id')],
        ], ['targetId.in' => 'Pilih target yang sudah terverifikasi.']);
    }

    public function startScan(StartScan $start): void
    {
        $this->validateWizard();
        $this->validate(['acknowledged' => 'accepted']);

        $scan = $start->handle(auth()->user(), Target::query()->findOrFail($this->targetId), $this->profileId, $this->accountId ?: null);
        $this->redirectRoute('scans.show', ['record' => $scan->id], navigate: true);
    }

    public function cancelScan(): void
    {
        $scan = $this->item();
        Gate::authorize('update', $scan);

        if (in_array($scan->status, [Scan::QUEUED, Scan::RUNNING], true)) {
            $scan->update(['status' => Scan::CANCELLED, 'finished_at' => now()]);
        }
    }

    /**
     * A completed scan is re-tested (closes fixed findings); a failed or cancelled one is simply run again.
     */
    public function retryScan(StartScan $start): void
    {
        $scan = $this->item();
        abort_unless(in_array($scan->status, [Scan::COMPLETED, Scan::FAILED, Scan::CANCELLED], true), 422);

        $new = $start->handle(auth()->user(), $scan->target, $scan->scan_profile_id, $scan->target_test_account_id,
            $scan->status === Scan::COMPLETED ? $scan : $scan->retestOf);
        $this->redirectRoute('scans.show', ['record' => $new->id], navigate: true);
    }

    public function generateReport(GenerateReport $generate): void
    {
        $scan = $this->item() instanceof Report ? $this->item()->scan : $this->item();
        $report = $generate->handle(auth()->user(), $scan);
        $this->redirectRoute('reports.show', ['record' => $report->id], navigate: true);
    }

    public function downloadReport(): StreamedResponse
    {
        $report = $this->item();

        return Storage::disk('local')->download($report->file_path, "jejaksa-scan-{$report->scan_id}-v{$report->versi}.html");
    }

    public function analyzeReport(AnalyzeReport $analyze): void
    {
        $analyze->handle(auth()->user(), $this->item());
        $this->notice = 'Analisis AI ditambahkan ke diskusi laporan.';
    }

    public function saveReview(): void
    {
        $finding = $this->item();
        Gate::authorize('update', $finding);
        $this->validate(['reviewStatus' => ['required', Rule::in([Finding::OPEN, Finding::CONFIRMED, Finding::FALSE_POSITIVE])]],
            ['reviewStatus.in' => 'Status fixed diberikan otomatis oleh re-test yang tidak lagi menemukan celah ini.']);

        $finding->update(['status' => $this->reviewStatus]);
        $this->notice = 'Status temuan disimpan.';
    }

    public function retestFinding(StartScan $start): void
    {
        $finding = $this->item();
        $scan = $finding->scan;
        $new = $start->handle(auth()->user(), $scan->target, $scan->scan_profile_id, $scan->target_test_account_id, $scan);
        $this->redirectRoute('scans.show', ['record' => $new->id], navigate: true);
    }

    public function addComment(): void
    {
        $commentable = $this->item();
        Gate::authorize('create', [Comment::class, $commentable]);
        $this->validate(['comment' => ['required', 'string', 'max:2000']]);

        $comment = $commentable->comments()->make(['isi' => $this->comment]);
        $comment->user()->associate(auth()->user());
        $comment->save();
        $this->comment = '';
    }

    public function deleteComment(int $id): void
    {
        $comment = $this->item()->comments()->findOrFail($id);
        Gate::authorize('delete', $comment);
        $comment->delete();
    }

    public function issueCertificate(int $scanId, IssueCertificate $issue): void
    {
        $scan = Scan::query()->findOrFail($scanId);
        Gate::authorize('view', $scan);
        $certificate = $issue->handle(auth()->user(), $scan);
        $this->notice = "Sertifikat {$certificate->nomor} diterbitkan.";
    }

    public function toggleTool(int $id): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        $tool = Tool::query()->findOrFail($id);
        $tool->update(['status' => ! $tool->status]);
        $this->notice = "Tool {$tool->nama} ".($tool->status ? 'diaktifkan.' : 'dinonaktifkan; agent dan scan tidak akan menjalankannya.');
    }

    public function registerAnalyst(RegisterAnalyst $register): void
    {
        $user = $register->handle(auth()->user(), $this->userForm);
        $this->userForm = ['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''];
        $this->notice = "Analyst {$user->email} didaftarkan.";
    }

    public function deleteUser(int $id): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('delete', $user);
        $user->delete();
        $this->notice = "Pengguna {$user->email} dihapus.";
    }
}; ?>

<div>
    @if($notice)
        <div role="status" class="info-box mb-6 flex items-center justify-between gap-4">
            <span>{{ $notice }}</span>
            <button type="button" class="shrink-0" wire:click="$set('notice', '')" aria-label="Tutup notifikasi">
                <x-jejaksa.icon name="close" size="16" />
            </button>
        </div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-400/50 bg-red-950/30 p-4 text-sm text-red-200">
            <p class="font-semibold">Periksa kembali data Anda.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div wire:loading.delay role="status" class="fixed bottom-5 right-5 z-50 rounded-lg border border-teal-600 bg-slate-900 px-4 py-2 text-xs text-teal-200">Memperbarui workspace…</div>
    @include('jejaksa.'.$area)
</div>
