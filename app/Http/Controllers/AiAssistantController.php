<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\Ai\AiChatService;
use App\Support\AiTextAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI Assistant — chat backend.
 *
 * The chat endpoint streams its reply as Server-Sent Events. In-flight
 * conversation state (attachments included) lives in a dedicated file cache
 * store with a 24-hour sliding TTL (see config/cache.php 'ai_chat' store);
 * a text-only archive of each conversation is also kept in ai_conversations/
 * ai_messages (assistant = 'internal') — see AiChatService's docblock for the
 * split. conversation() below reads that archive so the frontend can restore
 * the visible transcript after navigating away and back, independent of
 * whatever's left of the working cache.
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

        $employee = $this->currentEmployee();
        $sessionUser = session('user');

        $message = trim((string) ($validated['message'] ?? ''));
        $modelTier = $validated['model'] ?? 'default';
        $conversationId = $validated['conversation_id'];

        [$attachments, $rejectedNote] = $this->prepareAttachments($request->file('files', []));

        if ('' === $message && empty($attachments) && null === $rejectedNote) {
            abort(422, 'Message or a supported attachment is required.');
        }

        AuditLog::logAiPrompt(
            module: 'AI Assistant',
            auditableType: 'AiAssistantPrompt',
            actorId: $employee->employee_id,
            actorRoleId: $sessionUser['role']['id'] ?? null,
            actorName: $sessionUser['name'] ?? null,
            conversationId: $conversationId,
            message: $message,
            attachmentCount: count($attachments),
            modelTier: $modelTier,
        );

        // Release the session file lock before the long-running stream so this
        // request doesn't block other tabs/requests for the same logged-in user.
        $request->session()->save();

        return response()->stream(function () use ($employee, $conversationId, $message, $attachments, $modelTier, $rejectedNote) {
            // A multi-turn tool-use loop (up to MAX_TOOL_ITERATIONS round-trips to
            // Anthropic) can easily exceed PHP's default 30s max_execution_time,
            // which kills the request with an uncatchable fatal error mid-stream
            // (surfaces to the browser as a bare HTTP 500). The tool-iteration cap
            // and connection_aborted() check already bound this loop, so lifting
            // the time limit here is safe.
            set_time_limit(0);

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
     * Archived transcript of one conversation — read by the frontend on page
     * load (see assistant.blade.php's aiOpenConversation()) to restore the
     * visible chat after navigating away and back, since the DOM/JS state
     * that held it doesn't survive a full page load.
     */
    public function conversation(string $conversation): JsonResponse
    {
        $employee = $this->currentEmployee();
        $row = $this->findOwnedConversation($employee, $conversation);

        return response()->json([
            'id' => $row->conversation_id,
            'title' => $row->title,
            // Where the model's actual memory currently ends, so the UI can
            // mark it in the restored transcript instead of letting the user
            // discover it from an answer that seems to have forgotten earlier
            // messages.
            'context' => app(AiChatService::class)->contextState($employee, $conversation),
            'messages' => $row->messages->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
                'attachments' => $message->attachment_count,
                'at' => optional($message->created_at)->toIso8601String(),
            ])->all(),
        ]);
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
     * Conversation owned by the current employee, or 404.
     *
     * conversationId is a UUID supplied by the browser, so it's never trusted
     * alone — without the owner check, pasting someone else's UUID would be
     * enough to read their archived chat.
     */
    private function findOwnedConversation(Employee $employee, string $conversation): AiConversation
    {
        return AiConversation::with('messages')
            ->where('employee_id', $employee->employee_id)
            ->where('assistant', AiConversation::ASSISTANT_INTERNAL)
            ->where('conversation_id', $conversation)
            ->firstOrFail();
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
