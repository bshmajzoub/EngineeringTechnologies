<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFcmNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly array $tokens,
        private readonly array $data
    ) {
        $this->onQueue('fcm-notifications');
    }

    public function handle(FcmService $fcmService): void
    {
        $fcmService->sendToTokens($this->tokens, $this->data);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('FCM notification failed', [
            'tokens' => $this->tokens,
            'data' => $this->data,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
