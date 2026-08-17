<?php

namespace Tests\Feature\Communications;

use App\Models\Member;
use App\Models\Newsletter;
use App\Models\User;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterServiceTest extends TestCase
{
    use RefreshDatabase;

    private NewsletterService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NewsletterService();
        $this->user = User::factory()->create();
    }

    public function test_prepare_creates_sends_for_active_members_with_email(): void
    {
        Member::factory()->count(3)->active()->create(['email' => 'test@example.de']);
        Member::factory()->count(2)->active()->create(['email' => null]);
        Member::factory()->count(1)->inactive()->create(['email' => 'inactive@example.de']);

        $newsletter = Newsletter::create([
            'title' => 'Test',
            'subject_de' => 'Test Betreff',
            'body_de' => 'Test Inhalt',
            'status' => 'entwurf',
            'created_by' => $this->user->id,
        ]);

        $count = $this->service->prepare($newsletter);

        // Nur 3 aktive mit E-Mail
        $this->assertEquals(3, $count);
        $this->assertDatabaseCount('newsletter_sends', 3);
        $newsletter->refresh();
        $this->assertEquals('bereit', $newsletter->status);
    }

    public function test_prepare_returns_zero_for_no_active_members(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Leer',
            'subject_de' => 'Test',
            'body_de' => 'Test',
            'status' => 'entwurf',
            'created_by' => $this->user->id,
        ]);

        $count = $this->service->prepare($newsletter);

        $this->assertEquals(0, $count);
    }
}
