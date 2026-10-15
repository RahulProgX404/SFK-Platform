<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Access\UserType;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** ユーザー種別 スーパー管理者 (normally provisioned from the environment). */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['user_type' => UserType::SuperAdmin]);
    }

    /** ユーザー種別 管理者. */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['user_type' => UserType::Admin]);
    }

    /** 従業員カテゴリ (several allowed). */
    public function inCategories(int ...$categoryIds): static
    {
        return $this->afterCreating(fn (User $user) => $user->employeeCategories()->attach($categoryIds));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
