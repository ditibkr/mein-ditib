<?php

namespace Tests\Unit\Services;

use App\Models\Member;
use App\Services\MemberNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberNumberService();
    }

    public function test_generates_first_member_number(): void
    {
        $number = $this->service->generate();

        $this->assertEquals('M000001', $number);
    }

    public function test_increments_member_number(): void
    {
        Member::factory()->create(['member_number' => 'M000001']);

        $number = $this->service->generate();

        $this->assertEquals('M000002', $number);
    }

    public function test_pads_number_to_six_digits(): void
    {
        Member::factory()->create(['member_number' => 'M000099']);

        $number = $this->service->generate();

        $this->assertEquals('M000100', $number);
    }

    public function test_handles_missing_member_number(): void
    {
        Member::factory()->create(['member_number' => null]);

        $number = $this->service->generate();

        $this->assertEquals('M000001', $number);
    }
}
