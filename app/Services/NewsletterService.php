<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterService
{
    public function prepare(Newsletter $newsletter): int
    {
        $members = Member::active()
            ->whereNotNull('email')
            ->get(['id', 'email', 'first_name', 'last_name', 'language_preference']);

        DB::transaction(function () use ($newsletter, $members) {
            NewsletterSend::where('newsletter_id', $newsletter->id)->delete();

            $sends = $members->map(fn ($m) => [
                'newsletter_id' => $newsletter->id,
                'email' => $m->email,
                'member_name' => $m->first_name . ' ' . $m->last_name,
                'language' => $m->language_preference,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            NewsletterSend::insert($sends->toArray());

            $newsletter->update([
                'recipient_count' => $members->count(),
                'status' => 'bereit',
            ]);
        });

        return $members->count();
    }

    public function send(Newsletter $newsletter): void
    {
        if (!in_array($newsletter->status, ['bereit', 'geplant'])) {
            throw new \RuntimeException("Newsletter kann nicht versendet werden (Status: {$newsletter->status})");
        }

        $newsletter->update(['status' => 'wird_versendet']);

        NewsletterSend::where('newsletter_id', $newsletter->id)
            ->where('status', 'pending')
            ->chunkById(100, function ($sends) use ($newsletter) {
                foreach ($sends as $send) {
                    $this->sendToRecipient($newsletter, $send);
                }
            });

        $newsletter->update([
            'status' => 'versendet',
            'sent_at' => now(),
            'sent_count' => NewsletterSend::where('newsletter_id', $newsletter->id)
                ->where('status', 'sent')
                ->count(),
        ]);
    }

    private function sendToRecipient(Newsletter $newsletter, NewsletterSend $send): void
    {
        try {
            $subject = $send->language === 'tr' && $newsletter->subject_tr
                ? $newsletter->subject_tr
                : $newsletter->subject_de;

            $body = $send->language === 'tr' && $newsletter->body_tr
                ? $newsletter->body_tr
                : $newsletter->body_de;

            // Platzhalter ersetzen
            $body = str_replace('{{name}}', $send->member_name ?? '', $body);

            Mail::html($body, function ($message) use ($send, $subject) {
                $message->to($send->email, $send->member_name)
                    ->subject($subject);
            });

            $send->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Exception $e) {
            Log::error("Newsletter-Send fehlgeschlagen für {$send->email}: {$e->getMessage()}");
            $send->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
