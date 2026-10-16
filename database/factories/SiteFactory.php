<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        $host = fake()->unique()->domainName();

        return [
            'name' => fake()->company(),
            'base_url' => "https://{$host}",
            'host' => $host,
            'is_active' => true,
        ];
    }
}
