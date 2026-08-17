<?php

namespace App\Jobs;

use App\Models\Newsletter;
use App\Services\NewsletterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        private readonly Newsletter $newsletter
    ) {}

    public function handle(NewsletterService $service): void
    {
        Log::info("Starte Newsletter-Versand: #{$this->newsletter->id} '{$this->newsletter->title}'");

        $service->send($this->newsletter);

        Log::info("Newsletter #{$this->newsletter->id} versendet an {$this->newsletter->sent_count} Empfänger");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Newsletter-Job fehlgeschlagen #{$this->newsletter->id}: {$exception->getMessage()}");

        $this->newsletter->update(['status' => 'fehlgeschlagen']);
    }
}
