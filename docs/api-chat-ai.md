# API Chat AI (Secure LLM Chat API Hub)

Semua endpoint di bawah prefix `/api`. Body dan respon berupa JSON (kirim header `Accept: application/json`).

## 1. Login: ambil token Sanctum

`POST /api/login` (maks. 5 percobaan per menit)

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email": "analyst@example.com", "password": "password"}'
```

```json
{ "status": "success", "data": { "token": "1|abc...", "token_type": "Bearer" } }
```

Akun dev (`analyst@example.com`, `client@example.com`, password `password`) tersedia setelah `php artisan db:seed`.
Salah kredensial: `401` `{"status": "error", "message": "Email atau password salah."}`.

## 2. Chat: kirim prompt ke LLM

`POST /api/chat-ai` (wajib `Authorization: Bearer <token>`, dikunci `auth:sanctum`)

```bash
curl -X POST http://localhost:8000/api/chat-ai \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abc..." \
  -d '{"prompt": "Jelaskan middleware!"}'
```

```json
{
  "status": "success",
  "data": {
    "session_id": 12,
    "model_used": "qwen3:14b",
    "prompt": "Jelaskan middleware!",
    "ai_response": "Middleware adalah...",
    "timestamp": "2026-11-15 09:30:00"
  }
}
```

`session_id` = id baris di tabel `chat_logs`. Model dan jumlah token dicatat dari respon provider
(`model`, `usage`), bukan dari config, jadi tetap benar kalau provider berpindah.

| Status | Arti |
| ------ | ---- |
| 200 | Berhasil, tercatat di `chat_logs` |
| 401 | Token tidak ada atau tidak valid |
| 422 | `prompt` kosong, bukan string, atau lebih dari 1000 karakter (`errors.prompt`) |
| 502 | LLM tidak bisa dihubungi atau mengembalikan error; tidak ada log yang disimpan |

## 3. Logout

`POST /api/logout` (Bearer token) mencabut token yang dipakai. Respon `204`.

## Tabel `chat_logs`

`id`, `user_id` (FK, cascade), `prompt`, `ai_response`, `model_used`, `prompt_tokens`, `completion_tokens`, `total_tokens`, `created_at`, `updated_at`.

## Mencoba dari PowerShell (Windows)

Isi `.env` (nilai Senopati sama dengan default di `config/llm.php`; key diminta langsung ke Kamal, jangan di-commit):

```
LLM_BASE_URL=https://senopati.its.ac.id/llm-gateway/v1
LLM_MODEL=qwen3:14b
LLM_API_KEY=isi-key-asli
```

Ollama lokal: `LLM_BASE_URL=http://localhost:11434/v1`, `LLM_API_KEY` dikosongkan, `LLM_MODEL` sesuai hasil `ollama list`.
Setelah `php artisan db:seed` dan `php artisan serve`, di terminal lain:

```powershell
$h = @{ Accept = 'application/json' }
$login = Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/login -Headers $h -ContentType 'application/json' -Body '{"email":"analyst@example.com","password":"password"}'
$h.Authorization = "Bearer $($login.data.token)"
Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/chat-ai -Headers $h -ContentType 'application/json' -TimeoutSec 180 -Body '{"prompt":"Jelaskan middleware!"}' | ConvertTo-Json -Depth 5
```

Cek hasilnya di database: `php artisan tinker --execute="dump(App\Models\ChatLog::latest()->first()->toArray());"`.
Jika responnya 502, lihat `storage/logs/laravel.log`.

## Titik sambung untuk sisi LLM

`App\Actions\AskChatAi` memanggil `LlmClient::chat([...], ['tools' => false])` dan membaca `model` serta `usage`
dari `ChatResponse::$raw`. Fallback otomatis dan system prompt tema cukup diterapkan di dalam `LlmClient`
(atau service baru yang dipanggil action ini); controller dan log tidak perlu berubah selama respon tetap
membawa `model` dan `usage` format OpenAI.
