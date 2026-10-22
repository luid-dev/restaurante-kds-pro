<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DTOs\Company\CompanySettingsData;
use App\DTOs\Company\TaxSettingsData;
use App\Enums\IgvRegime;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tradeName = 'Pollería '.fake()->unique()->lastName();

        return [
            'ruc' => '20'.fake()->unique()->numerify('#########'),
            'business_name' => Str::upper($tradeName).' S.A.C.',
            'trade_name' => $tradeName,
            'slug' => Str::slug($tradeName).'-'.fake()->unique()->numerify('####'),
            'fiscal_address' => fake()->streetAddress(),
            'ubigeo' => '150101',
            'department' => 'LIMA',
            'province' => 'LIMA',
            'district' => 'LIMA',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9########'),
            'settings' => CompanySettingsData::defaults(),
            'is_active' => true,
        ];
    }

    /**
     * Restaurante MYPE acogido a la tasa reducida (Ley 31556 y modificatorias).
     */
    public function mypeRestaurant(string $rate = '10.50'): static
    {
        return $this->state(fn (): array => [
            'settings' => CompanySettingsData::defaults()->withTax(new TaxSettingsData(IgvRegime::MypeRestaurant, $rate)),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
