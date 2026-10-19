<?php

use App\Livewire\DemoWorkspace;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component {
    #[Locked] public string $area = 'dashboard';
    #[Locked] public ?string $record = null;
    #[Url(except: 'overview')] public string $tab = 'overview';
    #[Url(as: 'q', except: '')] public string $search = '';
    #[Url(except: '')] public string $filter = '';
    public string $notice = '';
    public array $targetForm = ['name' => '','url' => '','environment' => 'Staging','description' => '','scope' => '/','excluded' => '','authorized' => false];
    public array $accountForm = ['name' => '','role' => 'Nasabah','username' => ''];
    public array $profileForm = ['name' => '','description' => '','tools' => ['HTTP Headers']];
    public bool $showProfileForm = false;
    #[Locked] public int $step = 1;
    #[Locked] public string $requestId = '';
    public string $targetId = 't1';
    public string $profileId = 'p1';
    public string $account = '';
    public bool $acknowledged = false;
    public string $reviewStatus = 'unreviewed';
    public string $reviewNote = '';
    public string $message = '';

    public function mount(string $area = 'dashboard', ?string $record = null): void
    {
        $this->area = $area;
        $this->record = $record;
        $this->requestId = (string) Str::uuid();
        if ($area === 'scan-create') {
            $state = DemoWorkspace::read();
            $candidate = request()->query('target');
            if (is_string($candidate) && isset($state['targets'][$candidate])) {
                $this->targetId = $candidate;
            }
            $candidate = request()->query('profile');
            if (is_string($candidate) && isset($state['profiles'][$candidate])) {
                $this->profileId = $candidate;
            }
        }
        if ($record) {
            $collection = ['targets' => 'targets','scans' => 'scans','findings' => 'findings','reports' => 'reports','chat' => 'chats','executions' => 'scans'][$area] ?? abort(404);
            $item = DemoWorkspace::find($collection, $record);
            if ($area === 'targets') {
                $this->targetForm = array_intersect_key($item, $this->targetForm);
            }
            if ($area === 'findings') {
                $this->reviewStatus = $item['status'];
                $this->reviewNote = $item['note'];
            }
        }
    }

    public function with(): array
    {
        $state = DemoWorkspace::read();
        $collection = ['targets' => 'targets','scans' => 'scans','findings' => 'findings','reports' => 'reports','chat' => 'chats','executions' => 'scans'][$this->area] ?? null;
        $item = $this->record && $collection ? DemoWorkspace::find($collection, $this->record) : null;
        return ['state' => $state,'item' => $item];
    }

    public function saveTarget(): void
    {
        $validated = $this->validate([
            'targetForm.name' => 'required|string|max:100', 'targetForm.url' => ['required','url:http,https','max:255','regex:/^https?:\/\/[^@\s]+$/i'],
            'targetForm.environment' => ['required',Rule::in(['Staging','Development','Production'])], 'targetForm.description' => 'nullable|string|max:1000',
            'targetForm.scope' => 'required|string|max:1000', 'targetForm.excluded' => 'nullable|string|max:1000', 'targetForm.authorized' => 'boolean',
        ])['targetForm'];
        $state = DemoWorkspace::read();
        if ($this->record) {
            DemoWorkspace::find('targets', $this->record);
        }
        $id = $this->record ?? 't'.Str::lower(Str::random(8));
        $state['targets'][$id] = [...$validated,'id' => $id,'accounts' => $state['targets'][$id]['accounts'] ?? []];
        DemoWorkspace::write($state);
        $this->redirectRoute('targets.show', ['record' => $id], navigate:true);
    }

    public function addAccount(): void
    {
        $validated = $this->validate(['accountForm.name' => 'required|string|max:80','accountForm.role' => ['required',Rule::in(['Nasabah','Mitra','Admin target'])],'accountForm.username' => 'required|string|max:100'])['accountForm'];
        $state = DemoWorkspace::read();
        DemoWorkspace::find('targets', $this->record ?? '');
        $state['targets'][$this->record]['accounts'][] = $validated;
        DemoWorkspace::write($state);
        $this->accountForm = ['name' => '','role' => 'Nasabah','username' => ''];
        $this->notice = 'Metadata akun demo disimpan. Tidak ada kredensial yang disimpan.';
    }

    public function saveProfile(): void
    {
        $validated = $this->validate(['profileForm.name' => 'required|string|max:80','profileForm.description' => 'nullable|string|max:300','profileForm.tools' => 'required|array|min:1','profileForm.tools.*' => Rule::in(['HTTP Headers','TLS Check','URL Discovery'])])['profileForm'];
        $state = DemoWorkspace::read();
        $id = 'p'.Str::lower(Str::random(8));
        $state['profiles'][$id] = [...$validated,'id' => $id,'active' => true];
        DemoWorkspace::write($state);
        $this->showProfileForm = false;
        $this->notice = 'Profil demo berhasil dibuat.';
    }

    public function nextStep(): void
    {
        $this->validate(['targetId' => ['required',Rule::in(array_keys(DemoWorkspace::read()['targets']))],'profileId' => ['required',Rule::in(array_keys(DemoWorkspace::read()['profiles']))]]);
        $this->step = min(4, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function startScan(): void
    {
        $this->validate(['acknowledged' => 'accepted','targetId' => ['required',Rule::in(array_keys(DemoWorkspace::read()['targets']))],'profileId' => ['required',Rule::in(array_keys(DemoWorkspace::read()['profiles']))]]);
        $target = DemoWorkspace::find('targets', $this->targetId);
        if (!$target['authorized']) {
            $this->addError('targetId', 'Target belum memiliki pernyataan izin pengujian.');
            return;
        }
        $profile = DemoWorkspace::find('profiles', $this->profileId);
        abort_unless($profile['active'], 422);
        $accounts = array_column($target['accounts'], 'username');
        if ($this->account !== '' && !in_array($this->account, $accounts, true)) {
            $this->addError('account', 'Akun tidak tersedia pada target terpilih.');
            return;
        }
        $id = $this->createScan($this->targetId, $this->profileId, 'Wizard demo');
        $this->redirectRoute('scans.show', ['record' => $id], navigate:true);
    }

    private function createScan(string $target, string $profile, string $source): string
    {
        $state = DemoWorkspace::read();
        if (isset($state['scan_requests'][$this->requestId])) {
            return $state['scan_requests'][$this->requestId];
        }
        $id = 's'.Str::lower(Str::random(8));
        $state['scans'][$id] = ['id' => $id,'target' => $target,'profile' => $profile,'status' => 'queued','created' => now()->format('d M Y, H:i'),'progress' => 0,'source' => $source,'account' => $this->account,'scope' => $state['targets'][$target]['scope'],'excluded' => $state['targets'][$target]['excluded']];
        $state['scan_requests'][$this->requestId] = $id;
        DemoWorkspace::write($state);
        return $id;
    }

    public function failScan(): void
    {
        $scan = DemoWorkspace::find('scans', $this->record ?? '');
        if (!in_array($scan['status'], ['queued', 'running'], true)) {
            return;
        }
        $state = DemoWorkspace::read();
        $state['scans'][$this->record]['status'] = 'failed';
        DemoWorkspace::write($state);
        $this->notice = 'Skenario gagal disimulasikan. Buka percobaan baru melalui Coba ulang; riwayat awal tetap tersimpan.';
    }

    public function advanceScan(): void
    {
        $scan = DemoWorkspace::find('scans', $this->record ?? '');
        $state = DemoWorkspace::read();
        $next = ['queued' => 'running','running' => 'completed','cancel_requested' => 'cancelled'][$scan['status']] ?? null;
        if (!$next) {
            return;
        }
        $state['scans'][$this->record]['status'] = $next;
        $state['scans'][$this->record]['progress'] = $next === 'completed' ? 100 : ($next === 'running' ? 42 : $scan['progress']);
        if ($next === 'completed' && !collect($state['findings'])->contains('scan', $this->record)) {
            $id = 'f'.Str::lower(Str::random(8));
            $fixture = DemoWorkspace::fixtures()['findings']['f1'];
            $state['findings'][$id] = [...$fixture,'id' => $id,'scan' => $this->record,'target' => $scan['target'],'location' => $state['targets'][$scan['target']]['url'],'status' => 'unreviewed'];
        }
        DemoWorkspace::write($state);
        $this->notice = 'Status simulasi diperbarui. Tidak ada tool yang dieksekusi.';
    }

    public function cancelScan(): void
    {
        $scan = DemoWorkspace::find('scans', $this->record ?? '');
        if (!in_array($scan['status'], ['queued','running'], true)) {
            return;
        }
        $state = DemoWorkspace::read();
        $state['scans'][$this->record]['status'] = 'cancel_requested';
        DemoWorkspace::write($state);
    }

    public function retryScan(): void
    {
        $scan = DemoWorkspace::find('scans', $this->record ?? '');
        if (!in_array($scan['status'], ['failed','cancelled','completed'], true)) {
            return;
        }
        if (!DemoWorkspace::find('targets', $scan['target'])['authorized']) {
            $this->addError('scan', 'Izin target diperlukan.');
            return;
        }
        $id = $this->createScan($scan['target'], $scan['profile'], 'Retest demo');
        $this->redirectRoute('scans.show', ['record' => $id], navigate:true);
    }

    public function saveReview(): void
    {
        $this->validate(['reviewStatus' => ['required',Rule::in(['unreviewed','confirmed','false_positive','fixed_pending_retest','resolved'])],'reviewNote' => 'required|string|min:8|max:2000']);
        DemoWorkspace::find('findings', $this->record ?? '');
        if ($this->reviewStatus === 'resolved') {
            $this->addError('reviewStatus', 'Status terselesaikan memerlukan hasil retest dari backend. Gunakan Menunggu retest pada demo.');
            return;
        }
        $state = DemoWorkspace::read();
        $state['findings'][$this->record]['status'] = $this->reviewStatus;
        $state['findings'][$this->record]['note'] = $this->reviewNote;
        DemoWorkspace::write($state);
        $this->notice = 'Keputusan review demo tersimpan.';
    }

    public function retestFinding(): void
    {
        $finding = DemoWorkspace::find('findings', $this->record ?? '');
        $scan = DemoWorkspace::find('scans', $finding['scan']);
        if (!DemoWorkspace::find('targets', $finding['target'])['authorized']) {
            $this->addError('scan', 'Izin target diperlukan untuk retest.');
            return;
        }
        $id = $this->createScan($finding['target'], $scan['profile'], 'Retest temuan demo');
        $state = DemoWorkspace::read();
        $state['scans'][$id]['retest_of'] = $finding['scan'];
        $state['scans'][$id]['finding_source'] = $finding['id'];
        DemoWorkspace::write($state);
        $this->redirectRoute('scans.show', ['record' => $id], navigate: true);
    }

    public function generateReport(string $scanId): void
    {
        $scan = DemoWorkspace::find('scans', $scanId);
        if ($scan['status'] !== 'completed') {
            $this->addError('report', 'Laporan memerlukan pemindaian selesai.');
            return;
        }
        $state = DemoWorkspace::read();
        $id = 'r'.Str::lower(Str::random(8));
        $version = collect($state['reports'])->where('scan', $scanId)->max('version') + 1;
        $state['reports'][$id] = ['id' => $id,'scan' => $scanId,'version' => $version,'created' => now()->format('d M Y, H:i'),'status' => 'draft','target_snapshot' => $state['targets'][$scan['target']],'findings_snapshot' => collect($state['findings'])->where('scan', $scanId)->all()];
        DemoWorkspace::write($state);
        $this->redirectRoute('reports.show', ['record' => $id], navigate:true);
    }

    public function downloadReport(): StreamedResponse
    {
        $report = DemoWorkspace::find('reports', $this->record ?? '');
        $html = view('jejaksa.report-export', ['report' => $report,'state' => DemoWorkspace::read()])->render();
        return response()->streamDownload(function () use ($html): void {
            echo $html;
        }, 'jejaksa-demo-'.$report['id'].'-v'.$report['version'].'.html', ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function newChat(): void
    {
        $state = DemoWorkspace::read();
        $id = 'c'.Str::lower(Str::random(8));
        $state['chats'][$id] = ['id' => $id,'title' => 'Percakapan baru','target' => 't1','messages' => [],'plan' => false];
        DemoWorkspace::write($state);
        $this->redirectRoute('chat.show', ['record' => $id], navigate:true);
    }

    public function sendMessage(): void
    {
        $this->validate(['message' => 'required|string|max:2000']);
        DemoWorkspace::find('chats', $this->record ?? '');
        $state = DemoWorkspace::read();
        $state['chats'][$this->record]['messages'][] = ['role' => 'user','text' => $this->message];
        $state['chats'][$this->record]['messages'][] = ['role' => 'assistant','text' => 'Respons demo: berikut contoh rencana pemeriksaan header. Rencana ini menggunakan target Fintech Sandbox dan profil Baseline Web. Instruksi Anda tidak dikirim ke LLM. Tinjau scope sebelum mengonfirmasi.'];
        $state['chats'][$this->record]['plan'] = true;
        $state['chats'][$this->record]['title'] = Str::limit($this->message, 45);
        DemoWorkspace::write($state);
        $this->message = '';
    }

    public function confirmPlan(): void
    {
        $chat = DemoWorkspace::find('chats', $this->record ?? '');
        abort_unless($chat['plan'] && DemoWorkspace::find('targets', $chat['target'])['authorized'], 422);
        $state = DemoWorkspace::read();
        $state['chats'][$this->record]['plan'] = false;
        DemoWorkspace::write($state);
        $id = $this->createScan($chat['target'], 'p1', 'Asisten demo');
        $this->redirectRoute('scans.show', ['record' => $id], navigate:true);
    }

    public function toggleTool(string $id): void
    {
        DemoWorkspace::find('tools', $id);
        $state = DemoWorkspace::read();
        $state['tools'][$id]['active'] = !$state['tools'][$id]['active'];
        DemoWorkspace::write($state);
        $this->notice = 'Preferensi katalog demo diubah. Kesiapan eksekusi tidak berubah.';
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
