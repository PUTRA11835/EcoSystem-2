<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\Employee;
use App\Services\Ai\AiResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI Research — chat backend untuk asisten pencarian EKSTERNAL.
 *
 * Bentuknya sengaja kembar dengan AiAssistantController (SSE, tanpa persistensi,
 * state di cache 'ai_chat'); yang berbeda hanya service di baliknya — di sini
 * AiResearchService, yang memakai server tool web_search/web_fetch dan sama
 * sekali tidak menyentuh data EcoSystem.
 *
 * Selain 'delta'/'done'/'error', stream ini mengirim dua event tambahan:
 *   - status  : label progres ("Mencari di internet…") selagi server tool jalan
 *   - sources : daftar {url, title} sumber yang dipakai pada giliran tersebut
 */
class AiResearchController extends Controller
{
    private const SUPPORTED_IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * Batas lampiran sengaja ketat.
     *
     * Lampiran tidak hanya dikirim sekali: byte-nya ikut tersimpan di riwayat
     * percakapan (cache 'ai_chat') dan dikirim ULANG ke API pada setiap
     * pertanyaan lanjutan di percakapan yang sama. Jadi satu berkas besar
     * berbiaya ganda — ruang disk selama percakapan hidup, dan token tiap
     * giliran. Base64 juga menggelembungkan ukurannya ~33%: gambar 5 MB
     * menjadi ~6,8 MB di dalam riwayat.
     *
     * Screenshot layar SAP — kasus pemakaian utama halaman ini — jauh di bawah
     * batas ini, biasanya di bawah 1 MB.
     */
    private const MAX_ATTACHMENTS = 2;
    private const MAX_ATTACHMENT_KB = 5120;   // 5 MB per berkas

    public function index()
    {
        return view('ai.research');
    }

    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:4000',
            'conversation_id' => 'required|string|max:100',
            'model' => 'nullable|string|in:default,deep',
            'files' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'files.*' => 'file|max:' . self::MAX_ATTACHMENT_KB,
        ]);

        $employee = $this->currentEmployee();


        $message = trim((string) ($validated['message'] ?? ''));
        $modelTier = $validated['model'] ?? 'default';
        $conversationId = $validated['conversation_id'];

        [$attachments, $rejectedNote] = $this->prepareAttachments($request->file('files', []));

        if ('' === $message && empty($attachments) && null === $rejectedNote) {
            abort(422, 'Message or a supported attachment is required.');
        }

        // Lepas lock session sebelum stream panjang, supaya tab/request lain
        // milik user yang sama tidak ikut terblokir.
        $request->session()->save();

        return response()->stream(function () use ($employee, $conversationId, $message, $attachments, $modelTier, $rejectedNote) {
            $send = function (string $event, array $payload): void {
                echo 'event: ' . $event . "\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            if (null !== $rejectedNote) {
                $send('delta', ['text' => $rejectedNote]);
            }

            try {
                /** @var AiResearchService $research */
                $research = app(AiResearchService::class);

                $research->streamReply(
                    employee: $employee,
                    conversationId: $conversationId,
                    userText: $message,
                    attachments: $attachments,
                    modelTier: $modelTier,
                    onDelta: function (string $text) use ($send): void {
                        $send('delta', ['text' => $text]);
                    },
                    onEvent: function (string $event, array $payload) use ($send): void {
                        $send($event, $payload);
                    },
                    isAborted: fn () => 1 === connection_aborted(),
                );

                if (0 === connection_aborted()) {
                    $send('done', []);
                }
            } catch (\Throwable $e) {
                Log::error('AI research chat failed', ['error' => $e->getMessage()]);
                if (0 === connection_aborted()) {
                    $send('error', ['message' => 'Something went wrong while searching. Please try again.']);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Daftar percakapan milik user yang sedang login.
     *
     * Sengaja ringkas (judul + waktu): isi percakapan baru diambil saat dibuka,
     * supaya sidebar tetap enteng meski riwayatnya panjang.
     */
    public function conversations(): JsonResponse
    {
        $employee = $this->currentEmployee();

        $rows = AiConversation::where('employee_id', $employee->employee_id)
            ->where('assistant', AiConversation::ASSISTANT_RESEARCH)
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get(['conversation_id', 'title', 'model_tier', 'last_message_at']);

        return response()->json([
            'items' => $rows->map(fn (AiConversation $row) => [
                'id' => $row->conversation_id,
                'title' => $row->title,
                'model_tier' => $row->model_tier,
                'updated_at' => optional($row->last_message_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    /** Transkrip satu percakapan. */
    public function conversation(string $conversation): JsonResponse
    {
        $row = $this->findOwnedConversation($conversation);

        return response()->json([
            'id' => $row->conversation_id,
            'title' => $row->title,
            'model_tier' => $row->model_tier,
            'messages' => $row->messages->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
                'sources' => $message->sources ?? [],
                'attachments' => $message->attachment_count,
                'at' => optional($message->created_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Hapus satu percakapan — arsip DB sekaligus konteks di cache.
     *
     * POST, bukan DELETE: verb DELETE diblokir edge/WAF di production
     * (lihat catatan yang sama pada modul lain).
     */
    public function destroyConversation(string $conversation): JsonResponse
    {
        $row = $this->findOwnedConversation($conversation);

        $row->delete();   // ai_messages ikut terhapus lewat cascade

        app(AiResearchService::class)->forgetContext($this->currentEmployee(), $conversation);

        return response()->json(['ok' => true]);
    }

    /**
     * Percakapan milik user yang login — atau 404.
     *
     * conversationId adalah UUID yang dikirim browser, jadi ia TIDAK PERNAH
     * dipakai tanpa syarat pemilik: tanpa ini, menempelkan UUID orang lain
     * cukup untuk membaca risetnya (dan lampirannya). employee_id diambil dari
     * session server, bukan dari request.
     */
    private function findOwnedConversation(string $conversation): AiConversation
    {
        $employee = $this->currentEmployee();

        return AiConversation::with('messages')
            ->where('employee_id', $employee->employee_id)
            ->where('assistant', AiConversation::ASSISTANT_RESEARCH)
            ->where('conversation_id', $conversation)
            ->firstOrFail();
    }

    private function currentEmployee(): Employee
    {
        $sessionUser = session('user');

        if (!$sessionUser || 'employee' !== ($sessionUser['type'] ?? null)) {
            abort(401);
        }

        $employee = Employee::find($sessionUser['id']);

        if (!$employee) {
            abort(401);
        }

        return $employee;
    }
    /**
     * Pisahkan file terunggah menjadi lampiran siap-kirim (gambar/PDF) dan
     * catatan untuk user tentang file yang terpaksa dilewati.
     *
     * @param array<int, UploadedFile|null> $files
     * @return array{0: array<int, array{type: string, media_type: string, data: string}>, 1: ?string}
     */
    private function prepareAttachments(array $files): array
    {
        $attachments = [];
        $rejected = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();

            if ('application/pdf' === $mime) {
                $attachments[] = [
                    'type' => 'document',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode((string) file_get_contents($file->getRealPath())),
                ];
            } elseif (in_array($mime, self::SUPPORTED_IMAGE_MIMES, true)) {
                $attachments[] = [
                    'type' => 'image',
                    'media_type' => $mime,
                    'data' => base64_encode((string) file_get_contents($file->getRealPath())),
                ];
            } else {
                $rejected[] = $file->getClientOriginalName();
            }
        }

        $note = null;
        if (!empty($rejected)) {
            $names = implode(', ', $rejected);
            $note = "_Note: {$names} — this file type isn't supported yet. Only PDF and image attachments "
                . "(PNG, JPEG, GIF, WEBP) can be read right now._\n\n";
        }

        return [$attachments, $note];
    }
}
