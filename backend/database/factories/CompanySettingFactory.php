<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanySettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'group' => 'regional',
            'key' => 'timezone',
            'value' => 'America/Managua',
            'type' => 'string',
            'is_encrypted' => false,
        ];
    }
}
