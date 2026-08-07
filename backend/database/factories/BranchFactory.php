<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'uuid' => (string) Str::uuid(),
            'code' => strtoupper($this->faker->unique()->bothify('SUC-###')),
            'name' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'address' => $this->faker->address(),
            'is_headquarters' => false,
            'headquarters_unique_key' => null,
            'status' => 1,
        ];
    }
}
