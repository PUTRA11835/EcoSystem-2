<?php

namespace App\Console\Commands;

use App\Http\Controllers\EmailController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ProcessEmailInbox extends Command
{
    protected $signature   = 'email:process-inbox';
    protected $description = 'Proses email masuk di inbox: buat tiket baru atau tambah pesan ke tiket yang ada';

    public function handle(): void
    {
        $this->info('Memproses inbox email...');

        $controller = app(EmailController::class);
        $response   = $controller->processInbox(new Request());
        $data       = $response->getData(true);

        if (($data['status'] ?? '') === 'error') {
            $this->error('Failed: ' . ($data['message'] ?? 'Unknown error'));
            return;
        }

        $this->info("Done. Processed: {$data['processed']}, Skipped: {$data['skipped']}");

        if (!empty($data['errors'])) {
            foreach ($data['errors'] as $err) {
                $this->warn('  - ' . $err);
            }
        }
    }
}
