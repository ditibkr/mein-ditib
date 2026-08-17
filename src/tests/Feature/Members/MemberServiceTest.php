<?php

namespace Tests\Feature\Members;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberNumberService;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberService(new MemberNumberService());
        $this->user = User::factory()->create();
    }

    public function test_creates_member_with_auto_number(): void
    {
        $member = $this->service->create([
            'first_name' => 'Ahmed',
            'last_name' => 'Yilmaz',
            'email' => 'ahmed@example.de',
            'status' => 'aktiv',
            'category' => 'vollmitglied',
        ], $this->user->id);

        $this->assertNotNull($member->member_number);
        $this->assertEquals('M000001', $member->member_number);
        $this->assertEquals($this->user->id, $member->created_by);
    }

    public function test_sets_gdpr_consent_timestamp(): void
    {
        $member = $this->service->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => 'aktiv',
            'gdpr_consent' => true,
        ], $this->user->id);

        $this->assertTrue($member->gdpr_consent);
        $this->assertNotNull($member->gdpr_consent_at);
    }

    public function test_updates_member(): void
    {
        $member = Member::factory()->create();

        $updated = $this->service->update($member, [
            'first_name' => 'Neu',
            'last_name' => 'Name',
        ], $this->user->id);

        $this->assertEquals('Neu', $updated->first_name);
        $this->assertEquals($this->user->id, $updated->updated_by);
    }

    public function test_member_statistics(): void
    {
        Member::factory()->count(5)->active()->create();
        Member::factory()->count(2)->inactive()->create();

        $stats = $this->service->getStatistics();

        $this->assertEquals(7, $stats['total']);
        $this->assertEquals(5, $stats['active']);
    }
}
