<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LoginLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usercode' => 'USR' . $this->faker->unique()->numberBetween(1000, 9999),
            'ip_address' => $this->faker->ipv4,
            'device' => $this->faker->userAgent,
            'created_at' => $this->faker->dateTimeBetween('-10 days', 'now'),
        ];
    }
}
