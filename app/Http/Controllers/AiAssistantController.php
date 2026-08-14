<?php

namespace App\Http\Controllers;

/**
 * AI Assistant — antarmuka chat.
 *
 * Tahap ini UI saja: pengiriman pesan masih di-stub di sisi klien sampai
 * konfigurasi provider (endpoint + API key) tersedia. Ketika sudah siap,
 * tambahkan endpoint chat di sini dan ganti stub `aiSendToBackend()`
 * di resources/views/ai/assistant.blade.php.
 */
class AiAssistantController extends Controller
{
    public function index()
    {
        return view('ai.assistant');
    }
}
