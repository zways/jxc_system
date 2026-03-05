<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'username'          => fake()->unique()->userName(),
            'real_name'         => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => fake()->unique()->numerify('138########'),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'status'            => 'enabled',
            'employee_code'     => 'E' . fake()->unique()->numerify('####'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => 'super_admin_' . Str::random(6),
        ]);
    }
}
