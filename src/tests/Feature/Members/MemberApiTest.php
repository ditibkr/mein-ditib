<?php

namespace Tests\Feature\Members;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'vereinsadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('vereinsadmin');

        Sanctum::actingAs($this->admin);
    }

    public function test_can_list_members(): void
    {
        Member::factory()->count(5)->create();

        $response = $this->getJson('/api/members');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_filter_members_by_status(): void
    {
        Member::factory()->count(3)->active()->create();
        Member::factory()->count(2)->inactive()->create();

        $response = $this->getJson('/api/members?status=aktiv');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_search_members(): void
    {
        Member::factory()->create(['last_name' => 'Yilmaz', 'first_name' => 'Ahmed']);
        Member::factory()->create(['last_name' => 'Mueller', 'first_name' => 'Hans']);

        $response = $this->getJson('/api/members?search=Yilmaz');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_view_single_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->getJson("/api/members/{$member->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $member->id);
    }

    public function test_can_get_member_statistics(): void
    {
        Member::factory()->count(3)->active()->create();

        $response = $this->getJson('/api/members/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure(['total', 'active', 'newThisMonth']);
    }
}
