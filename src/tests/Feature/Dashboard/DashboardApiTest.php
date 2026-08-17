<?php

namespace Tests\Feature\Dashboard;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_dashboard_stats(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        Member::factory()->count(5)->active()->create();

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'members' => ['total', 'active', 'newThisMonth', 'byCategory'],
                'users' => ['total', 'active'],
                'communications' => ['newsletters_sent', 'newsletters_draft'],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(401);
    }

    public function test_dashboard_stats_reflect_actual_data(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        Member::factory()->count(3)->active()->create();
        Member::factory()->count(2)->inactive()->create();

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(5, $data['members']['total']);
        $this->assertEquals(3, $data['members']['active']);
    }
}
