<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->company(),
            'legal_name' => $this->faker->company().' S.A.',
            'short_name' => $this->faker->companySuffix(),
            'tax_id' => $this->faker->numerify('#########'),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'currency_id' => Currency::factory(),
            'timezone' => 'America/Managua',
            'country' => 'Nicaragua',
            'locale' => 'es_NI',
            'date_format' => 'dd/mm/yyyy',
            'time_format' => '24h',
            'status' => 1,
        ];
    }
}
