<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        $statuses = ['aktiv', 'ruhend', 'ausgetreten'];
        $categories = ['vollmitglied', 'foerdermitglied', 'ehrenmitglied', 'jugend'];

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'birth_place' => fake()->city(),
            'nationality' => 'Deutsch',
            'gender' => fake()->randomElement(['maennlich', 'weiblich']),
            'street' => fake()->streetName(),
            'house_number' => fake()->buildingNumber(),
            'zip_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'Deutschland',
            'membership_start' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement($statuses),
            'category' => fake()->randomElement($categories),
            'membership_fee' => fake()->randomElement([10, 15, 20, 30, 50]),
            'fee_interval' => 'monatlich',
            'language_preference' => fake()->randomElement(['de', 'tr']),
            'gdpr_consent' => true,
            'gdpr_consent_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'aktiv',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ausgetreten',
            'membership_end' => now()->format('Y-m-d'),
        ]);
    }
}
