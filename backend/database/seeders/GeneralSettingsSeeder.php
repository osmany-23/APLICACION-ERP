<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Timezone;
use App\Services\CompanySettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeneralSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $this->seedCurrencies($now);
        $this->seedCountries($now);
        $this->seedTimezones($now);
        $this->seedCompanyDefaults();
    }

    private function seedCurrencies(mixed $now): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $rows = [
            ['code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'is_base' => true],
            ['code' => 'USD', 'name' => 'Dolar Estadounidense', 'symbol' => '$', 'is_base' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'is_base' => false],
        ];

        foreach ($rows as $row) {
            Currency::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'symbol' => $row['symbol'],
                    'decimal_places' => 2,
                    'decimal_separator' => '.',
                    'thousands_separator' => ',',
                    'symbol_position' => 'before',
                    'is_base' => $row['is_base'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedCountries(mixed $now): void
    {
        if (! Schema::hasTable('countries')) {
            return;
        }

        $currencyIds = Currency::query()->pluck('id', 'code');
        $rows = [
            ['iso2' => 'NI', 'iso3' => 'NIC', 'name' => 'Nicaragua', 'phone_code' => '+505', 'currency' => 'NIO'],
            ['iso2' => 'SV', 'iso3' => 'SLV', 'name' => 'El Salvador', 'phone_code' => '+503', 'currency' => 'USD'],
            ['iso2' => 'HN', 'iso3' => 'HND', 'name' => 'Honduras', 'phone_code' => '+504', 'currency' => null],
            ['iso2' => 'GT', 'iso3' => 'GTM', 'name' => 'Guatemala', 'phone_code' => '+502', 'currency' => null],
            ['iso2' => 'CR', 'iso3' => 'CRI', 'name' => 'Costa Rica', 'phone_code' => '+506', 'currency' => null],
            ['iso2' => 'US', 'iso3' => 'USA', 'name' => 'Estados Unidos', 'phone_code' => '+1', 'currency' => 'USD'],
        ];

        foreach ($rows as $row) {
            Country::query()->updateOrCreate(
                ['iso2' => $row['iso2']],
                [
                    'iso3' => $row['iso3'],
                    'name' => $row['name'],
                    'phone_code' => $row['phone_code'],
                    'currency_id' => $row['currency'] ? ($currencyIds[$row['currency']] ?? null) : null,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedTimezones(mixed $now): void
    {
        if (! Schema::hasTable('timezones')) {
            return;
        }

        $rows = [
            ['name' => 'America/Managua', 'offset' => '-06:00', 'country_iso2' => 'NI'],
            ['name' => 'America/El_Salvador', 'offset' => '-06:00', 'country_iso2' => 'SV'],
            ['name' => 'America/Tegucigalpa', 'offset' => '-06:00', 'country_iso2' => 'HN'],
            ['name' => 'America/Guatemala', 'offset' => '-06:00', 'country_iso2' => 'GT'],
            ['name' => 'America/Costa_Rica', 'offset' => '-06:00', 'country_iso2' => 'CR'],
            ['name' => 'America/Mexico_City', 'offset' => '-06:00', 'country_iso2' => 'MX'],
            ['name' => 'America/New_York', 'offset' => '-05:00', 'country_iso2' => 'US'],
        ];

        foreach ($rows as $row) {
            Timezone::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'offset' => $row['offset'],
                    'country_iso2' => $row['country_iso2'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedCompanyDefaults(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('company_settings')) {
            return;
        }

        $service = app(CompanySettingsService::class);

        DB::table('companies')->orderBy('id')->pluck('id')->each(function ($companyId) use ($service) {
            $service->seedDefaults((int) $companyId);
        });
    }
}
