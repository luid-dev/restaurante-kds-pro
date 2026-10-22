<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
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
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'pin_code' => null,
            'pin_changed_at' => null,
            'is_active' => true,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * El PIN requiere company_id (huella por empresa): se asigna después de resolverla.
     */
    public function withPin(string $pin): static
    {
        return $this->afterMaking(static function (User $user) use ($pin): void {
            $user->setPin($pin);
        });
    }

    /**
     * Personal operativo que solo ingresa con PIN en la terminal compartida.
     */
    public function pinOnly(string $pin): static
    {
        return $this->withPin($pin)->state(fn (): array => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
