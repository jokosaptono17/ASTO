<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nrp' => 'KT'.fake()->unique()->numerify('#####'),
            'name' => fake()->firstName().' '.fake()->lastName(),
            'department' => 'PLANT',
            'position' => 'MEKANIK',
            'role' => 'user',
            'is_active' => true,
        ];
    }

    public function activated(string $pin = '482915'): static
    {
        return $this->afterMaking(fn ($user) => $user->forceFill(['pin' => $pin, 'activated_at' => now()]));
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function she(): static
    {
        return $this->state(['role' => 'she']);
    }
}
