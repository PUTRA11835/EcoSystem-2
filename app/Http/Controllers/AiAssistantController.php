<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\Ai\AiChatService;
use App\Support\AiTextAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI Assistant — chat backend.
 *
 * The chat endpoint streams its reply as Server-Sent Events. No conversation
 * content is ever persisted to the database — AiChatService keeps in-flight
 * conversation state in a dedicated file cache store with a 1-hour sliding
 * TTL (see config/cache.php 'ai_chat' store).
 */
class AiAssistantController extends Controller
{
    private const SUPPORTED_IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    private const MAX_ATTACHMENTS = 5;

    public function index()
    {
        return view('ai.assistant');
    }

    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:4000',
            'conversation_id' => 'required|string|max:100',
            'model' => 'nullable|string|in:default,fast,reasoning',
            'files' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'files.*' => 'file|max:10240', // 10 MB per file
        ]);

        $sessionUser = session('user');
        if (!$sessionUser || 'employee' !== ($sessionUser['type'] ?? null)) {
            abort(401);
        }

        $employee = Employee::find($sessionUser['id']);
        if (!$employee) {
            abort(401);
        }

        $message = trim((string) ($validated['message'] ?? ''));
        $modelTier = $validated['model'] ?? 'default';
        $conversationId = $validated['conversation_id'];

        [$attachments, $rejectedNote] = $this->prepareAttachments($request->file('files', []));

        if ('' === $message && empty($attachments) && null === $rejectedNote) {
            abort(422, 'Message or a supported attachment is required.');
        }

        // Release the session file lock before the long-running stream so this
        // request doesn't block other tabs/requests for the same logged-in user.
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
                /** @var AiChatService $chat */
                $chat = app(AiChatService::class);

                $chat->streamReply(
                    employee: $employee,
                    conversationId: $conversationId,
                    userText: $message,
                    attachments: $attachments,
                    modelTier: $modelTier,
                    onDelta: function (string $text) use ($send): void {
                        $send('delta', ['text' => $text]);
                    },
                    isAborted: fn () => 1 === connection_aborted(),
                );

                if (0 === connection_aborted()) {
                    $send('done', []);
                }
            } catch (\Throwable $e) {
                Log::error('AI assistant chat failed', ['error' => $e->getMessage()]);
                if (0 === connection_aborted()) {
                    $send('error', ['message' => 'Something went wrong while generating a response. Please try again.']);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Split uploaded files into Claude-ready attachments (text/code, PDF,
     * image) and an optional user-facing note about anything that had to be
     * skipped.
     *
     * @param array<int, UploadedFile|null> $files
     * @return array{0: array<int, array<string, mixed>>, 1: ?string}
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

            // Teks/kode diperiksa DULUAN: tebakan MIME untuk berkas kode
            // sering salah (.ts → video/mp2t), dan tempelan besar dari
            // composer sampai ke sini sebagai berkas .txt.
            if (AiTextAttachment::isTextual($file)) {
                $attachments[] = AiTextAttachment::fromFile($file);
            } elseif ('application/pdf' === $mime) {
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
            $note = "_Note: {$names} — this file type isn't supported yet. Only PDF, image "
                . "(PNG, JPEG, GIF, WEBP), and text/code attachments can be read right now._\n\n";
        }

        return [$attachments, $note];
    }
}
